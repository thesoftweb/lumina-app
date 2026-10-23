<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Term extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'year',
        'order',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Term $term): void {
            if ($reason = $term->deletionBlockedReason()) {
                throw ValidationException::withMessages(['term' => $reason]);
            }
        });
    }

    /** Bimestre fechado pela secretaria: o professor não altera mais as notas; só a secretaria corrige. */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function deletionBlockedReason(): ?string
    {
        return $this->grades()->exists()
            ? 'Este bimestre possui notas lançadas e não pode ser excluído.'
            : null;
    }
}
