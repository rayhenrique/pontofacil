@extends('layouts.landing')

@section('content')
    <!-- Navbar Flutuante -->
    <x-landing.navbar />

    <!-- Conteúdo Principal na Nova Ordem -->
    <main class="flex-1">
        <!-- 1. Hero -->
        <x-landing.hero />

        <!-- 2. Como funciona -->
        <x-landing.how-it-works />

        <!-- 3. Para o colaborador / Para a empresa -->
        <x-landing.comparison />

        <!-- 4. Principais recursos -->
        <x-landing.features />

        <!-- 5. Segurança e rastreabilidade -->
        <x-landing.security />

        <!-- 6. Portaria 671 / REP-P + PTRP -->
        <x-landing.compliance />

        <!-- 7. Demonstração da interface -->
        <x-landing.interactive-preview />
    </main>

    <!-- 8. CTA final & Rodapé -->
    <x-landing.cta-footer />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Verificar preferência de movimento reduzido (Acessibilidade)
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            // Em modo reduzido, manter layout estático limpo sem disparar animações nem loops
            return;
        }

        // Registrar o plugin ScrollTrigger se disponível
        if (typeof gsap === 'undefined') return;
        if (typeof ScrollTrigger !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger);
        }

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
        // Regra Estrita: Conteúdo SEMPRE 100% visível; GSAP adiciona apenas movimento leve (y: 12-16) sem opacity: 0
        mm.add("(min-width: 768px)", () => {
            // Movimento sutil de entrada da Hero Section
            const heroTl = gsap.timeline({ defaults: { ease: 'power2.out', duration: 0.6 } });
            heroTl.from(".gsap-hero-element", {
                y: 16,
                duration: 0.6,
                stagger: 0.08,
                clearProps: "all"
            })
            .from(".gsap-hero-mockup", {
                y: 20,
                duration: 0.7,
                clearProps: "all"
            }, "-=0.3");

            // Animação sutil de Como Funciona
            gsap.from(".gsap-step-card", {
                scrollTrigger: {
                    trigger: "#como-funciona",
                    start: "top 85%",
                    once: true
                },
                y: 16,
                duration: 0.5,
                stagger: 0.1,
                ease: "power2.out",
                clearProps: "all"
            });

            // Animação sutil dos Cards de Colaborador vs Empresa
            gsap.from(".gsap-comparison-card", {
                scrollTrigger: {
                    trigger: "#para-empresas",
                    start: "top 85%",
                    once: true
                },
                y: 16,
                duration: 0.5,
                stagger: 0.12,
                ease: "power2.out",
                clearProps: "all"
            });

            // Animação sutil dos Recursos Principais
            gsap.from(".gsap-feature-card", {
                scrollTrigger: {
                    trigger: "#recursos",
                    start: "top 85%",
                    once: true
                },
                y: 16,
                duration: 0.5,
                stagger: 0.1,
                ease: "power2.out",
                clearProps: "all"
            });

            // Animação sutil dos Pilares de Segurança
            gsap.from(".gsap-security-card", {
                scrollTrigger: {
                    trigger: "#seguranca",
                    start: "top 85%",
                    once: true
                },
                y: 16,
                duration: 0.5,
                stagger: 0.1,
                ease: "power2.out",
                clearProps: "all"
            });
        });

        // Recalcular posições do ScrollTrigger após montagem completa
        setTimeout(() => {
            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.refresh();
            }
        }, 150);
    });
</script>
@endpush
