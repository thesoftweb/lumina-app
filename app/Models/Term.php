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
    ];

    protected static function booted(): void
    {
        static::deleting(function (Term $term): void {
            if ($reason = $term->deletionBlockedReason()) {
                throw ValidationException::withMessages(['term' => $reason]);
            }
        });
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
