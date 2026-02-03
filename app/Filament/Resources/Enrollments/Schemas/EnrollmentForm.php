<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\ClassroomPlan;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(9)
                            ->schema([
                                DatePicker::make('enrollment_date')
                                    ->label('Data da Matrícula')
                                    ->required(),
                                Select::make('status')
                                    ->label('Situação da Matrícula')
                                    ->options(EnrollmentStatus::class)
                                    ->default('active')
                                    ->required(),
                                Select::make('student_id')
                                    ->label('Aluno')
                                    ->options(Student::all()->pluck('name', 'id')->toArray())
                                    ->relationship('student', 'name')
                                    ->default(request()->query('student_id'))
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
                                TextInput::make('day_of_payment')
                                    ->required()
                                    ->numeric()
                                    ->label('Dia Vencimento')
                                    ->required(),
                            ]),
                        Section::make()
                            ->columnSpan(3)
                            ->schema([
                                Checkbox::make('new_student')
                                    ->label('Novo Aluno')
                                    ->helperText('Primeira matrícula deste aluno na instituição'),
                                Checkbox::make('enrollment_tax_paid')
                                    ->label('Taxa de Matrícula Paga')
                                    ->helperText('Taxa de matrícula já foi paga para este aluno'),
                                Checkbox::make('tuition_generated')
                                    ->label('Mensalidades Geradas')
                                    ->helperText('Mensalidades já foram geradas para este aluno'),
                                Checkbox::make('use_custom_discount')
                                    ->label('Desconto Personalizado')
                                    ->helperText('Marque para usar desconto diferente do plano')
                                    ->live(),
                                Select::make('discount_type')
                                    ->label('Tipo de Desconto')
                                    ->options([
                                        'percentage' => 'Percentual (%)',
                                        'fixed' => 'Valor Fixo (R$)',
                                    ])
                                    ->visible(fn(callable $get) => $get('use_custom_discount'))
                                    ->required(fn(callable $get) => $get('use_custom_discount')),
                                TextInput::make('discount_value')
                                    ->label('Valor do Desconto')
                                    ->numeric()
                                    ->visible(fn(callable $get) => $get('use_custom_discount'))
                                    ->required(fn(callable $get) => $get('use_custom_discount')),
                                Textarea::make('discount_reason')
                                    ->label('Motivo do Desconto')
                                    ->rows(3)
                                    ->visible(fn(callable $get) => $get('use_custom_discount')),
                            ]),
                    ]),
                Section::make()
                    ->heading('Documentação Entregue')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Checkbox::make('doc_historical_delivered')
                                    ->label('Histórico Escolar'),
                                Checkbox::make('doc_photo_3x4_delivered')
                                    ->label('Foto 3x4'),
                                Checkbox::make('doc_declaration_delivered')
                                    ->label('Declaração'),
                                Checkbox::make('doc_residence_proof_delivered')
                                    ->label('Comprovante de Residência'),
                                Checkbox::make('doc_student_document_delivered')
                                    ->label('Documento Aluno'),
                                Checkbox::make('doc_responsible_document_delivered')
                                    ->label('Documento Responsável'),
                            ]),
                    ]),
            ]);
    }
}
