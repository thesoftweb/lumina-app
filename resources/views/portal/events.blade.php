@extends('layouts.app')

@section('title', 'Eventos e Contribuições - Portal do Aluno')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Eventos e Contribuições</h1>
        <p class="mt-2 text-gray-600">Participe dos eventos da sua turma</p>
    </div>

    @if($events->isEmpty())
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Nenhum evento disponível</h3>
            <p class="mt-1 text-gray-600">Não há eventos ativos no momento para sua turma</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($events as $event)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ $event->name }}</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                                    {{ match($event->type) {
                                        'celebration' => 'Festa/Celebração',
                                        'trip' => 'Viagem',
                                        'extracurricular' => 'Atividade Extraclasse',
                                        'contribution' => 'Contribuição',
                                        default => $event->type,
                                    } }}
                                </span>
                            </p>
                        </div>
                    </div>

                    @if($event->description)
                        <p class="text-gray-600 text-sm mb-4">{{ Str::limit($event->description, 100) }}</p>
                    @endif

                    <div class="space-y-2 mb-6 pb-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Turma:</span>
                            <span class="font-medium">{{ $event->classroom->name }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Valor:</span>
                            <span class="text-2xl font-bold text-green-600">R$ {{ number_format($event->amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Vencimento:</span>
                            <span class="font-medium {{ now()->greaterThanOrEqualTo($event->due_date->subDays(3)) ? 'text-red-600' : '' }}">
                                {{ $event->due_date->format('d/m/Y') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Participantes:</span>
                            <span class="bg-gray-100 px-3 py-1 rounded-full text-sm font-medium">
                                {{ $event->paidParticipants()->count() }}
                            </span>
                        </div>
                    </div>

                    <button
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                        onclick="initiatePayment('{{ $event->id }}', '{{ $event->name }}', '{{ $event->amount }}')"
                    >
                        Participar e Pagar
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900" id="modalTitle"></h3>
            <button onclick="closePaymentModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div id="paymentContent" class="px-6 py-4">
            <div class="text-center">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-600">Processando pagamento...</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_js')
<script>
let currentEventId;

function initiatePayment(eventId, eventName, amount) {
    currentEventId = eventId;
    document.getElementById('modalTitle').textContent = eventName;
    document.getElementById('paymentModal').classList.remove('hidden');

    // Make request to initialize payment
    fetch(`/portal/events/${eventId}/pay`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPaymentOptions(data);
        } else {
            showError(data.error || 'Erro ao iniciar pagamento');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Erro ao conectar com o servidor');
    });
}

function displayPaymentOptions(data) {
    const invoiceId = data.invoice.id;
    const asaasId = data.invoice.asaas_invoice_id;
    const participantCount = data.participant_count || 1;
    const isExistingPayment = data.is_existing_payment || false;

    let content = `
        <div class="space-y-4">
            ${isExistingPayment ? `
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                    <p class="text-sm text-blue-800"><strong>ℹ️ Continuando pagamento anterior</strong></p>
                    <p class="text-xs text-blue-700 mt-1">Você pode continuar o pagamento desta cobrança</p>
                </div>
            ` : ''}
            ${participantCount > 1 ? `
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <p class="text-sm text-yellow-800"><strong>Atenção:</strong> ${participantCount} aluno(s) participando</p>
                    <p class="text-xs text-yellow-700 mt-1">Valor por aluno: R$ ${(parseFloat(data.invoice.amount) / participantCount).toFixed(2).replace('.', ',')}</p>
                </div>
            ` : ''}
            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600">Valor total a pagar:</p>
                <p class="text-3xl font-bold text-blue-600">R$ ${parseFloat(data.invoice.amount).toFixed(2).replace('.', ',')}</p>
            </div>
    `;

    if (data.payment_url) {
        content += `
            <a href="${data.payment_url}" target="_blank" class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-center transition-colors">
                Pagar com PIX ou Boleto
            </a>
        `;
    }

    content += `
        <button
            onclick="loadPixQrCode('${invoiceId}')"
            class="w-full bg-gray-200 hover:bg-gray-300 text-gray-900 font-bold py-2 px-4 rounded-lg transition-colors"
        >
            Gerar QR Code PIX
        </button>

        <button
            onclick="closePaymentModal()"
            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-900 font-bold py-2 px-4 rounded-lg transition-colors"
        >
            Fechar
        </button>
    `;

    document.getElementById('paymentContent').innerHTML = content;
}

function loadPixQrCode(invoiceId) {
    fetch(`/portal/invoices/${invoiceId}/pix-qrcode`)
        .then(response => response.json())
        .then(data => {
            // Handle both response formats
            const pixData = data.data || data;

            if (pixData.encodedImage || pixData.qrcode) {
                const qrCodeImage = pixData.encodedImage || pixData.qrcode;
                const payloadText = pixData.payload;

                let pixContent = `
                    <div class="text-center">
                        <h4 class="font-bold mb-4">Escaneie o QR Code</h4>
                        <img src="data:image/png;base64,${qrCodeImage}" alt="QR Code PIX" class="w-64 h-64 mx-auto mb-4">
                        <div class="bg-gray-100 p-4 rounded-lg mb-4">
                            <p class="text-xs text-gray-600 mb-2">Chave PIX (copia e cola):</p>
                            <p class="font-mono text-xs break-all">${payloadText}</p>
                            <button
                                onclick="copyToClipboard('${payloadText}')"
                                class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium"
                            >
                                Copiar
                            </button>
                        </div>
                        <button
                            onclick="closePaymentModal()"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                        >
                            Fechado
                        </button>
                    </div>
                `;
                document.getElementById('paymentContent').innerHTML = pixContent;
            } else {
                showError('Erro ao gerar QR Code PIX');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Erro ao carregar QR Code');
        });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg';
        toast.textContent = 'Copiado!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}

function showError(message) {
    const content = `
        <div class="text-center text-red-600">
            <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 0v2"></path>
            </svg>
            <p>${message}</p>
            <button
                onclick="closePaymentModal()"
                class="mt-4 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
            >
                Fechar
            </button>
        </div>
    `;
    document.getElementById('paymentContent').innerHTML = content;
}
</script>
@endsection
