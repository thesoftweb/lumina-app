<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\DocumentTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuração do Documento')
                    ->description('Selecione o modelo e preencha os dados básicos')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('document_template_id')
                            ->label('Modelo de Documento')
                            ->options(DocumentTemplate::pluck('name', 'id'))
                            ->reactive()
                            ->live()
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Rascunho',
                                'generated' => 'Gerado',
                                'sent' => 'Enviado',
                            ])
                            ->default('draft'),
                    ]),

                Section::make('Dados do Cliente')
                    ->description('Preencha as informações do cliente')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Nome do Cliente')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('customer_email')
                            ->label('Email do Cliente')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->maxLength(255),

                        TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->maxLength(255),
                    ]),

                Section::make('Dados da Transação')
                    ->description('Informações da transação ou recibo')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('receipt_number')
                            ->label('Número do Recibo')
                            ->maxLength(255),

                        TextInput::make('document_number')
                            ->label('Número do Documento')
                            ->maxLength(255),

                        TextInput::make('amount')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$'),

                        DatePicker::make('date')
                            ->label('Data')
                            ->default(now()),
                    ]),

                Section::make('Dados da Empresa')
                    ->description('Informações da empresa emissora')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nome da Empresa')
                            ->maxLength(255),

                        TextInput::make('company_address')
                            ->label('Endereço da Empresa')
                            ->maxLength(255),
                    ]),

                Hidden::make('content'),
                Hidden::make('data'),
            ]);
    }
}
