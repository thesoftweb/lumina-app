<?php

namespace Tests\Feature;

use App\Documents\PaymentReceipt;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentType;
use App\Models\AsaasWebhookEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Asaas\PaymentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segredo-do-webhook';

    private Company $company;

    private Customer $customer;

    private int $sequence = 0;

    /** @var array<string, array<string, mixed>|int> cobrança (ou código de erro) que a API devolve por id */
    private array $api = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(fn (HttpRequest $request) => $this->apiResponse($request));
        config([
            'asaas.api_key' => 'chave-de-teste',
            'asaas.environment' => 'sandbox',
            'asaas.webhook.secret' => self::SECRET,
        ]);

        $this->company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94', 'asaas_customer_id' => 'cus_maria']);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
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
            'object' => 'payment',
            'id' => 'pay_1',
            'customer' => 'cus_maria',
            'status' => 'PENDING',
            'value' => 500,
            'netValue' => 495,
            'billingType' => 'PIX',
            'description' => null,
            'dateCreated' => '2026-09-01',
            'dueDate' => '2026-09-10',
            'paymentDate' => null,
            'clientPaymentDate' => null,
            'confirmedDate' => null,
            'externalReference' => null,
            'deleted' => false,
            ...$attributes,
        ];
    }

    /** A API devolve a cobrança como está agora (o webhook relê a cobrança antes de aplicar). */
    private function fakeApi(array $charge): void
    {
        $this->api[$charge['id']] = $charge;
    }

    /** GET /payments/{id} responde com erro. */
    private function apiDown(string $id, int $status = 503): void
    {
        $this->api[$id] = $status;
    }

    private function apiResponse(HttpRequest $request)
    {
        if ($request->method() !== 'GET' || ! preg_match('#/payments/([^/?]+)$#', $request->url(), $match) || ! isset($this->api[$match[1]])) {
            return null; // requisição não prevista: preventStrayRequests barra
        }

        $state = $this->api[$match[1]];

        return is_int($state) ? Http::response(['errors' => [['description' => 'indisponível']]], $state) : Http::response($state);
    }

    private function notify(string $event, array $charge, ?string $eventId = null, ?string $token = self::SECRET): TestResponse
    {
        $headers = $token === null ? [] : ['asaas-access-token' => $token];

        return $this->withHeaders($headers)->postJson('/webhooks/asaas', [
            'id' => $eventId ?? 'evt_'.(++$this->sequence),
            'event' => $event,
            'dateCreated' => '2026-10-23 10:00:00',
            'payment' => $charge,
        ]);
    }

    /** Envia a notificação com a API devolvendo a mesma cobrança. */
    private function deliver(string $event, array $charge, ?string $eventId = null): AsaasWebhookEvent
    {
        $this->fakeApi($charge);
        $this->notify($event, $charge, $eventId)->assertOk();

        return AsaasWebhookEvent::query()->latest('id')->firstOrFail();
    }

    // Autenticação

    public function test_missing_or_wrong_token_is_rejected_without_touching_anything(): void
    {
        $invoice = $this->invoice();

        $this->notify('PAYMENT_DELETED', $this->charge(['deleted' => true]), token: null)->assertUnauthorized();
        $this->notify('PAYMENT_DELETED', $this->charge(['deleted' => true]), token: 'token-errado')->assertUnauthorized();
        $this->notify('PAYMENT_DELETED', $this->charge(['deleted' => true]), token: '')->assertUnauthorized();

        $this->assertSame(0, AsaasWebhookEvent::count());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_webhook_without_configured_secret_rejects_everything_and_logs_an_error(): void
    {
        config(['asaas.webhook.secret' => '']);
        Log::spy();

        $this->notify('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED']), token: '')->assertUnauthorized();
        $this->notify('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED']), token: 'qualquer')->assertUnauthorized();

        $this->assertSame(0, AsaasWebhookEvent::count());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'ASAAS_WEBHOOK_SECRET'))->twice();
    }

    public function test_valid_token_is_accepted_and_the_event_is_recorded(): void
    {
        $invoice = $this->invoice();
        $charge = $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-20']);
        $this->fakeApi($charge);

        $this->notify('PAYMENT_RECEIVED', $charge, 'evt_abc')->assertOk()->assertJson(['status' => 'received']);

        $record = AsaasWebhookEvent::query()->sole();
        $this->assertSame('evt_abc', $record->event_id);
        $this->assertSame('PAYMENT_RECEIVED', $record->event);
        $this->assertSame('pay_1', $record->payment_id);
        $this->assertSame('pay_1', $record->payload['payment']['id']);
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, $record->status);
        $this->assertNotNull($record->processed_at);
        $this->assertStringContainsString('000100', $record->result);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_payload_without_event_is_rejected(): void
    {
        $this->withHeader('asaas-access-token', self::SECRET)->postJson('/webhooks/asaas', ['foo' => 'bar'])->assertStatus(400);

        $this->assertSame(0, AsaasWebhookEvent::count());
    }

    // Idempotência e falhas

    public function test_repeated_event_is_answered_without_being_processed_again(): void
    {
        $invoice = $this->invoice();
        $charge = $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-20']);
        $this->fakeApi($charge);

        $this->notify('PAYMENT_RECEIVED', $charge, 'evt_same')->assertOk();
        // Fatura reaberta à mão: se reprocessasse, registraria outro pagamento
        $invoice->payments()->first()->delete();
        $invoice->update(['status' => InvoiceStatus::Open, 'balance' => 500]);

        $this->notify('PAYMENT_RECEIVED', $charge, 'evt_same')->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, AsaasWebhookEvent::count());
        $this->assertSame(1, AsaasWebhookEvent::query()->sole()->attempts);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
    }

    public function test_processing_failure_is_recorded_answers_200_and_is_retried_when_redelivered(): void
    {
        $invoice = $this->invoice();
        $charge = $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-20']);
        $this->fakeApi($charge);
        $this->mock(PaymentSynchronizer::class)->shouldReceive('apply')->andThrow(new RuntimeException('banco fora do ar'));

        $this->notify('PAYMENT_RECEIVED', $charge, 'evt_fail')->assertOk();

        $record = AsaasWebhookEvent::query()->sole();
        $this->assertSame(AsaasWebhookEvent::STATUS_FAILED, $record->status);
        $this->assertStringContainsString('banco fora do ar', $record->error);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);

        // Reentrega da mesma notificação: a que falhou é processada de novo
        $this->forgetMock(PaymentSynchronizer::class);
        $this->notify('PAYMENT_RECEIVED', $charge, 'evt_fail')->assertOk()->assertJson(['status' => 'retrying']);

        $record->refresh();
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, $record->status);
        $this->assertNull($record->error);
        $this->assertSame(2, $record->attempts);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    // Eventos de cobrança

    public function test_confirmed_and_received_mark_paid_once(): void
    {
        $invoice = $this->invoice();

        $this->deliver('PAYMENT_CONFIRMED', $this->charge(['status' => 'CONFIRMED', 'billingType' => 'CREDIT_CARD', 'confirmedDate' => '2026-10-20']));
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED', 'billingType' => 'CREDIT_CARD', 'confirmedDate' => '2026-10-20', 'paymentDate' => '2026-11-20']));

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $payment = $invoice->payments()->sole();
        $this->assertSame(PaymentType::CREDIT_CARD, $payment->payment_method);
        $this->assertSame('2026-10-20', $payment->payment_date->toDateString());
    }

    public function test_webhook_applies_the_current_state_read_from_the_api(): void
    {
        $invoice = $this->invoice();
        // Notificação antiga (OVERDUE), mas a cobrança já foi paga
        $this->fakeApi($this->charge(['status' => 'RECEIVED', 'billingType' => 'BOLETO', 'paymentDate' => '2026-10-22']));

        $this->notify('PAYMENT_OVERDUE', $this->charge(['status' => 'OVERDUE']))->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(PaymentType::BOLETO, $invoice->payments()->sole()->payment_method);
    }

    public function test_overdue_and_updated(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Open, 'due_date' => '2026-11-10']);

        $this->deliver('PAYMENT_UPDATED', $this->charge(['value' => 620, 'dueDate' => '2026-11-15']));
        $invoice->refresh();
        $this->assertSame('620.00', $invoice->amount);
        $this->assertSame('620.00', $invoice->balance);
        $this->assertSame('2026-11-15', $invoice->due_date->toDateString());
        $this->assertSame(InvoiceStatus::Open, $invoice->status);

        $record = $this->deliver('PAYMENT_OVERDUE', $this->charge(['status' => 'OVERDUE', 'value' => 620, 'dueDate' => '2026-10-20']));
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, $record->status);
    }

    public function test_deleted_never_cancels_a_paid_invoice(): void
    {
        $paid = $this->invoice(['status' => InvoiceStatus::Paid, 'balance' => 0]);
        InvoicePayment::create(['invoice_id' => $paid->id, 'payment_date' => '2026-09-05', 'amount' => 500, 'payment_method' => 'cash']);
        // Paga sem pagamento registrado (marcada à mão): também não é cancelada
        $markedPaid = $this->invoice(['asaas_invoice_id' => 'pay_2', 'number' => '000101', 'status' => InvoiceStatus::Paid, 'balance' => 0]);
        $open = $this->invoice(['asaas_invoice_id' => 'pay_3', 'number' => '000102']);

        $record = $this->deliver('PAYMENT_DELETED', $this->charge(['deleted' => true]));
        $this->deliver('PAYMENT_DELETED', $this->charge(['id' => 'pay_2', 'deleted' => true]));
        $this->deliver('PAYMENT_DELETED', $this->charge(['id' => 'pay_3', 'deleted' => true]));

        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $markedPaid->fresh()->status);
        $this->assertStringContainsString('não foi cancelada', $record->result);
        $this->assertSame(InvoiceStatus::Canceled, $open->fresh()->status);

        // Restaurada no Asaas: volta a ser cobrada
        $this->deliver('PAYMENT_RESTORED', $this->charge(['id' => 'pay_3', 'status' => 'OVERDUE']));
        $this->assertSame(InvoiceStatus::Overdue, $open->fresh()->status);
        $this->assertSame('500.00', $open->fresh()->balance);
    }

    public function test_deleted_uses_the_notification_when_the_api_is_unavailable(): void
    {
        $invoice = $this->invoice();
        $this->apiDown('pay_1');

        $this->notify('PAYMENT_DELETED', $this->charge())->assertOk();

        $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, AsaasWebhookEvent::query()->sole()->status);
    }

    public function test_refunded_reverses_the_payment_and_cancels_the_invoice(): void
    {
        $invoice = $this->invoice();
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-01']));
        $receipt = PaymentReceipt::issue($invoice->payments()->sole(), User::factory()->create(['company_id' => $this->company->id]));

        $this->deliver('PAYMENT_REFUNDED', $this->charge(['status' => 'REFUNDED', 'paymentDate' => '2026-10-01']));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Canceled, $invoice->status);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertNotNull($receipt->fresh()->canceled_at);
    }

    public function test_partial_refund_refund_in_progress_and_chargeback_only_warn(): void
    {
        $invoice = $this->invoice();
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-01']));

        $partial = $this->deliver('PAYMENT_PARTIALLY_REFUNDED', $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-01']));
        $inProgress = $this->deliver('PAYMENT_REFUND_IN_PROGRESS', $this->charge(['status' => 'REFUND_IN_PROGRESS']));
        $chargeback = $this->deliver('PAYMENT_CHARGEBACK_REQUESTED', $this->charge(['status' => 'CHARGEBACK_REQUESTED']));
        $denied = $this->deliver('PAYMENT_REFUND_DENIED', $this->charge(['status' => 'RECEIVED', 'paymentDate' => '2026-10-01']));

        $this->assertStringContainsString('Estorno parcial', $partial->result);
        $this->assertStringContainsString('Estorno em andamento', $inProgress->result);
        $this->assertStringContainsString('Chargeback', $chargeback->result);
        $this->assertStringContainsString('Estorno negado', $denied->result);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_received_in_cash_undone_reverses_the_cash_payment_and_reopens_the_invoice(): void
    {
        $invoice = $this->invoice();
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-10-15']));
        $receipt = PaymentReceipt::issue($invoice->payments()->sole(), User::factory()->create(['company_id' => $this->company->id]));
        $this->assertSame(PaymentType::CASH, $invoice->payments()->sole()->payment_method);

        $record = $this->deliver('PAYMENT_RECEIVED_IN_CASH_UNDONE', $this->charge(['status' => 'OVERDUE']));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
        $this->assertSame('500.00', $invoice->balance);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertNotNull($receipt->fresh()->canceled_at);
        $this->assertStringContainsString('Recebimento desfeito no Asaas', $invoice->notes);
        $this->assertStringContainsString('Recebimento desfeito', $record->result);
    }

    public function test_received_in_cash_undone_without_the_api_derives_the_status_from_the_due_date(): void
    {
        $invoice = $this->invoice(['due_date' => '2026-12-10']);
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['status' => 'RECEIVED_IN_CASH', 'paymentDate' => '2026-10-15', 'dueDate' => '2026-12-10']));
        $this->apiDown('pay_1', 500);

        // A notificação ainda traz o status anterior
        $this->notify('PAYMENT_RECEIVED_IN_CASH_UNDONE', $this->charge(['status' => 'RECEIVED_IN_CASH', 'dueDate' => '2026-12-10']))->assertOk();

        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_dunning_events(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Open, 'due_date' => '2026-09-10']);

        $this->deliver('PAYMENT_DUNNING_REQUESTED', $this->charge(['status' => 'DUNNING_REQUESTED']));
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);

        $this->deliver('PAYMENT_DUNNING_RECEIVED', $this->charge(['status' => 'DUNNING_RECEIVED', 'paymentDate' => '2026-10-21']));
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_informative_events_are_ignored_without_calling_the_api(): void
    {
        $invoice = $this->invoice();

        foreach (['PAYMENT_BANK_SLIP_VIEWED', 'PAYMENT_CHECKOUT_VIEWED', 'PAYMENT_ANTICIPATED', 'PAYMENT_AWAITING_RISK_ANALYSIS', 'PAYMENT_APPROVED_BY_RISK_ANALYSIS', 'PAYMENT_SPLIT_CANCELLED', 'TRANSFER_CREATED'] as $event) {
            $this->notify($event, $this->charge(['status' => 'RECEIVED']))->assertOk();
        }

        $this->assertSame(7, AsaasWebhookEvent::query()->where('status', AsaasWebhookEvent::STATUS_IGNORED)->count());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_invoice_is_found_by_external_reference_but_not_for_another_customer(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => null]);
        $other = $this->invoice(['asaas_invoice_id' => null, 'number' => '000101']);

        $this->deliver('PAYMENT_CREATED', $this->charge(['id' => 'pay_new', 'status' => 'PENDING', 'externalReference' => (string) $invoice->id]));
        $foreign = $this->deliver('PAYMENT_RECEIVED', $this->charge(['id' => 'pay_foreign', 'customer' => 'cus_outro', 'status' => 'RECEIVED', 'externalReference' => (string) $other->id]));

        $this->assertSame('pay_new', $invoice->fresh()->asaas_invoice_id);
        $this->assertSame(AsaasWebhookEvent::STATUS_IGNORED, $foreign->status);
        $this->assertNull($other->fresh()->asaas_invoice_id);
        $this->assertSame(InvoiceStatus::Overdue, $other->fresh()->status);
        $this->assertSame(2, Invoice::count());
    }

    // Importação

    public function test_charge_created_in_the_asaas_panel_is_imported_for_a_registered_guardian(): void
    {
        $this->invoice(['number' => '000150']);

        $record = $this->deliver('PAYMENT_CREATED', $this->charge([
            'id' => 'pay_panel',
            'description' => 'Uniforme escolar',
            'value' => 180.5,
            'dateCreated' => '2026-10-22',
            'dueDate' => '2026-11-05',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/panel',
        ]));

        $invoice = Invoice::query()->where('asaas_invoice_id', 'pay_panel')->sole();
        $this->assertSame($this->customer->id, $invoice->customer_id);
        $this->assertSame($this->company->id, $invoice->company_id);
        $this->assertSame(InvoiceType::OTHER, $invoice->billing_type);
        $this->assertSame('180.50', $invoice->amount);
        $this->assertSame('180.50', $invoice->balance);
        $this->assertSame('2026-11-05', $invoice->due_date->toDateString());
        $this->assertSame('2026-10-22', $invoice->issue_date->toDateString());
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('Uniforme escolar', $invoice->reference);
        $this->assertStringContainsString('pay_panel', $invoice->notes);
        $this->assertSame('000151', $invoice->number);
        $this->assertSame('synced', $invoice->asaas_sync_status);
        $this->assertSame('https://sandbox.asaas.com/i/panel', $invoice->invoice_link);
        $this->assertSame(AsaasWebhookEvent::STATUS_PROCESSED, $record->status);
        $this->assertStringContainsString('importada', $record->result);

        // O pagamento depois cai na fatura importada, sem criar outra
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['id' => 'pay_panel', 'status' => 'RECEIVED', 'value' => 180.5, 'dueDate' => '2026-11-05', 'paymentDate' => '2026-10-23']));
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(2, Invoice::count());
    }

    public function test_charge_paid_in_the_asaas_panel_is_imported_already_paid(): void
    {
        $this->deliver('PAYMENT_RECEIVED', $this->charge(['id' => 'pay_cash', 'status' => 'RECEIVED_IN_CASH', 'value' => 90, 'dueDate' => '2026-10-30', 'paymentDate' => '2026-10-22']));

        $invoice = Invoice::query()->where('asaas_invoice_id', 'pay_cash')->sole();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(PaymentType::CASH, $invoice->payments()->sole()->payment_method);
    }

    public function test_charge_without_invoice_is_not_imported_for_unknown_customers_or_foreign_references(): void
    {
        $unknown = $this->deliver('PAYMENT_CREATED', $this->charge(['id' => 'pay_x', 'customer' => 'cus_desconhecido', 'dueDate' => '2026-11-05']));
        $foreign = $this->deliver('PAYMENT_CREATED', $this->charge(['id' => 'pay_y', 'externalReference' => '99999', 'dueDate' => '2026-11-05']));
        $deleted = $this->deliver('PAYMENT_DELETED', $this->charge(['id' => 'pay_z', 'deleted' => true]));

        $this->assertSame(0, Invoice::count());
        $this->assertSame(AsaasWebhookEvent::STATUS_IGNORED, $unknown->status);
        $this->assertStringContainsString('não é um responsável cadastrado', $unknown->result);
        $this->assertStringContainsString('referência externa', $foreign->result);
        $this->assertSame(AsaasWebhookEvent::STATUS_IGNORED, $deleted->status);
    }

    // Cobranças de eventos

    private function participant(?Invoice $invoice = null): EventParticipant
    {
        $event = Event::create(['company_id' => $this->company->id, 'name' => 'Festa junina', 'amount' => 50, 'due_date' => '2026-11-01']);

        return EventParticipant::create(['event_id' => $event->id, 'customer_id' => $this->customer->id, 'invoice_id' => $invoice?->id, 'status' => 'pending']);
    }

    public function test_event_reference_branch_marks_the_participant_paid_once(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => 'pay_evt', 'billing_type' => 'event', 'status' => InvoiceStatus::Open, 'amount' => 50, 'balance' => 50]);
        $participant = $this->participant($invoice);
        $reference = "EVENT-{$participant->event_id}-{$this->customer->id}";

        $this->notify('PAYMENT_CONFIRMED', $this->charge(['id' => 'pay_evt', 'status' => 'CONFIRMED', 'externalReference' => $reference, 'confirmedDate' => '2026-10-20']))->assertOk();
        $this->notify('PAYMENT_RECEIVED', $this->charge(['id' => 'pay_evt', 'status' => 'RECEIVED', 'externalReference' => $reference, 'paymentDate' => '2026-10-21']))->assertOk();
        $this->notify('PAYMENT_DELETED', $this->charge(['id' => 'pay_evt', 'deleted' => true, 'externalReference' => $reference]))->assertOk();

        $participant->refresh();
        $this->assertSame('paid', $participant->status);
        $this->assertSame('2026-10-20', $participant->paid_at->toDateString());
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $payment = $invoice->payments()->sole();
        $this->assertSame(PaymentType::PIX, $payment->payment_method);
        $this->assertSame('pay_evt', $payment->asaas_payment_id);
        Http::assertNothingSent();
    }

    public function test_event_reference_branch_cancels_an_unpaid_participant(): void
    {
        $participant = $this->participant();

        $this->notify('PAYMENT_DELETED', $this->charge(['id' => 'pay_evt', 'deleted' => true, 'externalReference' => "EVENT-{$participant->event_id}-{$this->customer->id}"]))->assertOk();

        $this->assertSame('canceled', $participant->fresh()->status);
    }

    public function test_event_invoice_charged_by_invoice_id_also_updates_the_participant(): void
    {
        $invoice = $this->invoice(['asaas_invoice_id' => 'pay_evt', 'billing_type' => 'event', 'status' => InvoiceStatus::Open, 'amount' => 50, 'balance' => 50]);
        $participant = $this->participant($invoice);

        $this->deliver('PAYMENT_RECEIVED', $this->charge(['id' => 'pay_evt', 'status' => 'RECEIVED', 'value' => 50, 'externalReference' => (string) $invoice->id, 'paymentDate' => '2026-10-21']));

        $this->assertSame('paid', $participant->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1, $invoice->payments()->count());
    }
}
