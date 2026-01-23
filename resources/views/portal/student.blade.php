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
                <div class="overflow-x-auto rounded-lg shadow-sm">
                    <table class="w-full bg-white">
                        <thead>
                            <tr class="bg-azul-principal text-white">
                                <th class="px-6 py-4 text-left font-bold">Aluno</th>
                                <th class="px-6 py-4 text-left font-bold">Descrição</th>
                                <th class="px-6 py-4 text-left font-bold">Vencimento</th>
                                <th class="px-6 py-4 text-left font-bold">Valor</th>
                                <th class="px-6 py-4 text-left font-bold">Status</th>
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
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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
</body>

</html>