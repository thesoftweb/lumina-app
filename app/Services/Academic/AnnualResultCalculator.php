<?php

namespace App\Services\Academic;

use App\Enums\AssessmentMode;
use App\Enums\TranscriptResult;
use App\Models\Attendance;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Term;

/**
 * Calcula a situação anual de uma matrícula.
 *
 * Notas: a média anual é a média dos bimestres do ano; com ela igual ou acima da média mínima da
 * escola, a disciplina está aprovada. Conceitos: vale o conceito do último bimestre, aprovado quando
 * o conceito é "resultado satisfatório". Frequência abaixo da mínima (LDB: 75%) reprova. Enquanto
 * faltar nota de algum bimestre, o ano está em andamento ("Cursando").
 */
class AnnualResultCalculator
{
    public function forEnrollment(Enrollment $enrollment, ?Company $company = null): AnnualResult
    {
        $enrollment->loadMissing(['student', 'classroom.level', 'classroom.classroomSubjects.subject', 'academicYear']);
        $company ??= Company::current(auth()->user());
        $mode = $enrollment->classroom->assessment_mode ?? AssessmentMode::Numeric;
        $minimumGrade = (float) ($company?->minimum_passing_grade ?? 6);
        $minimumAttendance = (float) ($company?->minimum_attendance_percent ?? 75);

        $terms = Term::query()
            ->when($enrollment->academicYear?->year, fn ($query, $year) => $query->where('year', $year))
            ->orderBy('order')
            ->get();

        $grades = $enrollment->grade()
            ->with('conceptScale')
            ->get()
            ->keyBy(fn (Grade $grade): string => $grade->classroom_subject_id.':'.$grade->term_id);

        $subjects = $enrollment->classroom->classroomSubjects
            ->map(fn (ClassroomSubject $assignment): SubjectResult => $this->subject($assignment, $terms, $grades, $mode, $minimumGrade))
            ->sortBy(fn (SubjectResult $subject): string => mb_strtolower($subject->name))
            ->values();

        $attendance = $this->attendance($enrollment);

        return new AnnualResult(
            enrollment: $enrollment,
            mode: $mode,
            terms: $terms,
            subjects: $subjects,
            attendance: $attendance,
            minimumGrade: $minimumGrade,
            minimumAttendance: $minimumAttendance,
            result: $this->result($subjects, $attendance, $minimumAttendance),
        );
    }

    private function subject(ClassroomSubject $assignment, $terms, $grades, AssessmentMode $mode, float $minimumGrade): SubjectResult
    {
        $termGrades = [];
        $numbers = [];
        $concepts = [];

        foreach ($terms as $term) {
            $grade = $grades->get($assignment->id.':'.$term->id);

            if ($mode === AssessmentMode::Numeric && $grade?->grade !== null) {
                $numbers[$term->id] = (float) $grade->grade;
                $termGrades[$term->id] = number_format((float) $grade->grade, 2, ',', '.');
            } elseif ($mode === AssessmentMode::Concept && $grade?->conceptScale) {
                $concepts[$term->id] = $grade->conceptScale;
                $termGrades[$term->id] = $grade->conceptScale->code;
            } else {
                $termGrades[$term->id] = null;
            }
        }

        $complete = $terms->isNotEmpty() && count($mode === AssessmentMode::Numeric ? $numbers : $concepts) === $terms->count();
        $average = $mode === AssessmentMode::Numeric && $complete ? round(array_sum($numbers) / count($numbers), 2) : null;
        $lastConcept = $mode === AssessmentMode::Concept && $complete ? $concepts[$terms->last()->id] : null;

        $status = match (true) {
            ! $complete => SubjectResult::PENDING,
            $mode === AssessmentMode::Numeric => $average >= $minimumGrade ? SubjectResult::APPROVED : SubjectResult::FAILED,
            default => $lastConcept->is_passing ? SubjectResult::APPROVED : SubjectResult::FAILED,
        };

        return new SubjectResult(
            assignmentId: $assignment->id,
            name: $assignment->subject->name,
            workload: $assignment->workload_hours,
            termGrades: $termGrades,
            average: $average,
            partialAverage: $numbers !== [] ? round(array_sum($numbers) / count($numbers), 2) : null,
            concept: $lastConcept?->code,
            status: $status,
        );
    }

    /** @return array{total: int, present: int, absences: int, justified: int, percentage: float|null} */
    private function attendance(Enrollment $enrollment): array
    {
        $year = $enrollment->academicYear;
        $records = Attendance::query()
            ->where('enrollment_id', $enrollment->id)
            ->when($year?->start_at, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($year?->end_at, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->get(['present', 'justified']);

        $total = $records->count();
        $present = $records->where('present', true)->count();
        $absences = $records->where('present', false);

        return [
            'total' => $total,
            'present' => $present,
            'absences' => $absences->count(),
            'justified' => $absences->where('justified', true)->count(),
            'percentage' => $total ? round($present / $total * 100, 1) : null,
        ];
    }

    private function result($subjects, array $attendance, float $minimumAttendance): TranscriptResult
    {
        return match (true) {
            $subjects->isEmpty() || $subjects->contains('status', SubjectResult::PENDING) => TranscriptResult::InProgress,
            $attendance['percentage'] !== null && $attendance['percentage'] < $minimumAttendance => TranscriptResult::FailedAttendance,
            $subjects->contains('status', SubjectResult::FAILED) => TranscriptResult::Failed,
            default => TranscriptResult::Approved,
        };
    }
}
