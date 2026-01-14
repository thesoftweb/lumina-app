<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LessonPlanStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Scheduled => 'Agendado',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
