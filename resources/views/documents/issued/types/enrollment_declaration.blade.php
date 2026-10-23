@php
    use App\Models\IssuedDocument;

    $student = $content['student'] ?? [];
    $enrollment = $content['enrollment'] ?? [];
    $word = fn (string $male, string $female, string $neutral) => match ($student['gender'] ?? null) {
        'M' => $male,
        'F' => $female,
        default => $neutral,
    };
    $cpf = $public ? IssuedDocument::maskDocument($student['cpf'] ?? null) : ($student['cpf'] ?? null);
    $grade = collect([$enrollment['level'] ?? null, $enrollment['classroom'] ?? null])->filter()->implode(' – ');
@endphp

<p>
    Declaramos, para os devidos fins, que <strong>{{ $student['name'] ?? '' }}</strong>{{ filled($student['birth_date'] ?? null) ? ', '.$word('nascido', 'nascida', 'nascido(a)').' em '.$student['birth_date'] : '' }}{{ filled($cpf) ? ', CPF '.$cpf : '' }},
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
