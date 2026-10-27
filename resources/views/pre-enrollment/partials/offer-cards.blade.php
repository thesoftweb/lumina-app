{{--
    Vagas e valores do ano, como opções de rádio. Valor fixo: mensalidade do plano e taxa de matrícula, sem desconto.
    Parâmetros: $offers (com taken_count), $academicYear, $selected (vaga marcada quando não há old()).
--}}
@php
    $money = fn ($value) => \Illuminate\Support\Number::currency((float) $value, 'BRL', 'pt_BR');
    $selected = (string) old('admission_offer_id', $selected ?? '');
    // Vagas ligadas a turmas: a família escolhe a turma, não só a série
    $byClassroom = $offers->contains(fn ($offer) => $offer->classroom_id !== null);
@endphp

<section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
    <fieldset id="campo-admission-offer-id" @error('admission_offer_id') aria-describedby="campo-admission-offer-id-erro" @enderror>
        <legend class="text-xl font-bold text-gray-900">{{ $byClassroom ? 'Turmas e valores' : 'Valores' }} {{ $academicYear->year }}</legend>
        <p class="mt-1 text-sm text-gray-600">Escolha a {{ $byClassroom ? 'turma' : 'série' }} pretendida <span class="text-red-600" aria-hidden="true">*</span></p>

        @error('admission_offer_id')
            <p id="campo-admission-offer-id-erro" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($offers as $offer)
                @php
                    $remaining = $offer->vacancies !== null ? max(0, $offer->vacancies - $offer->taken_count) : null;
                @endphp
                <label class="relative block cursor-pointer">
                    <input type="radio" name="admission_offer_id" value="{{ $offer->id }}" class="sr-only"
                        @checked($selected === (string) $offer->id) required />
                    <div class="offer-card h-full rounded-xl border-2 border-gray-200 bg-white p-4 transition hover:border-azul-principal/60">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-gray-900 text-lg leading-snug">{{ $offer->name }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ collect([$offer->level?->name, $offer->shift?->getLabel()])->filter()->implode(' · ') }}
                                </p>
                            </div>
                            <span class="offer-check shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-transparent" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </span>
                        </div>

                        @if ($offer->monthlyAmount() !== null)
                            <p class="mt-3">
                                <span class="text-2xl font-bold text-azul-principal">{{ $money($offer->monthlyAmount()) }}</span>
                                <span class="text-sm text-gray-600">/mês</span>
                            </p>
                        @endif

                        @if ($offer->enrollment_fee !== null)
                            <p class="mt-1 text-sm text-gray-700">Taxa de matrícula: <strong>{{ $money($offer->enrollment_fee) }}</strong></p>
                        @endif

                        @if ($offer->description)
                            <p class="mt-2 text-sm text-gray-600">{{ $offer->description }}</p>
                        @endif

                        @if ($remaining !== null)
                            @if ($remaining === 0)
                                <p class="mt-2 inline-block rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Lista de espera</p>
                            @elseif ($remaining <= 3)
                                <p class="mt-2 inline-block rounded-full bg-laranja-accent px-3 py-1 text-xs font-semibold text-orange-800">Últimas vagas</p>
                            @else
                                <p class="mt-2 text-xs text-gray-600">{{ $remaining }} vagas disponíveis</p>
                            @endif
                        @endif
                    </div>
                </label>
            @endforeach
        </div>
    </fieldset>
</section>
