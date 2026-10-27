<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class Plan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'has_discount' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // O desconto do plano é um valor disponível para negociação; só vale na matrícula que o aplicar
        static::saving(function (Plan $plan): void {
            $base = (float) $plan->base_amount;
            $value = (float) $plan->discount_value;

            // Desconto zerado é o mesmo que "Nenhum" (há planos antigos gravados como "Fixo" com valor 0)
            if (! in_array($plan->discount_type, ['percentage', 'fixed'], true) || $value <= 0) {
                $plan->discount_type = 'none';
                $value = 0.0;
            }

            if ($plan->discount_type === 'percentage' && $value > 100) {
                throw ValidationException::withMessages(['discount_value' => 'O desconto percentual não pode passar de 100%.']);
            }

            if ($plan->discount_type === 'fixed' && $value > $base) {
                throw ValidationException::withMessages(['discount_value' => 'O desconto não pode ser maior que o valor base.']);
            }

            $plan->discount_value = $value;
            $plan->has_discount = $plan->discount_type !== 'none';
            $plan->final_value = round(max(0, match ($plan->discount_type) {
                'percentage' => $base * (1 - $value / 100),
                'fixed' => $base - $value,
                default => $base,
            }), 2);
        });
    }

    /** "10%" ou "R$ 20,00": o desconto disponível para negociar na matrícula. */
    public function discountLabel(): ?string
    {
        return match ($this->discount_type) {
            'percentage' => Number::percentage((float) $this->discount_value, maxPrecision: 2, locale: 'pt_BR'),
            'fixed' => Number::currency((float) $this->discount_value, 'BRL', 'pt_BR'),
            default => null,
        };
    }

    /** Planos sem ano letivo são os anteriores à pré-matrícula (legado) e valem como antes. */
    public function scopeForAcademicYear($query, ?int $academicYearId)
    {
        return $query->where(fn ($query) => $query->whereNull('plans.academic_year_id')->orWhere('plans.academic_year_id', $academicYearId));
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class)->using(ClassroomPlan::class);
    }
}
