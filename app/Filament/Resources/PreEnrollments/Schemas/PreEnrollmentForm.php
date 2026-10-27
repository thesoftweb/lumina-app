<?php

namespace App\Filament\Resources\PreEnrollments\Schemas;

use App\Enums\DegreeOfKinship;
use App\Enums\LeadSource;
use App\Enums\PreEnrollmentStatus;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\PreEnrollment;
use App\Models\User;
use App\Rules\Cpf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Cadastro e acompanhamento da ficha. O ano letivo é escolhido na criação e não muda depois:
 * a ficha (e a matrícula que sair dela) pertence a esse ano.
 */
class PreEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                Section::make('Ano letivo e interesse')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('academic_year_id')->label('Ano letivo')
                            ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                            ->default(fn (): ?int => PreEnrollmentResource::defaultAcademicYearId())
                            ->required()->live()
                            ->disabledOn('edit')
                            ->helperText('A ficha pertence a este ano e não pode ser movida para outro.')
                            ->afterStateUpdated(fn (Set $set) => $set('admission_offer_id', null)),
                        Select::make('admission_offer_id')->label('Série / vaga de interesse')
                            ->options(fn (Get $get): array => AdmissionOffer::query()
                                ->where('academic_year_id', $get('academic_year_id'))
                                ->pluck('name', 'id')->all())
                            ->searchable(),
                        Select::make('source')->label('Como conheceu a escola')->options(LeadSource::class),
                    ]),

                Section::make('Aluno')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->disabled(fn (?PreEnrollment $record): bool => (bool) $record?->isConverted())
                    ->schema([
                        TextInput::make('student_name')->label('Nome completo')->required()->maxLength(255)->columnSpanFull(),
                        DatePicker::make('student_birth_date')->label('Data de nascimento')->required()->maxDate(today()),
                        Select::make('student_gender')->label('Gênero')->options(['M' => 'Masculino', 'F' => 'Feminino', 'O' => 'Outro']),
                        TextInput::make('student_previous_school')->label('Escola atual / anterior')->maxLength(255)->columnSpanFull(),
                        Toggle::make('student_has_special_needs')->label('Tem necessidade específica')->live()->columnSpanFull(),
                        Textarea::make('student_special_needs_notes')->label('Detalhes')->rows(2)->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('student_has_special_needs')),
                    ]),

                Section::make('Responsável')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->disabled(fn (?PreEnrollment $record): bool => (bool) $record?->isConverted())
                    ->schema([
                        TextInput::make('guardian_name')->label('Nome completo')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('guardian_document')->label('CPF')
                            ->mask('999.999.999-99')->placeholder('000.000.000-00')
                            ->formatStateUsing(fn (?string $state): ?string => $state ? Cpf::format($state) : null)
                            ->rule(new Cpf)->required(),
                        Select::make('guardian_kinship')->label('Parentesco')
                            ->options(collect(DegreeOfKinship::cases())->mapWithKeys(fn (DegreeOfKinship $kinship): array => [$kinship->value => Str::ucfirst($kinship->value)])->all()),
                        TextInput::make('guardian_phone')->label('Telefone / WhatsApp')
                            ->mask('(99) 99999-9999')->placeholder('(00) 00000-0000')->tel()->required(),
                        TextInput::make('guardian_email')->label('E-mail')->email()->maxLength(255),
                        TextInput::make('postal_code')->label('CEP')->mask('99999-999')->maxLength(9),
                        TextInput::make('city_name')->label('Cidade')->maxLength(255),
                        TextInput::make('address')->label('Endereço')->maxLength(255),
                        TextInput::make('address_number')->label('Número')->maxLength(20),
                        TextInput::make('neighborhood')->label('Bairro')->maxLength(255)->columnSpanFull(),
                    ]),

                Section::make('Acompanhamento')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('status')->label('Situação')
                            ->options(fn (?PreEnrollment $record): array => collect(PreEnrollmentStatus::cases())
                                ->reject(fn (PreEnrollmentStatus $status): bool => $status === PreEnrollmentStatus::Converted && ! $record?->isConverted())
                                ->mapWithKeys(fn (PreEnrollmentStatus $status): array => [$status->value => $status->getLabel()])->all())
                            ->default(PreEnrollmentStatus::New->value)
                            ->disabled(fn (?PreEnrollment $record): bool => (bool) $record?->isConverted())
                            ->helperText('"Matriculada" só pela ação Converter em matrícula.')
                            ->required()->live(),
                        Select::make('assigned_to')->label('Responsável na secretaria')
                            ->options(fn (): array => User::query()->role(['secretary', 'admin'])->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable(),
                        TextInput::make('lost_reason')->label('Motivo da desistência')->maxLength(255)->columnSpanFull()
                            ->visible(fn (Get $get): bool => in_array($get('status'), [PreEnrollmentStatus::Lost, PreEnrollmentStatus::Lost->value], true))
                            ->required(fn (Get $get): bool => in_array($get('status'), [PreEnrollmentStatus::Lost, PreEnrollmentStatus::Lost->value], true)),
                        Textarea::make('message')->label('Mensagem da família')->rows(3)->columnSpanFull(),
                        Textarea::make('internal_notes')->label('Anotações internas')->rows(3)->columnSpanFull()
                            ->helperText('Não aparecem para a família.'),
                    ]),
            ]);
    }
}
