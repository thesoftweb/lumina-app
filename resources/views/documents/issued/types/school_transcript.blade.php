{{--
    Histórico escolar. Usado na página do documento (navegador) e no PDF (dompdf): só tabelas e CSS
    simples. $public = true na verificação pública: oculta parte do documento do aluno.
--}}
@php
    use App\Models\IssuedDocument;

    $student = $content['student'] ?? [];
    $years = $content['years'] ?? [];
    $parents = $student['parents'] ?? [];
    $document = ($public ?? false) ? IssuedDocument::maskDocument($student['document'] ?? null) : ($student['document'] ?? null);
    $sum = fn (array $year): ?int => collect($year['subjects'] ?? [])->sum(fn (array $subject): int => (int) ($subject['workload'] ?? 0)) ?: null;
@endphp
<style>
    .ht {
        width: 100%;
        border-collapse: collapse;
        line-height: 1.35;
    }

    .ht td,
    .ht th {
        padding: 4px 6px;
        border: 1px solid #cbd5e1;
        font-size: 8.5pt;
        vertical-align: top;
    }

    .ht th {
        background: #f1f5f9;
        color: #0f172a;
        font-weight: bold;
        text-align: center;
    }

    .ht .num {
        width: 16%;
        text-align: center;
    }

    .ht-label {
        display: block;
        color: #64748b;
        font-size: 6.5pt;
        text-transform: uppercase;
    }

    .ht-year {
        margin-top: 12px;
        page-break-inside: avoid;
    }

    .ht-year-head td {
        background: #0369a1;
        color: #fff;
        border-color: #0369a1;
        font-weight: bold;
    }

    .ht-year-foot td {
        background: #f8fafc;
        font-size: 8pt;
    }

    .ht-section {
        margin: 14px 0 4px;
        color: #0369a1;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .ht-note,
    p.ht-note {
        margin: 4px 0 0;
        color: #475569;
        font-size: 7.5pt;
        line-height: 1.4;
        text-align: left;
    }
</style>

<div class="ht-section">Identificação do aluno</div>
<table class="ht">
    <tr>
        <td colspan="2"><span class="ht-label">Nome</span><strong>{{ $student['name'] ?? '' }}</strong></td>
        <td><span class="ht-label">Data de nascimento</span>{{ $student['birth_date'] ?? '—' }}</td>
    </tr>
    <tr>
        <td><span class="ht-label">Naturalidade</span>{{ $student['birthplace'] ?? '—' }}</td>
        <td><span class="ht-label">Documento</span>{{ $document ?: '—' }}</td>
        <td><span class="ht-label">Registro do aluno</span>{{ $student['registration'] ?? '—' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="ht-label">Filiação</span>{{ $parents ? implode(' e ', $parents) : '—' }}</td>
    </tr>
</table>

<div class="ht-section">Vida escolar</div>
@foreach ($years as $year)
    <table class="ht ht-year">
        <tr class="ht-year-head">
            <td colspan="3">
                {{ $year['year'] }} · {{ $year['grade'] }} — {{ $year['school'] }}{{ filled($year['place'] ?? null) ? ' ('.$year['place'].')' : '' }}
            </td>
        </tr>
        <tr>
            <th style="text-align: left">Componente curricular</th>
            <th class="num">{{ ($year['concept'] ?? false) ? 'Conceito' : 'Nota final' }}</th>
            <th class="num">Carga horária</th>
        </tr>
        @forelse ($year['subjects'] ?? [] as $subject)
            <tr>
                <td>{{ $subject['name'] }}</td>
                <td class="num">{{ $subject['grade'] }}</td>
                <td class="num">{{ filled($subject['workload'] ?? null) ? $subject['workload'].' h' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Componentes curriculares não informados.</td></tr>
        @endforelse
        <tr class="ht-year-foot">
            <td colspan="3">
                <strong>Resultado: {{ $year['result'] }}</strong>
                · Frequência: {{ $year['attendance'] ?? '—' }}
                · Carga horária total: {{ ($year['workload'] ?? $sum($year)) ? ($year['workload'] ?? $sum($year)).' h' : '—' }}
                @if (filled($year['school_days'] ?? null))
                    · Dias letivos: {{ $year['school_days'] }}
                @endif
                @if (filled($year['notes'] ?? null))
                    <div class="ht-note">{{ $year['notes'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endforeach

@if (filled($content['observations'] ?? null))
    <div class="ht-section">Observações</div>
    <p class="ht-note" style="font-size: 8.5pt">{{ $content['observations'] }}</p>
@endif

<p class="ht-note" style="margin-top: 10px">
    Certificamos que os dados acima conferem com os registros desta instituição e com a documentação
    de transferência apresentada para os anos cursados em outras escolas.
</p>
