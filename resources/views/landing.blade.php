@extends('layouts.landing')

@section('content')
    <!-- Navbar Flutuante -->
    <x-landing.navbar />

    <!-- Conteúdo Principal -->
    <main class="flex-1">
        <!-- Sec 2: Hero Section (A Nova Era do Ponto & Relógio ao Vivo) -->
        <x-landing.hero />

        <!-- Sec 3: O Conflito (Jeito Antigo vs. PontoFácil) -->
        <x-landing.comparison />

        <!-- Sec 4: Tríade de Poder (Core Features da Portaria 671) -->
        <x-landing.features />

        <!-- Sec 5: Demonstração Interativa (Tabs Colaborador vs Gestor) -->
        <x-landing.interactive-preview />

        <!-- Sec 6: Timeline de Evolução (Histórico do Sistema) -->
        <x-landing.timeline />
    </main>

    <!-- Sec 7: CTA Final & Rodapé Executivo -->
    <x-landing.cta-footer />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof gsap === 'undefined') return;

        // Registrar o plugin ScrollTrigger
        gsap.registerPlugin(ScrollTrigger);

        // 1. Laser do Scanner QR Code (loop infinito contínuo em todas as resoluções)
        const scannerLaser = document.querySelector('.gsap-scanner-laser');
        if (scannerLaser) {
            gsap.to(scannerLaser, {
                top: '88%',
                duration: 1.6,
                repeat: -1,
                yoyo: true,
                ease: 'power1.inOut'
            });
        }

        // matchMedia para performance e responsividade
        const mm = gsap.matchMedia();

        // DISPOSITIVOS DESKTOP & TABLETS (>= 768px)
        mm.add("(min-width: 768px)", () => {
            // Animação de Entrada da Hero Section
            const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
            heroTl.from(".gsap-hero-element", {
                y: 35,
                opacity: 0,
                duration: 0.85,
                stagger: 0.1,
                clearProps: "all"
            })
            .from(".gsap-hero-mockup", {
                scale: 0.94,
                y: 40,
                opacity: 0,
                duration: 1.0,
                ease: "power2.out",
                clearProps: "all"
            }, "-=0.5");

            // Animação dos Cards de Comparativo
            gsap.from(".gsap-comparison-old", {
                scrollTrigger: {
                    trigger: "#comparativo",
                    start: "top 80%",
                    once: true
                },
                x: -30,
                opacity: 0.3,
                duration: 0.8,
                ease: "power2.out"
            });

            gsap.from(".gsap-comparison-new", {
                scrollTrigger: {
                    trigger: "#comparativo",
                    start: "top 80%",
                    once: true
                },
                scale: 0.96,
                opacity: 0,
                duration: 0.85,
                ease: "back.out(1.15)",
                clearProps: "all"
            });

            // Animação dos 3 Cards de Features com Stagger 0.2
            gsap.from(".gsap-feature-card", {
                scrollTrigger: {
                    trigger: "#recursos",
                    start: "top 80%",
                    once: true
                },
                y: 40,
                opacity: 0,
                duration: 0.8,
                stagger: 0.2,
                ease: "power3.out",
                clearProps: "all"
            });

            // Animação da Timeline de Versões com Deslocamento X
            gsap.from(".gsap-timeline-item", {
                scrollTrigger: {
                    trigger: "#evolucao",
                    start: "top 80%",
                    once: true
                },
                x: -30,
                opacity: 0,
                duration: 0.75,
                stagger: 0.2,
                ease: "power2.out",
                clearProps: "all"
            });
        });

        // DISPOSITIVOS MÓVEIS (< 768px)
        mm.add("(max-width: 767px)", () => {
            gsap.from(".gsap-hero-element", {
                y: 20,
                opacity: 0,
                duration: 0.6,
                stagger: 0.08,
                ease: "power2.out",
                clearProps: "all"
            });

            gsap.from(".gsap-hero-mockup", {
                opacity: 0,
                y: 20,
                duration: 0.6,
                ease: "power2.out",
                clearProps: "all"
            });

            gsap.from(".gsap-feature-card", {
                scrollTrigger: {
                    trigger: "#recursos",
                    start: "top 85%",
                    once: true
                },
                opacity: 0,
                y: 20,
                duration: 0.5,
                stagger: 0.15,
                ease: "power2.out",
                clearProps: "all"
            });

            gsap.from(".gsap-timeline-item", {
                scrollTrigger: {
                    trigger: "#evolucao",
                    start: "top 85%",
                    once: true
                },
                opacity: 0,
                x: -15,
                duration: 0.5,
                stagger: 0.15,
                ease: "power2.out",
                clearProps: "all"
            });
        });

        // Recalcular posições do ScrollTrigger
        setTimeout(() => {
            ScrollTrigger.refresh();
        }, 150);
    });
</script>
@endpush
