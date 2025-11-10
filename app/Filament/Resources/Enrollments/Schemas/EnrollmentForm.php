<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Classroom;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
