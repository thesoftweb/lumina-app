@extends('layouts.app')

@section('title', 'Agendas - Portal do Aluno')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Agendas</h1>
        <p class="mt-2 text-gray-600">Confira os compromissos e eventos importantes da sua turma</p>
    </div>

    @if($agendas->isEmpty())
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Nenhuma agenda disponível</h3>
            <p class="mt-1 text-gray-600">Não há agendas para sua turma no momento</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($agendas as $agenda)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-l-4 border-blue-500">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h12a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                </svg>
                                <time class="text-sm font-semibold text-gray-600">
                                    {{ $agenda->date->format('d/m/Y') }}
                                </time>
                                <span class="text-sm text-gray-500">
                                    às {{ $agenda->date->format('H:i') }}
                                </span>
                            </div>

                            <h3 class="text-xl font-bold text-gray-900">{{ $agenda->title }}</h3>

                            @if($agenda->description)
                                <div class="mt-3 text-gray-700 text-sm prose prose-sm max-w-none">
                                    {!! Str::limit(strip_tags($agenda->description), 200) !!}
                                </div>
                            @endif

                            @if($agenda->global)
                                <div class="mt-3">
                                    <span class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-xs font-semibold">
                                        Agenda Global
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($agenda->description)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <details class="cursor-pointer">
                                <summary class="font-semibold text-blue-600 hover:text-blue-700 text-sm">
                                    Ver detalhes completos
                                </summary>
                                <div class="mt-3 text-gray-700 text-sm prose prose-sm max-w-none">
                                    {!! $agenda->description !!}
                                </div>
                            </details>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($agendas->hasPages())
            <div class="mt-8">
                {{ $agendas->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
