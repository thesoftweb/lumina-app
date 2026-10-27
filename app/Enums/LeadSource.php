<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Como o responsável conheceu a escola, informado na ficha pública. */
enum LeadSource: string implements HasLabel
{
    case Referral = 'referral';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Google = 'google';
    case WhatsApp = 'whatsapp';
    case Walkin = 'walkin';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Referral => 'Indicação de amigo ou família',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Google => 'Pesquisa no Google',
            self::WhatsApp => 'WhatsApp',
            self::Walkin => 'Passei em frente à escola',
            self::Other => 'Outro',
        };
    }
}
