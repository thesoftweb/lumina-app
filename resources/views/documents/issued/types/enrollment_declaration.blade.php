@php
    use App\Documents\DocumentText;

    $student = $content['student'] ?? [];
    $enrollment = $content['enrollment'] ?? [];
    $word = fn (string $male, string $female, string $neutral): string => DocumentText::byGender($student['gender'] ?? null, $male, $female, $neutral);
    $grade = collect([$enrollment['level'] ?? null, $enrollment['classroom'] ?? null])->filter()->implode(' – ');
@endphp

<p>
    Declaramos, para os devidos fins, que {{ DocumentText::studentIntro($student, $public) }},
    está regularmente {{ $word('matriculado', 'matriculada', 'matriculado(a)') }} nesta instituição de ensino
    @if ($grade)
        em <strong>{{ $grade }}</strong>,
    @endif
    no ano letivo <strong>{{ $enrollment['academic_year'] ?? '' }}</strong>, sob a matrícula nº {{ $enrollment['number'] ?? '' }}{{ filled($enrollment['date'] ?? null) ? ', efetivada em '.$enrollment['date'] : '' }}.
</p>

@if ($document->purpose)
    <p>Esta declaração é emitida a pedido do interessado, para fins de {{ $document->purpose }}.</p>
@else
    <p>Por ser expressão da verdade, firmamos a presente declaração.</p>
@endif
