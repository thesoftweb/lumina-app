<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Por onde a secretaria falou com o responsável na cobrança. */
enum CollectionChannel: string implements HasIcon, HasLabel
{
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Phone => 'Ligação',
            self::Email => 'E-mail',
            self::InPerson => 'Pessoalmente',
            self::Other => 'Outro',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::WhatsApp => 'heroicon-o-chat-bubble-left-right',
            self::Phone => 'heroicon-o-phone',
            self::Email => 'heroicon-o-envelope',
            self::InPerson => 'heroicon-o-user',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}
