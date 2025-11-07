<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('Informações do Cliente')
                    ->description('Um cliente é representa um responsável financeiro de um ou mais alunos.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome Completo')
                                    ->prefixIcon('heroicon-o-user')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('document')
                                    ->label('CPF')
                                    ->required()
                                    ->unique()
                                    ->prefixIcon('heroicon-o-identification')
                                    ->mask('999.999.999-99')
                                    ->placeholder('999.999.999-99')
                                    ->maxLength(14),
                                TextInput::make('email')
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->label('E-mail')
                                    ->email(),
                                TextInput::make('phone')
                                    ->label('Telefone')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->mask('(99) 99999-9999')
                                    ->placeholder('(99) 99999-9999')
                                    ->maxLength(15),
                            ]),
                    ])
            ]);
    }
}
