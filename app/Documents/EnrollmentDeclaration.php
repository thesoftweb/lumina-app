<?php

namespace App\Documents;

use App\Documents\Concerns\DescribesEnrollment;
use App\Enums\IssuedDocumentType;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\DocumentIssuer;

/** Declaração de que o aluno está regularmente matriculado na turma e no ano letivo. */
class EnrollmentDeclaration
{
    use DescribesEnrollment;

    public static function issue(Enrollment $enrollment, User $issuer, ?string $purpose = null, ?int $validForDays = null): IssuedDocument
    {
        self::ensureActive($enrollment);

        return app(DocumentIssuer::class)->issue(
            type: IssuedDocumentType::EnrollmentDeclaration,
            documentable: $enrollment,
            subject: $enrollment->student->name,
            content: self::enrollmentContent($enrollment),
            issuer: $issuer,
            purpose: $purpose,
            validUntil: $validForDays ? today()->addDays($validForDays) : null,
        );
    }
}
