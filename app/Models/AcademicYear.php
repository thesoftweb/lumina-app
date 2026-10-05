<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AcademicYear extends Model
{
    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
        'year' => 'integer',
        'is_default' => 'boolean',
    ];

    protected $fillable = [
        'description',
        'year',
        'start_at',
        'end_at',
        'is_default',
    ];

    protected static function booted(): void
    {
        static::saved(function (AcademicYear $year): void {
            if ($year->is_default && ($year->wasRecentlyCreated || $year->wasChanged('is_default'))) {
                static::query()->whereKeyNot($year->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });

        static::deleting(function (AcademicYear $year): void {
            if ($year->enrollments()->exists()) {
                throw ValidationException::withMessages([
                    'academic_year' => 'Este ano letivo possui matrículas e não pode ser excluído.',
                ]);
            }
        });
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'academic_year_id');
    }
}
