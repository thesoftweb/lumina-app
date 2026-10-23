<?php

namespace Tests\Feature;

use App\Documents\PaymentReceipt;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Widgets\InvoiceTotalsOverview;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesScreenTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('secretary');
        $this->actingAs($user);

        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94']);
        $this->enrollment = $this->enroll($this->customer, 'Ana Clara', 'Turma A');
    }

    private function enroll(Customer $customer, string $studentName, string $classroomName): Enrollment
    {
        $student = Student::create(['name' => $studentName, 'customer_id' => $customer->id, 'date_of_birth' => '2015-03-10', 'gender' => 'F']);
        $classroom = Classroom::create(['name' => $classroomName, 'level_id' => Level::create(['name' => "Nível {$classroomName}"])->id]);

        return Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);
    }

    private function invoice(string $number, array $attributes = []): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'enrollment_id' => $this->enrollment->id,
            'number' => $number,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-11-10',
            'amount' => 100,
            'balance' => 100,
            'status' => 'open',
            'billing_type' => 'tuition',
            ...$attributes,
        ]);
    }

    /** Uma fatura de cada situação relevante para abas, filtros e totais. */
    private function scenario(): array
    {
        return [
            'late' => $this->invoice('ATRASADA', ['due_date' => '2026-10-10', 'balance' => 100]),
            'partialLate' => $this->invoice('PARCIAL', ['due_date' => '2026-09-10', 'status' => 'partial', 'balance' => 40]),
            'soon' => $this->invoice('PROXIMA', ['due_date' => '2026-10-27', 'balance' => 100, 'asaas_invoice_id' => 'pay_1', 'asaas_sync_status' => 'synced']),
            'future' => $this->invoice('FUTURA', ['due_date' => '2026-12-10', 'balance' => 100, 'billing_type' => 'material', 'asaas_sync_status' => 'failed']),
            'paid' => $this->invoice('PAGA', ['due_date' => '2026-10-05', 'status' => 'paid', 'balance' => 0, 'asaas_invoice_id' => 'pay_2', 'asaas_sync_status' => 'synced']),
            'canceled' => $this->invoice('CANCELADA', ['due_date' => '2026-10-05', 'status' => 'canceled', 'balance' => 100]),
        ];
    }

    public function test_tabs_split_invoices_and_show_counts(): void
    {
        ['late' => $late, 'partialLate' => $partialLate, 'soon' => $soon, 'future' => $future, 'paid' => $paid, 'canceled' => $canceled] = $this->scenario();

        $page = Livewire::test(ListInvoices::class);
        $tabs = $page->instance()->getTabs();

        $this->assertSame(['todas', 'em_aberto', 'vencidas', 'pagas', 'canceladas'], array_keys($tabs));
        $this->assertSame(
            ['Todas' => '6', 'Em aberto' => '4', 'Vencidas' => '2', 'Pagas' => '1', 'Canceladas' => '1'],
            collect($tabs)->mapWithKeys(fn ($tab): array => [$tab->getLabel() => $tab->getBadge()])->all(),
        );

        $page->assertCanSeeTableRecords([$late, $partialLate, $soon, $future, $paid, $canceled]);

        $page->set('activeTab', 'em_aberto')
            ->assertCanSeeTableRecords([$late, $partialLate, $soon, $future])
            ->assertCanNotSeeTableRecords([$paid, $canceled]);

        $page->set('activeTab', 'vencidas')
            ->assertCanSeeTableRecords([$late, $partialLate])
            ->assertCanNotSeeTableRecords([$soon, $future, $paid, $canceled]);

        $page->set('activeTab', 'pagas')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$late, $canceled]);

        $page->set('activeTab', 'canceladas')
            ->assertCanSeeTableRecords([$canceled])
            ->assertCanNotSeeTableRecords([$late, $paid]);
    }

    public function test_each_filter_narrows_the_list(): void
    {
        ['late' => $late, 'partialLate' => $partialLate, 'soon' => $soon, 'future' => $future, 'paid' => $paid, 'canceled' => $canceled] = $this->scenario();
        $all = [$late, $partialLate, $soon, $future, $paid, $canceled];
        $only = fn (array $visible) => [$visible, array_values(array_filter($all, fn (Invoice $invoice): bool => ! in_array($invoice, $visible, true)))];

        $cases = [
            ['status', 'paid', [$paid]],
            ['billing_type', 'material', [$future]],
            ['due_period', ['from' => '2026-10-01', 'until' => '2026-10-31'], [$late, $soon, $paid, $canceled]],
            ['due_soon', true, [$soon]],
            ['with_balance', true, [$late, $partialLate, $soon, $future]],
            ['without_asaas_charge', true, [$late, $partialLate, $future]],
            ['asaas_sync_failed', true, [$future]],
        ];

        foreach ($cases as [$filter, $value, $visible]) {
            [$seen, $hidden] = $only($visible);

            Livewire::test(ListInvoices::class)
                ->filterTable($filter, $value)
                ->assertCanSeeTableRecords($seen)
                ->assertCanNotSeeTableRecords($hidden);
        }
    }

    public function test_classroom_filter_uses_the_enrollment_classroom(): void
    {
        $mine = $this->invoice('TURMA-A');
        $otherEnrollment = $this->enroll($this->customer, 'Pedro', 'Turma B');
        $other = $this->invoice('TURMA-B', ['enrollment_id' => $otherEnrollment->id]);
        $loose = $this->invoice('AVULSA', ['enrollment_id' => null]);

        Livewire::test(ListInvoices::class)
            ->filterTable('classroom', $this->enrollment->classroom_id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other, $loose]);
    }

    public function test_search_finds_by_number_customer_and_student(): void
    {
        $ana = $this->invoice('000078');
        $otherCustomer = Customer::create(['name' => 'José Pagador', 'document' => '111.222.333-44']);
        $pedroEnrollment = $this->enroll($otherCustomer, 'Pedro Henrique', 'Turma B');
        $pedro = $this->invoice('000099', ['customer_id' => $otherCustomer->id, 'enrollment_id' => $pedroEnrollment->id]);

        Livewire::test(ListInvoices::class)->searchTable('Pedro Henrique')
            ->assertCanSeeTableRecords([$pedro])->assertCanNotSeeTableRecords([$ana]);

        Livewire::test(ListInvoices::class)->searchTable('Maria')
            ->assertCanSeeTableRecords([$ana])->assertCanNotSeeTableRecords([$pedro]);

        Livewire::test(ListInvoices::class)->searchTable('000078')
            ->assertCanSeeTableRecords([$ana])->assertCanNotSeeTableRecords([$pedro]);
    }

    public function test_columns_show_student_competence_and_asaas_indicator(): void
    {
        $invoice = $this->invoice('000078', ['billing_period_start' => '2026-10-01', 'due_date' => '2026-10-10', 'asaas_invoice_id' => 'pay_1', 'asaas_sync_status' => 'synced']);
        $noPeriod = $this->invoice('000079', ['due_date' => '2026-12-10', 'billing_type' => 'material']);

        $this->assertSame('outubro/2026', $invoice->competenceLabel());
        $this->assertSame('dezembro/2026', $noPeriod->competenceLabel());

        Livewire::test(ListInvoices::class)
            ->assertTableColumnStateSet('competence', 'outubro/2026', $invoice)
            ->assertTableColumnStateSet('competence', 'dezembro/2026', $noPeriod)
            ->assertSee('Ana Clara')
            ->assertSee('Cobrança no Asaas sincronizada')
            ->assertSee('Sem cobrança no Asaas');

        $dueDate = Livewire::test(ListInvoices::class)->instance()->getTable()->getColumn('due_date');
        $this->assertSame('Vencida há 13 dia(s)', $dueDate->record($invoice)->getTooltip());
        $this->assertSame('danger', $dueDate->getColor($invoice->due_date));
        $this->assertNull($dueDate->record($noPeriod)->getTooltip());
    }

    public function test_row_opens_the_edit_page(): void
    {
        $invoice = $this->invoice('000078');

        $url = Livewire::test(ListInvoices::class)->instance()->getTable()->getRecordUrl($invoice);

        $this->assertSame(InvoiceResource::getUrl('edit', ['record' => $invoice]), $url);
    }

    public function test_view_action_opens_a_slide_over_with_the_payments(): void
    {
        $invoice = $this->invoice('000078', ['status' => 'paid', 'balance' => 0, 'billing_period_start' => '2026-10-01', 'notes' => 'Pago na secretaria']);
        $payment = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 100, 'payment_method' => 'pix']);
        $receipt = PaymentReceipt::issue($payment, auth()->user());

        $page = Livewire::test(ListInvoices::class)
            ->mountAction(TestAction::make('view')->table($invoice))
            ->assertMountedActionModalSee([
                'Fatura nº 000078', 'Resumo', 'outubro/2026', 'Ana Clara', 'Turma A',
                'Pagamentos recebidos', '10/10/2026', 'PIX', $receipt->number,
                'Sem cobrança no Asaas', 'Pago na secretaria',
            ]);

        $action = $page->instance()->getTable()->getAction('view');
        $this->assertTrue($action->isModalSlideOver());
        $this->assertNull($action->record($invoice)->getUrl());
    }

    public function test_totals_widget_sums_open_overdue_and_received_this_month(): void
    {
        ['late' => $late, 'paid' => $paid] = $this->scenario();
        InvoicePayment::create(['invoice_id' => $paid->id, 'payment_date' => '2026-10-05', 'amount' => 100, 'payment_method' => 'pix']);
        InvoicePayment::create(['invoice_id' => $late->id, 'payment_date' => '2026-10-20', 'amount' => 25.50, 'payment_method' => 'cash']);
        InvoicePayment::create(['invoice_id' => $late->id, 'payment_date' => '2026-09-30', 'amount' => 999, 'payment_method' => 'cash']);

        // Em aberto: 100 + 40 + 100 + 100; vencido: 100 + 40; recebido em outubro: 100 + 25,50
        Livewire::test(InvoiceTotalsOverview::class)
            ->assertSeeInOrder(['Total em aberto', "R\$\u{a0}340,00", '4 faturas com saldo a pagar'])
            ->assertSeeInOrder(['Total vencido', "R\$\u{a0}140,00", '2 faturas vencidas'])
            ->assertSeeInOrder(['Recebido em outubro/2026', "R\$\u{a0}125,50", '2 pagamentos registrados']);
    }

    public function test_edit_page_groups_the_header_actions_and_shows_the_summary_column(): void
    {
        Http::fake();
        $invoice = $this->invoice('000078', ['asaas_invoice_id' => 'pay_1', 'asaas_sync_status' => 'failed']);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 30, 'payment_method' => 'pix']);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->assertSee('Fatura nº 000078')
            ->assertSee('Maria Responsável · Ana Clara · novembro/2026')
            ->assertSee(['Cobrança', 'Valor e datas', 'Classificação financeira', 'Observações', 'Situação', 'Asaas'])
            ->assertSee('Falha na sincronização com o Asaas')
            ->assertActionVisible('issuePaymentReceipt')
            ->assertActionVisible('sync_asaas_payment')
            ->assertActionHidden('generate_asaas_charge')
            ->assertActionVisible('view')
            ->assertActionVisible('receivePayment')
            ->assertActionVisible('undoPayment')
            // Fatura com pagamento: valor e vencimento ficam só leitura; observações são só locais
            ->assertFormFieldDisabled('amount')
            ->assertFormFieldDisabled('due_date')
            ->fillForm(['notes' => 'Combinado com a mãe'])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();
        $this->assertSame('Combinado com a mãe', $invoice->fresh()->notes);
        $this->assertSame('100.00', $invoice->fresh()->amount);
    }

    public function test_old_view_url_still_works(): void
    {
        $invoice = $this->invoice('000078');

        $this->get(InvoiceResource::getUrl('view', ['record' => $invoice]))
            ->assertOk()
            ->assertSee('Fatura nº 000078');
    }

    public function test_teacher_cannot_open_invoices(): void
    {
        $invoice = $this->invoice('000078');
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $this->actingAs($teacher);

        $this->get(InvoiceResource::getUrl('index'))->assertForbidden();
        $this->get(InvoiceResource::getUrl('edit', ['record' => $invoice]))->assertForbidden();
    }
}
