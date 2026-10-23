<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\City;
use App\Models\Company;
use App\Models\State;
use App\Rules\Cnpj;
use Filament\Forms\Components\FileUpload;
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
                        TextInput::make('tax_id')
                            ->label('CNPJ')
                            ->mask('**.***.***/****-99')
                            ->placeholder('00.000.000/0000-00')
                            ->formatStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : $state)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null)
                            ->rule(new Cnpj)
                            ->unique(ignoreRecord: true),
                        TextInput::make('inep_code')
                            ->label('Código INEP')
                            ->mask('99999999')
                            ->placeholder('00000000')
                            ->regex('/^\d{8}$/')
                            ->validationMessages(['regex' => 'O código INEP tem 8 dígitos.'])
                            ->helperText('Código da escola no Censo Escolar. Aparece nas declarações.'),
                        TextInput::make('phone')
                            ->label('Telefone')
                            ->mask('(99) 99999-9999')
                            ->placeholder('(00) 00000-0000')
                            ->maxLength(15)
                            ->autocomplete('tel'),
                        TextInput::make('website')->label('Site')->url()->maxLength(255)->placeholder('https://'),
                    ]),
                Section::make('Endereço')
                    ->description('Sai no cabeçalho dos documentos; a cidade também é usada no local e data.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('postal_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->placeholder('00000-000')
                            ->maxLength(9),
                        Select::make('state')
                            ->label('Estado')
                            ->options(fn (): array => State::query()->orderBy('name')->pluck('name', 'code')->all())
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Select $component, ?Company $record) => $component->state($record?->city?->state_id))
                            ->afterStateUpdated(fn (callable $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label('Cidade')
                            ->options(function (callable $get): array {
                                $stateCode = $get('state');

                                if (blank($stateCode)) {
                                    return [];
                                }

                                return City::query()->where('state_id', $stateCode)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->disabled(fn (callable $get): bool => blank($get('state'))),
                        TextInput::make('address')
                            ->label('Rua / avenida')
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        TextInput::make('address_number')->label('Número')->maxLength(20),
                        TextInput::make('neighborhood')->label('Bairro')->maxLength(255),
                    ]),
                Section::make('Documentos e identidade visual')
                    ->description('Logo e responsável que assina recibos, declarações e a ficha de matrícula.')
                    ->icon('heroicon-o-document-check')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->disk(Company::LOGO_DISK)
                            ->directory(Company::LOGO_DIRECTORY)
                            ->visibility('public')
                            ->automaticallyResizeImagesMode('contain')
                            ->automaticallyResizeImagesToWidth('800')
                            ->automaticallyResizeImagesToHeight('800')
                            ->imagePreviewHeight('120')
                            ->helperText('PNG, JPG ou WebP de até 2 MB. Prefira fundo transparente ou branco.')
                            ->columnSpanFull(),
                        TextInput::make('document_signer_name')
                            ->label('Quem assina')
                            ->placeholder('Ex.: Maria da Silva')
                            ->maxLength(255)
                            ->requiredWith('document_signer_role'),
                        TextInput::make('document_signer_role')
                            ->label('Cargo')
                            ->placeholder('Ex.: Diretora, Secretária escolar')
                            ->maxLength(255)
                            ->requiredWith('document_signer_name'),
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
