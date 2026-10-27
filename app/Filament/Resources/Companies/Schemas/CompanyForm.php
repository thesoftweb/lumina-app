<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\City;
use App\Models\Company;
use App\Models\State;
use App\Rules\Cnpj;
use App\Services\Collections\CollectionMessage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Configurações da empresa')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Instituição')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Dados da instituição')
                                    ->description('Identificação e contato principal da escola.')
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
                            ]),
                        Tab::make('Documentos')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Section::make('Documentos e identidade visual')
                                    ->description('Logo e responsável que assina recibos, declarações e a ficha de matrícula.')
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
                                        TextInput::make('school_secretary_name')
                                            ->label('Secretário(a) escolar')
                                            ->placeholder('Ex.: João Pereira')
                                            ->helperText('Assina o histórico escolar junto com quem assina os documentos.')
                                            ->maxLength(255),
                                        TextInput::make('school_secretary_registration')
                                            ->label('Registro / autorização do secretário(a)')
                                            ->placeholder('Ex.: Portaria nº 123/2020')
                                            ->maxLength(255),
                                    ]),
                            ]),
                        Tab::make('Avaliação')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Avaliação escolar')
                                    ->description('Configure a média mínima e os conceitos disponíveis para turmas de avaliação conceitual.')
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('minimum_passing_grade')
                                            ->label('Média mínima')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(10)
                                            ->step(0.01)
                                            ->default(6)
                                            ->helperText('Média anual mínima para aprovação na disciplina, de 0 a 10.')
                                            ->required(),
                                        TextInput::make('minimum_attendance_percent')
                                            ->label('Frequência mínima')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->default(75)
                                            ->suffix('%')
                                            ->helperText('Abaixo dela o aluno é reprovado por frequência (LDB: 75%).')
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
                            ]),
                        Tab::make('Cobrança')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Multa e juros por atraso')
                                    ->description('Use os mesmos valores configurados no Asaas. Servem para estimar o valor atualizado das faturas vencidas; quem cobra é o Asaas.')
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('late_fine_percent')
                                            ->label('Multa')
                                            ->numeric()->minValue(0)->maxValue(100)
                                            ->suffix('%')
                                            ->helperText('Cobrada uma vez sobre o valor da fatura.'),
                                        TextInput::make('late_interest_monthly_percent')
                                            ->label('Juros ao mês')
                                            ->numeric()->minValue(0)->maxValue(100)
                                            ->suffix('% ao mês')
                                            ->helperText('Proporcionais aos dias de atraso.'),
                                    ]),
                                Section::make('Mensagem de cobrança')
                                    ->description('Texto enviado pelo WhatsApp na tela de Inadimplência. Deixe em branco para usar a mensagem padrão.')
                                    ->schema([
                                        Textarea::make('collection_message')
                                            ->label('Modelo da mensagem')
                                            ->rows(10)
                                            ->placeholder(CollectionMessage::DEFAULT_TEMPLATE)
                                            ->helperText(new HtmlString('Variáveis: '.collect(CollectionMessage::VARIABLES)
                                                ->map(fn (string $description, string $variable): string => '<code>'.e($variable).'</code> '.e($description))
                                                ->implode('; ').'.')),
                                    ]),
                            ]),
                        Tab::make('Integração financeira')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Section::make('Integração financeira')
                                    ->description('Credenciais cadastradas da empresa. A conexão ativa de cobranças é gerenciada na configuração do servidor.')
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
                            ]),
                    ]),
            ]);
    }
}
