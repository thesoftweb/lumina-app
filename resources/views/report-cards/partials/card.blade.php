<div class="space-y-4">
    <div>
        <strong>{{ $student->name }}</strong><br>
        {{ $classroom->name }} — {{ $academicYear?->description ?? 'Ano letivo não informado' }}
    </div>

    <table class="w-full border-collapse text-sm">
        <thead>
            <tr>
                <th class="border p-2 text-left">Disciplina</th>
                @foreach ($terms as $term)
                    <th class="border p-2">{{ $term->name }}</th>
                @endforeach
                @if ($assessmentMode === \App\Enums\AssessmentMode::Numeric)
                    <th class="border p-2">Média anual</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                <tr>
                    <td class="border p-2">{{ $subject['name'] }}</td>
                    @foreach ($terms as $term)
                        <td class="border p-2 text-center">{{ $subject['grades'][$term->id] ?? '—' }}</td>
                    @endforeach
                    @if ($assessmentMode === \App\Enums\AssessmentMode::Numeric)
                        <td class="border p-2 text-center font-semibold">
                            {{ $subject['annual_average'] !== null ? number_format($subject['annual_average'], 2, ',', '.') : '—' }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>
        Faltas: {{ $attendance['absences'] }} |
        Justificadas: {{ $attendance['justified'] }} |
        Frequência: {{ $attendance['percentage'] !== null ? $attendance['percentage'].'%' : '—' }}
    </p>
</div>
