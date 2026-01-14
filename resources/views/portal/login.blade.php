<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Portal do Aluno - Centro Educacional Criança Inteligente</title>

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

    <!-- Fontes do Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Estilos Personalizados -->
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

<body class="bg-azul-claro">
    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-20">
            <!-- Logo -->
            <a href="/" class="flex items-center">
                <img src="{{ asset('assets/images/positive.png') }}" alt="Centro Educacional Criança Inteligente"
                    class="h-20" />
            </a>

            <!-- Voltar -->
            <a href="/" class="text-azul-principal hover:text-azul-hover font-bold transition">
                ← Voltar
            </a>
        </div>
    </header>

    <!-- SEÇÃO PRINCIPAL -->
    <section class="py-16 md:py-24">
        <div class="max-w-2xl mx-auto px-4">
            <div class="bg-white rounded-lg shadow-lg p-8 md:p-12">
                <div class="text-center mb-8">
                    <h1 class="text-4xl md:text-5xl font-bold text-azul-principal mb-4">
                        Portal do Aluno
                    </h1>
                    <p class="text-gray-600 text-lg">
                        Consulte as matrículas e faturas do seu filho
                    </p>
                </div>

                @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('portal.access') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label for="document" class="block text-gray-700 font-bold mb-3">
                            CPF do Responsável
                        </label>
                        <input
                            type="text"
                            id="document"
                            name="document"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus-azul focus:outline-none text-lg @if($errors->has('document')) border-red-500 @endif"
                            placeholder="000.000.000-00"
                            required
                            maxlength="14"
                            value="{{ old('document') }}" />
                        @if($errors->has('document'))
                        <p class="text-red-600 text-sm mt-2">{{ $errors->first('document') }}</p>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-azul-principal hover:bg-azul-hover text-white px-6 py-3 rounded-lg font-bold transition text-lg">
                        Acessar Portal
                    </button>
                </form>

                <div class="mt-8 p-6 bg-azul-claro rounded-lg">
                    <p class="text-gray-700 text-sm">
                        <strong>ℹ️ Informação:</strong> Digite o CPF do responsável para consultar todas as matrículas vinculadas e as faturas disponíveis.
                    </p>
                </div>
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

    <!-- Script para formatar CPF -->
    <script>
        document.getElementById('document').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }

            e.target.value = value.substring(0, 14);
        });
    </script>
</body>

</html>