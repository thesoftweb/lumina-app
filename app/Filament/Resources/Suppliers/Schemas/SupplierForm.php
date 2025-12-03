<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações de Fornecedor')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Radio::make('document_type')
                            ->columnSpanFull()
                            ->label('Tipo de Cadastro')
                            ->required()
                            ->reactive()
                            ->options([
                                'F' => 'Pessoa Física',
                                'J' => 'Pessoa Jurídica'
                            ])
                            ->default('F')
                            ->inline(),
                        TextInput::make('document')
                            ->prefixIcon('heroicon-o-identification')
                            ->label(fn(Get $get): string => $get('document_type') === 'F' ? 'CPF' : 'CNPJ')
                            ->mask(fn(Get $get): string => $get('document_type') === 'F' ? '999.999.999-99' : '99.999.999/9999-99')
                            ->required(),
                        TextInput::make('name')
                            ->label('Nome')
                            ->required(),
                        TextInput::make('description')
                            ->label('Descrição')
                            ->helperText('Nome fantasia ou razão social do fornecedor')
                            ->required(),
                        TextInput::make('email')
                            ->label('E-mail'),
                        TextInput::make('phone')
                            ->mask('(99) 99999-9999')
                            ->label('Contato'),
                        Textarea::make('details')
                            ->columnSpanFull()
                            ->label('Observações')
                    ])
            ]);
    }
}
