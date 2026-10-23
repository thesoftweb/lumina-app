<?php

namespace App\Documents;

use App\Enums\EnrollmentStatus;
use App\Enums\IssuedDocumentType;
use App\Models\Company;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\Student;
use App\Models\StudentPickupAuthorization;
use App\Models\User;
use App\Services\DocumentIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carteirinha de autorização para buscar o aluno: uma por pessoa autorizada, válida até o fim do ano letivo
 * da matrícula ativa. Pedir de novo devolve a mesma (2ª via); revogar a autorização ou alterar os dados da
 * pessoa cancela a carteirinha, e o QR code passa a mostrar que ela não vale mais.
 */
class PickupCard
{
    public static function existing(StudentPickupAuthorization $authorization): ?IssuedDocument
    {
        return IssuedDocument::query()
            ->where('type', IssuedDocumentType::PickupCard)
            ->whereMorphedTo('documentable', $authorization)
            ->whereNull('canceled_at')
            ->latest('id')
            ->first();
    }

    public static function cancelFor(StudentPickupAuthorization $authorization, string $reason): void
    {
        if ($card = self::existing($authorization)) {
            app(DocumentIssuer::class)->cancel($card, auth()->user(), $reason);
        }
    }

    /** Retira a autorização: a pessoa deixa de poder buscar o aluno e a carteirinha é cancelada. */
    public static function revoke(StudentPickupAuthorization $authorization, ?string $reason = null): void
    {
        $reason = filled($reason) ? trim($reason) : null;

        DB::transaction(function () use ($authorization, $reason): void {
            $authorization->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
            self::cancelFor($authorization, 'Autorização revogada em '.now()->format('d/m/Y').($reason ? ": {$reason}" : '.'));
        });
    }

    public static function activeEnrollment(Student $student): ?Enrollment
    {
        return $student->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->with(['classroom.level', 'academicYear'])
            ->latest('enrollment_date')
            ->first();
    }

    public static function issue(StudentPickupAuthorization $authorization, User $issuer): IssuedDocument
    {
        return DB::transaction(function () use ($authorization, $issuer): IssuedDocument {
            // Trava a autorização: dois cliques seguidos não geram duas carteirinhas
            $authorization = StudentPickupAuthorization::query()->whereKey($authorization->getKey())->lockForUpdate()->firstOrFail();

            if ($existing = self::existing($authorization)) {
                return $existing;
            }

            if ($authorization->isRevoked()) {
                throw ValidationException::withMessages(['authorization' => 'Esta autorização foi revogada; cadastre a pessoa de novo se ela voltar a ser autorizada.']);
            }

            $student = $authorization->student;
            $enrollment = self::activeEnrollment($student);

            if (! $enrollment) {
                throw ValidationException::withMessages(['enrollment' => "{$student->name} não tem matrícula ativa; a carteirinha vale até o fim do ano letivo da matrícula."]);
            }

            $validUntil = $enrollment->academicYear?->end_at ?? today()->endOfYear();

            if ($validUntil->isPast()) {
                throw ValidationException::withMessages(['enrollment' => 'O ano letivo da matrícula ativa já terminou.']);
            }

            $issuer->loadMissing('company');

            return app(DocumentIssuer::class)->issue(
                type: IssuedDocumentType::PickupCard,
                documentable: $authorization,
                subject: "{$authorization->name} → {$student->name}",
                content: [
                    'student' => [
                        'name' => $student->name,
                        'gender' => $student->gender,
                        'photo' => app(DocumentIssuer::class)->snapshotFile(Student::PHOTO_DISK, $student->photo_path, 'photos'),
                        'grade' => collect([$enrollment->classroom?->level?->name, $enrollment->classroom?->name])->filter()->implode(' – ') ?: null,
                        'academic_year' => $enrollment->academicYear?->description,
                    ],
                    'person' => [
                        'name' => $authorization->name,
                        'document' => $authorization->document,
                        'relationship' => $authorization->relationship,
                    ],
                    'school_phone' => Company::current($issuer)?->phone,
                ],
                issuer: $issuer,
                validUntil: $validUntil,
            );
        });
    }

    /** "Ana C. S.": o nome do aluno abreviado para a verificação pública, sem expor a criança. */
    public static function abbreviate(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';
        $initials = collect($parts)
            ->reject(fn (string $part): bool => in_array(mb_strtolower($part), ['de', 'da', 'do', 'das', 'dos', 'e'], true))
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)).'.')
            ->implode(' ');

        return trim("{$first} {$initials}");
    }
}
