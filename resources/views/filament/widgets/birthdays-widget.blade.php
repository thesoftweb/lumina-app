<x-filament-widgets::widget>
    <x-filament::section heading="Aniversariantes do mês">
        <div class="space-y-2">
            @forelse ($this->getStudents() as $student)
                <div class="flex justify-between gap-4 border-b py-2">
                    <span>{{ $student->name }} <small>({{ $student->enrollments->pluck('classroom.name')->filter()->join(', ') }})</small></span>
                    <strong>{{ $student->date_of_birth->format('d/m') }} — {{ now()->year - $student->date_of_birth->year }} anos</strong>
                </div>
            @empty
                <p>Nenhum aniversariante das suas turmas neste mês.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
