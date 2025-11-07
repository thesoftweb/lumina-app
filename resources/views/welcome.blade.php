<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Centro Educacional Criança Inteligente</title>

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

        .hover-azul:hover {
            background-color: #0077a8;
        }

        html {
            scroll-behavior: smooth;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }
        }
    </style>
</head>

<body class="bg-white">
    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-20">
            <!-- Logo -->
            <a href="/" class="flex items-center">
                <img src="{{ asset('assets/images/logo_positive.png') }}" alt="Centro Educacional Criança Inteligente"
                    class="h-20" />
            </a>

            <!-- Menu Desktop -->
            <nav class="hidden md:flex items-center space-x-8">
                <a href="/" class="text-gray-700 hover:text-azul-principal font-medium">
                    Início
                </a>
                <a href="#ensino" class="text-gray-700 hover:text-azul-principal font-medium">
                    Ensino
                </a>
                <a href="#proposta" class="text-gray-700 hover:text-azul-principal font-medium">
                    Proposta
                </a>
                <a href="#atividades" class="text-gray-700 hover:text-azul-principal font-medium">
                    Atividades
                </a>
                <a href="#contato" class="text-gray-700 hover:text-azul-principal font-medium">
                    Contato
                </a>
                <a href="portal.html" class="text-gray-700 hover:text-azul-principal font-medium">
                    Portal
                </a>
                <a href="matricula.html"
                    class="bg-azul-principal hover:bg-azul-hover text-white px-6 py-2 rounded-lg font-bold transition">
                    Matrícula
                </a>
            </nav>

            <!-- Menu Mobile -->
            <button class="md:hidden text-gray-700 hover:text-azul-principal" id="mobileMenuButton">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
        </div>

        <!-- Menu Mobile Dropdown -->
        <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-200 absolute w-full">
            <div class="max-w-7xl mx-auto px-4 py-4 space-y-4">
                <a href="/" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Início
                </a>
                <a href="#ensino" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Ensino
                </a>
                <a href="#proposta" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Proposta
                </a>
                <a href="#atividades" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Atividades
                </a>
                <a href="#contato" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Contato
                </a>
                <a href="portal.html" class="block text-gray-700 hover:text-azul-principal font-medium">
                    Portal
                </a>
                <a href="matricula.html"
                    class="block w-full text-center bg-azul-principal hover:bg-azul-hover text-white px-6 py-2 rounded-lg font-bold transition">
                    Matrícula
                </a>
            </div>
        </div>
    </header>

    <!-- BANNER/HERO -->
    <section id="inicio" class="mx-auto relative flex items-center bg-azul-claro overflow-hidden">
        <img src="{{ asset('assets/images/banner_home.jpg') }}" alt="" aria-hidden="true"
            class="w-full h-full object-contain object-center bg-verde-secundaria mt-20" />
    </section>

    <!-- SEÇÃO CTA -->
    <section id="cta" class="py-16 md:py-24 bg-azul-principal text-center">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
                Reserve sua Matrícula
            </h2>
            <p class="text-lg text-white mb-8">
                Agende uma visita e descubra como podemos ajudar no desenvolvimento do
                seu filho.
            </p>
            <button onclick="window.location.href='matricula.html'"
                class="bg-orange-400 hover:bg-orange-500 text-white px-6 py-2 rounded-lg font-bold transition">
                Quero falar com CECI
            </button>
        </div>
    </section>

    <!-- SEÇÃO SOBRE -->
    <section id="ensino" class="py-16 md:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-azul-principal">
                Ensino de Excelência
            </h2>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-azul-claro p-8 rounded-lg hover:shadow-lg transition">
                    <div class="text-5xl mb-4">👩‍🏫</div>
                    <h3 class="text-xl font-bold text-azul-principal mb-3">
                        Professores Qualificados
                    </h3>
                    <p class="text-gray-700">
                        Nossa equipe é composta por profissionais experientes e dedicados
                        ao desenvolvimento das crianças.
                    </p>
                </div>

                <div class="bg-verde-claro p-8 rounded-lg hover:shadow-lg transition">
                    <div class="text-5xl mb-4">🏫</div>
                    <h3 class="text-xl font-bold text-verde-secundaria mb-3">
                        Infraestrutura Completa
                    </h3>
                    <p class="text-gray-700">
                        Contamos com salas amplas, áreas de lazer seguras e materiais
                        pedagógicos modernos.
                    </p>
                </div>

                <div class="p-8 rounded-lg hover:shadow-lg transition" style="background-color: #fff9f0">
                    <div class="text-5xl mb-4">💚</div>
                    <h3 class="text-xl font-bold text-verde-secundaria mb-3">
                        Educação com Amor
                    </h3>
                    <p class="text-gray-700">
                        Acreditamos que cada criança é especial e merece atenção
                        individualizada e carinho.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- SEÇÃO PROPOSTA PEDAGÓGICA -->
    <section id="proposta" class="py-16 md:py-24 bg-verde-secundaria">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-8 text-white">
                Proposta Pedagógica
            </h2>

            <div class="max-w-4xl mx-auto text-center mb-8">
                <p class="text-white text-lg">
                    Acompanhamento individual que estimula autonomia, empatia e a
                    alegria de aprender. Utilizamos metodologias ativas — projetos,
                    brincadeiras e tecnologia — fundamentadas em respeito, criatividade
                    e cooperação, com forte parceria família‑escola em um ambiente
                    seguro e afetivo.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="p-6 rounded-lg shadow-sm hover:shadow-md transition bg-azul-claro">
                    <div class="text-4xl mb-3">🎯</div>
                    <h3 class="text-lg font-bold text-azul-principal mb-2">
                        Aprendizagem Significativa
                    </h3>
                    <p class="text-gray-700 text-sm">
                        Atividades planejadas para conectar experiências da criança ao
                        conteúdo, promovendo compreensão profunda.
                    </p>
                </div>

                <div class="p-6 rounded-lg shadow-sm hover:shadow-md transition bg-verde-claro">
                    <div class="text-4xl mb-3">🤝</div>
                    <h3 class="text-lg font-bold text-verde-secundaria mb-2">
                        Envolvimento Familiar
                    </h3>
                    <p class="text-gray-700 text-sm">
                        Trabalhamos em parceria com famílias para apoiar o desenvolvimento
                        socioemocional e cognitivo.
                    </p>
                </div>

                <div class="p-6 rounded-lg hover:shadow-md transition" style="background-color: #fff9f0">
                    <div class="text-4xl mb-3">🎨</div>
                    <h3 class="text-lg font-bold" style="color: #ff9500">
                        Desenvolvimento Integral
                    </h3>
                    <p class="text-gray-700 text-sm">
                        Integramos aspectos motor, afetivo, social e intelectual em nossas
                        propostas diárias.
                    </p>
                </div>
            </div>

            <div class="text-center mt-8">
                <button onclick="window.location.href='matricula.html'"
                    class="inline-block bg-azul-principal text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition">
                    Leia a proposta completa
                </button>
            </div>
        </div>
    </section>

    <!-- SEÇÃO FUNCIONALIDADES -->
    <section id="atividades" class="py-16 md:py-24 bg-laranja-accent">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-azul-principal">
                Nossas Atividades
            </h2>

            <div class="grid md:grid-cols-2 gap-8">
                <div class="bg-white p-8 rounded-lg shadow-sm hover:shadow-md transition">
                    <div class="flex gap-4 items-start">
                        <div class="text-4xl">👨‍👩‍👧</div>
                        <div>
                            <h3 class="text-xl font-bold text-verde-secundaria mb-2">
                                Envolvimento Familiar
                            </h3>
                            <p class="text-gray-700">
                                Comunicação constante com pais e eventos especiais para
                                integração da família.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Educação Nutricional -->
                <div class="bg-white p-8 rounded-lg shadow-sm hover:shadow-md transition">
                    <div class="flex gap-4 items-start">
                        <div class="text-4xl">🥗</div>
                        <div>
                            <h3 class="text-xl font-bold text-azul-principal mb-2">
                                Educação Nutricional
                            </h3>
                            <p class="text-gray-700">
                                Atividades e oficinas para ensinar escolhas alimentares
                                saudáveis de forma lúdica.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Robótica -->
                <div class="bg-white p-8 rounded-lg shadow-sm hover:shadow-md transition">
                    <div class="flex gap-4 items-start">
                        <div class="text-4xl">🤖</div>
                        <div>
                            <h3 class="text-xl font-bold text-verde-secundaria mb-2">
                                Robótica
                            </h3>
                            <p class="text-gray-700">
                                Introdução a robôs e kits educativos para desenvolver
                                raciocínio lógico e criatividade.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pensamento Computacional -->
                <div class="bg-white p-8 rounded-lg shadow-sm hover:shadow-md transition">
                    <div class="flex gap-4 items-start">
                        <div class="text-4xl">🧩</div>
                        <div>
                            <h3 class="text-xl font-bold text-azul-principal mb-2">
                                Pensamento Computacional
                            </h3>
                            <p class="text-gray-700">
                                Atividades que desenvolvem decomposição de problemas, padrões
                                e lógica algorítmica.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SEÇÃO MAPA -->
    <section id="mapa" class="py-16 md:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-azul-principal">
                Onde nos encontrar
            </h2>
            <div class="rounded-lg overflow-hidden shadow-md h-96 mb-12">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.7787382026677!2d-34.96332772500126!3d-7.151562892852822!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7aceed7e442a493%3A0xbfa179da597e98db!2sCentro%20Educacional%20Crian%C3%A7a%20Inteligente!5e0!3m2!1spt-BR!2sbr!4v1761261353718!5m2!1spt-BR!2sbr"
                    width="100%" height="100%" style="border: 0" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <!-- SEÇÃO CONTATO -->
    <section id="contato" class="py-16 md:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-azul-principal">
                Entre em Contato
            </h2>

            <div class="grid md:grid-cols-2 gap-12">
                <!-- Formulário -->
                <div class="bg-azul-claro p-8 rounded-lg shadow-sm">
                    <form id="contactForm" onsubmit="return submitForm(event)">
                        <div class="mb-6">
                            <label class="block text-gray-700 font-bold mb-2">Nome</label>
                            <input type="text" name="name"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus-azul focus:outline-none"
                                placeholder="Seu nome" required />
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 font-bold mb-2">Email</label>
                            <input type="email" name="email"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus-azul focus:outline-none"
                                placeholder="seu@email.com" required />
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 font-bold mb-2">Telefone</label>
                            <input type="tel" name="phone"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus-azul focus:outline-none"
                                placeholder="(11) 9XXXX-XXXX" />
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 font-bold mb-2">Mensagem</label>
                            <textarea name="message" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus-azul focus:outline-none h-32"
                                placeholder="Sua mensagem aqui..." required></textarea>
                        </div>

                        <button type="submit"
                            class="w-full text-white px-8 py-3 rounded-lg font-bold transition hover-azul"
                            style="background-color: #0094d2">
                            Enviar Mensagem
                        </button>
                    </form>
                </div>

                <!-- Informações de Contato -->
                <div>
                    <div class="mb-8 bg-azul-claro p-8 rounded-lg shadow-sm">
                        <h3 class="text-2xl font-bold text-azul-principal mb-4">
                            Informações Gerais
                        </h3>
                        <p class="text-gray-700 mb-6">
                            Estamos sempre prontos para responder suas dúvidas e conversar
                            sobre como podemos contribuir para o desenvolvimento do seu
                            filho.
                        </p>

                        <div class="space-y-4">
                            <div class="flex gap-4 items-start">
                                <span class="text-2xl">📍</span>
                                <div>
                                    <p class="font-bold text-gray-800">Endereço</p>
                                    <p class="text-gray-600">
                                        Rua Serra Redonda, 115<br />Santa Rita - PB, 58303-666
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4 items-start">
                                <span class="text-2xl">📞</span>
                                <div>
                                    <p class="font-bold text-gray-800">Telefone</p>
                                    <p class="text-gray-600">(11) 3456-7890</p>
                                </div>
                            </div>

                            <div class="flex gap-4 items-start">
                                <span class="text-2xl">✉️</span>
                                <div>
                                    <p class="font-bold text-gray-800">Email</p>
                                    <p class="text-gray-600">educa.ceci@gmail.com</p>
                                </div>
                            </div>

                            <div class="flex gap-4 items-start">
                                <span class="text-2xl">⏰</span>
                                <div>
                                    <p class="font-bold text-gray-800">Horário</p>
                                    <p class="text-gray-600">
                                        Seg-Sex: 07:00 - 18:00<br />Sábado: 09:00 - 14:00
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-verde-claro p-8 rounded-lg shadow-sm">
                        <h3 class="text-2xl font-bold text-verde-secundaria mb-4">
                            Redes Sociais
                        </h3>
                        <div class="flex gap-4">
                            <a href="#"
                                class="w-12 h-12 rounded-lg flex items-center justify-center text-white transition hover-azul"
                                style="background-color: #0094d2">f</a>
                            <a href="#"
                                class="w-12 h-12 rounded-lg flex items-center justify-center text-white transition hover-azul"
                                style="background-color: #0094d2">📷</a>
                            <a href="#"
                                class="w-12 h-12 rounded-lg flex items-center justify-center text-white transition hover-azul"
                                style="background-color: #0094d2">🎬</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-azul-principal text-white">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid md:grid-cols-4 gap-8">
                <!-- Logo e Descrição -->
                <div class="col-span-1">
                    <img src="assets/images/logo-white.png" alt="Centro Educacional Criança Inteligente"
                        class="h-16 mb-4" />
                    <p class="text-sm">
                        Educação de qualidade e desenvolvimento integral para seu filho.
                    </p>
                </div>

                <!-- Links Rápidos -->
                <div>
                    <h4 class="font-bold text-lg mb-4">Links Rápidos</h4>
                    <ul class="space-y-2">
                        <li>
                            <a href="/" class="hover:text-azul-claro transition">Início</a>
                        </li>
                        <li>
                            <a href="#ensino" class="hover:text-azul-claro transition">Ensino</a>
                        </li>
                        <li>
                            <a href="#proposta" class="hover:text-azul-claro transition">Proposta</a>
                        </li>
                        <li>
                            <a href="#atividades" class="hover:text-azul-claro transition">Atividades</a>
                        </li>
                        <li>
                            <a href="matricula.html" class="hover:text-azul-claro transition">Matrícula</a>
                        </li>
                    </ul>
                </div>

                <!-- Contato -->
                <div>
                    <h4 class="font-bold text-lg mb-4">Contato</h4>
                    <ul class="space-y-2">
                        <li>📞 (11) 3456-7890</li>
                        <li>✉️ educa.ceci@gmail.com</li>
                        <li>📍 Rua Serra Redonda, 115<br />Santa Rita - PB, 58303-666</li>
                    </ul>
                </div>

                <!-- Horário de Funcionamento -->
                <div>
                    <h4 class="font-bold text-lg mb-4">Horário</h4>
                    <ul class="space-y-2">
                        <li>Segunda a Sexta</li>
                        <li>07:00 - 18:00</li>
                        <li>Sábado</li>
                        <li>09:00 - 14:00</li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-white/20">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm mb-4 md:mb-0">
                        © 2025 Centro Educacional Criança Inteligente. Todos os direitos
                        reservados.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="hover:text-azul-claro transition">
                            Política de Privacidade
                        </a>
                        <a href="#" class="hover:text-azul-claro transition">
                            Termos de Uso
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle Mobile Menu
        const mobileMenuButton = document.getElementById("mobileMenuButton");
        const mobileMenu = document.getElementById("mobileMenu");

        mobileMenuButton.addEventListener("click", () => {
            mobileMenu.classList.toggle("hidden");
        });

        // Close mobile menu when clicking outside
        document.addEventListener("click", (e) => {
            if (
                !mobileMenu.contains(e.target) &&
                !mobileMenuButton.contains(e.target) &&
                !mobileMenu.classList.contains("hidden")
            ) {
                mobileMenu.classList.add("hidden");
            }
        });

        // Form submission
        function submitForm(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            // Show success message (in production, you'd handle this after API call)
            alert("Mensagem enviada com sucesso! Entraremos em contato em breve.");
            event.target.reset();

            return false;
        }
    </script>
</body>

</html>
