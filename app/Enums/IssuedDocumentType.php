<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Tipos de documento emitidos pela escola. A série define a numeração (DEC-2026-000001). */
enum IssuedDocumentType: string implements HasLabel
{
    case EnrollmentDeclaration = 'enrollment_declaration';
    case AttendanceDeclaration = 'attendance_declaration';
    case FinancialClearanceDeclaration = 'financial_clearance_declaration';
    case PaymentReceipt = 'payment_receipt';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnrollmentDeclaration => 'Declaração de matrícula',
            self::AttendanceDeclaration => 'Declaração de frequência',
            self::FinancialClearanceDeclaration => 'Declaração de quitação',
            self::PaymentReceipt => 'Recibo de pagamento',
        };
    }

    public function series(): string
    {
        return match ($this) {
            self::EnrollmentDeclaration,
            self::AttendanceDeclaration,
            self::FinancialClearanceDeclaration => 'DEC',
            self::PaymentReceipt => 'REC',
        };
    }

    /** Corpo do documento em resources/views/documents/issued/types. */
    public function view(): string
    {
        return 'documents.issued.types.'.$this->value;
    }
}
