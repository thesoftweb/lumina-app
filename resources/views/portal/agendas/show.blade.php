@extends('layouts.app')

@section('title', $agenda->title . ' - Portal do Aluno')

@section('extra_css')
<style>
    .agenda-content {
        color: #374151;
        font-size: 1rem;
        line-height: 1.8;
        overflow-wrap: anywhere;
    }

    .agenda-content > * + * {
        margin-top: 1rem;
    }

    .agenda-content ul,
    .agenda-content ol {
        padding-left: 1.35rem;
    }

    .agenda-content ul {
        list-style: disc;
    }

    .agenda-content ol {
        list-style: decimal;
    }

    .agenda-content a {
        color: #0077a8;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
</style>
@endsection

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-6 md:mb-8">
        <a href="{{ route('portal.agendas.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:border-azul-principal hover:text-azul-principal focus:outline-none focus:ring-4 focus:ring-blue-100">
            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
            </svg>
            Voltar para agendas
        </a>
    </div>

    <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-azul-claro px-5 py-5 sm:px-8 sm:py-6">
            <time datetime="{{ $agenda->date->toIso8601String() }}" class="inline-flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.12em] text-azul-hover">
                <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                </svg>
                {{ $agenda->date->format('d/m/Y') }}
                @if($agenda->date->format('H:i') !== '00:00')
                    <span aria-hidden="true">•</span> {{ $agenda->date->format('H:i') }}
                @endif
            </time>
            <h1 class="mt-3 text-2xl font-extrabold leading-tight text-gray-900 sm:text-3xl">
                {{ $agenda->title }}
            </h1>
        </div>

        <div class="px-5 py-6 sm:px-8 sm:py-8">
            @if($agenda->description)
                <div class="agenda-content">
                    {!! $agenda->description !!}
                </div>
            @else
                <p class="text-gray-600">Este comunicado não possui informações adicionais.</p>
            @endif
        </div>
    </article>
</div>
@endsection
