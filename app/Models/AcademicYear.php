<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AcademicYear extends Model
{
    /** Ano novo não vira padrão sozinho: a coluna tem padrão true no banco, mas o padrão só muda pela chave. */
    protected $attributes = [
        'is_default' => false,
    ];

    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
        'year' => 'integer',
        'is_default' => 'boolean',
        'admissions_open' => 'boolean',
        'admissions_starts_at' => 'date',
        'admissions_ends_at' => 'date',
    ];

    protected $fillable = [
        'description',
        'year',
        'start_at',
        'end_at',
        'is_default',
        'admissions_open',
        'admissions_starts_at',
        'admissions_ends_at',
        'admissions_notes',
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

    /** Anos com a ficha pública de pré-matrícula aberta hoje (chave ligada e dentro do período). */
    public function scopeAcceptingPreEnrollments($query)
    {
        return $query->where('admissions_open', true)
            ->where(fn ($query) => $query->whereNull('admissions_starts_at')->orWhereDate('admissions_starts_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('admissions_ends_at')->orWhereDate('admissions_ends_at', '>=', today()));
    }

    public function acceptsPreEnrollments(): bool
    {
        return $this->admissions_open
            && (! $this->admissions_starts_at || $this->admissions_starts_at->lte(today()))
            && (! $this->admissions_ends_at || $this->admissions_ends_at->gte(today()));
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'academic_year_id');
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function admissionOffers()
    {
        return $this->hasMany(AdmissionOffer::class);
    }

    public function preEnrollments()
    {
        return $this->hasMany(PreEnrollment::class);
    }
}
