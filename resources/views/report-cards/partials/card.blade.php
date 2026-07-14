<div class="space-y-4">
    <div><strong>{{ $student->name }}</strong><br>{{ $classroom->name }} — {{ $academicYear?->description ?? 'Ano letivo não informado' }}</div>
    <table class="w-full border-collapse text-sm">
        <thead><tr><th class="border p-2 text-left">Disciplina</th>@foreach($terms as $term)<th class="border p-2">{{ $term->name }}</th>@endforeach</tr></thead>
        <tbody>@foreach($subjects as $subject)<tr><td class="border p-2">{{ $subject['name'] }}</td>@foreach($terms as $term)<td class="border p-2 text-center">{{ $subject['grades'][$term->id] ?? '-' }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
    <p>Faltas: {{ $attendance['absences'] }} | Justificadas: {{ $attendance['justified'] }} | Frequência: {{ $attendance['percentage'] !== null ? $attendance['percentage'].'%' : '-' }}</p>
</div>
