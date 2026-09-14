<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $portalPage === 'invoices' ? 'Faturas' : ($portalPage === 'enrollments' ? 'Matrículas' : 'Início') }} - Portal do Aluno</title>

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Nunito:wght@700;800&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: "Inter", sans-serif;
        }

        h1, h2, h3 {
            font-family: "Nunito", "Inter", sans-serif;
        }

        .focus-azul:focus {
            border-color: #0094d2;
            box-shadow: 0 0 0 3px rgba(0, 148, 210, 0.2);
        }

        .portal-link:focus-visible {
            outline: 3px solid rgba(0, 148, 210, 0.3);
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between h-24">
            <a href="{{ route('portal.show') }}" class="flex items-center" aria-label="Página inicial do Portal do Aluno">
                <!-- Logo para Desktop -->
                <img src="{{ asset('assets/images/positive.png') }}" alt="Centro Educacional Criança Inteligente"
                    class="hidden md:block h-20" />
                <!-- Logo para Mobile -->
                <img src="{{ asset('assets/images/logo_mobile.png') }}" alt="Centro Educacional Criança Inteligente"
                    class="md:hidden h-16" />
            </a>

            <div class="flex items-center gap-4">
                <span class="text-gray-700 font-medium text-sm md:text-base">Portal do Aluno</span>
                <a href="{{ route('portal.login') }}" class="text-azul-principal hover:text-azul-hover font-bold transition text-sm md:text-base">
                    Sair
                </a>
            </div>
        </div>
    </header>

    <!-- SEÇÃO PRINCIPAL -->
    <section class="py-8 md:py-16">
        <div class="max-w-7xl mx-auto px-4">
            @if($portalPage === 'home')
            <!-- Bem-vindo -->
            <div class="mb-6 md:mb-8">
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-verde-secundaria">Área da família</p>
                <h1 class="text-3xl md:text-4xl font-bold text-azul-principal mb-2">
                    Bem-vindo ao Portal
                </h1>
                <p class="text-gray-600">
                    Acompanhe a agenda escolar, as matrículas e as faturas vinculadas ao CPF <strong>{{ $document }}</strong>.
                </p>
            </div>
            @endif

            @if($portalPage === 'home')
            <nav aria-label="Áreas do portal" class="mb-10">
                <div class="grid gap-3 md:grid-cols-3">
                    <a href="{{ route('portal.agendas.index') }}" class="portal-link group flex min-h-24 items-center gap-4 rounded-xl border border-blue-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-azul-principal hover:shadow-md md:min-h-40 md:flex-col md:items-start md:justify-between md:p-5">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-azul-claro text-azul-hover">
                            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1 md:flex-none">
                            <span class="flex items-center gap-2 text-lg font-extrabold text-gray-900">
                                Agenda
                                <span class="rounded-full bg-azul-claro px-2 py-0.5 font-sans text-xs text-azul-hover">{{ $agendas->count() }}</span>
                            </span>
                            <span class="mt-1 block text-sm text-gray-600">Avisos, reuniões e datas importantes.</span>
                        </span>
                        <svg aria-hidden="true" class="h-5 w-5 flex-none text-gray-400 transition group-hover:translate-x-1 group-hover:text-azul-principal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>

                    <a href="{{ route('portal.invoices.index') }}" @if($portalPage === 'invoices') aria-current="page" @endif class="portal-link group flex min-h-24 items-center gap-4 rounded-xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-verde-principal hover:shadow-md md:min-h-40 md:flex-col md:items-start md:justify-between md:p-5 {{ $portalPage === 'invoices' ? 'border-verde-principal ring-2 ring-green-100' : 'border-gray-200' }}">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-verde-claro text-verde-secundaria">
                            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a4 4 0 0 0-8 0v2m-3 0h14l-1 12H7L6 9Zm5 5h2" /></svg>
                        </span>
                        <span class="min-w-0 flex-1 md:flex-none">
                            <span class="flex items-center gap-2 text-lg font-extrabold text-gray-900">
                                Faturas
                                <span class="rounded-full bg-verde-claro px-2 py-0.5 font-sans text-xs text-verde-secundaria">{{ $invoices->count() }}</span>
                            </span>
                            <span class="mt-1 block text-sm text-gray-600">Consulte vencimentos e faça pagamentos.</span>
                        </span>
                        <svg aria-hidden="true" class="h-5 w-5 flex-none text-gray-400 transition group-hover:translate-x-1 group-hover:text-verde-secundaria" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>

                    <a href="{{ route('portal.enrollments.index') }}" @if($portalPage === 'enrollments') aria-current="page" @endif class="portal-link group flex min-h-24 items-center gap-4 rounded-xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-verde-principal hover:shadow-md md:min-h-40 md:flex-col md:items-start md:justify-between md:p-5 {{ $portalPage === 'enrollments' ? 'border-verde-principal ring-2 ring-green-100' : 'border-gray-200' }}">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-verde-claro text-verde-secundaria">
                            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14 3 9l9-5 9 5-9 5Zm0 0 6.16-3.42A12 12 0 0 1 18 17.5c-3.5 2-8.5 2-12 0a12 12 0 0 1-.16-6.92L12 14Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1 md:flex-none">
                            <span class="text-lg font-extrabold text-gray-900">Matrículas</span>
                            <span class="mt-1 block text-sm text-gray-600">Veja turmas e dados dos alunos.</span>
                        </span>
                        <svg aria-hidden="true" class="h-5 w-5 flex-none text-gray-400 transition group-hover:translate-x-1 group-hover:text-verde-secundaria" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>
                </div>
            </nav>
            @else
            <div class="mb-8">
                <a href="{{ route('portal.show') }}" class="portal-link inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:border-azul-principal hover:text-azul-principal">
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" /></svg>
                    Voltar para o início
                </a>
            </div>
            @endif

            @if($portalPage === 'enrollments')
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

            @endif

            @if($portalPage === 'invoices')
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
                                <th class="px-6 py-4 text-left font-bold">Valor Original</th>
                                <th class="px-6 py-4 text-left font-bold">Desconto</th>
                                <th class="px-6 py-4 text-left font-bold">Valor Final</th>
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
                                <td class="px-6 py-4 text-gray-700">
                                    @if($invoice->original_amount)
                                        R$ {{ number_format($invoice->original_amount, 2, ',', '.') }}
                                    @else
                                        R$ {{ number_format($invoice->final_amount ?? 0, 2, ',', '.') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    @if($invoice->discount_value && $invoice->discount_value > 0)
                                        <span class="text-verde-principal font-bold">
                                            @if($invoice->discount_type === 'percentage')
                                                -{{ number_format($invoice->discount_value, 2, ',', '.') }}%
                                            @else
                                                -R$ {{ number_format($invoice->discount_value, 2, ',', '.') }}
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-bold text-azul-principal">
                                    R$ {{ number_format($invoice->final_amount ?? 0, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                    $statusClass = 'bg-gray-200 text-gray-800';
                                    $statusText = 'Pendente';

                                    if ($invoice->status?->value === 'paid') {
                                    $statusClass = 'bg-verde-claro text-verde-secundaria';
                                    $statusText = 'Pago';
                                    } elseif ($invoice->status?->value === 'overdue') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $statusText = 'Vencido';
                                    }
                                    @endphp
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($invoice->status?->value === 'paid')
                                        <span class="text-verde-secundaria font-bold text-xs">✅ Pagamento Confirmado</span>
                                    @elseif($invoice->asaas_invoice_id)
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

                        <div class="grid grid-cols-2 gap-3 mb-4 text-xs">
                            <div>
                                <p class="text-gray-600">Vencimento:</p>
                                <p class="font-bold">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Valor Original:</p>
                                <p class="font-bold text-gray-700">
                                    @if($invoice->original_amount)
                                        R$ {{ number_format($invoice->original_amount, 2, ',', '.') }}
                                    @else
                                        R$ {{ number_format($invoice->final_amount ?? 0, 2, ',', '.') }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-span-2 flex items-center justify-between rounded-md bg-azul-claro px-3 py-2">
                                <p class="font-medium text-azul-hover">Valor final</p>
                                <p class="text-sm font-bold text-azul-principal">R$ {{ number_format($invoice->final_amount ?? 0, 2, ',', '.') }}</p>
                            </div>
                        </div>

                        @if($invoice->discount_value && $invoice->discount_value > 0)
                        <div class="bg-verde-claro p-3 rounded-lg mb-4">
                            <p class="text-xs text-verde-principal font-bold">
                                Desconto:
                                @if($invoice->discount_type === 'percentage')
                                    -{{ number_format($invoice->discount_value, 2, ',', '.') }}%
                                    @php
                                        $discountAmount = ($invoice->original_amount ?? 0) * ($invoice->discount_value / 100);
                                    @endphp
                                    (R$ {{ number_format($discountAmount, 2, ',', '.') }})
                                @else
                                    -R$ {{ number_format($invoice->discount_value, 2, ',', '.') }}
                                @endif
                            </p>
                        </div>
                        @endif

                        <div class="mb-4">
                            @php
                            $statusClass = 'bg-gray-200 text-gray-800';
                            $statusText = 'Pendente';

                            if ($invoice->status?->value === 'paid') {
                            $statusClass = 'bg-verde-claro text-verde-secundaria';
                            $statusText = 'Pago';
                            } elseif ($invoice->status?->value === 'overdue') {
                            $statusClass = 'bg-red-100 text-red-800';
                            $statusText = 'Vencido';
                            }
                            @endphp
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        @if($invoice->status?->value === 'paid')
                            <div class="text-center py-2">
                                <p class="text-verde-secundaria font-bold text-xs">✅ Pagamento Confirmado</p>
                            </div>
                        @elseif($invoice->asaas_invoice_id)
                            <div class="flex gap-2">
                                <a href="{{ $invoice->invoice_link }}" target="_blank" class="inline-flex min-h-11 flex-1 items-center justify-center px-3 py-2 bg-gray-600 text-white rounded-md text-xs font-bold text-center hover:bg-gray-700 transition">
                                    📄 Ver
                                </a>
                                <a href="javascript:void(0)" onclick="showAsaasLinks('{{ $invoice->id }}')" class="inline-flex min-h-11 flex-1 items-center justify-center px-3 py-2 bg-azul-principal text-white rounded-md text-xs font-bold text-center hover:bg-azul-hover transition">
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
            @endif
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

    @if($portalPage === 'invoices')
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
                        pixQrCodeUrl: '{{ route("portal.invoices.pix-qrcode", $invoice->id) }}',
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
            content += `
                <a href="javascript:void(0)" onclick="loadPixQrCode('${invoiceId}')" class="block w-full px-4 py-3 bg-verde-principal text-white rounded-lg font-bold text-center hover:bg-verde-hover transition">
                    📱 Pagar com PIX
                </a>
            `;

            content += `
                </div>
            `;

            document.getElementById('asaasContent').innerHTML = content;
            document.getElementById('asaasModal').classList.remove('hidden');
        }

        async function loadPixQrCode(invoiceId) {
            const data = invoiceAsaasLinks[invoiceId];
            if (!data) {
                alert('QR Code não disponível');
                return;
            }

            // Mostrar carregamento
            document.getElementById('asaasContent').innerHTML = `
                <div class="text-center py-8">
                    <p class="text-gray-600 mb-4">Carregando QR Code PIX...</p>
                    <div class="inline-block animate-spin">
                        <div class="border-4 border-gray-300 border-t-azul-principal rounded-full w-12 h-12"></div>
                    </div>
                </div>
            `;

            try {
                const response = await fetch(data.pixQrCodeUrl);
                const result = await response.json();

                if (!result.success) {
                    alert(result.message || 'Erro ao carregar QR Code');
                    closeAsaasModal();
                    return;
                }

                showQRCode(result.data);
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar QR Code. Tente novamente.');
                closeAsaasModal();
            }
        }

        function showQRCode(data) {
            let qrContent = `
                <div class="text-center">
                    <h4 class="text-lg font-bold text-azul-principal mb-2">${data.reference}</h4>
                    <p class="text-gray-600 mb-1"><strong>${data.description}</strong></p>
                    <p class="text-sm text-gray-500 mb-2">Valor: R$ ${data.amount}</p>
                    <p class="text-xs text-gray-500 mb-4">Vencimento: ${data.dueDate}</p>

                    <p class="text-sm text-gray-600 mb-4">Escaneie o código QR com seu app bancário:</p>
                    <img src="data:image/png;base64,${data.encodedImage}" alt="QR Code PIX" class="w-64 h-64 mx-auto mb-4" />

                    <div class="bg-gray-100 p-4 rounded-lg mb-4 text-left">
                        <p class="text-xs text-gray-600 mb-2"><strong>Ou copie o código PIX:</strong></p>
                        <div class="flex items-center gap-2">
                            <input type="text" value="${data.payload}" id="pixPayloadInput" readonly class="flex-1 px-3 py-2 text-xs border rounded bg-white" />
                            <button onclick="copyPixPayload()" class="px-3 py-2 bg-azul-principal text-white rounded font-bold text-xs hover:bg-azul-hover transition">
                                📋 Copiar
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">QR Code válido até: ${data.expirationDate}</p>
                </div>
            `;

            document.getElementById('asaasContent').innerHTML = qrContent;
        }

        function copyPixPayload() {
            const input = document.getElementById('pixPayloadInput');
            if (!input) {
                alert('Erro ao copiar código');
                return;
            }

            input.select();
            input.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');
                alert('Código PIX copiado com sucesso!');
            } catch (err) {
                // Fallback para Clipboard API
                navigator.clipboard.writeText(input.value).then(() => {
                    alert('Código PIX copiado com sucesso!');
                }).catch(() => {
                    alert('Erro ao copiar código');
                });
            }
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
    @endif
</body>

</html>
