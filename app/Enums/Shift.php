<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Turno ofertado na pré-matrícula. */
enum Shift: string implements HasLabel
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullTime = 'full_time';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => 'Manhã',
            self::Afternoon => 'Tarde',
            self::FullTime => 'Integral',
        };
    }
}
