<?php

namespace App\Documents;

use App\Enums\AssessmentMode;
use App\Enums\EnrollmentStatus;
use App\Enums\IssuedDocumentType;
use App\Enums\TranscriptResult;
use App\Models\Company;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\Student;
use App\Models\TranscriptSubject;
use App\Models\TranscriptYear;
use App\Models\User;
use App\Services\Academic\AnnualResultCalculator;
use App\Services\Academic\SubjectResult;
use App\Services\DocumentIssuer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Histórico escolar: a vida escolar do aluno ano a ano (desta escola e das anteriores), com notas ou
 * conceitos, carga horária, frequência e resultado. O conteúdo é congelado na emissão; o ano em curso
 * pode entrar como "Cursando", com as médias parciais.
 */
class SchoolTranscript
{
    public const EMPTY_MESSAGE = 'O aluno ainda não tem anos no histórico. Feche o ano letivo, registre o ano desta escola ou lance os anos anteriores.';

    public static function issue(Student $student, User $issuer, bool $includeCurrentYear = true, ?string $observations = null): IssuedDocument
    {
        $years = self::years($student, $includeCurrentYear, $issuer);

        if ($years->isEmpty()) {
            throw ValidationException::withMessages(['student' => self::EMPTY_MESSAGE]);
        }

        $company = Company::current($issuer);

        return app(DocumentIssuer::class)->issue(
            type: IssuedDocumentType::SchoolTranscript,
            documentable: $student,
            subject: $student->name,
            content: [
                'student' => [
                    'name' => $student->name,
                    'gender' => $student->gender,
                    'birth_date' => $student->date_of_birth?->format('d/m/Y'),
                    'birthplace' => $student->birthplace,
                    'parents' => array_values(array_filter([$student->affiliation_1, $student->affiliation_2], 'filled')),
                    'registration' => $student->reg_number,
                    'document' => $student->doc_number,
                ],
                'years' => $years->values()->all(),
                'observations' => filled($observations) ? trim($observations) : null,
                'second_signer' => filled($company?->school_secretary_name) ? [
                    'name' => $company->school_secretary_name,
                    'role' => 'Secretário(a) escolar'.(filled($company->school_secretary_registration) ? ' · '.$company->school_secretary_registration : ''),
                ] : null,
            ],
            issuer: $issuer,
        );
    }

    /** Ano em curso desta escola que ainda não foi gravado no histórico (entra como "Cursando"). */
    public static function currentEnrollment(Student $student): ?Enrollment
    {
        return $student->enrollments()
            ->with(['classroom', 'academicYear'])
            ->where('status', EnrollmentStatus::Active->value)
            ->whereDoesntHave('transcriptYear')
            ->whereHas('academicYear')
            ->get()
            ->sortByDesc(fn (Enrollment $enrollment): int => (int) $enrollment->academicYear->year)
            ->first();
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function years(Student $student, bool $includeCurrentYear, User $issuer): Collection
    {
        $years = $student->transcriptYears()->with('subjects')->get()
            ->map(fn (TranscriptYear $year): array => [
                'year' => $year->year,
                'grade' => $year->grade_label,
                'school' => $year->school_name,
                'place' => $year->schoolPlace(),
                'concept' => $year->assessment_mode === AssessmentMode::Concept,
                'result' => $year->result->getLabel(),
                'attendance' => self::percent($year->attendance_percent),
                'school_days' => $year->school_days,
                'workload' => $year->total_workload,
                'subjects' => $year->subjects->map(fn (TranscriptSubject $subject): array => [
                    'name' => $subject->name,
                    'grade' => $subject->resultLabel(),
                    'workload' => $subject->workload_hours,
                ])->values()->all(),
                'notes' => $year->notes,
            ]);

        if ($includeCurrentYear && ($enrollment = self::currentEnrollment($student))) {
            $annual = app(AnnualResultCalculator::class)->forEnrollment($enrollment, Company::current($issuer));
            $company = Company::current($issuer);

            $years->push([
                'year' => (int) $enrollment->academicYear->year,
                'grade' => $enrollment->classroom->grade_label ?: $enrollment->classroom->name,
                'school' => $company?->name ?? '',
                'place' => $company?->city_and_state,
                'concept' => $annual->mode === AssessmentMode::Concept,
                'result' => TranscriptResult::InProgress->getLabel(),
                'attendance' => self::percent($annual->attendance['percentage']),
                'school_days' => $enrollment->classroom->school_days,
                'workload' => $annual->totalWorkload(),
                'subjects' => $annual->subjects->map(fn (SubjectResult $subject): array => [
                    'name' => $subject->name,
                    'grade' => $subject->average !== null || $subject->concept !== null
                        ? $subject->finalLabel()
                        : ($subject->partialAverage !== null ? number_format($subject->partialAverage, 2, ',', '.').' (parcial)' : '—'),
                    'workload' => $subject->workload,
                ])->values()->all(),
                'notes' => 'Ano letivo em curso; médias parciais até a data de emissão.',
            ]);
        }

        return $years->sortBy('year')->values();
    }

    private static function percent(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 1, ',', '.').'%';
    }
}
