<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <h2 class="text-xl font-bold">Minhas Turmas</h2>

            @if ($this->getClassrooms()->isEmpty())
                <div class="text-gray-500 py-8 text-center">
                    <p>Você não está vinculado a nenhuma turma.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($this->getClassrooms() as $classroom)
                        <div class="border rounded-lg p-4 hover:shadow-lg transition">
                            <h3 class="font-semibold text-lg mb-2">{{ $classroom->name }}</h3>
                            <p class="text-sm text-gray-600 mb-3">
                                <strong>Nível:</strong> {{ $classroom->level?->name }}
                            </p>
                            <div class="flex gap-2">
                                <a href="{{ route('filament.admin.resources.classrooms.view', $classroom->id) }}"
                                   class="flex-1 px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 text-center">
                                    Visualizar
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
