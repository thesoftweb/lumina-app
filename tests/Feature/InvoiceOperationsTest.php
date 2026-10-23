<?php

namespace Tests\Feature;

use App\Documents\PaymentReceipt;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use App\Models\AsaasWebhookEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Asaas\ChargeOperationException;
use App\Services\Asaas\ChargeOperations;
use App\Services\Asaas\PaymentSynchronizer;
use App\Services\Asaas\SyncChange;
use Database\Seeders\RolePermissionSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Operações de fatura pelo sistema (ChargeOperations): Asaas primeiro; recusa do Asaas não muda nada.
 */
class InvoiceOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private ChargeOperations $operations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        // Recibos precisam de quem assina
        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('secretary');
        $this->actingAs($user);

        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94', 'asaas_customer_id' => 'cus_1']);
        $this->operations = app(ChargeOperations::class);
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
            'notes' => 'Mensalidade de novembro',
            'asaas_invoice_id' => 'pay_1',
            'asaas_sync_status' => 'synced',
            ...$attributes,
        ]);
    }

    /** Cobrança como o Asaas devolve em GET /payments/{id}. */
    private function charge(array $overrides = []): array
    {
        return [
            'id' => 'pay_1',
            'customer' => 'cus_1',
            'status' => 'PENDING',
            'billingType' => 'PIX',
            'value' => 100,
            'dueDate' => '2026-11-10',
            'description' => 'Mensalidade de novembro',
            'deleted' => false,
            ...$overrides,
        ];
    }

    private function refusal(string $description, int $status = 400): PromiseInterface
    {
        return Http::response(['errors' => [['code' => 'invalid_action', 'description' => $description]]], $status);
    }

    private function assertRefused(callable $operation, string $reason): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ter sido recusada.');
        } catch (ChargeOperationException $e) {
            $this->assertStringContainsString($reason, $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ criar

    public function test_creating_an_invoice_creates_the_charge_together(): void
    {
        Http::fake(['*/payments' => Http::response($this->charge(['id' => 'pay_new', 'invoiceUrl' => 'https://asaas.test/i/new', 'pixQrCode' => ['qrCode' => 'QR']]))]);

        $invoice = $this->operations->createInvoice(fn (): Invoice => $this->invoice(['asaas_invoice_id' => null, 'asaas_sync_status' => null]));

        $this->assertSame('pay_new', $invoice->asaas_invoice_id);
        $this->assertSame('synced', $invoice->asaas_sync_status);
        $this->assertSame('https://asaas.test/i/new', $invoice->invoice_link);
        $this->assertSame('QR', $invoice->invoice_qrcode);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/payments')
            && $request['customer'] === 'cus_1'
            && $request['externalReference'] === (string) $invoice->id
            && (float) $request['value'] === 100.0
            && $request['dueDate'] === '2026-11-10'
            && $request['description'] === 'Mensalidade de novembro');
    }

    public function test_invoice_past_its_due_date_gets_a_charge_due_today_and_keeps_its_own_due_date(): void
    {
        Http::fake(['*/payments' => Http::response($this->charge(['id' => 'pay_late', 'dueDate' => '2026-10-23']))]);

        $invoice = $this->operations->createInvoice(fn (): Invoice => $this->invoice([
            'due_date' => '2026-10-10', 'status' => 'overdue', 'asaas_invoice_id' => null, 'asaas_sync_status' => null,
        ]));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['dueDate'] === '2026-10-23');
        $this->assertSame('2026-10-10', $invoice->due_date->toDateString());
        $this->assertSame('2026-10-23', $invoice->asaas_due_date->toDateString());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);

        // A conciliação não troca o vencimento da fatura pelo da cobrança
        $result = app(PaymentSynchronizer::class)->apply($invoice, $this->charge(['id' => 'pay_late', 'dueDate' => '2026-10-23']));
        $this->assertSame(SyncChange::Unchanged, $result->change);
        $this->assertSame('2026-10-10', $invoice->fresh()->due_date->toDateString());

        // ...mas um vencimento novo dado no Asaas vale para a fatura
        app(PaymentSynchronizer::class)->apply($invoice->fresh(), $this->charge(['id' => 'pay_late', 'dueDate' => '2026-10-30']));
        $fresh = $invoice->fresh();
        $this->assertSame('2026-10-30', $fresh->due_date->toDateString());
        $this->assertNull($fresh->asaas_due_date);
        $this->assertSame(InvoiceStatus::Open, $fresh->status);
    }

    public function test_due_date_moved_to_the_past_goes_to_asaas_as_today(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge())->push($this->charge(['dueDate' => '2026-10-23']))]);

        $this->operations->update($invoice, ['due_date' => '2026-10-05']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['dueDate'] === '2026-10-23');
        $this->assertSame('2026-10-05', $invoice->due_date->toDateString());
        $this->assertSame('2026-10-23', $invoice->asaas_due_date->toDateString());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
    }

    public function test_invoice_is_not_created_when_asaas_refuses_the_charge(): void
    {
        Http::fake(['*/payments' => $this->refusal('O CPF ou CNPJ do cliente deve ser informado.')]);

        $this->assertRefused(
            fn () => $this->operations->createInvoice(fn (): Invoice => $this->invoice(['asaas_invoice_id' => null])),
            'O CPF ou CNPJ do cliente deve ser informado',
        );

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_customer_is_registered_on_asaas_first_and_its_refusal_blocks_the_invoice(): void
    {
        $this->customer->update(['asaas_customer_id' => null, 'document' => '']);
        Http::fake(['*/customers' => $this->refusal('O campo cpfCnpj é obrigatório.')]);

        $this->assertRefused(
            fn () => $this->operations->createInvoice(fn (): Invoice => $this->invoice(['asaas_invoice_id' => null])),
            'O campo cpfCnpj é obrigatório',
        );

        $this->assertDatabaseCount('invoices', 0);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/payments'));
    }

    public function test_charge_is_deleted_when_the_invoice_cannot_be_saved_after_asaas_accepted(): void
    {
        Http::fake([
            '*/payments/pay_new' => Http::response(['deleted' => true, 'id' => 'pay_new']),
            '*/payments' => Http::response($this->charge(['id' => 'pay_new'])),
        ]);
        Invoice::updating(fn () => throw new RuntimeException('falha no banco'));

        try {
            $this->operations->createInvoice(fn (): Invoice => $this->invoice(['asaas_invoice_id' => null]));
            $this->fail('Deveria ter falhado.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha no banco', $e->getMessage());
        }

        $this->assertDatabaseCount('invoices', 0);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/payments/pay_new'));
    }

    public function test_old_invoice_without_charge_can_be_linked_to_a_new_charge(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => null, 'asaas_sync_status' => null]);
        Http::fake(['*/payments' => Http::response($this->charge(['id' => 'pay_old']))]);

        $this->operations->attachCharge($invoice);

        $this->assertSame('pay_old', $invoice->asaas_invoice_id);
        $this->assertSame('synced', $invoice->asaas_sync_status);
    }

    // ------------------------------------------------------------------ receber

    public function test_full_receipt_is_written_off_on_asaas_and_the_webhook_does_not_duplicate_it(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1/receiveInCash' => Http::response($this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-10-22']))]);

        $payment = $this->operations->receive($invoice, 100, PaymentType::PIX, today()->subDay());

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/payments/pay_1/receiveInCash')
            && $request['paymentDate'] === '2026-10-22'
            && (float) $request['value'] === 100.0
            && $request['notifyCustomer'] === false);

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(PaymentType::PIX, $payment->payment_method);
        $this->assertSame('pay_1', $payment->reference);
        $this->assertSame('pay_1', $payment->asaas_payment_id);
        $this->assertSame('2026-10-22', $payment->payment_date->toDateString());

        // PAYMENT_RECEIVED do Asaas chegando depois: a sincronização não registra outro pagamento
        $result = app(PaymentSynchronizer::class)->apply($invoice, $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-10-22']));

        $this->assertSame(SyncChange::Unchanged, $result->change);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(PaymentType::PIX, $invoice->payments()->first()->payment_method);
    }

    public function test_full_receipt_already_registered_by_the_webhook_only_adjusts_the_method(): void
    {
        $invoice = $this->invoice();
        // O webhook chegou antes de a operação gravar (corrida): o pagamento já existe, em dinheiro
        InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-23', 'amount' => 100, 'payment_method' => 'cash', 'reference' => 'pay_1', 'asaas_payment_id' => 'pay_1']);
        Http::fake(['*/payments/pay_1/receiveInCash' => Http::response($this->charge(['status' => 'RECEIVED_IN_CASH']))]);

        $this->operations->receive($invoice, 100, PaymentType::BANK_TRANSFER, today());

        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(PaymentType::BANK_TRANSFER, $invoice->payments()->first()->payment_method);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_full_receipt_refused_by_asaas_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1/receiveInCash' => $this->refusal('Não é possível confirmar o recebimento desta cobrança.')]);

        $this->assertRefused(fn () => $this->operations->receive($invoice, 100, PaymentType::CASH, today()), 'Não é possível confirmar o recebimento');

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->balance);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_partial_receipt_lowers_the_charge_and_the_rest_paid_on_asaas_settles_without_duplicates(): void
    {
        $invoice = $this->invoice(['discount_value' => 10, 'discount_type' => 'percentage']);
        Http::fake([
            '*/payments/pay_1' => Http::sequence()
                ->push($this->charge(['discount' => ['value' => 10, 'type' => 'PERCENTAGE']]))
                ->push($this->charge(['value' => 60])),
        ]);

        $partial = $this->operations->receive($invoice, 40, PaymentType::CASH, today(), partial: true);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/payments/pay_1')
            && (float) $request['value'] === 60.0
            && $request['billingType'] === 'PIX'
            && $request['dueDate'] === '2026-11-10'
            && (float) $request['discount']['value'] === 0.0);

        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('60.00', $invoice->balance);
        $this->assertNull($partial->reference);
        $this->assertSame(PaymentType::CASH, $partial->payment_method);

        // O responsável paga o restante por PIX: a sincronização registra só esse restante
        $paid = $this->charge(['status' => 'RECEIVED', 'value' => 60, 'paymentDate' => '2026-10-25']);
        $result = app(PaymentSynchronizer::class)->apply($invoice, $paid);
        app(PaymentSynchronizer::class)->apply($invoice, $paid);

        $this->assertSame(SyncChange::Paid, $result->change);
        $this->assertSame([], $result->warnings);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertSame([['40.00', 'cash'], ['60.00', 'pix']], $invoice->payments()->orderBy('id')->get()
            ->map(fn (InvoicePayment $payment): array => [$payment->amount, $payment->payment_method->value])->all());

        // Cada pagamento tem o seu recibo
        $receipts = $invoice->payments()->get()->map(fn (InvoicePayment $payment) => PaymentReceipt::issue($payment, auth()->user())->number);
        $this->assertCount(2, $receipts->unique());
    }

    public function test_partial_receipt_refused_by_asaas_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Http::fake([
            '*/payments/pay_1' => Http::sequence()
                ->push($this->charge())
                ->pushResponse($this->refusal('O valor da cobrança não pode ser menor que R$ 5,00.')),
        ]);

        $this->assertRefused(fn () => $this->operations->receive($invoice, 98, PaymentType::CASH, today(), partial: true), 'não pode ser menor que R$ 5,00');

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->balance);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_partial_receipt_must_be_smaller_than_the_balance_and_payment_date_cannot_be_future(): void
    {
        $invoice = $this->invoice();
        Http::fake();

        $this->assertRefused(fn () => $this->operations->receive($invoice, 100, PaymentType::CASH, today(), partial: true), 'menor que o saldo');
        $this->assertRefused(fn () => $this->operations->receive($invoice, 100, PaymentType::CASH, today()->addDay()), 'não pode ser futura');

        Http::assertNothingSent();
        $this->assertSame(0, $invoice->payments()->count());
    }

    // ------------------------------------------------------------------ desfazer

    private function paidInvoice(array $attributes = []): array
    {
        $invoice = $this->invoice(['status' => 'paid', 'balance' => 0, ...$attributes]);
        $payment = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-20', 'amount' => 100, 'payment_method' => 'cash', 'reference' => 'pay_1', 'asaas_payment_id' => 'pay_1']);

        return [$invoice, $payment];
    }

    public function test_undoing_the_receipt_undoes_it_on_asaas_and_cancels_the_receipt(): void
    {
        [$invoice, $payment] = $this->paidInvoice();
        $receipt = PaymentReceipt::issue($payment, auth()->user());
        Http::fake(['*/payments/pay_1/undoReceivedInCash' => Http::response($this->charge())]);

        $this->operations->undoPayment($invoice, $payment);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/payments/pay_1/undoReceivedInCash'));
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('100.00', $invoice->balance);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertNotNull($receipt->fresh()->canceled_at);
    }

    public function test_undoing_the_receipt_of_a_past_due_invoice_makes_it_overdue(): void
    {
        [$invoice, $payment] = $this->paidInvoice(['due_date' => '2026-10-10']);
        Http::fake(['*/payments/pay_1/undoReceivedInCash' => Http::response($this->charge(['status' => 'OVERDUE']))]);

        $this->operations->undoPayment($invoice, $payment);

        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
    }

    public function test_undo_refused_by_asaas_changes_nothing(): void
    {
        [$invoice, $payment] = $this->paidInvoice();
        Http::fake(['*/payments/pay_1/undoReceivedInCash' => $this->refusal('Só é possível desfazer cobranças recebidas em dinheiro.')]);

        $this->assertRefused(fn () => $this->operations->undoPayment($invoice, $payment), 'Só é possível desfazer cobranças recebidas em dinheiro');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_undoing_a_partial_payment_gives_the_value_back_to_the_charge(): void
    {
        $invoice = $this->invoice(['status' => 'partial', 'balance' => 60]);
        $payment = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-20', 'amount' => 40, 'payment_method' => 'cash']);
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge(['value' => 60]))->push($this->charge())]);

        $this->assertTrue(ChargeOperations::undoablePayments($invoice)->contains($payment));
        $this->operations->undoPayment($invoice, $payment);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && (float) $request['value'] === 100.0);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('100.00', $invoice->balance);
    }

    public function test_after_a_write_off_only_the_charge_receipt_can_be_undone_first(): void
    {
        [$invoice, $linked] = $this->paidInvoice();
        $partial = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 20, 'payment_method' => 'cash']);
        Http::fake();

        $this->assertEquals([$linked->id], ChargeOperations::undoablePayments($invoice)->modelKeys());
        $this->assertRefused(fn () => $this->operations->undoPayment($invoice, $partial), 'Desfaça antes o recebimento registrado na cobrança do Asaas');
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ operação + webhook real

    /** Notificação do Asaas pelo endpoint (token configurado); o webhook relê a cobrança na API. */
    private function webhook(string $event, array $charge, string $eventId): void
    {
        config(['asaas.webhook.secret' => 'segredo-do-teste']);

        $this->withHeader('asaas-access-token', 'segredo-do-teste')
            ->postJson('/webhooks/asaas', ['id' => $eventId, 'event' => $event, 'dateCreated' => '2026-10-23 10:00:00', 'payment' => $charge])
            ->assertOk();

        // Processada de verdade (sem falha), e não ignorada
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, AsaasWebhookEvent::query()->where('event_id', $eventId)->value('status'));
    }

    public function test_receiving_in_the_system_then_the_received_webhook_does_not_duplicate_the_payment(): void
    {
        $invoice = $this->invoice();
        $received = $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-10-23']);
        Http::fake([
            '*/payments/pay_1/receiveInCash' => Http::response($received),
            '*/payments/pay_1' => Http::response($received),
        ]);

        $receipt = PaymentReceipt::issue($this->operations->receive($invoice, 100, PaymentType::PIX, today()), auth()->user());
        $this->webhook('PAYMENT_RECEIVED', $received, 'evt_received');
        $this->webhook('PAYMENT_RECEIVED', $received, 'evt_received_again');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(PaymentType::PIX, $invoice->payments()->sole()->payment_method);
        $this->assertNull($receipt->fresh()->canceled_at);
    }

    public function test_undoing_in_the_system_then_the_undone_webhook_does_not_undo_twice(): void
    {
        // 40 em dinheiro (parcial) e o restante baixado no Asaas: desfaz só a baixa
        $invoice = $this->invoice(['status' => 'paid', 'balance' => 0]);
        $partial = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 40, 'payment_method' => 'cash']);
        $writeOff = InvoicePayment::create(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-20', 'amount' => 60, 'payment_method' => 'cash', 'reference' => 'pay_1', 'asaas_payment_id' => 'pay_1']);
        $pending = $this->charge(['value' => 60]);
        Http::fake([
            '*/payments/pay_1/undoReceivedInCash' => Http::response($pending),
            '*/payments/pay_1' => Http::response($pending),
        ]);

        $this->operations->undoPayment($invoice, $writeOff);

        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('60.00', $invoice->balance);
        $this->assertEquals([$partial->id], $invoice->payments()->pluck('id')->all());

        $this->webhook('PAYMENT_RECEIVED_IN_CASH_UNDONE', $pending, 'evt_undone');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame('60.00', $invoice->balance);
        $this->assertEquals([$partial->id], $invoice->payments()->pluck('id')->all());
    }

    // ------------------------------------------------------------------ cancelar e restaurar

    public function test_cancel_deletes_the_charge_and_the_deleted_webhook_changes_nothing_else(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::response(['deleted' => true, 'id' => 'pay_1'])]);

        $this->operations->cancel($invoice, 'Aluno desistiu');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/payments/pay_1'));
        $this->assertSame(InvoiceStatus::Canceled, $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame('canceled', $invoice->asaas_sync_status);
        $this->assertStringContainsString('Aluno desistiu', $invoice->notes);

        $result = app(PaymentSynchronizer::class)->apply($invoice, $this->charge(['deleted' => true]));
        $this->assertSame(SyncChange::Unchanged, $result->change);
    }

    public function test_cancel_refused_by_asaas_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => $this->refusal('Cobrança não pode ser removida.')]);

        $this->assertRefused(fn () => $this->operations->cancel($invoice, 'Aluno desistiu'), 'Cobrança não pode ser removida');

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame('synced', $invoice->fresh()->asaas_sync_status);
    }

    public function test_invoice_with_payment_cannot_be_canceled_before_undoing_the_receipt(): void
    {
        [$invoice] = $this->paidInvoice();
        Http::fake();

        $this->assertFalse(ChargeOperations::canCancel($invoice));
        $this->assertRefused(fn () => $this->operations->cancel($invoice, 'Engano'), 'Desfaça o recebimento antes de cancelar');
        Http::assertNothingSent();
    }

    public function test_restore_brings_the_charge_back(): void
    {
        $invoice = $this->invoice(['status' => 'canceled', 'balance' => 0, 'asaas_sync_status' => 'canceled']);
        Http::fake(['*/payments/pay_1/restore' => Http::response($this->charge())]);

        $this->operations->restore($invoice);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/payments/pay_1/restore'));
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('100.00', $invoice->balance);
        $this->assertSame('synced', $invoice->asaas_sync_status);
    }

    public function test_restore_refused_by_asaas_changes_nothing(): void
    {
        $invoice = $this->invoice(['status' => 'canceled', 'balance' => 0, 'asaas_sync_status' => 'canceled']);
        Http::fake(['*/payments/pay_1/restore' => $this->refusal('Esta cobrança não pode ser restaurada.')]);

        $this->assertRefused(fn () => $this->operations->restore($invoice), 'Esta cobrança não pode ser restaurada');

        $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    // ------------------------------------------------------------------ editar

    public function test_update_changes_value_due_date_and_description_on_asaas_first(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge(['billingType' => 'BOLETO']))->push($this->charge(['value' => 150]))]);

        $this->operations->update($invoice, ['amount' => '150', 'due_date' => '2026-11-20', 'notes' => 'Mensalidade com material', 'reference' => 'MENS-11']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['billingType'] === 'BOLETO'
            && (float) $request['value'] === 150.0
            && $request['dueDate'] === '2026-11-20'
            && $request['description'] === 'Mensalidade com material');
        $this->assertSame('150.00', $invoice->amount);
        $this->assertSame('150.00', $invoice->balance);
        $this->assertSame('2026-11-20', $invoice->due_date->toDateString());
        $this->assertSame('MENS-11', $invoice->reference);
    }

    public function test_update_refused_by_asaas_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Http::fake(['*/payments/pay_1' => Http::sequence()->push($this->charge())->pushResponse($this->refusal('Data de vencimento inválida.'))]);

        $this->assertRefused(fn () => $this->operations->update($invoice, ['amount' => 150, 'due_date' => '2026-11-20', 'reference' => 'MENS-11']), 'Data de vencimento inválida');

        $fresh = $invoice->fresh();
        $this->assertSame('100.00', $fresh->amount);
        $this->assertSame('2026-11-10', $fresh->due_date->toDateString());
        $this->assertNull($fresh->reference);
    }

    public function test_local_only_fields_change_without_calling_asaas(): void
    {
        $invoice = $this->invoice();
        Http::fake();

        $this->operations->update($invoice, ['amount' => '100.00', 'due_date' => '2026-11-10', 'notes' => 'Mensalidade de novembro', 'reference' => 'MENS-11', 'account_id' => null]);

        Http::assertNothingSent();
        $this->assertSame('MENS-11', $invoice->reference);
    }

    public function test_value_and_due_date_of_a_paid_invoice_are_read_only_but_notes_change_locally(): void
    {
        [$invoice] = $this->paidInvoice();
        Http::fake();

        $this->assertFalse(ChargeOperations::canEditCharge($invoice));
        $this->assertRefused(fn () => $this->operations->update($invoice, ['amount' => 150]), 'só podem ser alterados em fatura aberta ou vencida');
        $this->assertRefused(fn () => $this->operations->update($invoice, ['due_date' => '2026-12-01']), 'só podem ser alterados em fatura aberta ou vencida');

        $this->operations->update($invoice, ['notes' => 'Pago na secretaria']);

        Http::assertNothingSent();
        $this->assertSame('Pago na secretaria', $invoice->notes);
        $this->assertSame('100.00', $invoice->amount);
    }

    public function test_customer_of_an_invoice_with_charge_cannot_be_changed(): void
    {
        $invoice = $this->invoice();
        $other = Customer::create(['name' => 'Outro', 'document' => '111.222.333-44']);
        Http::fake();

        $this->assertRefused(fn () => $this->operations->update($invoice, ['customer_id' => $other->id]), 'não pode ser trocado');
        $this->assertSame($this->customer->id, $invoice->fresh()->customer_id);
    }

    // ------------------------------------------------------------------ faturas sem cobrança

    public function test_invoice_without_charge_is_operated_only_in_the_system(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => null, 'asaas_sync_status' => null]);
        Http::fake();

        $this->operations->update($invoice, ['amount' => 120, 'due_date' => '2026-11-15']);
        $this->assertSame('120.00', $invoice->amount);

        $partial = $this->operations->receive($invoice, 20, PaymentType::CASH, today(), partial: true, reference: 'CX-1');
        $this->assertSame('CX-1', $partial->reference);
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);

        $this->operations->undoPayment($invoice, $partial);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('120.00', $invoice->balance);

        $this->operations->cancel($invoice, 'Lançada em duplicidade');
        $this->assertSame(InvoiceStatus::Canceled, $invoice->status);

        $this->operations->restore($invoice);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('120.00', $invoice->balance);

        $this->operations->receive($invoice, 120, PaymentType::PIX, today());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        Http::assertNothingSent();
    }
}
