<?php

namespace App\Services;

use App\Enums\AssessmentMode;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Term;

class ReportCardService
{
    public function forEnrollment(Enrollment $enrollment): array
    {
        $enrollment->load(['student', 'classroom.classroomSubjects.subject', 'classroom.level', 'academicYear']);
        $academicYear = $enrollment->academicYear;
        $terms = Term::query()
            ->when($academicYear?->year, fn ($query, $year) => $query->where('year', $year))
            ->orderBy('order')
            ->get();

        $grades = $enrollment->grade()
            ->with(['classroomSubject.subject', 'term', 'conceptScale'])
            ->get()
            ->keyBy(fn ($grade) => $grade->classroom_subject_id.':'.$grade->term_id);
        $mode = $enrollment->classroom->assessment_mode ?? AssessmentMode::Numeric;

        $subjects = $enrollment->classroom->classroomSubjects
            ->map(function ($assignment) use ($grades, $mode, $terms): array {
                $termGrades = $terms->mapWithKeys(function ($term) use ($assignment, $grades): array {
                    $grade = $grades->get($assignment->id.':'.$term->id);

                    return [$term->id => $grade?->grade ?? $grade?->conceptScale?->code];
                })->all();
                $numericGrades = collect($termGrades)->filter(fn ($grade) => is_numeric($grade));
                $annualAverage = $mode === AssessmentMode::Numeric
                    && $terms->isNotEmpty()
                    && $numericGrades->count() === $terms->count()
                        ? round($numericGrades->average(), 2)
                        : null;

                return [
                    'name' => $assignment->subject->name,
                    'grades' => $termGrades,
                    'annual_average' => $annualAverage,
                ];
            })
            ->sortBy('name')
            ->values();

        $attendance = Attendance::query()
            ->where('enrollment_id', $enrollment->id)
            ->when($academicYear?->start_at, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($academicYear?->end_at, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->get();
        $total = $attendance->count();
        $absences = $attendance->where('present', false);

        return [
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'classroom' => $enrollment->classroom,
            'academicYear' => $academicYear,
            'assessmentMode' => $mode,
            'terms' => $terms,
            'subjects' => $subjects,
            'attendance' => [
                'total' => $total,
                'absences' => $absences->count(),
                'justified' => $absences->where('justified', true)->count(),
                'percentage' => $total ? round(($attendance->where('present', true)->count() / $total) * 100, 1) : null,
            ],
        ];
    }
}
