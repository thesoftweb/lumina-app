<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Tipos de documento emitidos pela escola. A série define a numeração (DEC-2026-000001). */
enum IssuedDocumentType: string implements HasLabel
{
    case EnrollmentDeclaration = 'enrollment_declaration';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnrollmentDeclaration => 'Declaração de matrícula',
        };
    }

    public function series(): string
    {
        return match ($this) {
            self::EnrollmentDeclaration => 'DEC',
        };
    }

    /** Corpo do documento em resources/views/documents/issued/types. */
    public function view(): string
    {
        return 'documents.issued.types.'.$this->value;
    }
}
