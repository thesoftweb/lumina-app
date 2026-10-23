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
            ->columns(1)
            ->components([
                Section::make('Dados da instituição')
                    ->description('Identificação e contato principal da escola.')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('name')->label('Nome da instituição')->required()->maxLength(255)->autocomplete('organization'),
                        TextInput::make('email')->label('E-mail institucional')->email()->required()->maxLength(255)->unique(ignoreRecord: true)->autocomplete('email'),
                    ]),
                Section::make('Avaliação escolar')
                    ->description('Configure a média mínima e os conceitos disponíveis para turmas de avaliação conceitual.')
                    ->icon('heroicon-o-academic-cap')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('minimum_passing_grade')
                            ->label('Média mínima')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->step(0.01)
                            ->default(6)
                            ->helperText('Nota de referência para avaliação numérica, de 0 a 10.')
                            ->required(),
                        Repeater::make('conceptScales')
                            ->label('Escala de conceitos')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->helperText('Arraste para ordenar. Desative um conceito para preservá-lo no histórico.')
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                            ->itemLabel(fn (array $state): string => filled($state['code'] ?? null) ? $state['code'].' — '.($state['label'] ?? 'Novo conceito') : 'Novo conceito')
                            ->collapsible()
                            ->schema([
                                TextInput::make('code')->label('Código')->required()->maxLength(20)->distinct(),
                                TextInput::make('label')->label('Descrição')->required()->maxLength(255)->columnSpan(['default' => 1, 'md' => 1, 'xl' => 2]),
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
                                Toggle::make('is_passing')->label('Resultado satisfatório')->default(true),
                                Toggle::make('is_active')->label('Disponível para lançamento')->default(true),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar conceito')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Integração financeira')
                    ->description('Credenciais cadastradas da empresa. A conexão ativa de cobranças é gerenciada na configuração do servidor.')
                    ->icon('heroicon-o-credit-card')
                    ->collapsible()
                    ->collapsed()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('environment')
                            ->label('Ambiente Asaas')
                            ->options(['sandbox' => 'Testes (sandbox)', 'production' => 'Produção'])
                            ->default('sandbox')->required()->native(false),
                        TextInput::make('asaas_key')
                            ->label('Chave de acesso Asaas')->password()->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('Digite para substituir a chave')
                            ->helperText('Deixe em branco para manter a chave já cadastrada.')
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),
            ]);
    }
}
