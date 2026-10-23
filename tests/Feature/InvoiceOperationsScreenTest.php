<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** As operações de fatura nas telas: criar, editar, receber, desfazer, cancelar, restaurar e excluir. */
class InvoiceOperationsScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $this->actingAsRole('secretary');
        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94', 'asaas_customer_id' => 'cus_1']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => '000078',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-11-10',
            'amount' => 100,
            'balance' => 100,
            'status' => 'open',
            'billing_type' => 'tuition',
            'asaas_invoice_id' => 'pay_1',
            'asaas_sync_status' => 'synced',
            ...$attributes,
        ]);
    }

    private function charge(array $overrides = []): array
    {
        return ['id' => 'pay_1', 'status' => 'PENDING', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-11-10', 'deleted' => false, ...$overrides];
    }

    private function refusal(string $description): PromiseInterface
    {
        return Http::response(['errors' => [['code' => 'invalid', 'description' => $description]]], 400);
    }

    private function editPage(Invoice $invoice)
    {
        return Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()]);
    }

    private function customerTab()
    {
        return Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => EditCustomer::class]);
    }

    // ------------------------------------------------------------------ criar

    public function test_create_page_creates_the_invoice_with_its_charge(): void
    {
        Http::fake(['*/payments' => Http::response($this->charge(['id' => 'pay_new', 'invoiceUrl' => 'https://asaas.test/i/new']))]);

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'billing_type' => 'material',
                'amount' => 80,
                'issue_date' => '2026-10-23',
                'due_date' => '2026-11-05',
                'notes' => 'Apostila do 2º semestre',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame('pay_new', $invoice->asaas_invoice_id);
        $this->assertSame('80.00', $invoice->balance);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        Http::assertSent(fn (Request $request): bool => $request['description'] === 'Apostila do 2º semestre' && (float) $request['value'] === 80.0);
    }

    public function test_create_page_does_not_create_the_invoice_when_asaas_refuses(): void
    {
        Http::fake(['*/payments' => $this->refusal('O CPF ou CNPJ do cliente deve ser informado.')]);

        Livewire::test(CreateInvoice::class)
            ->fillForm(['customer_id' => $this->customer->id, 'billing_type' => 'material', 'amount' => 80, 'issue_date' => '2026-10-23', 'due_date' => '2026-11-05'])
            ->call('create')
            ->assertNotified('A fatura não foi criada');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_customer_tab_create_is_refused_without_creating_the_invoice(): void
    {
        Http::fake(['*/payments' => $this->refusal('Data de vencimento inválida.')]);

        $this->customerTab()
            ->callAction(TestAction::make('create')->table(), [
                'customer_id' => $this->customer->id, 'billing_type' => 'tuition',
                'issue_date' => '2026-10-23', 'due_date' => '2026-10-01', 'amount' => 120,
            ])
            ->assertNotified('A fatura não foi criada');

        $this->assertDatabaseCount('invoices', 0);
    }

    // ------------------------------------------------------------------ matrícula

    private function enrollment(): Enrollment
    {
        $student = Student::create(['name' => 'Ana Clara', 'customer_id' => $this->customer->id, 'date_of_birth' => '2015-03-10', 'gender' => 'F']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $plan = Plan::create(['name' => 'Mensal', 'base_amount' => 100, 'has_discount' => false, 'discount_type' => 'none', 'discount_value' => 0, 'final_value' => 100, 'is_active' => true]);
        $classroom->plans()->attach($plan);
        $year = AcademicYear::create(['description' => '2026.1', 'year' => 2026, 'is_default' => true]);

        return Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id, 'plan_id' => $plan->id,
            'enrollment_date' => '2026-10-20', 'status' => 'active', 'day_of_payment' => 10,
        ]);
    }

    public function test_enrollment_invoice_is_created_with_its_charge_or_not_at_all(): void
    {
        $enrollment = $this->enrollment();
        Http::fake(['*/payments' => Http::sequence()
            ->pushResponse($this->refusal('O valor da cobrança é inválido.'))
            ->push($this->charge(['id' => 'pay_mat'])),
        ]);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->id])
            ->callAction('createEnrollmentInvoice', ['enrollment_amount' => 300])
            ->assertNotified('A fatura não foi gerada');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertFalse((bool) $enrollment->fresh()->enrollment_tax_paid);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->id])
            ->callAction('createEnrollmentInvoice', ['enrollment_amount' => 300])
            ->assertNotified('Sucesso!');

        $this->assertSame('pay_mat', Invoice::query()->sole()->asaas_invoice_id);
        $this->assertTrue((bool) $enrollment->fresh()->enrollment_tax_paid);
    }

    public function test_tuition_batch_reports_the_months_refused_by_asaas_and_keeps_no_invoice_without_charge(): void
    {
        $enrollment = $this->enrollment();
        Http::fake(['*/payments' => Http::sequence()
            ->push($this->charge(['id' => 'pay_nov']))
            ->pushResponse($this->refusal('Limite de cobranças atingido.'))
            ->push($this->charge(['id' => 'pay_jan'])),
        ]);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->id])
            ->callAction('createTuitionInvoice', ['quantity' => 3, 'start_month' => 11, 'company_id' => 1])
            ->assertNotified('2 mensalidade(s) gerada(s); 1 não');

        $invoices = Invoice::query()->orderBy('billing_period_start')->get();
        $this->assertSame(['2026-11-01', '2027-01-01'], $invoices->map(fn (Invoice $invoice): string => $invoice->billing_period_start->toDateString())->all());
        $this->assertSame(['pay_nov', 'pay_jan'], $invoices->pluck('asaas_invoice_id')->all());
        $this->assertSame(0, Invoice::query()->whereNull('asaas_invoice_id')->count());
    }

    // ------------------------------------------------------------------ editar

    public function test_saving_the_edit_form_updates_the_charge_first(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge())->push($this->charge(['value' => 150]))]);

        $this->editPage($invoice)
            ->fillForm(['amount' => 150, 'due_date' => '2026-11-20'])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && (float) $request['value'] === 150.0 && $request['dueDate'] === '2026-11-20');
        $this->assertSame('150.00', $invoice->fresh()->amount);
        $this->assertSame('2026-11-20', $invoice->fresh()->due_date->toDateString());
    }

    public function test_saving_the_edit_form_refused_by_asaas_keeps_the_invoice(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge())->pushResponse($this->refusal('Data de vencimento inválida.'))]);

        $this->editPage($invoice)
            ->fillForm(['amount' => 150])
            ->call('save')
            ->assertNotified('Não foi possível salvar');

        $this->assertSame('100.00', $invoice->fresh()->amount);
    }

    public function test_paid_invoice_has_value_and_due_date_read_only_and_cannot_be_canceled(): void
    {
        $invoice = $this->invoice(['status' => 'paid', 'balance' => 0]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-20', 'amount' => 100, 'payment_method' => 'cash', 'reference' => 'pay_1']);

        $this->editPage($invoice)
            ->assertFormFieldDisabled('amount')
            ->assertFormFieldDisabled('due_date')
            ->assertFormFieldDisabled('customer_id')
            ->assertActionHidden('receivePayment')
            ->assertActionVisible('undoPayment')
            ->assertActionDisabled('cancelInvoice')
            ->assertActionHidden('restoreInvoice');
    }

    // ------------------------------------------------------------------ operações no cabeçalho

    public function test_receive_full_and_partial_from_the_edit_page(): void
    {
        $full = $this->invoice();
        Http::fake([
            '*/payments/pay_1/receiveInCash' => Http::response($this->charge(['status' => 'RECEIVED_IN_CASH'])),
            '*/payments/pay_2' => Http::sequence()->push($this->charge(['id' => 'pay_2']))->push($this->charge(['id' => 'pay_2', 'value' => 70])),
        ]);

        $this->editPage($full)
            ->callAction('receivePayment', ['kind' => 'full', 'amount' => 100, 'payment_method' => 'pix', 'payment_date' => '2026-10-23'])
            ->assertHasNoActionErrors()
            ->assertNotified('Pagamento registrado');

        $this->assertSame(InvoiceStatus::Paid, $full->fresh()->status);
        $this->assertSame(PaymentType::PIX, $full->payments()->sole()->payment_method);

        $partial = $this->invoice(['number' => '000079', 'asaas_invoice_id' => 'pay_2']);

        $this->editPage($partial)
            ->callAction('receivePayment', ['kind' => 'partial', 'amount' => 30, 'payment_method' => 'cash', 'payment_date' => '2026-10-23'])
            ->assertNotified('Pagamento parcial registrado');

        $this->assertSame(InvoiceStatus::Partial, $partial->fresh()->status);
        $this->assertSame('70.00', $partial->fresh()->balance);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with($request->url(), '/payments/pay_2') && (float) $request['value'] === 70.0);
    }

    public function test_receive_refused_by_asaas_shows_the_reason_and_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1/receiveInCash' => $this->refusal('Cobrança já recebida.')]);

        $this->editPage($invoice)
            ->callAction('receivePayment', ['kind' => 'full', 'amount' => 100, 'payment_method' => 'cash', 'payment_date' => '2026-10-23'])
            ->assertNotified('Não foi possível concluir');

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_undo_cancel_and_restore_from_the_edit_page(): void
    {
        $invoice = $this->invoice(['status' => 'paid', 'balance' => 0]);
        $payment = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-20', 'amount' => 100, 'payment_method' => 'cash', 'reference' => 'pay_1', 'asaas_payment_id' => 'pay_1']);
        Http::fake([
            '*/payments/pay_1/undoReceivedInCash' => Http::response($this->charge()),
            '*/payments/pay_1/restore' => Http::response($this->charge()),
            '*/payments/pay_1' => Http::response(['deleted' => true, 'id' => 'pay_1']),
        ]);

        $this->editPage($invoice)
            ->callAction('undoPayment', ['payment_id' => $payment->id])
            ->assertNotified('Recebimento desfeito');
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);

        $this->editPage($invoice)
            ->callAction('cancelInvoice', ['reason' => 'Matrícula trancada'])
            ->assertNotified('Fatura cancelada');
        $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);

        $this->editPage($invoice)
            ->assertActionHidden('cancelInvoice')
            ->callAction('restoreInvoice')
            ->assertNotified('Fatura restaurada');
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->balance);
    }

    public function test_cancel_refused_by_asaas_keeps_the_invoice_open(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => $this->refusal('Cobrança não pode ser removida.')]);

        $this->editPage($invoice)
            ->callAction('cancelInvoice', ['reason' => 'Engano'])
            ->assertNotified('Não foi possível concluir');

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
    }

    // ------------------------------------------------------------------ listagem e aba do responsável

    public function test_list_row_and_customer_tab_use_the_same_operations(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => null, 'asaas_sync_status' => null]);
        Http::fake();

        Livewire::test(ListInvoices::class)
            ->callAction(TestAction::make('receivePayment')->table($invoice), ['kind' => 'partial', 'amount' => 40, 'payment_method' => 'cash', 'payment_date' => '2026-10-23'])
            ->assertNotified('Pagamento parcial registrado');
        $this->assertSame('60.00', $invoice->fresh()->balance);

        $this->customerTab()
            ->assertActionExists(TestAction::make('undoPayment')->table($invoice))
            ->assertActionExists(TestAction::make('cancelInvoice')->table($invoice))
            ->callAction(TestAction::make('receivePayment')->table($invoice), ['kind' => 'full', 'amount' => 60, 'payment_method' => 'pix', 'payment_date' => '2026-10-23'])
            ->assertNotified('Pagamento registrado');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_customer_tab_quick_edit_goes_through_asaas(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge())->push($this->charge(['dueDate' => '2026-11-15']))]);

        $this->customerTab()
            ->callAction(TestAction::make('editCharge')->table($invoice), ['amount' => 100, 'due_date' => '2026-11-15', 'notes' => 'Nova descrição'])
            ->assertNotified('Fatura atualizada');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['dueDate'] === '2026-11-15' && $request['description'] === 'Nova descrição');
        $this->assertSame('2026-11-15', $invoice->fresh()->due_date->toDateString());
    }

    public function test_customer_tab_bulk_due_day_skips_paid_invoices_and_reports_refusals(): void
    {
        $open = $this->invoice();
        $refused = $this->invoice(['number' => '000079', 'asaas_invoice_id' => 'pay_2']);
        $paid = $this->invoice(['number' => '000080', 'asaas_invoice_id' => 'pay_3', 'status' => 'paid', 'balance' => 0]);
        Http::fake([
            '*/payments/pay_1' => Http::sequence()->push($this->charge())->push($this->charge(['dueDate' => '2026-11-15'])),
            '*/payments/pay_2' => Http::sequence()->push($this->charge(['id' => 'pay_2']))->pushResponse($this->refusal('Cobrança vencida não pode ser alterada.')),
        ]);

        $this->customerTab()
            ->selectTableRecords([$open, $refused, $paid])
            ->callAction(TestAction::make('bulkDueDay')->table()->bulk(), ['due_day' => 15])
            ->assertNotified('Alguns vencimentos não foram alterados');

        $this->assertSame('2026-11-15', $open->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-10', $refused->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-10', $paid->fresh()->due_date->toDateString());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'pay_3'));
    }

    // ------------------------------------------------------------------ excluir e permissões

    public function test_only_admin_deletes_and_never_an_invoice_with_an_active_charge(): void
    {
        $charged = $this->invoice();
        $local = $this->invoice(['number' => '000079', 'asaas_invoice_id' => null, 'asaas_sync_status' => null]);

        $this->editPage($local)->assertActionHidden('delete');

        $this->actingAsRole('admin');
        $this->editPage($charged)->assertActionHidden('delete');
        $this->editPage($local)->assertActionVisible('delete');

        Livewire::test(ListInvoices::class)
            ->selectTableRecords([$charged, $local])
            ->callAction(TestAction::make('delete')->table()->bulk())
            ->assertNotified('Nenhuma fatura foi excluída');

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_teacher_cannot_create_or_operate_invoices(): void
    {
        $invoice = $this->invoice();
        $this->actingAsRole('teacher');

        $this->get(InvoiceResource::getUrl('create'))->assertForbidden();
        $this->get(InvoiceResource::getUrl('edit', ['record' => $invoice]))->assertForbidden();
    }
}
