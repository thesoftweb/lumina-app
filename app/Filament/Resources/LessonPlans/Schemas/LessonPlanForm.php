<?php

namespace App\Filament\Resources\LessonPlans\Schemas;

use App\Enums\LessonPlanStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LessonPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();
        $isTeacher = $user->hasRole('teacher');

        return $schema
            ->components([
                Section::make('Informações Básicas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->label('Título da Aula')
                            ->columnSpanFull(),

                        Select::make('classroom_id')
                            ->relationship(
                                'classroom',
                                'name',
                                fn($query) => $isTeacher && $user->teacher
                                    ? $query->whereHas('teachers', fn($q) => $q->where('teacher_id', $user->teacher->id))
                                    : $query
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('subject_id')
                            ->relationship('subject', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('term_id')
                            ->relationship('term', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('teacher_id')
                            ->relationship('teacher', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->hidden($isTeacher)
                            ->default($isTeacher ? $user->teacher?->id : null),

                        DatePicker::make('scheduled_date')
                            ->required()
                            ->label('Data Agendada'),

                        TextInput::make('duration_minutes')
                            ->numeric()
                            ->label('Duração (minutos)')
                            ->default(50),
                    ]),

                Section::make('Conteúdo da Aula')
                    ->schema([
                        RichEditor::make('description')
                            ->required()
                            ->columnSpanFull()
                            ->label('Descrição'),

                        RichEditor::make('objectives')
                            ->required()
                            ->columnSpanFull()
                            ->label('Objetivos de Aprendizado'),

                        RichEditor::make('methodology')
                            ->columnSpanFull()
                            ->label('Metodologia'),

                        Textarea::make('resources')
                            ->columnSpanFull()
                            ->label('Recursos Necessários'),
                    ]),

                Select::make('status')
                    ->options(LessonPlanStatus::class)
                    ->default(LessonPlanStatus::Draft)
                    ->label('Status'),
            ]);
    }
}
                    ])
                    ->default('draft')
                    ->label('Status'),
            ]);
    }
}
