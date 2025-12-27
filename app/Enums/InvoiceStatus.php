<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Overdue = 'overdue';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'info',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Canceled => 'danger',
            self::Overdue => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Partial => 'Parcial',
            self::Paid => 'Paga',
            self::Canceled => 'Cancelada',
            self::Overdue => 'Vencida',
        };
    }
}
