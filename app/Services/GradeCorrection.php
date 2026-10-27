<?php

namespace App\Services;

use App\Enums\AssessmentMode;
use App\Models\ClassroomSubject;
use App\Models\ConceptScale;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lançamento e correção de nota pela secretaria (vale também para bimestre fechado). Cada alteração
 * fica registrada em grade_changes com o antes, o depois e o motivo.
 */
class GradeCorrection
{
    public const FIELDS = ['grade_1', 'grade_2', 'recovery_grade', 'concept_scale_id', 'comments'];

    public function __construct(private GradeCalculationService $calculator) {}

    /**
     * @param  array<string, mixed>  $values  grade_1, grade_2, recovery_grade (numérico) ou concept_scale_id (conceito); comments
     */
    public function save(Enrollment $enrollment, ClassroomSubject $assignment, Term $term, array $values, User $user, string $reason): Grade
    {
        if ($assignment->classroom_id !== $enrollment->classroom_id) {
            throw ValidationException::withMessages(['classroom_subject_id' => 'Esta disciplina não é da turma da matrícula.']);
        }

        if (! $enrollment->acceptsTerm($term)) {
            throw ValidationException::withMessages(['term_id' => 'O bimestre não pertence ao ano letivo desta matrícula.']);
        }

        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo da correção.']);
        }

        $mode = $enrollment->classroom->assessment_mode ?? AssessmentMode::Numeric;

        return DB::transaction(function () use ($enrollment, $assignment, $term, $values, $user, $reason, $mode): Grade {
            $grade = Grade::query()->lockForUpdate()->firstOrNew([
                'enrollment_id' => $enrollment->id,
                'classroom_subject_id' => $assignment->id,
                'term_id' => $term->id,
            ]);
            $before = $grade->exists ? $this->snapshot($grade) : null;

            if ($mode === AssessmentMode::Numeric) {
                foreach (['grade_1', 'grade_2', 'recovery_grade'] as $field) {
                    $value = $values[$field] ?? null;

                    if (filled($value) && (! is_numeric($value) || $value < 0 || $value > 10)) {
                        throw ValidationException::withMessages([$field => 'A nota deve estar entre 0 e 10.']);
                    }

                    $grade->{$field} = filled($value) ? $value : null;
                }

                $grade->concept_scale_id = null;
                $grade->grade = $this->calculator->calculate($grade->grade_1, $grade->grade_2, $grade->recovery_grade);
            } else {
                $conceptId = $values['concept_scale_id'] ?? null;

                if (filled($conceptId) && ! ConceptScale::query()->whereKey($conceptId)->exists()) {
                    throw ValidationException::withMessages(['concept_scale_id' => 'Conceito inválido.']);
                }

                $grade->fill(['grade' => null, 'grade_1' => null, 'grade_2' => null, 'recovery_grade' => null, 'concept_scale_id' => filled($conceptId) ? $conceptId : null]);
            }

            if (array_key_exists('comments', $values)) {
                $grade->comments = filled($values['comments']) ? $values['comments'] : null;
            }

            $grade->save();
            $after = $this->snapshot($grade);

            if ($before !== $after) {
                GradeChange::create([
                    'grade_id' => $grade->id,
                    'enrollment_id' => $enrollment->id,
                    'classroom_subject_id' => $assignment->id,
                    'term_id' => $term->id,
                    'user_id' => $user->id,
                    'before' => $before,
                    'after' => $after,
                    'reason' => trim($reason),
                    'created_at' => now(),
                ]);
            }

            return $grade;
        });
    }

    /** @return array<string, string|int|null> */
    private function snapshot(Grade $grade): array
    {
        return collect(['grade', ...self::FIELDS])
            ->mapWithKeys(fn (string $field): array => [$field => $grade->{$field} === null ? null : (is_numeric($grade->{$field}) && $field !== 'concept_scale_id' ? number_format((float) $grade->{$field}, 2, '.', '') : $grade->{$field})])
            ->all();
    }
}
