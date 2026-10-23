<?php

namespace App\Models;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Services\Collections\OverdueDebt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contato de cobrança feito com o responsável (histórico da inadimplência). */
class CollectionContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'channel' => CollectionChannel::class,
        'outcome' => CollectionOutcome::class,
        'promised_date' => 'date',
        'promised_amount' => 'decimal:2',
        'overdue_total' => 'decimal:2',
        'invoice_ids' => 'array',
    ];

    /**
     * Registra um contato com o retrato da dívida no momento (saldo e faturas vencidas).
     *
     * @param  array{notes?: ?string, promised_date?: mixed, promised_amount?: mixed, message?: ?string}  $details
     */
    public static function record(OverdueDebt $debt, CollectionChannel $channel, CollectionOutcome $outcome, array $details = [], ?User $user = null): self
    {
        $promised = $outcome === CollectionOutcome::Promised;

        return $debt->customer->collectionContacts()->create([
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'channel' => $channel,
            'outcome' => $outcome,
            'notes' => filled($details['notes'] ?? null) ? trim($details['notes']) : null,
            'promised_date' => $promised ? ($details['promised_date'] ?? null) : null,
            'promised_amount' => $promised && filled($details['promised_amount'] ?? null) ? $details['promised_amount'] : null,
            'overdue_total' => $debt->balance(),
            'invoice_ids' => $debt->invoiceIds(),
            'message' => $details['message'] ?? null,
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Promessa com data passada: o responsável não pagou no combinado (ele ainda está na lista de vencidos). */
    public function promiseIsBroken(): bool
    {
        return $this->promised_date !== null && $this->promised_date->lt(today());
    }
}
