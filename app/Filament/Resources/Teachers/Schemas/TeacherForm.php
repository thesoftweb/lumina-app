<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Pessoais do Professor')
                    ->description('Preencha os dados pessoais do professor.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->prefixIcon('heroicon-o-user')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('date_of_birth')
                                    ->prefixIcon('heroicon-o-cake')
                                    ->label('Data de Nascimento')
                                    ->nullable(),
                                TextInput::make('email')
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->label('Email')
                                    ->email()
                                    ->nullable()
                                    ->unique(table: 'teachers', column: 'email', ignorable: fn($record) => $record)
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->label('Telefone')
                                    ->mask('(99) 99999-9999')
                                    ->nullable()
                                    ->maxLength(20),
                                TextInput::make('document_number')
                                    ->prefixIcon('heroicon-o-identification')
                                    ->label('CPF / Documento')
                                    ->nullable()
                                    ->mask('999.999.999-99')
                                    ->unique(table: 'teachers', column: 'document_number', ignorable: fn($record) => $record)
                                    ->maxLength(100),
                            ]),
                    ]),
            ]);
    }
}
