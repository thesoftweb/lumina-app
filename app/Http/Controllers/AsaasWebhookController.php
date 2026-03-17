<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\EventParticipant;
use App\Services\AsaasService;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    protected AsaasService $asaasService;
    protected EventService $eventService;

    public function __construct(AsaasService $asaasService, EventService $eventService)
    {
        $this->asaasService = $asaasService;
        $this->eventService = $eventService;
    }

    /**
     * Handle webhook notifications from Asaas
     *
     * Tipos de eventos suportados:
     * - PAYMENT_RECEIVED: Pagamento recebido
     * - PAYMENT_CONFIRMED: Pagamento confirmado
     * - PAYMENT_OVERDUE: Pagamento vencido
     * - PAYMENT_DELETED: Pagamento deletado
     * - PAYMENT_RESTORED: Pagamento restaurado
     */
    public function handle(Request $request)
    {
        Log::info('Asaas webhook received', [
            'event' => $request->input('event'),
            'payload' => $request->input('payment'),
        ]);

        $event = $request->input('event');
        $paymentData = $request->input('payment', []);

        if (!$event || !$paymentData) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Extrair referência externa (invoice ID)
        $externalReference = $paymentData['externalReference'] ?? null;

        if (!$externalReference) {
            Log::warning('Asaas webhook: No external reference found', $paymentData);
            return response()->json(['status' => 'ok'], 200); // Aceitar mesmo sem referência
        }

        // Verificar se é referência de evento (EVENT-{event_id}-{customer_id})
        if (strpos($externalReference, 'EVENT-') === 0) {
            return $this->handleEventPayment($event, $paymentData, $externalReference);
        }

        // Buscar invoice pelo ID (armazenado em externalReference)
        $invoice = Invoice::find($externalReference);

        if (!$invoice) {
            Log::warning("Asaas webhook: Invoice not found for reference: {$externalReference}");
            return response()->json(['status' => 'ok'], 200); // Aceitar e ignorar
        }

        try {
            // Mapear evento para ação
            switch ($event) {
                case 'PAYMENT_RECEIVED':
                case 'PAYMENT_CONFIRMED':
                    $this->handlePaymentReceived($invoice, $paymentData);
                    break;

                case 'PAYMENT_OVERDUE':
                    $this->handlePaymentOverdue($invoice, $paymentData);
                    break;

                case 'PAYMENT_DELETED':
                    $this->handlePaymentDeleted($invoice, $paymentData);
                    break;

                case 'PAYMENT_RESTORED':
                    $this->handlePaymentRestored($invoice, $paymentData);
                    break;

                default:
                    Log::info("Asaas webhook: Unknown event type: {$event}");
                    break;
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error("Asaas webhook error processing invoice {$invoice->id}: " . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle payment received
     */
    private function handlePaymentReceived(Invoice $invoice, array $paymentData): void
    {
        $paymentDate = $paymentData['confirmedDate'] ?? now()->format('Y-m-d');
        $paymentId = $paymentData['id'] ?? null;
        $amount = $paymentData['value'] ?? $invoice->amount;

        // Sincronizar status do Asaas
        $this->asaasService->syncPaymentStatus($invoice);

        Log::info("Asaas payment received", [
            'invoice_id' => $invoice->id,
            'reference' => $invoice->reference,
            'asaas_id' => $paymentId,
            'amount' => $amount,
            'date' => $paymentDate,
        ]);
    }

    /**
     * Handle payment overdue
     */
    private function handlePaymentOverdue(Invoice $invoice, array $paymentData): void
    {
        $invoice->update([
            'status' => 'overdue',
            'asaas_sync_status' => 'synced',
            'asaas_synced_at' => now(),
        ]);

        Log::info("Asaas payment marked overdue", [
            'invoice_id' => $invoice->id,
            'reference' => $invoice->reference,
        ]);
    }

    /**
     * Handle payment deleted
     */
    private function handlePaymentDeleted(Invoice $invoice, array $paymentData): void
    {
        $invoice->update([
            'status' => 'canceled',
            'asaas_sync_status' => 'canceled',
            'asaas_synced_at' => now(),
        ]);

        Log::info("Asaas payment deleted", [
            'invoice_id' => $invoice->id,
            'reference' => $invoice->reference,
        ]);
    }

    /**
     * Handle payment restored
     */
    private function handlePaymentRestored(Invoice $invoice, array $paymentData): void
    {
        // Sincronizar status atual do Asaas
        $this->asaasService->syncPaymentStatus($invoice);

        Log::info("Asaas payment restored", [
            'invoice_id' => $invoice->id,
            'reference' => $invoice->reference,
        ]);
    }

    /**
     * Handle event payment
     * Reference format: EVENT-{event_id}-{customer_id}
     */
    private function handleEventPayment(string $event, array $paymentData, string $reference): \Illuminate\Http\JsonResponse
    {
        // Parse reference: EVENT-{event_id}-{customer_id}
        $parts = explode('-', $reference);

        if (count($parts) < 3) {
            Log::warning("Asaas webhook: Invalid event reference format: {$reference}");
            return response()->json(['status' => 'ok'], 200);
        }

        $eventId = $parts[1];
        $customerId = $parts[2];

        // Find event participant
        $participant = EventParticipant::where('event_id', $eventId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$participant) {
            Log::warning("Asaas webhook: EventParticipant not found - Event: {$eventId}, Customer: {$customerId}");
            return response()->json(['status' => 'ok'], 200);
        }

        try {
            switch ($event) {
                case 'PAYMENT_RECEIVED':
                case 'PAYMENT_CONFIRMED':
                    $paymentDate = $paymentData['confirmedDate'] ?? now()->format('Y-m-d');
                    $paymentId = $paymentData['id'] ?? null;

                    // Mark participant as paid
                    $this->eventService->markParticipantAsPaid($participant, [
                        'paid_at' => $paymentDate,
                        'asaas_payment_id' => $paymentId,
                        'payment_method' => $paymentData['billingType'] ?? 'PIX',
                    ]);

                    Log::info("Event payment received", [
                        'event_id' => $eventId,
                        'customer_id' => $customerId,
                        'asaas_id' => $paymentId,
                        'amount' => $paymentData['value'] ?? null,
                    ]);
                    break;

                case 'PAYMENT_OVERDUE':
                    // Do nothing for events - status remains pending
                    Log::info("Event payment overdue", [
                        'event_id' => $eventId,
                        'customer_id' => $customerId,
                    ]);
                    break;

                case 'PAYMENT_DELETED':
                    $participant->update(['status' => 'canceled']);
                    Log::info("Event participation canceled", [
                        'event_id' => $eventId,
                        'customer_id' => $customerId,
                    ]);
                    break;
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error("Error processing event payment webhook: " . $e->getMessage(), [
                'event_id' => $eventId,
                'customer_id' => $customerId,
                'reference' => $reference,
            ]);
            return response()->json(['status' => 'ok'], 200); // Still return ok to prevent Asaas retry
        }
    }
}
