<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class Enrollment extends Model
{
    public const DUPLICATE_IN_YEAR_MESSAGE = 'Este aluno já possui uma matrícula não cancelada neste ano letivo.';

    /** Documentos exigidos na matrícula, na ordem usada no formulário, na visualização e na ficha impressa. */
    public const DOCUMENTS = [
        'doc_historical_delivered' => 'Histórico escolar',
        'doc_photo_3x4_delivered' => 'Foto 3x4',
        'doc_declaration_delivered' => 'Declaração',
        'doc_residence_proof_delivered' => 'Comprovante de residência',
        'doc_student_document_delivered' => 'Documento do aluno',
        'doc_responsible_document_delivered' => 'Documento do responsável',
    ];

    protected $guarded = [];

    protected $casts = [
        'enrollment_date' => 'date',
        'status' => EnrollmentStatus::class,
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
                || $enrollment->status === EnrollmentStatus::Canceled) {
                return;
            }

            $duplicate = static::query()
                ->notCanceledInYear($enrollment->student_id, $enrollment->academic_year_id)
                ->when($enrollment->exists, fn ($query) => $query->whereKeyNot($enrollment->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'student_id' => self::DUPLICATE_IN_YEAR_MESSAGE,
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

    public function number(): string
    {
        return str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function deliveredDocumentsCount(): int
    {
        return collect(array_keys(self::DOCUMENTS))->filter(fn (string $field): bool => (bool) $this->{$field})->count();
    }

    /** Mensalidade com desconto, calculada pelas mesmas regras usadas na geração das faturas. */
    public function tuitionBreakdown(): ?array
    {
        if (! $this->plan) {
            return null;
        }

        return app(InvoiceService::class)->calculateTuitionDiscount($this, (float) $this->plan->base_amount);
    }

    public function tuitionDiscountLabel(): ?string
    {
        $tuition = $this->tuitionBreakdown();

        if (! $tuition || ! $tuition['discount_type']) {
            return null;
        }

        $value = $tuition['discount_type'] === 'percentage'
            ? Number::percentage((float) $tuition['discount_value'], maxPrecision: 2, locale: 'pt_BR')
            : Number::currency((float) $tuition['discount_value'], 'BRL', 'pt_BR');

        return $value.($tuition['discount_source'] === 'enrollment_custom' ? ' (personalizado)' : ' (do plano)');
    }

    /** Um aluno só pode ter uma matrícula não cancelada por ano letivo. */
    public function scopeNotCanceledInYear($query, $studentId, $academicYearId)
    {
        return $query->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', '!=', EnrollmentStatus::Canceled->value);
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

    /** Ano gravado no histórico escolar ao fechar o ano letivo. */
    public function transcriptYear()
    {
        return $this->hasOne(TranscriptYear::class);
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
