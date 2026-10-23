<?php

namespace App\Services;

use App\Enums\AssessmentMode;
use App\Models\Company;
use App\Models\Enrollment;
use App\Services\Academic\AnnualResultCalculator;
use App\Services\Academic\SubjectResult;

/** Dados do boletim: notas por bimestre, média anual, situação de cada disciplina, frequência e resultado. */
class ReportCardService
{
    public function __construct(private AnnualResultCalculator $calculator) {}

    public function forEnrollment(Enrollment $enrollment): array
    {
        $company = Company::current(auth()->user());
        $annual = $this->calculator->forEnrollment($enrollment, $company);
        $grades = $enrollment->grade()->with('conceptScale')->get()
            ->keyBy(fn ($grade) => $grade->classroom_subject_id.':'.$grade->term_id);

        $subjects = $annual->subjects->map(fn (SubjectResult $subject): array => [
            'name' => $subject->name,
            // Valor lançado em cada bimestre (nota ou código do conceito)
            'grades' => $annual->terms->mapWithKeys(function ($term) use ($grades, $subject): array {
                $grade = $grades->get($subject->assignmentId.':'.$term->id);

                return [$term->id => $grade?->grade ?? $grade?->conceptScale?->code];
            })->all(),
            'annual_average' => $subject->average,
            'final' => $subject->finalLabel(),
            'status' => $subject->status,
            'status_label' => $subject->statusLabel(),
            'workload' => $subject->workload,
        ]);

        return [
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'classroom' => $enrollment->classroom,
            'academicYear' => $enrollment->academicYear,
            'assessmentMode' => $annual->mode ?? AssessmentMode::Numeric,
            'terms' => $annual->terms,
            'subjects' => $subjects,
            'attendance' => [
                'total' => $annual->attendance['total'],
                'absences' => $annual->attendance['absences'],
                'justified' => $annual->attendance['justified'],
                'percentage' => $annual->attendance['percentage'],
            ],
            'annual' => $annual,
            'company' => $company,
        ];
    }
}
