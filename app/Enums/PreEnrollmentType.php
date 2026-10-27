<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Aluno novo na escola ou aluno que já estuda aqui e renova para o próximo ano. */
enum PreEnrollmentType: string implements HasColor, HasLabel
{
    case NewStudent = 'new';
    case Reenrollment = 'reenrollment';

    public function getLabel(): string
    {
        return match ($this) {
            self::NewStudent => 'Novato',
            self::Reenrollment => 'Rematrícula',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NewStudent => 'info',
            self::Reenrollment => 'success',
        };
    }

    /** Desconto só pode ser negociado na rematrícula; aluno novato paga o valor configurado. */
    public function allowsDiscount(): bool
    {
        return $this === self::Reenrollment;
    }
}
