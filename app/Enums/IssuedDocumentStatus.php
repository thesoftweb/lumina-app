<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IssuedDocumentStatus: string implements HasColor, HasLabel
{
    case Valid = 'valid';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => 'Válido',
            self::Expired => 'Vencido',
            self::Canceled => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Expired => 'warning',
            self::Canceled => 'danger',
        };
    }
}
