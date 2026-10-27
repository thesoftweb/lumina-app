@extends('pre-enrollment.layout')

@section('title', isset($closedYear) ? 'Pré-matrícula '.$closedYear->year : 'Pré-matrícula')

@section('content')
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-10 text-center">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
            @isset($closedYear)
                Pré-matrícula {{ $closedYear->year }}
            @else
                Pré-matrícula
            @endisset
        </h1>

        @if ($years->isNotEmpty())
            @isset($closedYear)
                <p class="mt-3 text-gray-700">As inscrições para {{ $closedYear->description }} não estão abertas no momento.</p>
            @endisset
            <p class="mt-3 text-gray-700">Escolha o ano letivo para preencher a ficha:</p>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2" role="list">
                @foreach ($years as $year)
                    <li>
                        <a href="{{ route('pre-enrollment.create', ['year' => $year->year]) }}"
                            class="block w-full rounded-xl bg-azul-principal hover:bg-azul-hover text-white font-bold px-6 py-4 text-lg transition">
                            {{ $year->description }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-4 space-y-3 text-gray-700">
                @isset($closedYear)
                    @if ($closedYear->admissions_open && $closedYear->admissions_starts_at?->isFuture())
                        <p>As inscrições para {{ $closedYear->description }} abrem em <strong>{{ $closedYear->admissions_starts_at->format('d/m/Y') }}</strong>.</p>
                    @else
                        <p>As inscrições para {{ $closedYear->description }} estão encerradas ou ainda não começaram.</p>
                    @endif
                @else
                    <p>No momento não há inscrições abertas. Em breve divulgaremos o período da próxima pré-matrícula.</p>
                @endisset
                <p>Fale com a secretaria para saber sobre vagas e valores.</p>
            </div>

            @if ($company?->phone)
                <a href="tel:{{ preg_replace('/\D/', '', $company->phone) }}"
                    class="mt-6 inline-flex items-center justify-center w-full sm:w-auto rounded-xl bg-azul-principal hover:bg-azul-hover text-white font-bold px-6 py-4 transition">
                    Ligar para a secretaria
                </a>
            @endif
        @endif
    </section>
@endsection
