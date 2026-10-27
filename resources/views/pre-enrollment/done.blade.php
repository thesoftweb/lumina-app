@extends('pre-enrollment.layout')

@section('title', ($reenrollmentChild ?? null) ? 'Rematrícula confirmada' : 'Ficha enviada')

@section('content')
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-10 text-center" aria-labelledby="titulo">
        <div class="mx-auto w-16 h-16 rounded-full bg-verde-claro text-verde-secundaria flex items-center justify-center" aria-hidden="true">
            <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        @if ($reenrollmentChild ?? null)
            <h1 id="titulo" class="mt-4 text-2xl md:text-3xl font-bold text-gray-900">Rematrícula confirmada!</h1>
            <p class="mt-2 text-gray-700">Recebemos a confirmação da rematrícula de {{ $reenrollmentChild }} para o ano letivo {{ $academicYear->description }}.</p>
        @else
            <h1 id="titulo" class="mt-4 text-2xl md:text-3xl font-bold text-gray-900">Pré-matrícula enviada!</h1>
            <p class="mt-2 text-gray-700">Recebemos a ficha para o ano letivo {{ $academicYear->description }}.</p>
        @endif

        <div class="mt-6 rounded-xl bg-azul-claro border border-azul-principal/30 p-5">
            <p class="text-sm font-medium text-gray-700" id="protocolo-label">Número do protocolo</p>
            <p class="mt-1 text-3xl md:text-4xl font-bold tracking-wider text-azul-principal font-mono break-all"
                id="protocolo" aria-labelledby="protocolo-label">{{ $code }}</p>
            <button type="button" id="copiar"
                class="mt-4 inline-flex items-center justify-center min-h-[44px] rounded-lg border border-azul-principal text-azul-principal font-semibold px-5 hover:bg-white transition">
                Copiar protocolo
            </button>
            <p class="mt-2 text-sm text-verde-secundaria" role="status" aria-live="polite" id="copiado"></p>
        </div>

        <div class="mt-8 text-left">
            <h2 class="text-lg font-semibold text-gray-900">Próximos passos</h2>
            <ol class="mt-3 space-y-3 text-gray-700 list-decimal list-inside">
                @if ($reenrollmentChild ?? null)
                    <li>A secretaria confere os dados e entra em contato pelo WhatsApp ou telefone informado.</li>
                    <li>Se algum documento precisar ser atualizado, avisamos nesse contato.</li>
                @else
                    <li>A secretaria entrará em contato pelo WhatsApp ou telefone informado para confirmar a vaga.</li>
                    <li>Vamos combinar uma visita à escola e a entrega dos documentos.</li>
                @endif
                <li>A matrícula é concluída com a assinatura do contrato e o pagamento da taxa de matrícula.</li>
            </ol>
            <p class="mt-4 text-sm text-gray-600">Guarde o número do protocolo: ele ajuda a secretaria a encontrar sua ficha.</p>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="/" class="inline-flex items-center justify-center min-h-[48px] rounded-xl bg-azul-principal hover:bg-azul-hover text-white font-bold px-6 transition">
                Voltar para a página inicial
            </a>
            @if ($company?->phone)
                <a href="tel:{{ preg_replace('/\D/', '', $company->phone) }}"
                    class="inline-flex items-center justify-center min-h-[48px] rounded-xl border border-gray-300 text-gray-800 font-semibold px-6 hover:bg-gray-50 transition">
                    Falar com a secretaria
                </a>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.getElementById('copiar').addEventListener('click', async () => {
            const code = document.getElementById('protocolo').textContent.trim();
            const status = document.getElementById('copiado');
            try {
                await navigator.clipboard.writeText(code);
                status.textContent = 'Protocolo copiado.';
            } catch (e) {
                status.textContent = 'Não foi possível copiar. Anote o número: ' + code;
            }
        });
    </script>
@endpush
