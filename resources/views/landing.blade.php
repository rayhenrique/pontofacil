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

            // 2. Animação de Como Funciona (Passos 1, 2, 3)
            gsap.from(".gsap-step-card", {
                scrollTrigger: {
                    trigger: "#como-funciona",
                    start: "top 80%",
                    once: true
                },
                y: 35,
                opacity: 0,
                duration: 0.75,
                stagger: 0.18,
                ease: "power3.out",
                clearProps: "all"
            });

            // 3. Animação dos Cards de Colaborador vs Empresa
            gsap.from(".gsap-comparison-card", {
                scrollTrigger: {
                    trigger: "#para-empresas",
                    start: "top 80%",
                    once: true
                },
                y: 35,
                opacity: 0,
                duration: 0.8,
                stagger: 0.2,
                ease: "power3.out",
                clearProps: "all"
            });

            // 4. Animação dos 4 Cards de Recursos com Stagger 0.15
            gsap.from(".gsap-feature-card", {
                scrollTrigger: {
                    trigger: "#recursos",
                    start: "top 80%",
                    once: true
                },
                y: 35,
                opacity: 0,
                duration: 0.8,
                stagger: 0.15,
                ease: "power3.out",
                clearProps: "all"
            });

            // 5. Animação dos 3 Pilares de Segurança
            gsap.from(".gsap-security-card", {
                scrollTrigger: {
                    trigger: "#seguranca",
                    start: "top 80%",
                    once: true
                },
                y: 35,
                opacity: 0,
                duration: 0.75,
                stagger: 0.18,
                ease: "power3.out",
                clearProps: "all"
            });

            // 6. Animação dos 4 Pilares da Portaria 671
            gsap.from(".gsap-compliance-card", {
                scrollTrigger: {
                    trigger: "#portaria-671",
                    start: "top 80%",
                    once: true
                },
                y: 35,
                opacity: 0,
                duration: 0.75,
                stagger: 0.15,
                ease: "power3.out",
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

            gsap.from(".gsap-step-card", {
                scrollTrigger: {
                    trigger: "#como-funciona",
                    start: "top 85%",
                    once: true
                },
                opacity: 0,
                y: 20,
                duration: 0.5,
                stagger: 0.12,
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
                stagger: 0.12,
                ease: "power2.out",
                clearProps: "all"
            });

            gsap.from(".gsap-security-card", {
                scrollTrigger: {
                    trigger: "#seguranca",
                    start: "top 85%",
                    once: true
                },
                opacity: 0,
                y: 20,
                duration: 0.5,
                stagger: 0.12,
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
