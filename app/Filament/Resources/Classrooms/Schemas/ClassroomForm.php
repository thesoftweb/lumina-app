<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use App\Enums\AssessmentMode;
use App\Models\AcademicYear;
use App\Models\Plan;
use App\Models\Subject;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Turmas')
                    ->description('Gerencie as turmas aqui.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->prefixIcon('heroicon-o-academic-cap')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255),
                        Select::make('academic_year_id')
                            ->prefixIcon('heroicon-o-calendar')
                            ->label('Ano letivo')
                            ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                            ->default(fn () => request()->query('academic_year_id'))
                            ->live()
                            ->helperText('Deixe em branco só para turmas antigas sem ano. Turmas de um ano novo (ex.: 2027) são cadastros novos, não edições das turmas atuais.'),
                        Select::make('assessment_mode')
                            ->label('Modo de avaliação')
                            ->options(collect(AssessmentMode::cases())->mapWithKeys(fn (AssessmentMode $mode): array => [$mode->value => $mode->label()]))
                            ->default(AssessmentMode::Numeric->value)
                            ->required()
                            ->helperText('Não poderá ser alterado depois que houver resultados lançados.'),
                        TextInput::make('grade_label')
                            ->prefixIcon('heroicon-o-bookmark')
                            ->label('Série / ano escolar')
                            ->placeholder('3º ano')
                            ->helperText('Como a série aparece no boletim e no histórico escolar.')
                            ->maxLength(60),
                        TextInput::make('school_days')
                            ->prefixIcon('heroicon-o-calendar-days')
                            ->label('Dias letivos no ano')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(366)
                            ->placeholder('200'),                        Select::make('level_id')
                            ->prefixIcon('heroicon-o-book-open')
                            ->options(function () {
                                return \App\Models\Level::all()->pluck('name', 'id');
                            })
                            ->createOptionForm(function (Schema $schema) {
                                return $schema->components([
                                    TextInput::make('name')
                                        ->prefixIcon('heroicon-o-academic-cap')
                                        ->label('Titulo')
                                        ->required()
                                        ->maxLength(255),
                                    Textarea::make('description')
                                        ->label('Detalhes')
                                        ->maxLength(65535),
                                ]);
                            })
                            ->label('Nivel')
                            ->relationship('level', 'name')
                            ->required(),
                        Select::make('teacher_ids')
                            ->options(function () {
                                return \App\Models\Teacher::all()->pluck('name', 'id');
                            })
                            ->prefixIcon('heroicon-o-user-group')
                            ->label('Docentes')
                            ->preload()
                            ->multiple()
                            ->relationship('teachers', 'name'),
                        Select::make('plan_ids')
                            ->options(function () {
                                return Plan::query()->where('active', true)->pluck('name', 'id');
                            })
                            ->prefixIcon('heroicon-o-credit-card')
                            ->label('Plano de Pagamentos')
                            ->preload()
                            ->multiple()
                            ->required()
                            ->relationship('plans', 'name', fn ($query, Get $get) => $query->forAcademicYear($get('academic_year_id')))
                            ->helperText('Só aparecem os planos do ano letivo da turma e os planos antigos sem ano.'),
                        TextInput::make('whatsapp_group')
                            ->prefixIcon('heroicon-o-chat-bubble-left-right')
                            ->helperText('Enviado automaticamente após matricula.')
                            ->label('Grupo do WhatsApp')
                            ->url(),
                        Fieldset::make('Disciplinas')
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                CheckboxList::make('subject_ids')
                                    ->columnSpanFull()
                                    ->label('Disciplinas')
                                    ->relationship('subjects', 'name')
                                    ->columns(2)
                                    ->options(Subject::all()->pluck('name', 'id')),
                            ]),
                    ]),
            ]);
    }
}
