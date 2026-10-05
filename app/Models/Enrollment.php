<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Enrollment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enrollment_date' => 'date',
        'status' => \App\Enums\EnrollmentStatus::class,
        'doc_historical_delivered' => 'boolean',
        'doc_photo_3x4_delivered' => 'boolean',
        'doc_declaration_delivered' => 'boolean',
        'doc_residence_proof_delivered' => 'boolean',
        'doc_student_document_delivered' => 'boolean',
        'doc_responsible_document_delivered' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Enrollment $enrollment): void {
            if (! $enrollment->academic_year_id
                || ! $enrollment->student_id
                || $enrollment->status === \App\Enums\EnrollmentStatus::Canceled) {
                return;
            }

            $duplicate = static::query()
                ->where('student_id', $enrollment->student_id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->where('status', '!=', \App\Enums\EnrollmentStatus::Canceled->value)
                ->when($enrollment->exists, fn ($query) => $query->whereKeyNot($enrollment->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'student_id' => 'Este aluno já possui uma matrícula não cancelada neste ano letivo.',
                ]);
            }
        });

        static::deleting(function (Enrollment $enrollment): void {
            if ($enrollment->grade()->exists() || $enrollment->attendances()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Esta matrícula possui notas ou frequência. Cancele a matrícula em vez de excluí-la.',
                ]);
            }
        });
    }

    /** Matrículas sem ano letivo (legado) continuam aceitas em qualquer período. */
    public function scopeCoveringDate($query, $date)
    {
        return $query->where(function ($query) use ($date): void {
            $query->whereNull('academic_year_id')
                ->orWhereHas('academicYear', function ($query) use ($date): void {
                    $query->where(fn ($query) => $query->whereNull('start_at')->orWhereDate('start_at', '<=', $date))
                        ->where(fn ($query) => $query->whereNull('end_at')->orWhereDate('end_at', '>=', $date));
                });
        });
    }

    public function scopeForTerm($query, Term $term)
    {
        if (! $term->year) {
            return $query;
        }

        return $query->where(function ($query) use ($term): void {
            $query->whereNull('academic_year_id')
                ->orWhereHas('academicYear', fn ($query) => $query->where('year', $term->year));
        });
    }

    public function acceptsTerm(Term $term): bool
    {
        $year = $this->academicYear?->year;

        return ! $term->year || ! $year || (int) $term->year === (int) $year;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grade()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
