<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>@yield('title', 'Pré-matrícula') · {{ $company?->name ?? 'Centro Educacional Criança Inteligente' }}</title>

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

        /* 16px evita o zoom automático do iOS ao focar o campo */
        .field {
            display: block;
            width: 100%;
            min-height: 48px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.625rem 0.875rem;
            font-size: 16px;
            background: #fff;
            color: #111827;
        }

        .field:focus {
            outline: none;
            border-color: #0094d2;
            box-shadow: 0 0 0 3px rgba(0, 148, 210, 0.25);
        }

        .field[aria-invalid="true"] {
            border-color: #dc2626;
        }

        a:focus-visible,
        button:focus-visible,
        input[type="radio"]:focus-visible + .offer-card,
        input[type="checkbox"]:focus-visible {
            outline: 3px solid #0094d2;
            outline-offset: 2px;
        }

        input[type="radio"]:checked + .offer-card {
            border-color: #0094d2;
            box-shadow: 0 0 0 2px #0094d2;
            background: #e6f3f9;
        }

        input[type="radio"]:checked + .offer-card .offer-check {
            background: #0094d2;
            border-color: #0094d2;
            color: #fff;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 bg-white px-4 py-2 rounded shadow z-50">
        Pular para o conteúdo
    </a>

    <header class="bg-white border-b border-gray-200">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-between h-16 md:h-20">
            <a href="/" class="flex items-center" aria-label="Voltar para a página inicial">
                <img src="{{ asset('assets/images/positive.png') }}" alt="{{ $company?->name ?? 'Centro Educacional Criança Inteligente' }}" class="h-12 md:h-16 w-auto" />
            </a>
            <a href="/" class="text-sm font-medium text-gray-600 hover:text-azul-principal py-3 px-2">
                ← Página inicial
            </a>
        </div>
    </header>

    <main id="conteudo" class="max-w-3xl mx-auto px-4 py-6 md:py-10">
        @yield('content')
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-4 py-6 text-sm text-gray-600 space-y-1">
            <p class="font-semibold text-gray-800">{{ $company?->name ?? 'Centro Educacional Criança Inteligente' }}</p>
            @if ($company?->phone)
                <p>Telefone: <a href="tel:{{ preg_replace('/\D/', '', $company->phone) }}" class="text-azul-principal underline">{{ $company->phone }}</a></p>
            @endif
            @if ($company?->email)
                <p>E-mail: <a href="mailto:{{ $company->email }}" class="text-azul-principal underline">{{ $company->email }}</a></p>
            @endif
        </div>
    </footer>

    @stack('scripts')
</body>

</html>
