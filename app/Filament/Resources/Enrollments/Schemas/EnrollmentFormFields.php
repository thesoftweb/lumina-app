<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomPlan;
use App\Models\Enrollment;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Illuminate\Validation\ValidationException;

/** Campos da matrícula compartilhados pelo wizard de cadastro e pelas abas de edição. */
class EnrollmentFormFields
{
    public static function studentFields(): array
    {
        return [
            Select::make('student_id')->label('Aluno')->relationship('student', 'name')
                ->searchable()->preload()->default(request()->query('student_id'))->required()->live()
                ->rule(fn (callable $get, ?Enrollment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    if (self::blockingEnrollment($get, $record)) {
                        $fail(Enrollment::DUPLICATE_IN_YEAR_MESSAGE);
                    }
                }),
            Select::make('academic_year_id')->label('Ano letivo')
                ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                ->default(fn () => AcademicYear::query()->where('is_default', true)->value('id'))
                ->searchable()->required()->live(),
            Callout::make('Matrícula bloqueada')->danger()->columnSpanFull()
                ->description(fn (callable $get, ?Enrollment $record): ?string => ($enrollment = self::blockingEnrollment($get, $record)) ? sprintf(
                    '%s já possui matrícula "%s" na turma %s no ano letivo %s. Cada aluno só pode ter uma matrícula não cancelada por ano letivo: cancele a matrícula existente ou escolha outro ano letivo para continuar.',
                    $enrollment->student?->name, $enrollment->status->getLabel(),
                    $enrollment->classroom?->name ?? 'não informada', $enrollment->academicYear?->description,
                ) : null)
                ->visible(fn (callable $get, ?Enrollment $record): bool => (bool) self::blockingEnrollment($get, $record))
                ->actions([
                    Action::make('openBlockingEnrollment')->label('Abrir matrícula existente')->color('danger')
                        ->url(fn (callable $get, ?Enrollment $record): ?string => ($enrollment = self::blockingEnrollment($get, $record))
                            ? EnrollmentResource::getUrl('view', ['record' => $enrollment]) : null, shouldOpenInNewTab: true),
                ]),
        ];
    }

    /** Outra matrícula que impede este aluno de ser matriculado no ano letivo escolhido. */
    public static function blockingEnrollment(callable $get, ?Enrollment $record = null): ?Enrollment
    {
        if (blank($get('student_id')) || blank($get('academic_year_id'))
            || in_array($get('status'), [EnrollmentStatus::Canceled, EnrollmentStatus::Canceled->value], true)) {
            return null;
        }

        return Enrollment::query()->notCanceledInYear($get('student_id'), $get('academic_year_id'))
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->with(['student', 'classroom', 'academicYear'])->first();
    }

    public static function notifyIfBlocked(ValidationException $exception): void
    {
        if (! in_array(Enrollment::DUPLICATE_IN_YEAR_MESSAGE, $exception->errors()['data.student_id'] ?? [], true)) {
            return;
        }

        Notification::make()->danger()->title('Matrícula bloqueada')
            ->body('Este aluno já possui uma matrícula não cancelada neste ano letivo. Cancele a matrícula existente ou escolha outro ano letivo.')
            ->send();
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
        return collect(Enrollment::DOCUMENTS)
            ->map(fn (string $label, string $field): Checkbox => Checkbox::make($field)->label($label))
            ->values()->all();
    }
}
