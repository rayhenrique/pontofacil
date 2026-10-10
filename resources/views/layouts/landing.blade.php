<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>PontoFácil | Controle de Ponto e Gestão de Jornada</title>
    <meta name="description" content="Sistema de controle de ponto e gestão de jornada com registro pelo celular, comprovantes, espelho de ponto, banco de horas, tratamentos e trilha de auditoria.">
    <meta name="keywords" content="ponto eletrônico, controle de jornada, REP-P, PTRP, Portaria 671, banco de horas, espelho de ponto, controle de ponto">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="PontoFácil | Controle de Ponto e Gestão de Jornada">
    <meta property="og:description" content="Sistema de controle de ponto e gestão de jornada com registro pelo celular, comprovantes, espelho de ponto, banco de horas, tratamentos e trilha de auditoria.">
    <meta property="og:locale" content="pt_BR">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="PontoFácil | Controle de Ponto e Gestão de Jornada">
    <meta name="twitter:description" content="Sistema de controle de ponto e gestão de jornada com registro pelo celular, comprovantes, espelho de ponto, banco de horas, tratamentos e trilha de auditoria.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%234F46E5'><path d='M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' stroke='%234F46E5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>">

    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & App Bundle -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js (via CDN for standalone reactivity) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- GSAP & ScrollTrigger -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .font-mono-numbers {
            font-feature-settings: "tnum" 1;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased relative overflow-x-hidden selection:bg-indigo-600 selection:text-white">

    <!-- Fundo Visual Limpo e Sóbrio com Alto Contraste -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <!-- Mancha 1: Topo Central Discreta -->
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-indigo-100/40 rounded-full blur-3xl opacity-35"></div>
        
        <!-- Mancha 2: Direita Discreta -->
        <div class="absolute top-[800px] -right-48 w-[500px] h-[500px] bg-slate-200/35 rounded-full blur-3xl opacity-30"></div>
        
        <!-- Mancha 3: Inferior Suave -->
        <div class="absolute top-[2200px] left-1/2 -translate-x-1/2 w-[600px] h-[500px] bg-indigo-50/50 rounded-full blur-3xl opacity-25"></div>

        <!-- Grade Sutil de Pontos Tecnológicos -->
        <div class="absolute inset-0 bg-[radial-gradient(#cbd5e1_1px,transparent_1px)] [background-size:24px_24px] [mask-image:radial-gradient(ellipse_70%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-35"></div>
    </div>

    <!-- Conteúdo da Página -->
    <div class="relative z-10 flex flex-col min-h-screen">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
