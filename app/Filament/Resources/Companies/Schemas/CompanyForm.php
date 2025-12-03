<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Company Name')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('asaas_key')
                    ->label('Asaas Key')
                    ->password()
                    ->dehydrated(fn($state) => filled($state))
                    ->columnSpanFull(),
                TextInput::make('environment')
                    ->label('Environment')
                    ->default('sandbox')
                    ->columnSpanFull(),
            ]);
    }
}
