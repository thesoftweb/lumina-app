<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <label>Turma / disciplina
                <select wire:model.live="classroomSubjectId" class="w-full rounded-lg">
                    <option value="">Selecione</option>
                    @foreach ($this->getClassroomSubjects() as $assignment)
                        <option value="{{ $assignment->id }}">{{ $assignment->classroom->name }} — {{ $assignment->subject->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Bimestre
                <select wire:model.live="termId" class="w-full rounded-lg">
                    <option value="">Selecione</option>
                    @foreach ($this->getTerms() as $term)
                        <option value="{{ $term->id }}">{{ $term->name }} @if($term->year) ({{ $term->year }}) @endif</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($classroomSubjectId && $termId && count($grades))
            <table class="w-full text-sm">
                <thead><tr><th class="text-left">Aluno</th><th>Nota (0–10)</th><th>Comentários</th></tr></thead>
                <tbody>
                @foreach ($grades as $enrollmentId => $state)
                    <tr>
                        <td>{{ $state['name'] }}</td>
                        <td><input type="number" min="0" max="10" step="0.01" wire:model="grades.{{ $enrollmentId }}.grade" class="w-28 rounded-lg"></td>
                        <td><input wire:model="grades.{{ $enrollmentId }}.comments" class="w-full rounded-lg"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <x-filament::button wire:click="save">Salvar notas</x-filament::button>
        @endif
    </div>
</x-filament-panels::page>
