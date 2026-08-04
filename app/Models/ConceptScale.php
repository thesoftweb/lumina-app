<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ConceptScale extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'label',
        'sort_order',
        'color',
        'is_passing',
        'is_active',
    ];

    protected $casts = [
        'is_passing' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ConceptScale $concept): void {
            if ($concept->grades()->exists()) {
                throw ValidationException::withMessages([
                    'concept' => 'Este conceito já foi utilizado e deve ser apenas desativado.',
                ]);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}
