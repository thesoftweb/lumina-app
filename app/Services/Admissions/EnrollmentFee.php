<?php

namespace App\Services\Admissions;

use App\Models\Enrollment;

/**
 * Taxa de matrícula: a configurada na vaga da pré-matrícula que originou a matrícula. Para aluno novato
 * ela é fixa (sem desconto); na rematrícula serve de sugestão e pode ser negociada.
 */
class EnrollmentFee
{
    public function configured(Enrollment $enrollment): ?float
    {
        $fee = $enrollment->preEnrollment?->offer?->enrollment_fee;

        return $fee !== null ? (float) $fee : null;
    }

    public function isFixed(Enrollment $enrollment): bool
    {
        return $enrollment->new_student && $this->configured($enrollment) !== null;
    }

    /** Valor a cobrar: o configurado quando a taxa é fixa, senão o informado pela secretaria. */
    public function resolve(Enrollment $enrollment, float $informed): float
    {
        return $this->isFixed($enrollment) ? $this->configured($enrollment) : $informed;
    }
}
