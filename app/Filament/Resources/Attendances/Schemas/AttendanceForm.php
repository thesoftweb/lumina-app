<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();
        $isTeacher = $user->hasRole('teacher');

        return $schema
            ->components([
                Section::make('Informações da Frequência')
                    ->columns(2)
                    ->schema([
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

                        Select::make('enrollment_id')
                            ->relationship(
                                'enrollment',
                                'id',
                                fn($query) => $isTeacher && $user->teacher
                                    ? $query->whereHas(
                                        'classroom',
                                        fn($q) =>
                                        $q->whereHas('teachers', fn($t) => $t->where('teacher_id', $user->teacher->id))
                                    )
                                    : $query
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Aluno')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->student->name ?? 'N/A'),

                        DatePicker::make('date')
                            ->required()
                            ->label('Data da Aula'),

                        Select::make('teacher_id')
                            ->relationship('teacher', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->hidden($isTeacher)
                            ->default($isTeacher ? $user->teacher?->id : null),
                    ]),

                Section::make('Status de Presença')
                    ->schema([
                        Checkbox::make('present')
                            ->label('Presente')
                            ->default(true),

                        Checkbox::make('justified')
                            ->label('Ausência Justificada')
                            ->visible(fn($get) => !$get('present')),

                        Textarea::make('justification')
                            ->label('Justificativa')
                            ->visible(fn($get) => !$get('present') && $get('justified')),
                    ]),
            ]);
    }
}
