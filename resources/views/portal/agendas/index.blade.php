@extends('layouts.app')

@section('title', 'Agendas - Portal do Aluno')

@section('extra_css')
<style>
    .agenda-list {
        display: grid;
        gap: 0.85rem;
    }

    .agenda-entry {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        box-shadow: 0 12px 28px -25px rgba(17, 24, 39, 0.8);
        display: grid;
        grid-template-columns: 5rem minmax(0, 1fr);
        min-height: 7rem;
        overflow: hidden;
        transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
    }

    .agenda-entry:hover {
        border-color: rgba(0, 148, 210, 0.45);
        box-shadow: 0 18px 38px -27px rgba(0, 74, 107, 0.8);
        transform: translateY(-1px);
    }

    .agenda-date {
        align-items: center;
        background: #0094d2;
        color: #ffffff;
        display: flex;
        flex-direction: column;
        font-family: "Inter", sans-serif;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
        gap: 0.45rem;
        justify-content: center;
        letter-spacing: -0.035em;
        padding: 0.8rem 0.4rem;
        text-align: center;
    }

    .agenda-date svg {
        height: 1.25rem;
        opacity: 0.88;
        width: 1.25rem;
    }

    .agenda-date-value {
        font-size: 1.05rem;
        line-height: 1;
    }

    .agenda-body {
        display: grid;
        grid-template-rows: minmax(0, 1fr) auto;
        min-width: 0;
    }

    .agenda-title {
        align-items: center;
        display: flex;
        min-width: 0;
        padding: 1rem;
    }

    .agenda-action {
        align-items: center;
        background: #e6f3f9;
        color: #0077a8;
        display: flex;
        font-size: 0.875rem;
        font-weight: 800;
        gap: 0.5rem;
        justify-content: center;
        min-height: 3rem;
        padding: 0.7rem 1rem;
        transition: background-color 180ms ease, color 180ms ease;
        width: 100%;
    }

    .agenda-action:hover {
        background: #0077a8;
        color: #ffffff;
    }

    .agenda-action:focus-visible {
        outline: 3px solid rgba(0, 148, 210, 0.35);
        outline-offset: -4px;
    }

    @media (min-width: 640px) {
        .agenda-entry {
            grid-template-columns: 6.25rem minmax(0, 1fr);
            min-height: 6.5rem;
        }

        .agenda-date-value {
            font-size: 1.25rem;
        }

        .agenda-body {
            grid-template-columns: minmax(0, 1fr) 10rem;
            grid-template-rows: 1fr;
        }

        .agenda-title {
            padding: 1.25rem 1.5rem;
        }

        .agenda-action {
            height: 100%;
            min-height: 100%;
            padding-inline: 1.25rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .agenda-entry,
        .agenda-action {
            transition: none;
        }

        .agenda-entry:hover {
            transform: none;
        }
    }
</style>
@endsection

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-6 md:mb-8">
        <a href="{{ route('portal.show') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:border-azul-principal hover:text-azul-principal focus:outline-none focus:ring-4 focus:ring-blue-100">
            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
            </svg>
            Voltar para o início
        </a>
    </div>

    @if($agendas->isEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-12 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-azul-claro text-azul-principal">
                <svg aria-hidden="true" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                </svg>
            </div>
            <p class="mt-4 font-bold text-gray-900">Nenhuma agenda publicada</p>
            <p class="mt-1 text-sm text-gray-600">Novos avisos aparecerão aqui quando forem publicados.</p>
        </div>
    @else
        <ol class="agenda-list" aria-label="Agendas publicadas">
            @foreach($agendas as $agenda)
                <li class="agenda-entry">
                    <time datetime="{{ $agenda->date->toDateString() }}" class="agenda-date">
                        <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                        </svg>
                        <span class="agenda-date-value">{{ $agenda->date->format('d/m') }}</span>
                    </time>

                    <div class="agenda-body">
                        <div class="agenda-title">
                            <h2 class="text-base font-extrabold leading-snug text-gray-900 sm:text-lg">
                                {{ $agenda->title }}
                            </h2>
                        </div>

                        <a href="{{ route('portal.agendas.show', $agenda->id) }}"
                            class="agenda-action"
                            aria-label="Ver detalhes de {{ $agenda->title }}">
                            Ver detalhes
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
@endsection
