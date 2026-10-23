<?php

namespace Tests\Feature;

use App\Documents\DocumentText;
use App\Documents\PaymentReceipt;
use App\Enums\IssuedDocumentStatus;
use App\Enums\IssuedDocumentType;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\IssuedDocument;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Services\DocumentIssuer;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Invoice $invoice;

    private InvoicePayment $payment;

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
        $student = Student::create(['name' => 'Ana Clara', 'customer_id' => $this->customer->id, 'date_of_birth' => '2015-03-10', 'gender' => 'F']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);

        $this->invoice = $this->invoice(['enrollment_id' => $enrollment->id]);
        $this->payment = $this->pay($this->invoice, 500);
    }

    private function invoice(array $attributes = []): Invoice
    {
        $id = DB::table('invoices')->insertGetId([
            'customer_id' => $this->customer->id, 'number' => '000078', 'reference' => 'MENS-1-2026-10',
            'issue_date' => '2026-10-01', 'due_date' => '2026-10-10', 'billing_period_start' => '2026-10-01',
            'billing_type' => 'tuition', 'amount' => 500, 'balance' => 0, 'status' => 'paid',
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);

        return Invoice::findOrFail($id);
    }

    private function pay(Invoice $invoice, float $amount, string $method = 'pix', string $date = '2026-10-10'): InvoicePayment
    {
        return InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => $date, 'amount' => $amount, 'payment_method' => $method]);
    }

    public function test_amounts_are_written_in_words(): void
    {
        $cases = [
            500 => 'quinhentos reais',
            1 => 'um real',
            '1234.50' => 'mil duzentos e trinta e quatro reais e cinquenta centavos',
            '0.01' => 'um centavo',
            '0.50' => 'cinquenta centavos',
            '101.01' => 'cento e um reais e um centavo',
            1000000 => 'um milhão de reais',
            '1000000.10' => 'um milhão de reais e dez centavos',
            2500000 => 'dois milhões e quinhentos mil reais',
        ];

        foreach ($cases as $amount => $words) {
            $this->assertSame($words, DocumentText::moneyInWords($amount), (string) $amount);
        }
    }

    public function test_receipt_is_issued_from_the_invoice_with_the_payment_details(): void
    {
        Livewire::test(ViewInvoice::class, ['record' => $this->invoice->getKey()])
            ->assertActionVisible('issuePaymentReceipt')
            ->callAction('issuePaymentReceipt')
            ->assertHasNoActionErrors()
            ->assertNotified('Documento emitido');

        $receipt = IssuedDocument::sole();
        $this->assertSame(IssuedDocumentType::PaymentReceipt, $receipt->type);
        $this->assertSame('REC-2026-000001', $receipt->number);
        $this->assertTrue($receipt->documentable->is($this->payment));
        $this->assertNull($receipt->valid_until);
        $this->assertTrue($receipt->hasIntactContent());

        $this->get(route('issued-documents.print', $receipt))
            ->assertSee('Recibo de pagamento')
            ->assertSee("Recebemos de <strong>Maria Responsável</strong>, CPF 096.234.214-94, a importância de <strong>R\$\u{a0}500,00</strong> (quinhentos reais), referente à <strong>mensalidade de outubro de 2026</strong> da aluna <strong>Ana Clara</strong>, fatura nº 000078, paga em 10/10/2026, forma de pagamento: PIX.", false)
            ->assertSee('dando quitação do valor recebido')
            ->assertDontSee('Pagamento parcial');

        $this->get(route('filament.admin.resources.invoices.view', $this->invoice))->assertSee('REC-2026-000001');
    }

    public function test_asking_again_reopens_the_same_receipt_until_it_is_canceled(): void
    {
        $first = PaymentReceipt::issue($this->payment, auth()->user());

        Livewire::test(ViewInvoice::class, ['record' => $this->invoice->getKey()])
            ->callAction('issuePaymentReceipt', ['payment_id' => $this->payment->id])
            ->assertNotified('Documento já emitido');
        $this->assertSame(1, IssuedDocument::count());

        app(DocumentIssuer::class)->cancel($first, auth()->user(), 'Valor digitado errado');
        $second = PaymentReceipt::issue($this->payment, auth()->user());

        $this->assertSame('REC-2026-000002', $second->number);
        $this->assertTrue(PaymentReceipt::existing($this->payment)->is($second));
    }

    public function test_partial_payment_and_other_charge_types_are_described(): void
    {
        $material = $this->invoice(['number' => '000090', 'billing_type' => 'material', 'amount' => 300, 'balance' => 100, 'status' => 'partial', 'enrollment_id' => null]);
        $receipt = PaymentReceipt::issue($this->pay($material, 200, 'cash', '2026-10-20'), auth()->user());

        $this->get(route('issued-documents.print', $receipt))
            ->assertSee('referente ao <strong>material didático</strong>, fatura nº 000090, paga em 20/10/2026, forma de pagamento: Dinheiro.', false)
            ->assertSee("Pagamento parcial da fatura, cujo valor total é de R\$\u{a0}300,00.")
            ->assertDontSee('da aluna');
    }

    public function test_reversed_payment_or_deleted_invoice_cancels_the_receipt(): void
    {
        $receipt = PaymentReceipt::issue($this->payment, auth()->user());
        app(PaymentService::class)->reversePayment($this->payment);

        $receipt->refresh();
        $this->assertSame(IssuedDocumentStatus::Canceled, $receipt->status());
        $this->assertSame('Pagamento estornado em 23/10/2026.', $receipt->cancel_reason);

        $other = $this->invoice(['number' => '000091']);
        $otherReceipt = PaymentReceipt::issue($this->pay($other, 500), auth()->user());
        $other->delete();

        $this->assertSame('Fatura excluída em 23/10/2026.', $otherReceipt->fresh()->cancel_reason);

        auth()->logout();
        $this->get(route('documents.verify', $receipt->formattedCode()))
            ->assertSee(['Documento cancelado', 'Pagamento estornado em 23/10/2026.']);
    }

    public function test_receipt_is_available_in_the_customer_invoices_and_hidden_without_payments(): void
    {
        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => EditCustomer::class])
            ->callTableAction('issuePaymentReceipt', $this->invoice, ['payment_id' => $this->payment->id])
            ->assertNotified('Documento emitido');

        $this->assertSame(1, IssuedDocument::count());

        $unpaid = $this->invoice(['number' => '000092', 'status' => 'open', 'balance' => 500]);
        Livewire::test(ViewInvoice::class, ['record' => $unpaid->getKey()])
            ->assertActionHidden('issuePaymentReceipt');
    }

    public function test_verification_masks_the_payer_cpf(): void
    {
        $receipt = PaymentReceipt::issue($this->payment, auth()->user());
        auth()->logout();

        $this->get(route('documents.verify', $receipt->formattedCode()))
            ->assertSee(['Documento válido', 'Recibo de pagamento', 'CPF ***.234.214-**'])
            ->assertDontSee('096.234.214-94');
    }
}
