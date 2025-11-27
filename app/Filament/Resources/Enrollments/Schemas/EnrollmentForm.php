<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\AcademicYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year')
                    ->options(AcademicYear::query()->where('is_default',true)->pluck('description','id'))
                    ->label('Ano Escolar')
                    ->columnSpanFull(),
                DatePicker::make('enrollment_date')
                    ->label('Data da Matrícula')
                    ->required(),
                Select::make('status')
                    ->label('Status da Matrícula')
                    ->options([
                        'active' => 'Ativa',
                        'started' => 'Iniciada',
                        'canceled' => 'Cancelada',
                        'completed' => 'Concluída',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('student_id')
                    ->label('Aluno')
                    ->options(Student::all()->pluck('name', 'id')->toArray())
                    ->relationship('student', 'name')
                    ->required(),
                Select::make('classroom_id')
                    ->label('Turma')
                    ->options(Classroom::all()->pluck('name', 'id')->toArray())
                    ->relationship('classroom', 'name')
                    ->required(),
            ]);
    }
}
