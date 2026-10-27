<?php

namespace App\Models;

use App\Enums\PreEnrollmentStatus;
use App\Enums\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Vaga ofertada na pré-matrícula de um ano letivo: série, turno e valores divulgados na ficha pública. */
class AdmissionOffer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'shift' => Shift::class,
        'enrollment_fee' => 'decimal:2',
        'vacancies' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query->orderBy('sort_order')->orderBy('name'));

        // O valor divulgado vem do plano do mesmo ano: um plano de 2026 nunca aparece na ficha de 2027
        static::saving(function (AdmissionOffer $offer): void {
            if ($offer->plan_id && ! Plan::query()->whereKey($offer->plan_id)->where('academic_year_id', $offer->academic_year_id)->exists()) {
                throw ValidationException::withMessages([
                    'plan_id' => 'O plano da vaga precisa ser do mesmo ano letivo.',
                ]);
            }

            // A turma escolhida na ficha também é do ano: a família de 2027 nunca escolhe uma turma de 2026
            if ($offer->classroom_id && ! Classroom::query()->whereKey($offer->classroom_id)->where('academic_year_id', $offer->academic_year_id)->exists()) {
                throw ValidationException::withMessages([
                    'classroom_id' => 'A turma da vaga precisa ser do mesmo ano letivo.',
                ]);
            }
        });
    }

    /**
     * Vaga montada a partir de uma turma do ano: nome, nível, série e o plano de mensalidade vinculado à
     * turma (o primeiro do ano, por nome). Quantidade de vagas fica em branco: é opcional.
     *
     * @return array<string, mixed>
     */
    public static function attributesFromClassroom(Classroom $classroom): array
    {
        return [
            'academic_year_id' => $classroom->academic_year_id,
            'classroom_id' => $classroom->getKey(),
            'level_id' => $classroom->level_id,
            'name' => $classroom->name,
            'grade_label' => $classroom->grade_label,
            'plan_id' => $classroom->plans()->where('plans.academic_year_id', $classroom->academic_year_id)->orderBy('plans.name')->value('plans.id'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function preEnrollments()
    {
        return $this->hasMany(PreEnrollment::class);
    }

    /** Mensalidade fixa: o valor configurado no plano, sem desconto (desconto só se negocia na rematrícula). */
    public function monthlyAmount(): ?float
    {
        return $this->plan ? (float) $this->plan->base_amount : null;
    }

    /** Fichas que ainda ocupam a vaga (abertas ou já matriculadas). */
    public function takenCount(): int
    {
        return $this->preEnrollments()->where('status', '!=', PreEnrollmentStatus::Lost->value)->count();
    }
}
