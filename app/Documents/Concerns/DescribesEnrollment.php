<?php

namespace App\Documents\Concerns;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

/** Dados do aluno e da matrícula que as declarações escolares gravam na emissão. */
trait DescribesEnrollment
{
    public const NOT_ACTIVE_MESSAGE = 'Esta declaração só pode ser emitida para matrícula ativa.';

    public static function canIssue(Enrollment $enrollment): bool
    {
        return $enrollment->status === EnrollmentStatus::Active;
    }

    protected static function ensureActive(Enrollment $enrollment): void
    {
        if (! self::canIssue($enrollment)) {
            throw ValidationException::withMessages(['enrollment' => self::NOT_ACTIVE_MESSAGE]);
        }
    }

    /** @return array{student: array<string, mixed>, guardian: array<string, mixed>, enrollment: array<string, mixed>} */
    protected static function enrollmentContent(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['student.customer', 'classroom.level', 'academicYear']);
        $student = $enrollment->student;

        return [
            'student' => [
                'name' => $student->name,
                'gender' => $student->gender,
                'birth_date' => $student->date_of_birth?->format('d/m/Y'),
                'cpf' => $student->doc_number,
                'parents' => array_values(array_filter([$student->affiliation_1, $student->affiliation_2], 'filled')),
            ],
            'guardian' => ['name' => $student->customer?->name],
            'enrollment' => [
                'number' => $enrollment->number(),
                'date' => $enrollment->enrollment_date?->format('d/m/Y'),
                'classroom' => $enrollment->classroom?->name,
                'level' => $enrollment->classroom?->level?->name,
                'academic_year' => $enrollment->academicYear?->description,
            ],
        ];
    }
}
