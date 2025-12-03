<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Started = 'started';
    case Canceled = 'canceled';
    case Completed = 'completed';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Started => 'warning',
            self::Canceled => 'danger',
            self::Completed => 'primary',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Started => 'Iniciada',
            self::Canceled => 'Cancelada',
            self::Completed => 'Concluída',
        };
    }
}
