{{--
    Cabeçalho dos PDFs (dompdf): logo e dados da escola. Só tabelas e CSS simples, que o dompdf entende.
    $school: ['name', 'tax_id', 'inep_code', 'phone', 'email', 'address'] · $logo: data URI ou null
--}}
@php
    $contacts = collect([
        filled($school['tax_id'] ?? null) ? 'CNPJ '.$school['tax_id'] : null,
        filled($school['inep_code'] ?? null) ? 'INEP '.$school['inep_code'] : null,
        $school['phone'] ?? null,
        $school['email'] ?? null,
    ])->filter()->implode('  ·  ');
@endphp
<table class="pdf-header">
    <tr>
        @if ($logo)
            <td class="pdf-logo"><img src="{{ $logo }}" alt=""></td>
        @endif
        <td>
            <div class="pdf-school">{{ $school['name'] ?? 'Instituição de ensino' }}</div>
            @if ($contacts)
                <div class="pdf-muted">{{ $contacts }}</div>
            @endif
            @if (filled($school['address'] ?? null))
                <div class="pdf-muted">{{ $school['address'] }}</div>
            @endif
        </td>
    </tr>
</table>
