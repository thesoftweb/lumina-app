<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informações do Usuário')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),
                        TextEntry::make('email')
                            ->label('Email'),
                    ]),
                Section::make('Roles')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge(),
                    ]),
            ]);
    }
}
