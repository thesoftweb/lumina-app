<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
                Section::make('Informações Bancárias')
                    ->description('Preencha os dados bancários para pagamento.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('account_type')
                                    ->prefixIcon('heroicon-o-building-library')
                                    ->label('Tipo de Conta')
                                    ->options([
                                        'corrente' => 'Conta Corrente',
                                        'poupanca' => 'Conta Poupança',
                                    ])
                                    ->nullable(),
                                TextInput::make('bank_name')
                                    ->prefixIcon('heroicon-o-building-library')
                                    ->label('Nome do Banco')
                                    ->nullable()
                                    ->maxLength(255),
                                TextInput::make('bank_code')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->label('Código do Banco')
                                    ->nullable()
                                    ->mask('999')
                                    ->maxLength(3),
                                TextInput::make('agency_number')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->label('Agência')
                                    ->nullable()
                                    ->maxLength(10),
                                TextInput::make('account_number')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->label('Número da Conta')
                                    ->nullable()
                                    ->maxLength(20),
                                TextInput::make('account_holder_name')
                                    ->prefixIcon('heroicon-o-user')
                                    ->label('Titular da Conta')
                                    ->nullable()
                                    ->maxLength(255),
                            ]),
                    ]),
                Section::make('Dados PIX')
                    ->description('Preencha os dados PIX para recebimento.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('pix_key_type')
                                    ->prefixIcon('heroicon-o-key')
                                    ->label('Tipo de Chave PIX')
                                    ->options([
                                        'cpf' => 'CPF',
                                        'email' => 'Email',
                                        'phone' => 'Telefone',
                                        'random' => 'Chave Aleatória',
                                    ])
                                    ->nullable(),
                                TextInput::make('pix_key')
                                    ->prefixIcon('heroicon-o-key')
                                    ->label('Chave PIX')
                                    ->nullable()
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
