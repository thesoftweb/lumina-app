<?php

namespace App\Documents;

use App\Enums\EnrollmentStatus;
use App\Enums\IssuedDocumentType;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\DocumentIssuer;
use Illuminate\Validation\ValidationException;

/** Declaração de que o aluno está regularmente matriculado na turma e no ano letivo. */
class EnrollmentDeclaration
{
    public const NOT_ACTIVE_MESSAGE = 'A declaração de matrícula só pode ser emitida para matrícula ativa.';

    public static function canIssue(Enrollment $enrollment): bool
    {
        return $enrollment->status === EnrollmentStatus::Active;
    }

    public static function issue(Enrollment $enrollment, User $issuer, ?string $purpose = null, ?int $validForDays = null): IssuedDocument
    {
        if (! self::canIssue($enrollment)) {
            throw ValidationException::withMessages(['enrollment' => self::NOT_ACTIVE_MESSAGE]);
        }

        $enrollment->loadMissing(['student.customer', 'classroom.level', 'academicYear']);
        $student = $enrollment->student;

        return app(DocumentIssuer::class)->issue(
            type: IssuedDocumentType::EnrollmentDeclaration,
            documentable: $enrollment,
            subject: $student->name,
            content: [
                'student' => [
                    'name' => $student->name,
                    'gender' => $student->gender,
                    'birth_date' => $student->date_of_birth?->format('d/m/Y'),
                    'cpf' => $student->doc_number,
                ],
                'guardian' => ['name' => $student->customer?->name],
                'enrollment' => [
                    'number' => $enrollment->number(),
                    'date' => $enrollment->enrollment_date?->format('d/m/Y'),
                    'classroom' => $enrollment->classroom?->name,
                    'level' => $enrollment->classroom?->level?->name,
                    'academic_year' => $enrollment->academicYear?->description,
                ],
            ],
            issuer: $issuer,
            purpose: $purpose,
            validUntil: $validForDays ? today()->addDays($validForDays) : null,
        );
    }
}
