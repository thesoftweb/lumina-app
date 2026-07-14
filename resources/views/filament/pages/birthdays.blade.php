<x-filament-panels::page>
    <label>Mês
        <select wire:model.live="month" class="w-full rounded-lg">
            @foreach (range(1, 12) as $number)
                <option value="{{ $number }}">{{ \Carbon\Carbon::create()->month($number)->translatedFormat('F') }}</option>
            @endforeach
        </select>
    </label>
    <div class="mt-6 space-y-2">
        @forelse ($this->getStudents() as $student)
            <div class="flex justify-between border-b py-3">
                <span>{{ $student->name }} — {{ $student->enrollments->pluck('classroom.name')->filter()->join(', ') }}</span>
                <strong>{{ $student->date_of_birth->format('d/m') }} — {{ now()->year - $student->date_of_birth->year }} anos</strong>
            </div>
        @empty
            <p>Nenhum aniversariante encontrado.</p>
        @endforelse
    </div>
</x-filament-panels::page>
