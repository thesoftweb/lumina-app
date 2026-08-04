<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da instituição')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nome')->required(),
                        TextInput::make('email')->label('E-mail')->email()->required(),
                        TextInput::make('asaas_key')->label('Chave Asaas')->password()->dehydrated(fn ($state) => filled($state)),
                        TextInput::make('environment')->label('Ambiente')->default('sandbox'),
                    ]),
                Section::make('Avaliação escolar')
                    ->description('Configure a média mínima e os conceitos disponíveis para turmas de avaliação conceitual.')
                    ->schema([
                        TextInput::make('minimum_passing_grade')
                            ->label('Média mínima')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->step(0.01)
                            ->default(6)
                            ->required(),
                        Repeater::make('conceptScales')
                            ->label('Escala de conceitos')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->columns(5)
                            ->schema([
                                TextInput::make('code')->label('Código')->required()->maxLength(20)->distinct(),
                                TextInput::make('label')->label('Descrição')->required()->maxLength(255)->columnSpan(2),
                                Select::make('color')
                                    ->label('Cor')
                                    ->options([
                                        'success' => 'Verde',
                                        'info' => 'Azul',
                                        'warning' => 'Amarelo',
                                        'danger' => 'Vermelho',
                                        'gray' => 'Cinza',
                                    ])
                                    ->default('gray')
                                    ->required(),
                                Toggle::make('is_passing')->label('Satisfatório')->default(true),
                                Toggle::make('is_active')->label('Ativo')->default(true),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar conceito')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
