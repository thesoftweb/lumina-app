<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomPlan;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class EnrollmentWizardFields
{
    public static function studentFields(): array
    {
        return [
            Select::make('student_id')->label('Aluno')->relationship('student', 'name')
                ->searchable()->preload()->default(request()->query('student_id'))->required(),
            Select::make('academic_year_id')->label('Ano letivo')
                ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                ->default(fn () => AcademicYear::query()->where('is_default', true)->value('id'))
                ->searchable()->required(),
        ];
    }

    public static function classroomFields(): array
    {
        return [
            Select::make('classroom_id')->label('Turma')
                ->options(fn (): array => Classroom::query()->pluck('name', 'id')->all())
                ->searchable()->live()->afterStateUpdated(fn (callable $set) => $set('plan_id', null))->required(),
            DatePicker::make('enrollment_date')->label('Data da matrícula')->default(today())->required(),
            Select::make('status')->label('Situação da matrícula')
                ->options(EnrollmentStatus::class)->default(EnrollmentStatus::Active->value)->required(),
            Checkbox::make('new_student')->label('Primeira matrícula do aluno'),
        ];
    }

    public static function paymentFields(): array
    {
        return [
            Select::make('plan_id')->label('Plano de pagamento')
                ->options(fn (callable $get): array => ClassroomPlan::query()
                    ->where('classroom_id', $get('classroom_id'))->with('plan')
                    ->get()->pluck('plan.name', 'plan.id')->all())
                ->searchable()->required(),
            TextInput::make('day_of_payment')->label('Dia do vencimento')
                ->numeric()->minValue(1)->maxValue(31)->required(),
            Checkbox::make('enrollment_tax_paid')->label('Taxa de matrícula paga'),
            Checkbox::make('tuition_generated')->label('Mensalidades geradas'),
            Checkbox::make('use_custom_discount')->label('Usar desconto personalizado')->live()->columnSpanFull(),
            Select::make('discount_type')->label('Tipo de desconto')
                ->options(['percentage' => 'Percentual (%)', 'fixed' => 'Valor fixo (R$)'])
                ->visible(fn (callable $get) => $get('use_custom_discount'))
                ->required(fn (callable $get) => $get('use_custom_discount')),
            TextInput::make('discount_value')->label('Valor do desconto')->numeric()->minValue(0)
                ->visible(fn (callable $get) => $get('use_custom_discount'))
                ->required(fn (callable $get) => $get('use_custom_discount')),
            Textarea::make('discount_reason')->label('Motivo do desconto')->rows(3)->columnSpanFull()
                ->visible(fn (callable $get) => $get('use_custom_discount')),
        ];
    }

    public static function documentFields(): array
    {
        return [
            Checkbox::make('doc_historical_delivered')->label('Histórico escolar'),
            Checkbox::make('doc_photo_3x4_delivered')->label('Foto 3x4'),
            Checkbox::make('doc_declaration_delivered')->label('Declaração'),
            Checkbox::make('doc_residence_proof_delivered')->label('Comprovante de residência'),
            Checkbox::make('doc_student_document_delivered')->label('Documento do aluno'),
            Checkbox::make('doc_responsible_document_delivered')->label('Documento do responsável'),
        ];
    }
}
