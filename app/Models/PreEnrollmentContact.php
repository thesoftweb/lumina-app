<?php

namespace App\Models;

use App\Enums\CollectionChannel;
use App\Enums\PreEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contato da secretaria com a família de uma pré-matrícula (histórico do lead). */
class PreEnrollmentContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'channel' => CollectionChannel::class,
        'status_after' => PreEnrollmentStatus::class,
    ];

    /** Registra o contato e, se informado, move a ficha para a nova situação. */
    public static function record(PreEnrollment $preEnrollment, CollectionChannel $channel, ?string $notes = null, ?PreEnrollmentStatus $statusAfter = null, ?User $user = null): self
    {
        $contact = $preEnrollment->contacts()->create([
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'channel' => $channel,
            'notes' => filled($notes) ? trim($notes) : null,
            'status_after' => $statusAfter,
        ]);

        $preEnrollment->forceFill(array_filter([
            'last_contact_at' => $contact->created_at,
            'status' => $statusAfter && ! $preEnrollment->isConverted() ? $statusAfter : null,
        ]))->save();

        return $contact;
    }

    public function preEnrollment(): BelongsTo
    {
        return $this->belongsTo(PreEnrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
