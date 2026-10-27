<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Funil da pré-matrícula: da ficha recebida até a matrícula criada ou a desistência. */
enum PreEnrollmentStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InContact = 'in_contact';
    case VisitScheduled = 'visit_scheduled';
    case AwaitingDocuments = 'awaiting_documents';
    case Converted = 'converted';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nova',
            self::InContact => 'Em contato',
            self::VisitScheduled => 'Visita agendada',
            self::AwaitingDocuments => 'Aguardando documentos',
            self::Converted => 'Matriculada',
            self::Lost => 'Desistiu',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InContact, self::VisitScheduled => 'warning',
            self::AwaitingDocuments => 'primary',
            self::Converted => 'success',
            self::Lost => 'gray',
        };
    }

    /** Fichas ainda em negociação: contam como lead aberto e podem virar matrícula. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::Lost], true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
