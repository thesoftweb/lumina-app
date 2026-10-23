<?php

namespace App\Enums;

enum AssessmentMode: string
{
    case Numeric = 'numeric';
    case Concept = 'concept';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Notas (N1, N2 e recuperação)',
            self::Concept => 'Conceito bimestral',
        };
    }
}
