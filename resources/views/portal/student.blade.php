<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Meus Alunos - Portal do Aluno</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "azul-principal": "#0094d2",
                        "azul-hover": "#0077a8",
                        "azul-claro": "#e6f3f9",
                        "verde-principal": "#4caf50",
                        "verde-hover": "#3d8b40",
                        "verde-claro": "#e8f5e9",
                        "verde-secundaria": "#2e7d32",
                        "laranja-accent": "#fff3e0",
                    },
                },
            },
        };
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: "Inter", sans-serif;
        }

        .focus-azul:focus {
            border-color: #0094d2;
            box-shadow: 0 0 0 3px rgba(0, 148, 210, 0.2);
        }

        html {
            scroll-behavior: smooth;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-20">
            <a href="/" class="flex items-center">
                <img src="{{ asset('assets/images/positive.png') }}" alt="Centro Educacional Criança Inteligente"
                    class="h-20" />
            </a>

            <div class="flex items-center gap-4">
                <span class="text-gray-700 font-medium">Portal do Aluno</span>
                <a href="{{ route('portal.login') }}" class="text-azul-principal hover:text-azul-hover font-bold transition">
                    Sair
                </a>
            </div>
        </div>
    </header>

    <!-- SEÇÃO PRINCIPAL -->
    <section class="py-8 md:py-16">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Bem-vindo -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-azul-principal mb-2">
                    Bem-vindo ao Portal
                </h1>
                <p class="text-gray-600">
                    Consulte as matrículas e faturas vinculadas ao CPF: <strong>{{ $document }}</strong>
                </p>
            </div>

            <!-- SEÇÃO MATRÍCULAS -->
            <div class="mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-azul-principal mb-6">
                    📚 Matrículas Ativas
                </h2>

                @if($enrollments->isEmpty())
                <div class="bg-white p-8 rounded-lg shadow-sm border-l-4 border-azul-principal">
                    <p class="text-gray-600 text-center">
                        Nenhuma matrícula encontrada para este CPF.
                    </p>
                </div>
                @else
                <div class="grid md:grid-cols-2 gap-6">
                    @foreach($enrollments as $enrollment)
                    <div class="bg-white p-6 rounded-lg shadow-sm hover:shadow-md transition border-l-4 border-verde-secundaria">
                        <div class="mb-4">
                            <h3 class="text-xl font-bold text-verde-secundaria">
                                {{ $enrollment->student?->name ?? 'Aluno' }}
                            </h3>
                            <p class="text-gray-600 text-sm">
                                Matrícula: <strong>#{{ $enrollment->id }}</strong>
                            </p>
                        </div>

                        <div class="space-y-2 text-sm text-gray-700">
                            <p>
                                <strong>Série/Turma:</strong>
                                {{ $enrollment->classroom?->name ?? 'N/A' }}
                            </p>
                            <p>
                                <strong>Ano Letivo:</strong>
                                2026
                            </p>
                            <p>
                                <strong>Status:</strong>
                                <span class="inline-block px-3 py-1 rounded-full text-white bg-verde-secundaria text-xs font-bold">
                                    Ativa
                                </span>
                            </p>
                            @if($enrollment->created_at)
                            <p>
                                <strong>Data de Matrícula:</strong>
                                {{ $enrollment->created_at->format('d/m/Y') }}
                            </p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- SEÇÃO FATURAS -->
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-azul-principal mb-6">
                    💰 Faturas Disponíveis
                </h2>

                @if($invoices->isEmpty())
                <div class="bg-white p-8 rounded-lg shadow-sm border-l-4 border-azul-principal">
                    <p class="text-gray-600 text-center">
                        Nenhuma fatura encontrada para as matrículas.
                    </p>
                </div>
                @else
                <!-- Versão Desktop -->
                <div class="hidden md:block overflow-x-auto rounded-lg shadow-sm">
                    <table class="w-full bg-white">
                        <thead>
                            <tr class="bg-azul-principal text-white">
                                <th class="px-6 py-4 text-left font-bold">Aluno</th>
                                <th class="px-6 py-4 text-left font-bold">Descrição</th>
                                <th class="px-6 py-4 text-left font-bold">Vencimento</th>
                                <th class="px-6 py-4 text-left font-bold">Valor</th>
                                <th class="px-6 py-4 text-left font-bold">Status</th>
                                <th class="px-6 py-4 text-left font-bold">Pagamento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($invoices as $invoice)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $invoice->enrollment?->student?->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ $invoice->description ?? 'Mensalidade' }}
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 font-bold text-azul-principal">
                                    R$ {{ number_format($invoice->amount ?? 0, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                    $statusClass = 'bg-gray-200 text-gray-800';
                                    $statusText = 'Pendente';

                                    if ($invoice->status === 'paid') {
                                    $statusClass = 'bg-verde-claro text-verde-secundaria';
                                    $statusText = 'Pago';
                                    } elseif ($invoice->status === 'overdue') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $statusText = 'Vencido';
                                    }
                                    @endphp
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($invoice->asaas_invoice_id)
                                        <div class="flex gap-2">
                                            <a href="{{ $invoice->invoice_link }}" target="_blank" class="inline-block px-3 py-1 bg-gray-600 text-white rounded-md text-xs font-bold hover:bg-gray-700 transition">
                                                📄 Ver
                                            </a>
                                            <a href="javascript:void(0)" onclick="showAsaasLinks('{{ $invoice->id }}')" class="inline-block px-3 py-1 bg-azul-principal text-white rounded-md text-xs font-bold hover:bg-azul-hover transition">
                                                💳 Pagar
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-gray-500 text-xs">Aguardando processamento</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Versão Mobile -->
                <div class="md:hidden space-y-4">
                    @foreach($invoices as $invoice)
                    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-verde-secundaria">
                        <div class="mb-3">
                            <h3 class="font-bold text-gray-900">
                                {{ $invoice->enrollment?->student?->name ?? 'N/A' }}
                            </h3>
                            <p class="text-sm text-gray-600">
                                {{ $invoice->description ?? 'Mensalidade' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                            <div>
                                <p class="text-gray-600">Vencimento:</p>
                                <p class="font-bold">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Valor:</p>
                                <p class="font-bold text-azul-principal">R$ {{ number_format($invoice->amount ?? 0, 2, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            @php
                            $statusClass = 'bg-gray-200 text-gray-800';
                            $statusText = 'Pendente';

                            if ($invoice->status === 'paid') {
                            $statusClass = 'bg-verde-claro text-verde-secundaria';
                            $statusText = 'Pago';
                            } elseif ($invoice->status === 'overdue') {
                            $statusClass = 'bg-red-100 text-red-800';
                            $statusText = 'Vencido';
                            }
                            @endphp
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        @if($invoice->asaas_invoice_id)
                            <div class="flex gap-2">
                                <a href="{{ $invoice->invoice_link }}" target="_blank" class="flex-1 px-3 py-2 bg-gray-600 text-white rounded-md text-xs font-bold text-center hover:bg-gray-700 transition">
                                    📄 Ver
                                </a>
                                <a href="javascript:void(0)" onclick="showAsaasLinks('{{ $invoice->id }}')" class="flex-1 px-3 py-2 bg-azul-principal text-white rounded-md text-xs font-bold text-center hover:bg-azul-hover transition">
                                    💳 Pagar
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500 text-xs text-center py-2">Aguardando processamento</p>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-azul-principal text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="text-center text-sm">
                <p>© 2025 Centro Educacional Criança Inteligente. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Modal de Links Asaas -->
    <div id="asaasModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-xl font-bold text-azul-principal mb-4">
                Opções de Pagamento
            </h3>
            <div id="asaasContent" class="space-y-3">
                <!-- Conteúdo carregado dinamicamente -->
            </div>
            <button onclick="closeAsaasModal()" class="w-full mt-6 px-4 py-2 bg-gray-300 text-gray-800 rounded-lg font-bold hover:bg-gray-400 transition">
                Fechar
            </button>
        </div>
    </div>

    <script>
        // Dados das faturas carregados do backend
        const invoiceAsaasLinks = {
            @foreach($invoices as $invoice)
                @if($invoice->asaas_invoice_id)
                    '{{ $invoice->id }}': {
                        reference: '{{ $invoice->reference }}',
                        invoiceLink: '{{ $invoice->invoice_link ?? '' }}',
                        qrCode: '{{ $invoice->invoice_qrcode ?? '' }}',
                    },
                @endif
            @endforeach
        };

        function showAsaasLinks(invoiceId) {
            const data = invoiceAsaasLinks[invoiceId];
            if (!data) {
                alert('Links de pagamento não disponíveis');
                return;
            }

            let content = `
                <p class="text-gray-600 mb-4"><strong>Referência:</strong> ${data.reference}</p>
                <div class="space-y-3">
            `;

            // Adicionar botão de visualizar fatura
            if (data.invoiceLink) {
                content += `
                    <a href="${data.invoiceLink}" target="_blank" class="block w-full px-4 py-3 bg-gray-600 text-white rounded-lg font-bold text-center hover:bg-gray-700 transition">
                        📄 Visualizar Fatura
                    </a>
                `;
            }

            // Adicionar botão de QR Code PIX
            if (data.qrCode) {
                content += `
                    <a href="#" onclick="showQRCode('${invoiceId}')" class="block w-full px-4 py-3 bg-verde-principal text-white rounded-lg font-bold text-center hover:bg-verde-hover transition">
                        📱 Ver QR Code PIX
                    </a>
                `;
            }

            content += `
                </div>
            `;

            document.getElementById('asaasContent').innerHTML = content;
            document.getElementById('asaasModal').classList.remove('hidden');
        }

        function showQRCode(invoiceId) {
            const data = invoiceAsaasLinks[invoiceId];
            if (!data || !data.qrCode) {
                alert('QR Code não disponível');
                return;
            }

            let qrContent = `
                <div class="text-center">
                    <p class="text-gray-600 mb-4"><strong>Referência:</strong> ${data.reference}</p>
                    <p class="text-sm text-gray-600 mb-3">Escaneie o código QR com seu app bancário ou celular:</p>
                    <img src="data:image/svg+xml;base64,${data.qrCode}" alt="QR Code PIX" class="w-64 h-64 mx-auto mb-4" />
                    <p class="text-xs text-gray-500">QR Code válido até o vencimento da fatura</p>
                </div>
            `;

            document.getElementById('asaasContent').innerHTML = qrContent;
            document.getElementById('asaasModal').classList.remove('hidden');
        }

        function closeAsaasModal() {
            document.getElementById('asaasModal').classList.add('hidden');
        }

        // Fechar modal ao clicar fora
        document.getElementById('asaasModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAsaasModal();
            }
        });
    </script>
</body>

</html>
