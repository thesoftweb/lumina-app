<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <label>Turma
                <select wire:model.live="classroomId" class="w-full rounded-lg">
                    <option value="">Selecione</option>
                    @foreach ($this->getClassrooms() as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Data <input type="date" wire:model.live="date" class="w-full rounded-lg"></label>
        </div>
        @if ($classroomId && count($attendance))
            <table class="w-full text-sm">
                <thead><tr><th>Aluno</th><th>Presente</th><th>Justificada</th><th>Justificativa</th></tr></thead>
                <tbody>
                @foreach ($attendance as $enrollmentId => $state)
                    <tr>
                        <td>{{ $state['name'] }}</td>
                        <td><input type="checkbox" wire:model="attendance.{{ $enrollmentId }}.present"></td>
                        <td><input type="checkbox" wire:model="attendance.{{ $enrollmentId }}.justified"></td>
                        <td><input wire:model="attendance.{{ $enrollmentId }}.justification" class="w-full rounded-lg"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <x-filament::button wire:click="save">Salvar frequência</x-filament::button>
        @elseif ($classroomId)
            <p>Nenhuma matrícula ativa encontrada.</p>
        @endif
    </div>
</x-filament-panels::page>
