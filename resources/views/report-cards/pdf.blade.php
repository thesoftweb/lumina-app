{{-- Boletim escolar em PDF (dompdf). Dados de App\Services\ReportCardService. --}}
@php
    use App\Enums\AssessmentMode;
    use App\Enums\TranscriptResult;
    use App\Services\Academic\SubjectResult;

    $numeric = $assessmentMode === AssessmentMode::Numeric;
    $school = [
        'name' => $company?->name,
        'tax_id' => $company?->tax_id,
        'inep_code' => $company?->inep_code,
        'phone' => $company?->phone,
        'email' => $company?->email,
        'address' => $company?->full_address,
    ];
    $grade = collect([$classroom->grade_label, $classroom->name])->filter()->unique()->implode(' – ');
    $result = $annual->result;
    $resultClass = match ($result) {
        TranscriptResult::Approved => 'ok',
        TranscriptResult::Failed, TranscriptResult::FailedAttendance => 'bad',
        default => 'info',
    };
    $statusClass = fn (string $status): string => match ($status) {
        SubjectResult::APPROVED => 'ok',
        SubjectResult::FAILED => 'bad',
        default => '',
    };
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Boletim – {{ $student->name }}</title>
    @include('pdf.styles')
</head>
<body>
    @include('pdf.header', ['school' => $school, 'logo' => $company?->logoDataUri()])

    <div class="pdf-title">
        Boletim escolar
        <div class="pdf-subtitle">Ano letivo {{ $academicYear?->description ?? $academicYear?->year ?? '—' }}</div>
    </div>

    <table class="pdf-info">
        <tr>
            <td style="width: 50%"><span class="pdf-label">Aluno(a)</span><strong>{{ $student->name }}</strong></td>
            <td><span class="pdf-label">Série / turma</span>{{ $grade ?: '—' }}</td>
            <td><span class="pdf-label">Matrícula</span>{{ $enrollment->number() }}</td>
        </tr>
    </table>

    <div class="pdf-section">Desempenho</div>
    <table class="pdf-grid">
        <thead>
            <tr>
                <th class="left">Disciplina</th>
                @foreach ($terms as $term)
                    <th>{{ $term->name }}</th>
                @endforeach
                <th>{{ $numeric ? 'Média anual' : 'Conceito final' }}</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td class="left">{{ $subject['name'] }}</td>
                    @foreach ($terms as $term)
                        @php($value = $subject['grades'][$term->id] ?? null)
                        <td>{{ $value === null ? '—' : (is_numeric($value) ? number_format((float) $value, 2, ',', '.') : $value) }}</td>
                    @endforeach
                    <td><strong>{{ $subject['final'] }}</strong></td>
                    <td class="{{ $statusClass($subject['status']) }}">{{ $subject['status_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $terms->count() + 3 }}">Nenhuma disciplina cadastrada na turma.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pdf-section">Frequência</div>
    <table class="pdf-info">
        <tr>
            <td><span class="pdf-label">Aulas registradas</span>{{ $attendance['total'] }}</td>
            <td><span class="pdf-label">Faltas</span>{{ $attendance['absences'] }}</td>
            <td><span class="pdf-label">Faltas justificadas</span>{{ $attendance['justified'] }}</td>
            <td><span class="pdf-label">Frequência</span><strong>{{ $attendance['percentage'] !== null ? number_format($attendance['percentage'], 1, ',', '.').'%' : 'Sem registros' }}</strong></td>
        </tr>
    </table>

    <div class="pdf-result">
        <span class="pdf-label">Situação no ano</span>
        <span class="{{ $resultClass }}" style="font-size: 11pt">{{ $result->getLabel() }}</span>
        <div class="pdf-notes">
            {{ $annual->rulesLabel() }}.
            @if ($result === TranscriptResult::InProgress)
                O resultado final sai quando todos os bimestres tiverem nota.
            @endif
        </div>
    </div>

    <div class="pdf-footer">Boletim gerado em {{ now()->format('d/m/Y H:i') }} · {{ $school['name'] }}</div>
</body>
</html>
