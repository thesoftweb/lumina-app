<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notificação recebida do webhook do Asaas. Gravada antes de ser processada: o event_id único impede
 * processar duas vezes a mesma notificação, e o registro guarda o que foi feito (ou o erro).
 */
class AsaasWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    /** @return array<string, mixed> */
    public function payment(): array
    {
        $payment = $this->payload['payment'] ?? null;

        return is_array($payment) ? $payment : [];
    }
}
