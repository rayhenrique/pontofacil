<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>PontoFácil • Controle de Jornada Digital • REP-P + PTRP (Portaria 671)</title>
    <meta name="description" content="Controle de jornada digital • REP-P + PTRP. Arquitetura preparada para a Portaria 671, QR Code e localização como apoio à validação e trilha de auditoria para conformidade fiscal.">
    <meta name="keywords" content="controle de ponto, portaria 671, REP-P, PTRP, ponto eletrônico, QR Code ponto, ponto GPS, espelho de ponto, AFD, AEJ, RH">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="PontoFácil • Controle de Jornada Digital • REP-P + PTRP">
    <meta property="og:description" content="Controle de jornada digital com arquitetura REP-P + PTRP. Fechamento de folha em minutos, QR Code, localização registrada como evidência e trilha de auditoria.">
    <meta property="og:locale" content="pt_BR">
    
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

    <!-- Simulação Mesh Gradient: Efeitos visuais suaves de fundo -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <!-- Mancha 1: Topo Central -->
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[720px] h-[560px] bg-gradient-to-tr from-indigo-300/40 via-indigo-200/30 to-purple-200/35 rounded-full blur-3xl opacity-80"></div>
        
        <!-- Mancha 2: Direita / Meio Superior -->
        <div class="absolute top-[600px] -right-48 w-[640px] h-[640px] bg-indigo-100/50 rounded-full blur-3xl opacity-70"></div>
        
        <!-- Mancha 3: Esquerda / Seção Central -->
        <div class="absolute top-[1600px] -left-48 w-[580px] h-[580px] bg-purple-100/40 rounded-full blur-3xl opacity-60"></div>
        
        <!-- Mancha 4: Fundo Inferior -->
        <div class="absolute top-[2600px] left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-indigo-200/30 rounded-full blur-3xl opacity-50"></div>

        <!-- Grade Sutil de Pontos Tecnológicos -->
        <div class="absolute inset-0 bg-[radial-gradient(#c7d2fe_1px,transparent_1px)] [background-size:24px_24px] [mask-image:radial-gradient(ellipse_70%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-60"></div>
    </div>

    <!-- Conteúdo da Página -->
    <div class="relative z-10 flex flex-col min-h-screen">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
