<?php

namespace App\Jobs;

use App\Models\AsaasWebhookEvent;
use App\Services\Asaas\WebhookProcessor;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Processa uma notificação do webhook do Asaas. O controlador despacha com afterResponse(): roda no
 * mesmo processo, logo depois de o Asaas receber o 200, sem depender de worker de fila.
 */
class ProcessAsaasWebhookEvent
{
    use Dispatchable;

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(WebhookProcessor $processor): void
    {
        $record = AsaasWebhookEvent::query()->find($this->webhookEventId);

        if ($record) {
            $processor->process($record);
        }
    }
}
