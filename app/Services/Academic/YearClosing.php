<?php

namespace App\Services\Academic;

use App\Enums\EnrollmentStatus;
use App\Enums\TranscriptResult;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Enrollment;
use App\Models\TranscriptYear;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fechamento do ano letivo: grava o resultado de cada matrícula no histórico escolar do aluno
 * (transcript_years), com as disciplinas, médias ou conceitos, carga horária e frequência do ano.
 *
 * Fechar de novo recalcula notas e frequência; um resultado ajustado manualmente (conselho de
 * classe) é mantido.
 */
class YearClosing
{
    public function __construct(private AnnualResultCalculator $calculator) {}

    /**
     * Grava o ano da matrícula. Sem resultado informado, vale o calculado; um resultado diferente do
     * calculado exige motivo. Ano em andamento só é gravado com resultado informado (ex.: Transferido).
     */
    public function closeEnrollment(Enrollment $enrollment, User $user, ?TranscriptResult $result = null, ?string $reason = null): TranscriptYear
    {
        $enrollment->loadMissing(['academicYear', 'classroom', 'student']);

        if (! $enrollment->academicYear) {
            throw ValidationException::withMessages(['enrollment' => 'A matrícula não tem ano letivo; informe o ano letivo antes de fechar.']);
        }

        $company = Company::current($user);
        $annual = $this->calculator->forEnrollment($enrollment, $company);
        $existing = TranscriptYear::query()->where('enrollment_id', $enrollment->id)->first();

        // Ajuste anterior (conselho de classe) continua valendo ao fechar de novo
        if ($result === null && $existing?->isAdjusted()) {
            $result = $existing->result;
            $reason = $existing->adjustment_reason;
        }

        $result ??= $annual->result;

        if ($result === TranscriptResult::InProgress && $annual->result === TranscriptResult::InProgress) {
            throw ValidationException::withMessages([
                'enrollment' => 'Faltam notas em: '.$annual->pendingSubjects()->pluck('name')->implode(', ').'.',
            ]);
        }

        if ($result !== $annual->result && blank($reason)) {
            throw ValidationException::withMessages(['adjustment_reason' => 'Informe o motivo do resultado diferente do calculado ('.$annual->result->getLabel().').']);
        }

        return DB::transaction(function () use ($enrollment, $user, $company, $annual, $result, $reason, $existing): TranscriptYear {
            $year = $existing ?? new TranscriptYear(['enrollment_id' => $enrollment->id]);
            $year->fill([
                'student_id' => $enrollment->student_id,
                'source' => TranscriptYear::INTERNAL,
                'year' => $enrollment->academicYear->year,
                'grade_label' => $enrollment->classroom->grade_label ?: $enrollment->classroom->name,
                'school_name' => $company?->name ?? 'Esta escola',
                'school_city' => $company?->city?->name,
                'school_state' => $company?->city?->state?->abbreviation,
                'assessment_mode' => $annual->mode,
                'attendance_percent' => $annual->attendance['percentage'],
                'school_days' => $enrollment->classroom->school_days,
                'total_workload' => $annual->totalWorkload(),
                'result' => $result,
                'computed_result' => $annual->result,
                'adjustment_reason' => $result !== $annual->result ? trim((string) $reason) : null,
                'recorded_by' => $user->id,
            ])->save();

            $year->subjects()->delete();
            $annual->subjects->values()->each(fn (SubjectResult $subject, int $index) => $year->subjects()->create([
                'name' => $subject->name,
                'final_grade' => $subject->average ?? $subject->partialAverage,
                'concept' => $subject->concept,
                'workload_hours' => $subject->workload,
                'result' => $subject->status,
                'sort_order' => $index,
            ]));

            return $year->refresh();
        });
    }

    /**
     * Fecha o ano de todas as matrículas da turma no ano letivo. Se faltar nota de alguém, nada é
     * gravado e a lista de pendências volta na exceção.
     *
     * @return Collection<int, TranscriptYear>
     */
    public function closeClassroom(Classroom $classroom, AcademicYear $academicYear, User $user): Collection
    {
        $enrollments = $this->enrollments($classroom, $academicYear);

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages(['classroom' => 'Nenhuma matrícula ativa nesta turma e ano letivo.']);
        }

        $pending = $this->pending($enrollments, $user);

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'classroom' => 'Faltam notas: '.$pending->take(10)->map(fn (array $item): string => $item['student'].' ('.$item['subjects'].')')->implode('; ')
                    .($pending->count() > 10 ? ' e mais '.($pending->count() - 10).' aluno(s).' : '.'),
            ]);
        }

        return DB::transaction(fn (): Collection => $enrollments->map(fn (Enrollment $enrollment): TranscriptYear => $this->closeEnrollment($enrollment, $user)));
    }

    /**
     * Prévia do fechamento: quantos alunos em cada resultado e quem tem nota faltando.
     *
     * @return array{total: int, results: array<string, int>, pending: Collection<int, array{student: string, subjects: string}>}
     */
    public function preview(Classroom $classroom, AcademicYear $academicYear, User $user): array
    {
        $enrollments = $this->enrollments($classroom, $academicYear);
        $company = Company::current($user);
        $results = $enrollments->map(fn (Enrollment $enrollment): TranscriptResult => $this->calculator->forEnrollment($enrollment, $company)->result);

        return [
            'total' => $enrollments->count(),
            'results' => $results->countBy(fn (TranscriptResult $result): string => $result->getLabel())->all(),
            'pending' => $this->pending($enrollments, $user),
        ];
    }

    /** @return Collection<int, Enrollment> */
    private function enrollments(Classroom $classroom, AcademicYear $academicYear): Collection
    {
        return Enrollment::query()
            ->with(['student', 'classroom', 'academicYear'])
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->get()
            ->sortBy('student.name')
            ->values();
    }

    /** @return Collection<int, array{student: string, subjects: string}> */
    private function pending(Collection $enrollments, User $user): Collection
    {
        $company = Company::current($user);

        return $enrollments
            ->map(fn (Enrollment $enrollment): array => [
                'student' => $enrollment->student->name,
                'subjects' => $this->calculator->forEnrollment($enrollment, $company)->pendingSubjects()->pluck('name')->implode(', '),
            ])
            ->filter(fn (array $item): bool => $item['subjects'] !== '')
            ->values();
    }
}
