<?php

namespace App\Services\Academic;

use App\Enums\AssessmentMode;
use App\Enums\TranscriptResult;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Support\Collection;

/** Situação anual de uma matrícula: disciplinas, frequência e resultado pelas regras da escola. */
final class AnnualResult
{
    /**
     * @param  Collection<int, Term>  $terms
     * @param  Collection<int, SubjectResult>  $subjects
     * @param  array{total: int, present: int, absences: int, justified: int, percentage: float|null}  $attendance
     */
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly AssessmentMode $mode,
        public readonly Collection $terms,
        public readonly Collection $subjects,
        public readonly array $attendance,
        public readonly float $minimumGrade,
        public readonly float $minimumAttendance,
        public readonly TranscriptResult $result,
    ) {}

    /** Disciplinas ainda sem resultado (falta nota de algum bimestre). */
    public function pendingSubjects(): Collection
    {
        return $this->subjects->where('status', SubjectResult::PENDING)->values();
    }

    public function totalWorkload(): ?int
    {
        $total = (int) $this->subjects->sum(fn (SubjectResult $subject): int => (int) $subject->workload);

        return $total > 0 ? $total : null;
    }

    /** "Média mínima 6,0 · frequência mínima 75%" */
    public function rulesLabel(): string
    {
        return collect([
            $this->mode === AssessmentMode::Numeric ? 'Média mínima '.number_format($this->minimumGrade, 1, ',', '.') : 'Aprovação pelo conceito final',
            'frequência mínima '.rtrim(rtrim(number_format($this->minimumAttendance, 2, ',', ''), '0'), ',').'%',
        ])->implode(' · ');
    }
}
