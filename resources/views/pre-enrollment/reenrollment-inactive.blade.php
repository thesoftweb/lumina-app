@extends('pre-enrollment.layout')

@section('title', 'Rematrícula '.$academicYear->year)

@section('content')
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-10 text-center">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Rematrícula {{ $academicYear->year }}</h1>
        <div class="mt-4 space-y-3 text-gray-700">
            <p>Esta rematrícula já foi concluída ou o link não está mais ativo.</p>
            <p>Se precisar de ajuda, fale com a secretaria.</p>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            @if ($company?->phone)
                <a href="tel:{{ preg_replace('/\D/', '', $company->phone) }}"
                    class="inline-flex items-center justify-center min-h-[48px] rounded-xl bg-azul-principal hover:bg-azul-hover text-white font-bold px-6 transition">
                    Ligar para a secretaria
                </a>
            @endif
            <a href="/" class="inline-flex items-center justify-center min-h-[48px] rounded-xl border border-gray-300 text-gray-800 font-semibold px-6 hover:bg-gray-50 transition">
                Voltar para a página inicial
            </a>
        </div>
    </section>
@endsection
