<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal do Aluno')</title>

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

    @yield('extra_css')
</head>

<body class="bg-gray-50">
    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between h-24">
            <a href="{{ route('portal.show') }}" class="flex items-center">
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
            @yield('content')
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-gray-900 text-gray-300 py-8 mt-16 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; {{ now()->year }} Centro Educacional Criança Inteligente. Todos os direitos reservados.</p>
        </div>
    </footer>

    @yield('extra_js')
</body>

</html>
