<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Resultado de um contato de cobrança. */
enum CollectionOutcome: string implements HasColor, HasLabel
{
    case MessageSent = 'message_sent';
    case Promised = 'promised';
    case Negotiating = 'negotiating';
    case NoAnswer = 'no_answer';
    case ReportedPaid = 'reported_paid';
    case Disputed = 'disputed';
    case WrongContact = 'wrong_contact';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::MessageSent => 'Mensagem enviada',
            self::Promised => 'Prometeu pagar',
            self::Negotiating => 'Em negociação',
            self::NoAnswer => 'Não respondeu',
            self::ReportedPaid => 'Informou que já pagou',
            self::Disputed => 'Contestou a cobrança',
            self::WrongContact => 'Contato errado',
            self::Other => 'Outro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Promised, self::ReportedPaid => 'success',
            self::MessageSent, self::Negotiating => 'info',
            self::NoAnswer, self::Other => 'gray',
            self::Disputed, self::WrongContact => 'danger',
        };
    }
}
