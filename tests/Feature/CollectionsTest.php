<?php

namespace Tests\Feature;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Filament\Pages\Delinquency;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Classroom;
use App\Models\CollectionContact;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Services\Collections\CollectionMessage;
use App\Services\Collections\LateFeeCalculator;
use App\Services\Collections\OverdueDebt;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Inadimplência: valor atualizado com multa e juros, mensagem de cobrança e histórico de contatos. */
class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Customer $customer;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test', 'phone' => '(83) 3333-4444',
            'late_fine_percent' => 2, 'late_interest_monthly_percent' => 6,
        ]);
        $user = User::factory()->create(['name' => 'Secretária', 'company_id' => $this->company->id]);
        $user->assignRole('secretary');
        $this->actingAs($user);

        $this->customer = Customer::create(['name' => 'Maria de Souza', 'document' => '096.234.214-94', 'phone' => '(83) 98888-7777']);
        $student = Student::create(['name' => 'Ana Clara', 'customer_id' => $this->customer->id, 'phone_primary' => '83 99999-0000', 'date_of_birth' => '2015-03-10', 'gender' => 'F']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $this->enrollment = Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);
    }

    private function invoice(array $attributes = [], ?Customer $customer = null): Invoice
    {
        return Invoice::create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'enrollment_id' => $customer ? null : $this->enrollment->id,
            'number' => (string) random_int(1000, 9999),
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-23',
            'billing_period_start' => '2026-09-01',
            'amount' => 100,
            'balance' => 100,
            'status' => 'overdue',
            'billing_type' => 'tuition',
            ...$attributes,
        ]);
    }

    private function calculator(): LateFeeCalculator
    {
        return LateFeeCalculator::forCompany($this->company);
    }

    // ------------------------------------------------------------------ cálculo

    public function test_fine_is_charged_once_and_interest_pro_rata_per_day(): void
    {
        $amounts = $this->calculator()->invoice($this->invoice());

        $this->assertSame(30, $amounts->daysLate);
        $this->assertSame(100.0, $amounts->base);
        $this->assertSame(2.0, $amounts->fine);
        $this->assertSame(6.0, $amounts->interest); // 6% ao mês = 0,2% ao dia × 30 dias
        $this->assertSame(108.0, $amounts->total);
        $this->assertSame('2% de multa + 6% ao mês de juros', $this->calculator()->rulesLabel());
    }

    public function test_discount_is_lost_after_the_due_date_and_partial_payments_leave_only_the_balance(): void
    {
        $discounted = $this->calculator()->invoice($this->invoice([
            'due_date' => '2026-10-13', 'amount' => 90, 'balance' => 90, 'original_amount' => 100, 'final_amount' => 90,
            'discount_type' => 'fixed', 'discount_value' => 10,
        ]));
        $this->assertSame(100.0, $discounted->base);
        $this->assertSame(104.0, $discounted->total); // 100 + 2 de multa + 2 de juros (10 dias)

        $partial = $this->calculator()->invoice($this->invoice(['due_date' => '2026-10-13', 'balance' => 40, 'status' => 'partial']));
        $this->assertSame(40.0, $partial->base);
        $this->assertSame(41.6, $partial->total);
    }

    public function test_charge_created_after_the_due_date_only_runs_late_after_its_own_due_date(): void
    {
        $amounts = $this->calculator()->invoice($this->invoice([
            'due_date' => '2026-10-10', 'asaas_due_date' => '2026-10-23', 'amount' => 90, 'balance' => 90, 'original_amount' => 100,
        ]));

        $this->assertSame(13, $amounts->daysLate);
        $this->assertSame(0, $amounts->chargedDays);
        $this->assertSame(90.0, $amounts->total);
    }

    public function test_without_rules_the_updated_value_is_the_balance(): void
    {
        $this->company->update(['late_fine_percent' => null, 'late_interest_monthly_percent' => null]);

        $amounts = $this->calculator()->invoice($this->invoice());

        $this->assertSame(100.0, $amounts->total);
        $this->assertNull($this->calculator()->rulesLabel());
    }

    // ------------------------------------------------------------------ mensagem

    public function test_default_message_lists_each_invoice_with_payment_link_and_fresh_pix(): void
    {
        Http::fake(['*/payments/pay_1/pixQrCode' => Http::response(['payload' => 'PIX-ATUALIZADO', 'encodedImage' => 'x'])]);
        $this->invoice(['asaas_invoice_id' => 'pay_1', 'asaas_sync_status' => 'synced', 'invoice_link' => 'https://asaas.test/i/1', 'invoice_qrcode' => 'PIX-ANTIGO']);
        $this->invoice(['due_date' => '2026-10-13', 'billing_period_start' => '2026-10-01', 'invoice_qrcode' => 'PIX-GUARDADO']);

        $message = app(CollectionMessage::class)->build(OverdueDebt::for($this->customer, $this->calculator()), $this->company);

        $this->assertStringStartsWith('Olá, Maria! Tudo bem?', $message);
        $this->assertStringContainsString('Aqui é da secretaria da Escola Ceci. Identificamos em aberto 2 faturas vencidas, no valor atualizado de R$ 212,00 até hoje:', $message);
        $this->assertStringContainsString("*Mensalidade setembro/2026* (Ana Clara)\nVencimento: 23/09/2026 · Valor atualizado: R$ 108,00\nPagar pelo link: https://asaas.test/i/1\nPIX copia e cola:\nPIX-ATUALIZADO", $message);
        $this->assertStringContainsString("*Mensalidade outubro/2026* (Ana Clara)\nVencimento: 13/10/2026 · Valor atualizado: R$ 104,00\nPIX copia e cola:\nPIX-GUARDADO", $message);
        $this->assertStringNotContainsString('PIX-ANTIGO', $message);
    }

    public function test_company_template_replaces_the_default(): void
    {
        $this->company->update(['collection_message' => "Oi {responsavel_completo}, {quantidade}: {valor_original} (com encargos {total}). Fone {telefone_escola}."]);
        $this->invoice();

        $message = app(CollectionMessage::class)->build(OverdueDebt::for($this->customer, $this->calculator()), $this->company);

        $this->assertSame('Oi Maria de Souza, 1 fatura vencida: R$ 100,00 (com encargos R$ 108,00). Fone (83) 3333-4444.', $message);
    }

    public function test_phones_come_from_the_customer_and_the_students_in_whatsapp_format(): void
    {
        $this->assertSame([
            '5583988887777' => 'Responsável · (83) 98888-7777',
            '5583999990000' => 'Cadastro de Ana · 83 99999-0000',
        ], CollectionMessage::phones($this->customer->fresh()));

        $this->assertSame('5583988887777', CollectionMessage::normalizePhone('+55 (83) 98888-7777'));
        $this->assertSame('5583988887777', CollectionMessage::normalizePhone('083 98888-7777'));
        $this->assertNull(CollectionMessage::normalizePhone('8888-7777'));
        $this->assertSame('https://wa.me/5583988887777?text=Ol%C3%A1%21%0Atudo', CollectionMessage::whatsappUrl('5583988887777', "Olá!\ntudo"));
    }

    // ------------------------------------------------------------------ tela

    public function test_page_lists_only_customers_with_overdue_invoices_and_their_updated_total(): void
    {
        $this->invoice();
        $this->invoice(['due_date' => '2026-10-13', 'billing_period_start' => '2026-10-01']);
        $onTime = Customer::create(['name' => 'Em dia', 'document' => '529.982.247-25']);
        $this->invoice(['due_date' => '2026-11-10', 'status' => 'open'], $onTime);
        $paid = Customer::create(['name' => 'Pagou', 'document' => '111.444.777-35']);
        $this->invoice(['balance' => 0, 'status' => 'paid'], $paid);

        Livewire::test(Delinquency::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->customer])
            ->assertCanNotSeeTableRecords([$onTime, $paid])
            ->assertTableColumnStateSet('overdue_invoices_sum_balance', 200, $this->customer)
            ->assertTableColumnStateSet('updated_total', 212.0, $this->customer)
            ->assertTableColumnStateSet('overdue_invoices_min_due_date', 30, $this->customer)
            ->assertSee('2% de multa + 6% ao mês de juros');
    }

    public function test_whatsapp_records_the_contact_and_opens_the_conversation(): void
    {
        $this->invoice(['invoice_link' => 'https://asaas.test/i/1']);

        Livewire::test(Delinquency::class)
            ->mountTableAction('whatsapp', $this->customer)
            ->assertTableActionDataSet(['phone' => '5583988887777'])
            ->assertTableActionDataSet(fn (array $state): bool => str_contains($state['message'], 'https://asaas.test/i/1'))
            ->setTableActionData(['phone' => '5583999990000', 'message' => 'Olá, Maria! Segue o link.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNotified('Contato registrado');

        $contact = CollectionContact::sole();
        $this->assertSame(CollectionChannel::WhatsApp, $contact->channel);
        $this->assertSame(CollectionOutcome::MessageSent, $contact->outcome);
        $this->assertSame('Olá, Maria! Segue o link.', $contact->message);
        $this->assertSame('100.00', $contact->overdue_total);
        $this->assertCount(1, $contact->invoice_ids);
        $this->assertSame('Secretária', $contact->user->name);
    }

    public function test_promise_is_tracked_and_flagged_when_it_is_not_kept(): void
    {
        $this->invoice();

        Livewire::test(Delinquency::class)
            ->callTableAction('registerContact', $this->customer, [
                'channel' => 'phone', 'outcome' => 'promised', 'promised_date' => '2026-10-25', 'promised_amount' => '50', 'notes' => 'Paga metade sexta',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Contato registrado');

        $contact = CollectionContact::sole();
        $this->assertSame('2026-10-25', $contact->promised_date->toDateString());
        $this->assertSame('50.00', $contact->promised_amount);
        $this->assertFalse($contact->promiseIsBroken());

        Livewire::test(Delinquency::class)
            ->filterTable('contact', 'open_promise')->assertCanSeeTableRecords([$this->customer])
            ->filterTable('contact', 'broken_promise')->assertCanNotSeeTableRecords([$this->customer]);

        $this->travelTo(now()->setDate(2026, 10, 27));

        Livewire::test(Delinquency::class)
            ->filterTable('contact', 'broken_promise')->assertCanSeeTableRecords([$this->customer])
            ->assertSee('Não cumprida · 25/10/2026');
    }

    public function test_promise_date_is_required_only_for_promises(): void
    {
        $this->invoice();

        Livewire::test(Delinquency::class)
            ->callTableAction('registerContact', $this->customer, ['channel' => 'phone', 'outcome' => 'promised'])
            ->assertHasTableActionErrors(['promised_date' => 'required']);

        Livewire::test(Delinquency::class)
            ->callTableAction('registerContact', $this->customer, ['channel' => 'phone', 'outcome' => 'no_answer', 'promised_date' => '2026-10-30'])
            ->assertHasNoTableActionErrors();

        $this->assertNull(CollectionContact::sole()->promised_date);
    }

    public function test_late_filter_uses_the_oldest_overdue_invoice(): void
    {
        $this->invoice(['due_date' => '2026-07-01']);
        $recent = Customer::create(['name' => 'Recente', 'document' => '529.982.247-25']);
        $this->invoice(['due_date' => '2026-10-13'], $recent);

        Livewire::test(Delinquency::class)
            ->filterTable('late', 'over_90')->assertCanSeeTableRecords([$this->customer])->assertCanNotSeeTableRecords([$recent])
            ->filterTable('late', 'up_to_30')->assertCanSeeTableRecords([$recent])->assertCanNotSeeTableRecords([$this->customer]);
    }

    public function test_details_show_contacts_invoices_and_history(): void
    {
        $this->invoice();
        CollectionContact::record(OverdueDebt::for($this->customer, $this->calculator()), CollectionChannel::Phone, CollectionOutcome::NoAnswer, ['notes' => 'Caixa postal']);

        Livewire::test(Delinquency::class)
            ->mountAction(TestAction::make('details')->table($this->customer))
            ->assertMountedActionModalSee(['Responsável · (83) 98888-7777', 'Mensalidade setembro/2026', 'há 30 dias', 'Ligação', 'Não respondeu', 'Caixa postal']);
    }

    public function test_only_the_office_sees_the_page(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $this->actingAs($teacher);

        $this->assertFalse(Delinquency::canAccess());
        $this->get(Delinquency::getUrl())->assertForbidden();
    }

    public function test_invoice_details_show_the_updated_value(): void
    {
        $invoice = $this->invoice();

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])->assertOk();
        $this->get(route('filament.admin.resources.invoices.view', $invoice))
            ->assertOk()
            ->assertSee(['Multa e juros até hoje', 'Valor atualizado (estimado)', '2% de multa (R$ 2,00) + 6% ao mês por 30 dia(s) (R$ 6,00), sobre R$ 100,00']);
    }
}
