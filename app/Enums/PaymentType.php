<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentType: string implements HasColor, HasLabel
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case BANK_TRANSFER = 'transfer';
    case CASH = 'cash';
    case CHECK = 'check';
    case OTHER = 'other';
    case PIX = 'pix';
    case BOLETO = 'boleto';
    case ONLINE_PAYMENT = 'online_payment';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::CREDIT_CARD => 'Cartão de Crédito',
            self::DEBIT_CARD => 'Cartão de Débito',
            self::BANK_TRANSFER => 'Transferência Bancária',
            self::CASH => 'Dinheiro',
            self::CHECK => 'Cheque',
            self::OTHER => 'Outro',
            self::PIX => 'PIX',
            self::BOLETO => 'Boleto',
            self::ONLINE_PAYMENT => 'Pagamento Online',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CREDIT_CARD => 'primary',
            self::DEBIT_CARD => 'success',
            self::BANK_TRANSFER => 'warning',
            self::CASH => 'info',
            self::CHECK => 'secondary',
            self::OTHER => 'gray',
            self::PIX => 'success',
            self::BOLETO => 'indigo',
            self::ONLINE_PAYMENT => 'purple',
        };
    }
}
