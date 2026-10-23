<?php

namespace App\Documents;

use App\Documents\Concerns\DescribesEnrollment;
use App\Enums\IssuedDocumentType;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\DocumentIssuer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Declaração de matrícula com a frequência apurada pelas chamadas lançadas, do início do ano letivo
 * (ou da data da matrícula, se for posterior) até o dia da emissão.
 */
class AttendanceDeclaration
{
    use DescribesEnrollment;

    public const NO_ATTENDANCE_MESSAGE = 'Ainda não há chamadas lançadas para esta matrícula no período.';

    /**
     * @return array{from: CarbonInterface, until: CarbonInterface, total: int, present: int, absent: int, justified: int, percentage: ?float}
     */
    public static function summary(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('academicYear');
        $until = today();
        $from = collect([$enrollment->academicYear?->start_at, $enrollment->enrollment_date])->filter()->max()
            ?? $enrollment->attendances()->min('date');
        $from = $from ? Carbon::parse($from)->startOfDay() : $until;

        // toBase(): sem os casts do modelo, que transformariam a soma de presenças em booleano
        $counts = $enrollment->attendances()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $until)
            ->toBase()
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when present then 1 else 0 end) as present_count')
            ->selectRaw('sum(case when not present and justified then 1 else 0 end) as justified_count')
            ->first();

        $total = (int) $counts->total_count;
        $present = (int) $counts->present_count;

        return [
            'from' => $from,
            'until' => $until,
            'total' => $total,
            'present' => $present,
            'absent' => $total - $present,
            'justified' => (int) $counts->justified_count,
            'percentage' => $total > 0 ? round($present / $total * 100, 1) : null,
        ];
    }

    public static function describe(array $summary): string
    {
        return $summary['total'] === 0
            ? self::NO_ATTENDANCE_MESSAGE
            : sprintf(
                'Frequência de %s até hoje: %d presenças em %d aulas registradas desde %s.',
                Number::percentage($summary['percentage'], maxPrecision: 1, locale: 'pt_BR'),
                $summary['present'],
                $summary['total'],
                $summary['from']->format('d/m/Y'),
            );
    }

    public static function issue(Enrollment $enrollment, User $issuer, ?string $purpose = null, ?int $validForDays = null): IssuedDocument
    {
        self::ensureActive($enrollment);
        $summary = self::summary($enrollment);

        if ($summary['total'] === 0) {
            throw ValidationException::withMessages(['attendance' => self::NO_ATTENDANCE_MESSAGE]);
        }

        return app(DocumentIssuer::class)->issue(
            type: IssuedDocumentType::AttendanceDeclaration,
            documentable: $enrollment,
            subject: $enrollment->student->name,
            content: [
                ...self::enrollmentContent($enrollment),
                // Percentual gravado como texto: o conteúdo congelado só guarda textos e inteiros.
                'attendance' => [
                    'from' => $summary['from']->format('d/m/Y'),
                    'until' => $summary['until']->format('d/m/Y'),
                    'total' => $summary['total'],
                    'present' => $summary['present'],
                    'absent' => $summary['absent'],
                    'justified' => $summary['justified'],
                    'percentage' => Number::percentage($summary['percentage'], maxPrecision: 1, locale: 'pt_BR'),
                ],
            ],
            issuer: $issuer,
            purpose: $purpose,
            validUntil: $validForDays ? today()->addDays($validForDays) : null,
        );
    }
}
