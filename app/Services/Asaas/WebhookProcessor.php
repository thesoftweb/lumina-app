<?php

namespace App\Services\Asaas;

use App\Models\AsaasWebhookEvent;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Services\AsaasService;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa uma notificação do webhook do Asaas já gravada em asaas_webhook_events.
 *
 * Eventos de cobrança de faturas: acha a fatura (asaas_invoice_id ou externalReference = id da fatura),
 * relê a cobrança na API (situação atual; se a consulta falhar, usa o objeto "payment" da notificação)
 * e aplica pelo PaymentSynchronizer. Cobrança sem fatura, criada direto no painel do Asaas para um
 * responsável cadastrado, é importada como fatura "Outro". Cobranças de eventos (EVENT-...) marcam o
 * participante. Eventos só informativos ficam como "ignored".
 *
 * Nunca lança: falha vira status "failed" com o erro, e o webhook já respondeu 200 (o Asaas pausa a
 * fila depois de 15 falhas seguidas; a conciliação diária corrige o que ficar para trás).
 */
class WebhookProcessor
{
    /** Eventos que mudam (ou podem mudar) a situação da cobrança: aplicados pelo sincronizador. */
    public const SYNC_EVENTS = [
        'PAYMENT_CREATED',
        'PAYMENT_UPDATED',
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED',
        'PAYMENT_OVERDUE',
        'PAYMENT_DELETED',
        'PAYMENT_RESTORED',
        'PAYMENT_REFUNDED',
        'PAYMENT_PARTIALLY_REFUNDED',
        'PAYMENT_REFUND_IN_PROGRESS',
        'PAYMENT_REFUND_DENIED',
        'PAYMENT_RECEIVED_IN_CASH_UNDONE',
        'PAYMENT_CHARGEBACK_REQUESTED',
        'PAYMENT_CHARGEBACK_DISPUTE',
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
        'PAYMENT_DUNNING_REQUESTED',
        'PAYMENT_DUNNING_RECEIVED',
    ];

    /** Cobrança sem fatura no sistema é importada nestes eventos (nos demais, só fica registrada). */
    public const IMPORT_EVENTS = [
        'PAYMENT_CREATED',
        'PAYMENT_UPDATED',
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED',
        'PAYMENT_OVERDUE',
        'PAYMENT_RESTORED',
        'PAYMENT_DUNNING_REQUESTED',
        'PAYMENT_DUNNING_RECEIVED',
    ];

    /** Avisos que acompanham o resultado do sincronizador. */
    private const EVENT_NOTES = [
        'PAYMENT_PARTIALLY_REFUNDED' => 'Estorno parcial no Asaas: a fatura não foi alterada; confira o valor estornado e ajuste manualmente.',
        'PAYMENT_REFUND_DENIED' => 'Estorno negado no Asaas.',
    ];

    public function __construct(
        private AsaasService $asaas,
        private PaymentSynchronizer $synchronizer,
        private ChargeImporter $importer,
        private EventService $events,
    ) {}

    public function process(AsaasWebhookEvent $record): void
    {
        // Só notificação ainda pendente (recebida agora ou reenviada depois de falhar)
        if ($record->refresh()->status !== AsaasWebhookEvent::STATUS_RECEIVED) {
            return;
        }

        $record->increment('attempts');

        try {
            [$status, $result] = $this->handle($record->event, $record->payment());

            $record->update([
                'status' => $status,
                'result' => $result,
                'error' => null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Asaas: falha ao processar notificação do webhook', [
                'webhook_event_id' => $record->id,
                'event_id' => $record->event_id,
                'event' => $record->event,
                'payment_id' => $record->payment_id,
                'exception' => $e,
            ]);

            $record->update([
                'status' => AsaasWebhookEvent::STATUS_FAILED,
                'error' => get_class($e).': '.$e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{0: string, 1: string}
     */
    private function handle(string $event, array $payment): array
    {
        if (! str_starts_with($event, 'PAYMENT_')) {
            return $this->ignored("Evento {$event} não é de cobrança.");
        }

        if (empty($payment['id'])) {
            return $this->ignored('Notificação sem o objeto da cobrança.');
        }

        $reference = (string) ($payment['externalReference'] ?? '');

        // Cobranças de eventos: EVENT-{event_id}-{customer_id}
        if (str_starts_with($reference, 'EVENT-')) {
            return $this->handleEventCharge($event, $payment, $reference);
        }

        if (! in_array($event, self::SYNC_EVENTS, true)) {
            return $this->ignored("Evento {$event} só informativo.");
        }

        [$invoice, $mismatch] = $this->findInvoice($payment);

        if ($mismatch) {
            return $this->ignored($mismatch);
        }

        if (! $invoice) {
            return $this->handleChargeWithoutInvoice($event, $payment);
        }

        $current = $this->currentPayment($event, $payment);
        $result = $this->synchronizer->apply($invoice, $current, undoReceipt: $event === 'PAYMENT_RECEIVED_IN_CASH_UNDONE');
        $this->syncParticipant($invoice->refresh());

        return $this->processed($this->describe($invoice, $result, self::EVENT_NOTES[$event] ?? null));
    }

    /**
     * Fatura da cobrança: pelo id da cobrança ou pelo externalReference (id da fatura), desde que a
     * fatura não esteja ligada a outra cobrança nem seja de outro cliente.
     *
     * @param  array<string, mixed>  $payment
     * @return array{0: ?Invoice, 1: ?string}
     */
    private function findInvoice(array $payment): array
    {
        $invoice = Invoice::query()->where('asaas_invoice_id', $payment['id'])->first();

        if ($invoice) {
            return [$invoice, null];
        }

        $reference = (string) ($payment['externalReference'] ?? '');
        $invoice = ctype_digit($reference) ? Invoice::query()->with('customer')->find((int) $reference) : null;

        if (! $invoice) {
            return [null, null];
        }

        $label = $this->invoiceLabel($invoice);

        if ($invoice->asaas_invoice_id) {
            return [null, "A fatura {$label} está ligada a outra cobrança ({$invoice->asaas_invoice_id}); notificação ignorada."];
        }

        $asaasCustomer = $payment['customer'] ?? null;
        $invoiceCustomer = $invoice->customer?->asaas_customer_id;

        if ($asaasCustomer && $invoiceCustomer && $asaasCustomer !== $invoiceCustomer) {
            return [null, "A cobrança referencia a fatura {$label}, mas é de outro cliente do Asaas ({$asaasCustomer}); notificação ignorada."];
        }

        return [$invoice, null];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{0: string, 1: string}
     */
    private function handleChargeWithoutInvoice(string $event, array $payment): array
    {
        if (! in_array($event, self::IMPORT_EVENTS, true)) {
            return $this->ignored("Cobrança {$payment['id']} sem fatura no sistema; nada a fazer em {$event}.");
        }

        $current = $this->currentPayment($event, $payment);
        $outcome = $this->importer->import($current);

        if (! $outcome->invoice) {
            Log::warning('Asaas: cobrança sem fatura no sistema não foi importada', [
                'asaas_payment_id' => $payment['id'],
                'asaas_customer_id' => $payment['customer'] ?? null,
                'reason' => $outcome->reason,
            ]);

            return $this->ignored("Cobrança {$payment['id']} sem fatura no sistema e não importada: {$outcome->reason}");
        }

        $this->syncParticipant($outcome->invoice->refresh());
        $prefix = $outcome->imported
            ? "Cobrança importada como a fatura {$this->invoiceLabel($outcome->invoice)} de {$outcome->customer?->name}."
            : null;

        return $this->processed($this->describe($outcome->invoice, $outcome->sync, $prefix));
    }

    /**
     * Situação atual da cobrança, lida na API do Asaas (as notificações podem chegar fora de ordem).
     * Se a consulta falhar, usa o objeto da notificação, ajustado ao que o evento afirma.
     *
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function currentPayment(string $event, array $payment): array
    {
        $fetched = $this->asaas->findPayment((string) $payment['id']);

        if (is_array($fetched) && ($fetched['id'] ?? null) === $payment['id']) {
            return $fetched;
        }

        Log::warning('Asaas: cobrança não pôde ser consultada na API; usando os dados da notificação', [
            'asaas_payment_id' => $payment['id'],
            'event' => $event,
        ]);

        return match ($event) {
            'PAYMENT_DELETED' => [...$payment, 'deleted' => true],
            'PAYMENT_RESTORED' => [...$payment, 'deleted' => false],
            'PAYMENT_RECEIVED_IN_CASH_UNDONE' => in_array($payment['status'] ?? null, PaymentSynchronizer::PAID_STATUSES, true)
                ? [...$payment, 'status' => $this->unpaidStatusFor($payment)]
                : $payment,
            default => $payment,
        };
    }

    /** @param  array<string, mixed>  $payment */
    private function unpaidStatusFor(array $payment): string
    {
        $dueDate = $payment['dueDate'] ?? null;

        return is_string($dueDate) && Carbon::parse($dueDate)->lt(today()) ? 'OVERDUE' : 'PENDING';
    }

    /** Fatura de evento (criada pelo EventService): o participante acompanha a fatura. */
    private function syncParticipant(Invoice $invoice): void
    {
        $participant = EventParticipant::query()->where('invoice_id', $invoice->id)->first();

        if (! $participant) {
            return;
        }

        $status = $invoice->status->value;

        if ($status === 'paid' && $participant->status !== 'paid') {
            $participant->update([
                'status' => 'paid',
                'paid_at' => $invoice->payments()->latest('payment_date')->value('payment_date') ?? now(),
            ]);
        } elseif ($status === 'canceled' && $participant->status !== 'canceled') {
            $participant->update(['status' => 'canceled']);
        } elseif (in_array($status, ['open', 'overdue'], true) && $participant->status === 'paid') {
            $participant->update(['status' => 'pending', 'paid_at' => null]);
        }
    }

    /**
     * Cobrança de evento com referência EVENT-{event_id}-{customer_id}.
     *
     * @param  array<string, mixed>  $payment
     * @return array{0: string, 1: string}
     */
    private function handleEventCharge(string $event, array $payment, string $reference): array
    {
        $parts = explode('-', $reference);

        if (count($parts) < 3) {
            return $this->ignored("Referência de evento inválida: {$reference}.");
        }

        [, $eventId, $customerId] = $parts;

        $participant = EventParticipant::query()
            ->where('event_id', $eventId)
            ->where('customer_id', $customerId)
            ->first();

        if (! $participant) {
            return $this->ignored("Participante não encontrado (evento {$eventId}, cliente {$customerId}).");
        }

        switch ($event) {
            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CONFIRMED':
                // CONFIRMED e depois RECEIVED (cartão) não registram dois pagamentos
                if ($participant->status === 'paid') {
                    return $this->ignored("Participante do evento {$eventId} (cliente {$customerId}) já estava pago.");
                }

                $this->events->markParticipantAsPaid($participant, [
                    'paid_at' => $payment['paymentDate'] ?? $payment['confirmedDate'] ?? now()->toDateString(),
                    'asaas_payment_id' => $payment['id'],
                    'payment_method' => PaymentSynchronizer::paymentMethodFor($payment)->value,
                ]);

                return $this->processed("Participante do evento {$eventId} (cliente {$customerId}) marcado como pago.");

            case 'PAYMENT_DELETED':
                if ($participant->status === 'paid') {
                    return $this->processed("Cobrança do evento {$eventId} excluída no Asaas, mas o participante (cliente {$customerId}) já pagou; não foi cancelado.");
                }

                $participant->update(['status' => 'canceled']);

                return $this->processed("Participação no evento {$eventId} (cliente {$customerId}) cancelada.");

            default:
                return $this->ignored("Evento {$event} sem efeito em cobrança de evento.");
        }
    }

    private function describe(Invoice $invoice, ?SyncResult $result, ?string $prefix = null): string
    {
        $lines = array_filter([$prefix]);

        if ($result) {
            $line = "Fatura {$this->invoiceLabel($invoice)}: {$result->change->label()}";

            if ($result->statusBefore !== $result->statusAfter) {
                $line .= ' ('.($result->statusBefore?->getLabel() ?? '—').' → '.($result->statusAfter?->getLabel() ?? '—').')';
            }

            $lines[] = $line.'.';

            foreach ([...$result->updates, ...$result->warnings] as $detail) {
                $lines[] = "- {$detail}";
            }
        }

        return implode("\n", $lines);
    }

    private function invoiceLabel(Invoice $invoice): string
    {
        return $invoice->number ?: '#'.$invoice->getKey();
    }

    /** @return array{0: string, 1: string} */
    private function processed(string $result): array
    {
        return [AsaasWebhookEvent::STATUS_PROCESSED, $result];
    }

    /** @return array{0: string, 1: string} */
    private function ignored(string $reason): array
    {
        return [AsaasWebhookEvent::STATUS_IGNORED, $reason];
    }
}
