<?php

namespace App\Services;

class GradeCalculationService
{
    public function calculate(mixed $grade1, mixed $grade2, mixed $recoveryGrade = null): ?float
    {
        if (! is_numeric($grade1) || ! is_numeric($grade2)) {
            return null;
        }

        $grades = [(float) $grade1, (float) $grade2];

        if (is_numeric($recoveryGrade)) {
            $grades[] = (float) $recoveryGrade;
        }

        rsort($grades, SORT_NUMERIC);

        return round(($grades[0] + $grades[1]) / 2, 2);
    }
}
