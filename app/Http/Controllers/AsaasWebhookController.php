<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAsaasWebhookEvent;
use App\Models\AsaasWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe as notificações do webhook do Asaas (POST /webhooks/asaas). O token já foi conferido pelo
 * middleware VerifyAsaasWebhookToken.
 *
 * A notificação é gravada em asaas_webhook_events e o Asaas recebe 200 na hora; o processamento
 * (App\Services\Asaas\WebhookProcessor) roda logo depois da resposta. O Asaas entrega "pelo menos uma
 * vez": a mesma notificação (mesmo id) repetida responde 200 sem processar de novo. Falha de
 * processamento fica registrada como "failed" e NÃO vira erro HTTP, porque respostas de erro seguidas
 * fazem o Asaas pausar a fila de notificações.
 */
class AsaasWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (! is_string($event) || $event === '') {
            Log::warning('Asaas webhook: notificação sem o campo "event".');

            return response()->json(['error' => 'Payload inválido'], 400);
        }

        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $eventId = $this->eventId($payload);

        if (AsaasWebhookEvent::query()->where('event_id', $eventId)->exists()) {
            return $this->duplicate($eventId);
        }

        try {
            $record = AsaasWebhookEvent::create([
                'event_id' => $eventId,
                'event' => $event,
                'payment_id' => isset($payment['id']) ? (string) $payment['id'] : null,
                'external_reference' => isset($payment['externalReference']) ? (string) $payment['externalReference'] : null,
                'payload' => $payload,
                'status' => AsaasWebhookEvent::STATUS_RECEIVED,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A mesma notificação chegou duas vezes ao mesmo tempo
            return $this->duplicate($eventId);
        }

        ProcessAsaasWebhookEvent::dispatch($record->id)->afterResponse();

        return response()->json(['status' => 'received']);
    }

    /**
     * Id da notificação no Asaas (chave de idempotência). Sem ele (formato antigo), o hash do corpo:
     * só a reentrega idêntica é tratada como repetida.
     *
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): string
    {
        $id = $payload['id'] ?? null;

        if (is_string($id) && $id !== '') {
            return $id;
        }

        return 'sem-id:'.hash('sha256', (string) json_encode($payload));
    }

    /** Notificação já recebida: não processa de novo, a não ser que a tentativa anterior tenha falhado. */
    private function duplicate(string $eventId): JsonResponse
    {
        $retried = AsaasWebhookEvent::query()
            ->where('event_id', $eventId)
            ->where('status', AsaasWebhookEvent::STATUS_FAILED)
            ->update(['status' => AsaasWebhookEvent::STATUS_RECEIVED]);

        if ($retried) {
            $record = AsaasWebhookEvent::query()->where('event_id', $eventId)->firstOrFail();
            ProcessAsaasWebhookEvent::dispatch($record->id)->afterResponse();

            return response()->json(['status' => 'retrying']);
        }

        Log::info('Asaas webhook: notificação repetida ignorada', ['event_id' => $eventId]);

        return response()->json(['status' => 'duplicate']);
    }
}
