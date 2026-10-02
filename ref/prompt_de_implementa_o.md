# Prompt de Implementação — PontoFácil Landing Page

**Contexto e Papel:**
Atue como um Desenvolvedor Frontend Senior, Motion Designer e especialista em Laravel, Blade, Tailwind CSS e GSAP (Vibe Coding).
Seu objetivo é implementar a Landing Page Cinematic do "PontoFácil 2.0" (uma solução corporativa de controle de jornada via QR Code e GPS sob a Portaria 671/2021 MTP — REP-P & PTRP). Não crie páginas genéricas; siga rigorosamente as diretrizes visuais e arquiteturais abaixo.

## 1. Stack e Arquitetura Exigida
* **Framework:** Laravel 13.
* **Estilização:** Tailwind CSS v4 (uso extensivo de classes utilitárias, tipografia `font-sans` / Inter, cores de brand `indigo-600` e `slate-900`).
* **Interatividade de UI:** Alpine.js (`x-data` para as tabs e mobile menu).
* **Animações de Scroll e Motion:** GSAP (Core + ScrollTrigger).
* **Estrutura Blade:** Crie os arquivos divididos em componentes anônimos para manter o código limpo:
  * `resources/views/layouts/landing.blade.php` (Estrutura HTML5, `@yield('content')`, importação de fontes, GSAP e Alpine via CDN ou Vite).
  * `resources/views/landing.blade.php` (A página principal unindo os componentes).
  * `resources/views/components/landing/navbar.blade.php`
  * `resources/views/components/landing/hero.blade.php`
  * `resources/views/components/landing/comparison.blade.php`
  * `resources/views/components/landing/features.blade.php`
  * `resources/views/components/landing/interactive-preview.blade.php`
  * `resources/views/components/landing/timeline.blade.php`
  * `resources/views/components/landing/cta-footer.blade.php`

## 2. Design System & Tokens (Tailwind)
* **Cores:** Use as variações de `indigo` para a marca principal, `slate` para textos/bordas e `emerald` para status de sucesso/GPS.
* **Glassmorphism:** Aplique `bg-white/80 backdrop-blur-md border border-white/20 shadow-sm` nas superfícies flutuantes (navbar, cards de features).
* **Fundos (Mesh Gradient simulação):** No `body` ou wrapper principal, crie um fundo sutil: `bg-slate-50 relative overflow-hidden`. Adicione formas absolutas desfocadas: `<div class="absolute top-0 left-1/2 w-96 h-96 bg-indigo-200/40 rounded-full blur-3xl -translate-x-1/2"></div>`.

## 3. Lógica de Funcionalidades Específicas a Implementar

### A. O Relógio em Tempo Real (Hero Section)
* No componente Hero, crie um "mockup" de interface semelhante a um card do sistema.
* **JS puro/Alpine:** Adicione um pequeno script que atualiza o horário do card a cada segundo (formato `HH:MM:SS`) e um indicador bolinha verde pulsando (Tailwind `animate-pulse`), transmitindo precisão "cirúrgica" no fuso oficial `America/Maceio`.

### B. Tabs da Demonstração Interativa (Interactive Preview)
* Use Alpine.js: `<div x-data="{ tab: 'colaborador' }">...</div>`.
* Crie botões para alternar `tab = 'colaborador'` e `tab = 'gestor'`.
* **Visão Colaborador:** Dentro da aba, desenhe um wireframe/card de um escaneador de QR Code. 
* **O Efeito do Scanner (GSAP):** Crie uma div absoluta, de 2px de altura, cor ciano vibrante/índigo claro com box-shadow. No script do final da página, capture este elemento via GSAP e o anime em loop `yoyo` subindo e descendo dentro da caixa do QR Code.

## 4. Roteiro GSAP de Scroll (Inicializar no rodapé ou app.js)
Envolva as animações em `gsap.matchMedia()` para desativar efeitos pesados em telas pequenas.
Registre o `ScrollTrigger`.
1. **Hero:** Ao carregar a página, faça o título, subtítulo e os CTAs subirem (`y: 30`, `opacity: 0`) com `stagger: 0.1`. O mockup do relógio deve entrar flutuando suavemente (`scale: 0.95`, `opacity: 0`).
2. **Features/Cards:** Adicione a classe `.gsap-feature-card` nos 3 cards principais. Use ScrollTrigger para animá-los com `stagger: 0.2` assim que o container `start: "top 80%"` entrar na tela.
3. **Timeline:** Os itens da linha do tempo devem aparecer sequencialmente usando ScrollTrigger e animação de opacidade/deslocamento X (`x: -20` para a direita), exibindo todas as versões até a ativa `v1.7.0`.

## 5. Diretrizes Finais de Conversão & Identidade
* Todos os botões principais devem redirecionar para a URL primária (`/login`).
* O header deve conter a badge de texto "Adequado à Portaria 671 / MTP (REP-P & PTRP)".
* O rodapé deve conter crédito oficial com hiperlink: **Desenvolvido por [KL Tecnologia](https://kltecnologia.com)**.
* Garanta que o layout flua perfeitamente em mobile (empilhar grids usando `md:grid-cols-2` ou `lg:grid-cols-3`).