<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\ClassroomPlan;
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
                    ->options(AcademicYear::query()->where('is_default', true)->pluck('description', 'id'))
                    ->label('Ano Escolar')
                    ->columnSpanFull(),
                DatePicker::make('enrollment_date')
                    ->label('Data da Matrícula')
                    ->required(),
                Select::make('status')
                    ->label('Status da Matrícula')
                    ->options(EnrollmentStatus::class)
                    ->default('active')
                    ->required(),
                Select::make('student_id')
                    ->label('Aluno')
                    ->options(Student::all()->pluck('name', 'id')->toArray())
                    ->relationship('student', 'name')
                    ->required(),
                Select::make('classroom_id')
                    ->label('Turma')
                    ->live()
                    ->options(Classroom::all()->pluck('name', 'id')->toArray())
                    ->required(),
                Select::make('plan_id')
                    ->label('Plano de Pagamento')
                    ->live()
                    ->options(
                        fn(callable $get) =>
                        ClassroomPlan::where('classroom_id', $get('classroom_id'))
                            ->with('plan')
                            ->get()
                            ->pluck('plan.name', 'plan.id')
                            ->toArray()
                    )
                    ->required(),
                Select::make('day_of_payment')
                    ->label('Dia Vencimento')
                    ->required()
                    ->options([
                        '5' => 'Todo dia 05 de cada mês',
                        '10' => 'Todo dia 10 de cada mês',
                        '15' => 'Todo dia 15 de cada mês',
                    ])
            ]);
    }
}
