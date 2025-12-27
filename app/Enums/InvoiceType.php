<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasLabel
{
    case ENROLLMENT = 'enrollment';      // Taxa de matrícula
    case TUITION = 'tuition';            // Mensalidade
    case SERVICE = 'service';            // Serviço adicional
    case MATERIAL = 'material';          // Material didático
    case OTHER = 'other';                // Outras entradas

    public function getLabel(): string
    {
        return match ($this) {
            self::ENROLLMENT => 'Matrícula',
            self::TUITION => 'Mensalidade',
            self::SERVICE => 'Serviço Adicional',
            self::MATERIAL => 'Material Didático',
            self::OTHER => 'Outro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ENROLLMENT => 'primary',
            self::TUITION => 'success',
            self::SERVICE => 'warning',
            self::MATERIAL => 'info',
            self::OTHER => 'secondary',
        };
    }
}
