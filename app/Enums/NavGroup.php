<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Grupos do menu lateral. A ordem dos cases define a ordem no menu.
 * Não definir ícone no grupo: o Filament não aceita ícone no grupo e nos itens ao mesmo tempo.
 */
enum NavGroup: string implements HasLabel
{
    case Classroom = 'Sala de aula';
    case Academic = 'Acadêmico';
    case Finance = 'Financeiro';
    case Registry = 'Cadastros';
    case Documents = 'Documentos';
    case Settings = 'Configurações';
    case Admin = 'Administração';

    public function getLabel(): string
    {
        return $this->value;
    }
}
