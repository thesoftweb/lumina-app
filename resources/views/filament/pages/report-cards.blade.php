<x-filament-panels::page>
    <div class="space-y-6">
        <label>Aluno
            <select wire:model.live="enrollmentId" class="w-full rounded-lg">
                <option value="">Selecione</option>
                @foreach ($this->getEnrollments() as $enrollment)
                    <option value="{{ $enrollment->id }}">{{ $enrollment->student->name }} — {{ $enrollment->classroom->name }}</option>
                @endforeach
            </select>
        </label>
        @if ($report)
            @include('report-cards.partials.card', $report)
            <div class="flex gap-3">
                <x-filament::button tag="a" href="{{ route('report-cards.show', $enrollmentId) }}" target="_blank">Visualizar</x-filament::button>
                <x-filament::button tag="a" color="gray" href="{{ route('report-cards.pdf', $enrollmentId) }}" target="_blank">Gerar PDF</x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
