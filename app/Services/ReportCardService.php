<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Term;

class ReportCardService
{
    public function forEnrollment(Enrollment $enrollment): array
    {
        $enrollment->load(['student', 'classroom.classroomSubjects.subject', 'academicYear']);
        $academicYear = $enrollment->academicYear;
        $terms = Term::query()
            ->when($academicYear?->year, fn ($query, $year) => $query->where('year', $year))
            ->orderBy('order')
            ->get();

        $grades = $enrollment->grade()
            ->with(['classroomSubject.subject', 'term'])
            ->get()
            ->keyBy(fn ($grade) => $grade->classroom_subject_id.':'.$grade->term_id);

        $subjects = $enrollment->classroom->classroomSubjects
            ->map(fn ($assignment) => [
                'name' => $assignment->subject->name,
                'grades' => $terms->mapWithKeys(fn ($term) => [
                    $term->id => $grades->get($assignment->id.':'.$term->id)?->grade,
                ])->all(),
            ])
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
