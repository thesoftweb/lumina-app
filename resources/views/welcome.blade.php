<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Centro Educacional Criança Inteligente</title>
    <meta name="description"
        content="Centro Educacional Criança Inteligente, em Santa Rita - PB: ensino de excelência, proposta pedagógica com metodologias ativas e pré-matrícula online." />
    <meta name="theme-color" content="#0094d2" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "azul-principal": "#0094d2",
                        "azul-hover": "#0077a8",
                        "azul-escuro": "#00608a",
                        "azul-claro": "#e6f3f9",
                        "verde-principal": "#4caf50",
                        "verde-hover": "#3d8b40",
                        "verde-claro": "#e8f5e9",
                        "verde-secundaria": "#2e7d32",
                        "laranja-accent": "#fff3e0",
                        "laranja-escuro": "#b45309",
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

        html {
            scroll-behavior: smooth;
            /* Âncoras não ficam escondidas atrás do cabeçalho fixo */
            scroll-padding-top: 5rem;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }

        /* Campos com 16px no mínimo: o iOS não dá zoom ao focar */
        input,
        textarea,
        select {
            font-size: 1rem;
        }
    </style>
</head>

<body class="bg-white text-gray-800 antialiased pb-20 md:pb-0">
    <a href="#conteudo"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:bg-white focus:text-azul-escuro focus:px-4 focus:py-3 focus:rounded-lg focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-azul-principal">
        Pular para o conteúdo
    </a>

    <!-- HEADER -->
    <header class="sticky top-0 z-50 w-full bg-white/95 backdrop-blur border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16 md:h-20">
            <!-- Logo -->
            <a href="/"
                class="flex items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">
                <img src="{{ asset('assets/images/positive.png') }}" alt="Centro Educacional Criança Inteligente — página inicial"
                    width="3039" height="816" class="h-11 sm:h-12 md:h-16 w-auto" />
            </a>

            <!-- Menu Desktop -->
            <nav class="hidden md:flex items-center gap-6 lg:gap-8" aria-label="Menu principal">
                <a href="/" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Início</a>
                <a href="#ensino" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Ensino</a>
                <a href="#proposta" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Proposta</a>
                <a href="#atividades" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Atividades</a>
                <a href="#contato" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Contato</a>
                <a href="{{ route('portal.login') }}" class="text-gray-700 hover:text-azul-principal font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">Portal</a>
                <a href="{{ route('pre-enrollment.index') }}"
                    class="bg-azul-escuro hover:bg-azul-hover text-white px-5 py-2.5 rounded-lg font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                    Pré-matrícula
                </a>
            </nav>

            <!-- Botão do menu mobile -->
            <button type="button" id="mobileMenuButton"
                class="md:hidden -mr-2 inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-700 hover:text-azul-principal hover:bg-azul-claro focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal"
                aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu">
                <svg id="mobileMenuIconOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg id="mobileMenuIconClose" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Menu mobile -->
        <nav id="mobileMenu" aria-label="Menu principal"
            class="hidden md:hidden absolute inset-x-0 top-full bg-white border-b border-gray-200 shadow-lg max-h-[calc(100vh-4rem)] overflow-y-auto">
            <ul class="max-w-7xl mx-auto px-4 py-2 divide-y divide-gray-100">
                <li><a href="/" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Início</a></li>
                <li><a href="#ensino" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Ensino</a></li>
                <li><a href="#proposta" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Proposta</a></li>
                <li><a href="#atividades" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Atividades</a></li>
                <li><a href="#contato" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Contato</a></li>
                <li><a href="{{ route('portal.login') }}" class="block py-3 text-lg text-gray-700 hover:text-azul-principal font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">Portal do responsável</a></li>
                <li class="py-4">
                    <a href="{{ route('pre-enrollment.index') }}"
                        class="block w-full text-center bg-azul-escuro hover:bg-azul-hover text-white px-6 py-3 rounded-lg font-bold text-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                        Pré-matrícula
                    </a>
                </li>
            </ul>
        </nav>
    </header>

    <main id="conteudo" tabindex="-1" class="focus:outline-none">
        <!-- BANNER/HERO -->
        <section id="inicio" class="relative bg-verde-secundaria" aria-labelledby="titulo-principal">
            <h1 id="titulo-principal" class="sr-only">Centro Educacional Criança Inteligente</h1>
            <img src="{{ asset('assets/images/banner_home.jpg') }}" alt=""
                width="1600" height="600" fetchpriority="high"
                class="block w-full h-auto max-w-[1600px] mx-auto aspect-[8/3] object-cover object-center" />
        </section>

        <!-- SEÇÃO CTA -->
        <section id="cta" class="py-12 md:py-24 bg-azul-escuro text-center" aria-labelledby="titulo-cta">
            <div class="max-w-3xl mx-auto px-4">
                <h2 id="titulo-cta" class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-4 md:mb-6">
                    Reserve sua Matrícula
                </h2>
                <p class="text-base sm:text-lg text-white mb-8">
                    Agende uma visita e descubra como podemos ajudar no desenvolvimento do
                    seu filho.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('pre-enrollment.index') }}"
                        class="inline-flex items-center justify-center min-h-[44px] bg-white text-azul-escuro hover:bg-azul-claro px-6 py-3 rounded-lg font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-azul-escuro focus-visible:ring-white">
                        Fazer pré-matrícula
                    </a>
                    <a href="#contato"
                        class="inline-flex items-center justify-center min-h-[44px] border-2 border-white text-white hover:bg-white/10 px-6 py-3 rounded-lg font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-azul-escuro focus-visible:ring-white">
                        Quero falar com CECI
                    </a>
                </div>
            </div>
        </section>

        <!-- SEÇÃO SOBRE -->
        <section id="ensino" class="py-12 md:py-24 bg-white" aria-labelledby="titulo-ensino">
            <div class="max-w-7xl mx-auto px-4">
                <h2 id="titulo-ensino" class="text-3xl sm:text-4xl md:text-5xl font-bold text-center mb-8 md:mb-12 text-azul-escuro">
                    Ensino de Excelência
                </h2>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                    <div class="bg-azul-claro p-6 sm:p-8 rounded-lg hover:shadow-lg transition">
                        <div class="text-4xl sm:text-5xl mb-4" aria-hidden="true">👩‍🏫</div>
                        <h3 class="text-xl font-bold text-azul-escuro mb-3">
                            Professores Qualificados
                        </h3>
                        <p class="text-gray-700">
                            Nossa equipe é composta por profissionais experientes e dedicados
                            ao desenvolvimento das crianças.
                        </p>
                    </div>

                    <div class="bg-verde-claro p-6 sm:p-8 rounded-lg hover:shadow-lg transition">
                        <div class="text-4xl sm:text-5xl mb-4" aria-hidden="true">🏫</div>
                        <h3 class="text-xl font-bold text-verde-secundaria mb-3">
                            Infraestrutura Completa
                        </h3>
                        <p class="text-gray-700">
                            Contamos com salas amplas, áreas de lazer seguras e materiais
                            pedagógicos modernos.
                        </p>
                    </div>

                    <div class="p-6 sm:p-8 rounded-lg hover:shadow-lg transition sm:col-span-2 lg:col-span-1" style="background-color: #fff9f0">
                        <div class="text-4xl sm:text-5xl mb-4" aria-hidden="true">💚</div>
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
        <section id="proposta" class="py-12 md:py-24 bg-verde-secundaria" aria-labelledby="titulo-proposta">
            <div class="max-w-7xl mx-auto px-4">
                <h2 id="titulo-proposta" class="text-3xl sm:text-4xl md:text-5xl font-bold text-center mb-6 md:mb-8 text-white">
                    Proposta Pedagógica
                </h2>

                <div class="max-w-4xl mx-auto text-center mb-8">
                    <p class="text-white text-base sm:text-lg">
                        Acompanhamento individual que estimula autonomia, empatia e a
                        alegria de aprender. Utilizamos metodologias ativas — projetos,
                        brincadeiras e tecnologia — fundamentadas em respeito, criatividade
                        e cooperação, com forte parceria família‑escola em um ambiente
                        seguro e afetivo.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <div class="p-6 rounded-lg shadow-sm hover:shadow-md transition bg-azul-claro">
                        <div class="text-4xl mb-3" aria-hidden="true">🎯</div>
                        <h3 class="text-lg font-bold text-azul-escuro mb-2">
                            Aprendizagem Significativa
                        </h3>
                        <p class="text-gray-700">
                            Atividades planejadas para conectar experiências da criança ao
                            conteúdo, promovendo compreensão profunda.
                        </p>
                    </div>

                    <div class="p-6 rounded-lg shadow-sm hover:shadow-md transition bg-verde-claro">
                        <div class="text-4xl mb-3" aria-hidden="true">🤝</div>
                        <h3 class="text-lg font-bold text-verde-secundaria mb-2">
                            Envolvimento Familiar
                        </h3>
                        <p class="text-gray-700">
                            Trabalhamos em parceria com famílias para apoiar o desenvolvimento
                            socioemocional e cognitivo.
                        </p>
                    </div>

                    <div class="p-6 rounded-lg hover:shadow-md transition sm:col-span-2 lg:col-span-1" style="background-color: #fff9f0">
                        <div class="text-4xl mb-3" aria-hidden="true">🎨</div>
                        <h3 class="text-lg font-bold text-laranja-escuro mb-2">
                            Desenvolvimento Integral
                        </h3>
                        <p class="text-gray-700">
                            Integramos aspectos motor, afetivo, social e intelectual em nossas
                            propostas diárias.
                        </p>
                    </div>
                </div>

                <div class="text-center mt-8">
                    <a href="{{ route('pre-enrollment.index') }}"
                        class="inline-flex items-center justify-center min-h-[44px] w-full sm:w-auto bg-white text-verde-secundaria px-6 py-3 rounded-lg font-bold hover:bg-verde-claro transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-verde-secundaria focus-visible:ring-white">
                        Faça a pré-matrícula
                    </a>
                </div>
            </div>
        </section>

        <!-- SEÇÃO FUNCIONALIDADES -->
        <section id="atividades" class="py-12 md:py-24 bg-laranja-accent" aria-labelledby="titulo-atividades">
            <div class="max-w-7xl mx-auto px-4">
                <h2 id="titulo-atividades" class="text-3xl sm:text-4xl md:text-5xl font-bold text-center mb-8 md:mb-12 text-azul-escuro">
                    Nossas Atividades
                </h2>

                <div class="grid sm:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-sm hover:shadow-md transition">
                        <div class="flex gap-4 items-start">
                            <div class="text-3xl sm:text-4xl shrink-0" aria-hidden="true">👨‍👩‍👧</div>
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
                    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-sm hover:shadow-md transition">
                        <div class="flex gap-4 items-start">
                            <div class="text-3xl sm:text-4xl shrink-0" aria-hidden="true">🥗</div>
                            <div>
                                <h3 class="text-xl font-bold text-azul-escuro mb-2">
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
                    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-sm hover:shadow-md transition">
                        <div class="flex gap-4 items-start">
                            <div class="text-3xl sm:text-4xl shrink-0" aria-hidden="true">🤖</div>
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
                    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-sm hover:shadow-md transition">
                        <div class="flex gap-4 items-start">
                            <div class="text-3xl sm:text-4xl shrink-0" aria-hidden="true">🧩</div>
                            <div>
                                <h3 class="text-xl font-bold text-azul-escuro mb-2">
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
        <section id="mapa" class="py-12 md:py-24 bg-white" aria-labelledby="titulo-mapa">
            <div class="max-w-7xl mx-auto px-4">
                <h2 id="titulo-mapa" class="text-3xl sm:text-4xl md:text-5xl font-bold text-center mb-8 md:mb-12 text-azul-escuro">
                    Onde nos encontrar
                </h2>
                <div class="rounded-lg overflow-hidden shadow-md aspect-[4/3] sm:aspect-video lg:aspect-auto lg:h-96">
                    <iframe title="Mapa com a localização do Centro Educacional Criança Inteligente"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.7787382026677!2d-34.96332772500126!3d-7.151562892852822!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7aceed7e442a493%3A0xbfa179da597e98db!2sCentro%20Educacional%20Crian%C3%A7a%20Inteligente!5e0!3m2!1spt-BR!2sbr!4v1761261353718!5m2!1spt-BR!2sbr"
                        class="block w-full h-full border-0" allowfullscreen loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <p class="mt-4 text-center">
                    <a href="https://maps.google.com/?q=Centro+Educacional+Crian%C3%A7a+Inteligente+Santa+Rita+PB"
                        target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center min-h-[44px] text-azul-escuro font-semibold underline underline-offset-4 hover:text-azul-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal rounded">
                        Abrir no Google Maps<span class="sr-only"> (abre em nova aba)</span>
                    </a>
                </p>
            </div>
        </section>

        <!-- SEÇÃO CONTATO -->
        <section id="contato" class="py-12 md:py-24 bg-white" aria-labelledby="titulo-contato">
            <div class="max-w-7xl mx-auto px-4">
                <h2 id="titulo-contato" class="text-3xl sm:text-4xl md:text-5xl font-bold text-center mb-8 md:mb-12 text-azul-escuro">
                    Entre em Contato
                </h2>

                <div class="grid lg:grid-cols-2 gap-6 lg:gap-12">
                    <!-- Formulário -->
                    <div class="bg-azul-claro p-5 sm:p-8 rounded-lg shadow-sm">
                        <form id="contactForm" onsubmit="return submitForm(event)">
                            <div class="mb-5">
                                <label for="contact-name" class="block text-gray-700 font-bold mb-2">Nome</label>
                                <input type="text" id="contact-name" name="name" autocomplete="name"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-azul-principal focus:ring-2 focus:ring-azul-principal/30"
                                    placeholder="Seu nome" required />
                            </div>

                            <div class="mb-5">
                                <label for="contact-email" class="block text-gray-700 font-bold mb-2">Email</label>
                                <input type="email" id="contact-email" name="email" autocomplete="email" inputmode="email"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-azul-principal focus:ring-2 focus:ring-azul-principal/30"
                                    placeholder="seu@email.com" required />
                            </div>

                            <div class="mb-5">
                                <label for="contact-phone" class="block text-gray-700 font-bold mb-2">Telefone</label>
                                <input type="tel" id="contact-phone" name="phone" autocomplete="tel" inputmode="tel"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-azul-principal focus:ring-2 focus:ring-azul-principal/30"
                                    placeholder="(83) 9XXXX-XXXX" />
                            </div>

                            <div class="mb-6">
                                <label for="contact-message" class="block text-gray-700 font-bold mb-2">Mensagem</label>
                                <textarea id="contact-message" name="message" rows="5"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-azul-principal focus:ring-2 focus:ring-azul-principal/30"
                                    placeholder="Sua mensagem aqui..." required></textarea>
                            </div>

                            <button type="submit"
                                class="w-full min-h-[44px] bg-azul-escuro hover:bg-azul-hover text-white px-8 py-3 rounded-lg font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                                Enviar Mensagem
                            </button>
                            <p id="contactFormStatus" class="mt-4 text-verde-secundaria font-semibold hidden" role="status" aria-live="polite"></p>
                        </form>
                    </div>

                    <!-- Informações de Contato -->
                    <div class="space-y-6">
                        <div class="bg-azul-claro p-5 sm:p-8 rounded-lg shadow-sm">
                            <h3 class="text-2xl font-bold text-azul-escuro mb-4">
                                Informações Gerais
                            </h3>
                            <p class="text-gray-700 mb-6">
                                Estamos sempre prontos para responder suas dúvidas e conversar
                                sobre como podemos contribuir para o desenvolvimento do seu
                                filho.
                            </p>

                            <dl class="space-y-4">
                                <div class="flex gap-4 items-start">
                                    <span class="text-2xl" aria-hidden="true">📍</span>
                                    <div>
                                        <dt class="font-bold text-gray-800">Endereço</dt>
                                        <dd class="text-gray-700">
                                            Rua Serra Redonda, 115<br />Santa Rita - PB, 58303-666
                                        </dd>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-start">
                                    <span class="text-2xl" aria-hidden="true">📞</span>
                                    <div>
                                        <dt class="font-bold text-gray-800">Telefone</dt>
                                        <dd><a href="tel:+551134567890" class="text-gray-700 underline underline-offset-4 hover:text-azul-escuro">(11) 3456-7890</a></dd>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-start">
                                    <span class="text-2xl" aria-hidden="true">✉️</span>
                                    <div class="min-w-0">
                                        <dt class="font-bold text-gray-800">Email</dt>
                                        <dd><a href="mailto:educa.ceci@gmail.com" class="text-gray-700 underline underline-offset-4 hover:text-azul-escuro break-all">educa.ceci@gmail.com</a></dd>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-start">
                                    <span class="text-2xl" aria-hidden="true">⏰</span>
                                    <div>
                                        <dt class="font-bold text-gray-800">Horário</dt>
                                        <dd class="text-gray-700">
                                            Seg-Sex: 07:00 - 18:00<br />Sábado: 09:00 - 14:00
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-verde-claro p-5 sm:p-8 rounded-lg shadow-sm">
                            <h3 class="text-2xl font-bold text-verde-secundaria mb-4">
                                Redes Sociais
                            </h3>
                            <div class="flex gap-4">
                                <a href="#" aria-label="Facebook"
                                    class="w-12 h-12 rounded-lg flex items-center justify-center text-white font-bold transition bg-azul-escuro hover:bg-azul-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                                    <span aria-hidden="true">f</span></a>
                                <a href="#" aria-label="Instagram"
                                    class="w-12 h-12 rounded-lg flex items-center justify-center text-white transition bg-azul-escuro hover:bg-azul-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                                    <span aria-hidden="true">📷</span></a>
                                <a href="#" aria-label="YouTube"
                                    class="w-12 h-12 rounded-lg flex items-center justify-center text-white transition bg-azul-escuro hover:bg-azul-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                                    <span aria-hidden="true">🎬</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="bg-azul-escuro text-white">
        <div class="max-w-7xl mx-auto px-4 py-10 md:py-12">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Nome e Descrição -->
                <div>
                    <p class="font-bold text-xl mb-3">Centro Educacional Criança Inteligente</p>
                    <p class="text-sm">
                        Educação de qualidade e desenvolvimento integral para seu filho.
                    </p>
                </div>

                <!-- Links Rápidos -->
                <nav aria-label="Links rápidos">
                    <h2 class="font-bold text-lg mb-4">Links Rápidos</h2>
                    <ul class="space-y-1">
                        <li><a href="/" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Início</a></li>
                        <li><a href="#ensino" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Ensino</a></li>
                        <li><a href="#proposta" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Proposta</a></li>
                        <li><a href="#atividades" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Atividades</a></li>
                        <li><a href="{{ route('pre-enrollment.index') }}" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Pré-matrícula</a></li>
                        <li><a href="{{ route('portal.login') }}" class="inline-block py-1 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Portal do responsável</a></li>
                    </ul>
                </nav>

                <!-- Contato -->
                <div>
                    <h2 class="font-bold text-lg mb-4">Contato</h2>
                    <ul class="space-y-2">
                        <li><span aria-hidden="true">📞 </span><a href="tel:+551134567890" class="hover:underline underline-offset-4">(11) 3456-7890</a></li>
                        <li class="break-all"><span aria-hidden="true">✉️ </span><a href="mailto:educa.ceci@gmail.com" class="hover:underline underline-offset-4">educa.ceci@gmail.com</a></li>
                        <li><span aria-hidden="true">📍 </span>Rua Serra Redonda, 115<br />Santa Rita - PB, 58303-666</li>
                    </ul>
                </div>

                <!-- Horário de Funcionamento -->
                <div>
                    <h2 class="font-bold text-lg mb-4">Horário</h2>
                    <ul class="space-y-2">
                        <li>Segunda a Sexta</li>
                        <li>07:00 - 18:00</li>
                        <li>Sábado</li>
                        <li>09:00 - 14:00</li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 pt-8 border-t border-white/20">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
                    <p class="text-sm">
                        © {{ date('Y') }} Centro Educacional Criança Inteligente. Todos os direitos
                        reservados.
                    </p>
                    <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
                        <a href="#" class="hover:underline underline-offset-4">
                            Política de Privacidade
                        </a>
                        <a href="#" class="hover:underline underline-offset-4">
                            Termos de Uso
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Barra de ação fixa no celular -->
    <div class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200 px-4 pt-3"
        style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom))">
        <div class="flex gap-3">
            <a href="#contato"
                class="flex-1 inline-flex items-center justify-center min-h-[44px] rounded-lg border-2 border-azul-escuro text-azul-escuro font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-azul-principal">
                Contato
            </a>
            <a href="{{ route('pre-enrollment.index') }}"
                class="flex-[2] inline-flex items-center justify-center min-h-[44px] rounded-lg bg-azul-escuro hover:bg-azul-hover text-white font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-azul-principal">
                Pré-matrícula
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        (() => {
            const button = document.getElementById("mobileMenuButton");
            const menu = document.getElementById("mobileMenu");
            const iconOpen = document.getElementById("mobileMenuIconOpen");
            const iconClose = document.getElementById("mobileMenuIconClose");

            const setOpen = (open, returnFocus = false) => {
                menu.classList.toggle("hidden", !open);
                iconOpen.classList.toggle("hidden", open);
                iconClose.classList.toggle("hidden", !open);
                button.setAttribute("aria-expanded", String(open));
                button.setAttribute("aria-label", open ? "Fechar menu" : "Abrir menu");
                if (returnFocus) button.focus();
            };

            button.addEventListener("click", () => setOpen(menu.classList.contains("hidden")));

            menu.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => setOpen(false)));

            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && !menu.classList.contains("hidden")) setOpen(false, true);
            });

            document.addEventListener("click", (e) => {
                if (!menu.contains(e.target) && !button.contains(e.target) && !menu.classList.contains("hidden")) {
                    setOpen(false);
                }
            });

            window.matchMedia("(min-width: 768px)").addEventListener("change", (e) => {
                if (e.matches) setOpen(false);
            });
        })();

        // O formulário ainda não envia para o servidor: só confirma na tela
        function submitForm(event) {
            event.preventDefault();
            const status = document.getElementById("contactFormStatus");
            status.textContent = "Mensagem enviada com sucesso! Entraremos em contato em breve.";
            status.classList.remove("hidden");
            event.target.reset();

            return false;
        }
    </script>
</body>

</html>
