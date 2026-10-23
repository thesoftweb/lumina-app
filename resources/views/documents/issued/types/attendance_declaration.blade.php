@php
    use App\Documents\DocumentText;

    $student = $content['student'] ?? [];
    $enrollment = $content['enrollment'] ?? [];
    $attendance = $content['attendance'] ?? [];
    $word = fn (string $male, string $female, string $neutral): string => DocumentText::byGender($student['gender'] ?? null, $male, $female, $neutral);
    $grade = collect([$enrollment['level'] ?? null, $enrollment['classroom'] ?? null])->filter()->implode(' – ');
    $plural = fn (int $count, string $one, string $many): string => $count.' '.($count === 1 ? $one : $many);
    $present = (int) ($attendance['present'] ?? 0);
    $total = (int) ($attendance['total'] ?? 0);
    $absent = (int) ($attendance['absent'] ?? 0);
    $justified = (int) ($attendance['justified'] ?? 0);

    $absences = '';
    if ($absent > 0) {
        $absences = ' e '.$plural($absent, 'falta', 'faltas');
        if ($justified > 0) {
            $absences .= $justified === $absent
                ? ($absent === 1 ? ', justificada' : ', todas justificadas')
                : ', das quais '.$justified.' '.($justified === 1 ? 'justificada' : 'justificadas');
        }
    }
@endphp

<p>
    Declaramos, para os devidos fins, que {{ DocumentText::studentIntro($student, $public) }},
    está regularmente {{ $word('matriculado', 'matriculada', 'matriculado(a)') }} nesta instituição de ensino
    @if ($grade)
        em <strong>{{ $grade }}</strong>,
    @endif
    no ano letivo <strong>{{ $enrollment['academic_year'] ?? '' }}</strong>, sob a matrícula nº {{ $enrollment['number'] ?? '' }}.
</p>

<p>
    No período de {{ $attendance['from'] ?? '' }} a {{ $attendance['until'] ?? '' }}, {{ $word('o aluno', 'a aluna', 'o(a) aluno(a)') }} obteve frequência de <strong>{{ $attendance['percentage'] ?? '' }}</strong>, com {{ $plural($present, 'presença', 'presenças') }} em {{ $plural($total, 'aula registrada', 'aulas registradas') }}{{ $absences }}.
</p>

@if ($document->purpose)
    <p>Esta declaração é emitida a pedido do interessado, para fins de {{ $document->purpose }}.</p>
@else
    <p>Por ser expressão da verdade, firmamos a presente declaração.</p>
@endif
