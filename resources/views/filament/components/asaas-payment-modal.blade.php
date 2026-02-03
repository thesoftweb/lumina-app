<div class="space-y-6 p-6">
    <!-- Referência -->
    <div class="border-b pb-4">
        <p class="text-sm text-gray-600">Referência</p>
        <p class="text-lg font-bold text-gray-900">{{ $invoice->reference }}</p>
    </div>

    <!-- Valor -->
    <div class="border-b pb-4">
        <p class="text-sm text-gray-600">Valor</p>
        <p class="text-2xl font-bold text-blue-600">R$ {{ number_format($invoice->balance ?: $invoice->amount, 2, ',', '.') }}</p>
    </div>


    <div class="border-b pb-4">
        <p class="text-sm font-bold text-gray-900 mb-3">💳 PIX QR Code</p>
        <div class="bg-white p-4 rounded-lg border border-gray-200 flex justify-center">
            <svg viewBox="0 0 200 200" class="w-48 h-48 border-4 border-gray-300 p-2" xmlns="http://www.w3.org/2000/svg">
                <!-- QR Code fake para teste -->
                <rect width="200" height="200" fill="white"/>
                <rect x="10" y="10" width="50" height="50" fill="black"/>
                <rect x="140" y="10" width="50" height="50" fill="black"/>
                <rect x="10" y="140" width="50" height="50" fill="black"/>

                <!-- Padrão fake no meio -->
                <g fill="black">
                    <rect x="70" y="30" width="8" height="8"/>
                    <rect x="90" y="30" width="8" height="8"/>
                    <rect x="110" y="30" width="8" height="8"/>
                    <rect x="70" y="50" width="8" height="8"/>
                    <rect x="100" y="50" width="8" height="8"/>
                    <rect x="120" y="50" width="8" height="8"/>
                    <rect x="70" y="70" width="8" height="8"/>
                    <rect x="80" y="80" width="8" height="8"/>
                    <rect x="100" y="100" width="8" height="8"/>
                    <rect x="120" y="120" width="8" height="8"/>
                </g>
            </svg>
        </div>
        <a href="{{ $links['pix_url'] }}" target="_blank" class="inline-block mt-3 px-4 py-2 bg-green-600 text-white rounded-lg font-bold hover:bg-green-700 transition w-full text-center">
            🔗 Pagar com PIX
        </a>
    </div>


    <!-- Código de Barras Boleto -->

    <div class="border-b pb-4">
        <p class="text-sm font-bold text-gray-900 mb-3">📊 Boleto - Código de Barras</p>

        <div class="space-y-3">
            <input
                type="text"
                readonly
                value="{{ $links['boleto_barcode'] }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-900 font-mono text-sm focus:outline-none"
            />

            <a href="{{ $links['boleto_url'] }}" target="_blank" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition w-full text-center">
                🔗 Pagar com Boleto
            </a>
        </div>
    </div>



    <!-- Visualizar Fatura Completa -->
    @if($viewUrl)
    <div>
        <a href="{{ $viewUrl }}" target="_blank" class="inline-block px-4 py-2 bg-gray-600 text-white rounded-lg font-bold hover:bg-gray-700 transition w-full text-center">
            📄 Ver Fatura Completa
        </a>
    </div>
    @endif

    <!-- Informações Adicionais -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
        <p class="font-bold mb-1">💡 Dicas de Pagamento:</p>
        <ul class="list-disc list-inside space-y-1 text-xs">
            <li>PIX: Escaneie o QR Code ou copie o código acima</li>
            <li>Boleto: Use o código de barras em seu banco</li>
            <li>Validade: {{ $invoice->due_date->format('d/m/Y') }}</li>
        </ul>
    </div>
</div>
