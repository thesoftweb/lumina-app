<?php

namespace Tests\Feature;

use App\Documents\PaymentReceipt;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\Asaas\PaymentSynchronizer;
use App\Services\Asaas\SyncChange;
use App\Services\AsaasService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsaasReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['asaas.api_key' => 'chave-de-teste', 'asaas.environment' => 'sandbox']);

        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $this->user = User::factory()->create(['company_id' => $company->id]);
        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94', 'asaas_customer_id' => 'cus_maria']);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => '000100',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-10',
            'amount' => 500,
            'balance' => 500,
            'status' => InvoiceStatus::Overdue,
            'billing_type' => 'tuition',
            'asaas_invoice_id' => 'pay_1',
            ...$attributes,
        ]);
    }

    private function charge(array $attributes = []): array
    {
        return [
            'id' => 'pay_1',
            'customer' => 'cus_maria',
            'status' => 'PENDING',
            'value' => 500,
            'netValue' => 495,
            'billingType' => 'PIX',
            'dueDate' => '2026-09-10',
            'paymentDate' => null,
            'clientPaymentDate' => null,
            'confirmedDate' => null,
            'externalReference' => null,
            'deleted' => false,
            ...$attributes,
        ];
    }

    private function sync(Invoice $invoice, array $charge, bool $dryRun = false)
    {
        return app(PaymentSynchronizer::class)->apply($invoice, $charge, $dryRun);
    }

    /** @param  list<list<array<string, mixed>>>  $pages */
    private function fakeListing(array $pages): void
    {
        $responses = Http::sequence();

        foreach ($pages as $index => $data) {
            $responses->push(['object' => 'list', 'hasMore' => $index < count($pages) - 1, 'totalCount' => 0, 'limit' => 100, 'offset' => $index * 100, 'data' => $data]);
        }

        Http::fake(['*/payments?*' => $responses]);
    }

    public function test_received_in_cash_is_paid_in_cash_on_the_asaas_date(): void
    {
        $invoice = $this->invoice();

        $result = $this->sync($invoice, $this->charge(['status' => 'RECEIVED_IN_CASH', 'billingType' => 'BOLETO', 'paymentDate' => '2026-09-15', 'value' => 500]));

        $invoice->refresh();
        $payment = $invoice->payments()->sole();
        $this->assertSame(SyncChange::Paid, $result->change);
        $this->assertSame(InvoiceStatus::Overdue, $result->statusBefore);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(PaymentType::CASH, $payment->payment_method);
        $this->assertSame('2026-09-15', $payment->payment_date->toDateString());
        $this->assertSame('500.00', $payment->amount);
        $this->assertSame('pay_1', $payment->reference);
        $this->assertSame('pay_1', $payment->asaas_payment_id);
        $this->assertSame([], $result->warnings);
    }

    public function test_pix_and_boleto_use_the_billing_type_and_the_real_dates(): void
    {
        $pix = $this->invoice();
        $boleto = $this->invoice(['asaas_invoice_id' => 'pay_2']);

        $this->sync($pix, $this->charge(['status' => 'RECEIVED', 'billingType' => 'PIX', 'paymentDate' => '2026-09-09']));
        $this->sync($boleto, $this->charge(['id' => 'pay_2', 'status' => 'CONFIRMED', 'billingType' => 'BOLETO', 'clientPaymentDate' => '2026-09-08', 'confirmedDate' => '2026-09-11']));

        $this->assertSame(PaymentType::PIX, $pix->payments()->sole()->payment_method);
        $this->assertSame('2026-09-09', $pix->payments()->sole()->payment_date->toDateString());
        $this->assertSame(PaymentType::BOLETO, $boleto->payments()->sole()->payment_method);
        $this->assertSame('2026-09-08', $boleto->payments()->sole()->payment_date->toDateString());
    }

    public function test_billing_types_map_to_local_payment_methods(): void
    {
        $method = fn (array $charge) => PaymentSynchronizer::paymentMethodFor($charge);

        $this->assertSame(PaymentType::CREDIT_CARD, $method(['status' => 'CONFIRMED', 'billingType' => 'CREDIT_CARD']));
        $this->assertSame(PaymentType::DEBIT_CARD, $method(['status' => 'RECEIVED', 'billingType' => 'DEBIT_CARD']));
        $this->assertSame(PaymentType::BANK_TRANSFER, $method(['status' => 'RECEIVED', 'billingType' => 'DEPOSIT']));
        $this->assertSame(PaymentType::BANK_TRANSFER, $method(['status' => 'RECEIVED', 'billingType' => 'TRANSFER']));
        $this->assertSame(PaymentType::ONLINE_PAYMENT, $method(['status' => 'RECEIVED', 'billingType' => 'UNDEFINED']));
        $this->assertSame(PaymentType::CASH, $method(['status' => 'RECEIVED_IN_CASH', 'billingType' => 'PIX']));
    }

    public function test_syncing_twice_does_not_duplicate_the_payment_or_change_a_paid_invoice(): void
    {
        $invoice = $this->invoice();
        $charge = $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-09-15']);

        $this->sync($invoice, $charge);
        PaymentReceipt::issue($invoice->payments()->sole(), $this->user);
        $second = $this->sync($invoice, $charge);

        $this->assertSame(SyncChange::Unchanged, $second->change);
        $this->assertFalse($second->changed());
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(1, IssuedDocument::count());
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $invoice = $this->invoice();

        $result = $this->sync($invoice, $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-09-15', 'dueDate' => '2026-09-20']), dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(SyncChange::Paid, $result->change);
        $this->assertSame(PaymentType::CASH, $result->paymentMethod);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(0, InvoicePayment::count());
        $this->assertNull($invoice->fresh()->asaas_synced_at);
    }

    public function test_refunded_reverses_the_payment_cancels_the_receipt_and_cancels_the_invoice(): void
    {
        $invoice = $this->invoice();
        $this->sync($invoice, $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-09-09']));
        $receipt = PaymentReceipt::issue($invoice->payments()->sole(), $this->user);

        $result = $this->sync($invoice, $this->charge(['status' => 'REFUNDED', 'paymentDate' => '2026-09-09']));

        $invoice->refresh();
        $this->assertSame(SyncChange::Refunded, $result->change);
        $this->assertSame(1, $result->paymentsReversed);
        $this->assertSame(InvoiceStatus::Canceled, $invoice->status);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertStringContainsString('Cobrança estornada no Asaas em 23/10/2026', $invoice->notes);
        $this->assertNotNull($receipt->fresh()->canceled_at);

        // De novo: nada muda
        $this->assertSame(SyncChange::Unchanged, $this->sync($invoice, $this->charge(['status' => 'REFUNDED']))->change);
    }

    public function test_refund_in_progress_and_chargeback_only_warn(): void
    {
        $invoice = $this->invoice();

        foreach (['REFUND_IN_PROGRESS', 'CHARGEBACK_REQUESTED'] as $status) {
            $result = $this->sync($invoice, $this->charge(['status' => $status]));

            $this->assertSame(SyncChange::Attention, $result->change);
            $this->assertNotEmpty($result->warnings);
            $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        }
    }

    public function test_deleted_charge_cancels_an_invoice_without_payments(): void
    {
        $invoice = $this->invoice();
        $paid = $this->invoice(['asaas_invoice_id' => 'pay_2', 'status' => InvoiceStatus::Partial, 'balance' => 200]);
        InvoicePayment::create(['invoice_id' => $paid->id, 'payment_date' => '2026-09-01', 'amount' => 300, 'payment_method' => 'cash']);

        $canceled = $this->sync($invoice, $this->charge(['deleted' => true]));
        $kept = $this->sync($paid, $this->charge(['id' => 'pay_2', 'deleted' => true]));

        $this->assertSame(SyncChange::Canceled, $canceled->change);
        $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);
        $this->assertSame('canceled', $invoice->fresh()->asaas_sync_status);
        $this->assertStringContainsString('Cobrança excluída no Asaas', $invoice->fresh()->notes);
        $this->assertSame(SyncChange::Attention, $kept->change);
        $this->assertSame(InvoiceStatus::Partial, $paid->fresh()->status);

        // Restaurada no Asaas: volta a ser cobrada
        $restored = $this->sync($invoice, $this->charge(['status' => 'OVERDUE']));
        $this->assertSame(SyncChange::Overdue, $restored->change);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame('500.00', $invoice->fresh()->balance);
    }

    public function test_open_invoice_takes_the_value_and_due_date_from_asaas(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Open, 'due_date' => '2026-10-10']);

        $result = $this->sync($invoice, $this->charge(['status' => 'PENDING', 'value' => 550, 'dueDate' => '2026-11-10']));

        $invoice->refresh();
        $this->assertSame(SyncChange::Updated, $result->change);
        $this->assertCount(2, $result->updates);
        $this->assertSame('550.00', $invoice->amount);
        $this->assertSame('550.00', $invoice->balance);
        $this->assertSame('2026-11-10', $invoice->due_date->toDateString());
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
    }

    public function test_paid_invoice_with_open_charge_is_not_touched_and_warns(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid, 'balance' => 0]);

        $result = $this->sync($invoice, $this->charge(['status' => 'OVERDUE', 'value' => 999]));

        $this->assertSame(SyncChange::Attention, $result->change);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('500.00', $invoice->fresh()->amount);
    }

    public function test_listing_follows_has_more_through_all_pages(): void
    {
        $this->fakeListing([
            [$this->charge(['id' => 'pay_a'])],
            [$this->charge(['id' => 'pay_b'])],
            [$this->charge(['id' => 'pay_c'])],
        ]);

        $ids = app(AsaasService::class)->paginatePayments(['status' => 'PENDING'])->pluck('id')->all();

        $this->assertSame(['pay_a', 'pay_b', 'pay_c'], $ids);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['offset'] === 200 && $request['limit'] === 100 && $request['status'] === 'PENDING');
    }

    public function test_reconcile_applies_changes_and_reports_charges_without_invoice(): void
    {
        $byId = $this->invoice();
        $byReference = $this->invoice(['asaas_invoice_id' => null, 'number' => '000101']);
        $this->fakeListing([
            [
                $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-09-15']),
                $this->charge(['id' => 'pay_ref', 'status' => 'RECEIVED', 'billingType' => 'PIX', 'paymentDate' => '2026-09-12', 'externalReference' => (string) $byReference->id]),
            ],
            [
                $this->charge(['id' => 'pay_orphan', 'status' => 'RECEIVED_IN_CASH', 'externalReference' => '99999', 'value' => 321]),
                $this->charge(['id' => 'pay_event', 'status' => 'RECEIVED', 'externalReference' => 'EVENT-1-1']),
                $this->charge(['id' => 'pay_refund', 'status' => 'REFUND_IN_PROGRESS', 'externalReference' => '88888']),
            ],
        ]);

        $this->artisan('asaas:reconcile', ['--since' => '2026-01-01'])
            ->expectsOutputToContain('Faturas alteradas')
            ->expectsOutputToContain('pay_orphan')
            ->expectsOutputToContain('Maria Responsável')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Paid, $byId->fresh()->status);
        $this->assertSame(PaymentType::CASH, $byId->payments()->sole()->payment_method);
        $this->assertSame(InvoiceStatus::Paid, $byReference->fresh()->status);
        $this->assertSame('pay_ref', $byReference->fresh()->asaas_invoice_id);
        $this->assertSame(2, InvoicePayment::count());
        Http::assertSent(fn (Request $request): bool => $request['dueDate[ge]'] === '2026-01-01');
    }

    public function test_reconcile_dry_run_only_reports(): void
    {
        $invoice = $this->invoice();
        $this->fakeListing([[$this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-09-15'])]]);

        $this->artisan('asaas:reconcile', ['--dry-run' => true, '--status' => 'received_in_cash'])
            ->expectsOutputToContain('MUDARIAM')
            ->expectsOutputToContain('Dinheiro')
            ->expectsOutputToContain('nada foi gravado')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(0, InvoicePayment::count());
        Http::assertSent(fn (Request $request): bool => $request['status'] === 'RECEIVED_IN_CASH');
    }

    public function test_reconcile_rejects_invalid_options(): void
    {
        $this->artisan('asaas:reconcile', ['--since' => '23/10/2026'])->assertFailed();
        $this->artisan('asaas:reconcile', ['--status' => 'PAGO'])->assertFailed();
        Http::assertNothingSent();
    }

    public function test_reconcile_is_scheduled_daily_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'asaas:reconcile'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_webhook_payment_received_still_marks_the_invoice_paid(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::response($this->charge(['status' => 'RECEIVED', 'billingType' => 'BOLETO', 'paymentDate' => '2026-10-20']))]);

        $this->postJson('/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => $this->charge(['status' => 'RECEIVED', 'externalReference' => (string) $invoice->id]),
        ])->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $payment = $invoice->payments()->sole();
        $this->assertSame(PaymentType::BOLETO, $payment->payment_method);
        $this->assertSame('2026-10-20', $payment->payment_date->toDateString());
    }
}
