<?php

namespace App\Models;

use App\Documents\PickupCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pessoa autorizada a buscar o aluno na escola. Cada autorização ativa pode ter uma carteirinha. */
class StudentPickupAuthorization extends Model
{
    /** Campos impressos na carteirinha: se mudarem, a carteirinha emitida deixa de corresponder à pessoa. */
    public const CARD_FIELDS = ['name', 'document', 'relationship'];

    protected $guarded = [];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (StudentPickupAuthorization $authorization): void {
            if ($authorization->wasChanged(self::CARD_FIELDS)) {
                PickupCard::cancelFor($authorization, 'Dados do autorizado alterados em '.now()->format('d/m/Y').'; emita uma nova carteirinha.');
            }
        });

        static::deleting(function (StudentPickupAuthorization $authorization): void {
            PickupCard::cancelFor($authorization, 'Autorização excluída em '.now()->format('d/m/Y').'.');
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
