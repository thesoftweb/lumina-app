<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Resultado de um ano letivo no boletim e no histórico escolar. */
enum TranscriptResult: string implements HasColor, HasLabel
{
    case Approved = 'approved';
    case Failed = 'failed';
    case FailedAttendance = 'failed_attendance';
    case Transferred = 'transferred';
    case InProgress = 'in_progress';

    public function getLabel(): string
    {
        return match ($this) {
            self::Approved => 'Aprovado',
            self::Failed => 'Reprovado',
            self::FailedAttendance => 'Reprovado por frequência',
            self::Transferred => 'Transferido',
            self::InProgress => 'Cursando',
        };
    }

    public static function tryFromLabel(string $label): ?self
    {
        return collect(self::cases())->first(fn (self $case): bool => $case->getLabel() === $label);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Failed, self::FailedAttendance => 'danger',
            self::Transferred => 'warning',
            self::InProgress => 'info',
        };
    }
}
