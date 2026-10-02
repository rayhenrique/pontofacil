# 🎬 PontoFácil - Cinematic Landing Page Specification

## 1. Visão Geral
**Objetivo:** Criar uma experiência de rolagem imersiva e cinematográfica para vender/apresentar o sistema PontoFácil. 
**Promessa Central:** "Do Caos da Folha à Precisão em 1 Segundo. O fim das planilhas manuais."
**Conversão (CTA):** Acesso direto à tela de login/sistema funcional (`/login`).

## 2. Público-Alvo e Tom
* **Quem:** Gestores de RH, donos de PMEs, administradores de órgãos públicos e secretarias municipais.
* **Dor:** Fechamento de folha caótico, ponto manual fraudável, biometria quebrada, risco de multas trabalhistas.
* **Sentimento Projetado:** Alívio imediato, transparência inquestionável, precisão matemática, modernidade suíça e ausência total de burocracia.

## 3. Design System & Estética
**Vibe:** Swiss-Modern SaaS com Glassmorphism Suave.
* **Fundo (Canvas):** Off-white (`#F8FAFC`) com `mesh gradients` super sutis no fundo mesclando branco e violeta muito claro (`#EEF2FF`, `#E0E7FE`).
* **Cores Principais:**
  * **Brand Primary:** `#4F46E5` (Índigo vibrante) / Hover: `#4338CA`.
  * **Dark Accent / Executivo:** `#1E1B4B` / `#0F172A` (Textos principais e fundos de contraste).
  * **Success / GPS Active:** `#10B981` (Para indicadores "Ao Vivo").
* **Tipografia:** 
  * Principal: `Inter` ou `Plus Jakarta Sans`. 
  * Títulos: Pesados e limpos (`tracking-tight`, `font-bold`).
  * Microcopys: Discretas (`text-xs`, `uppercase`, `tracking-widest`).
* **Superfícies:** Cards predominantemente brancos (`#FFFFFF`), bordas ultra finas (`border-slate-200/60`), cantos arredondados macios (`rounded-2xl` a `rounded-3xl`), sombras leves (`shadow-sm`, `shadow-md`) e glassmorphism pontual (`backdrop-blur-md`, `bg-white/80`).

## 4. Estrutura Narrativa (Sequência de Seções)

### Sec 1: Navbar Flutuante
* **Layout:** Fixa no topo, glassmorphism (`backdrop-blur-md`).
* **Elementos:** Logo PontoFácil à esquerda, badge "Portaria 671 (REP-A)" discreta no centro, botão "Acessar Sistema" (Índigo) à direita.

### Sec 2: Hero Section ("A Nova Era do Ponto")
* **Copy:** "Chega de planilhas manuais. Seu fechamento de folha em minutos, não em dias."
* **Sub-copy:** "Controle de ponto eletrônico inteligente. Validação por QR Code dinâmico e geolocalização exata, 100% aderente à Portaria 671."
* **CTAs:** "Acessar PontoFácil" (Principal) e "Falar com Consultor" (Secundário/Ghost).
* **Elemento Visual Central:** Um Mockup interativo. Um card flutuante em perspectiva leve (isométrica) reproduzindo a UI real do aplicativo: Um relógio digital rodando a hora ao vivo (HH:MM:SS), pulsando um ponto verde.

### Sec 3: O Conflito (Jeito Antigo vs. PontoFácil)
* **Layout:** Grid de duas colunas comparativas.
* **O Jeito Antigo:** Tons pastéis avermelhados/cinzas. Texto riscado: "Planilhas de Excel quebradas", "Esquecimento de bater o ponto", "Horas extras não computadas".
* **Com o PontoFácil:** Tons de índigo e verde. "1 toque na câmera", "Espelho calculado na hora", "Auditoria de cada segundo".

### Sec 4: Tríade de Poder (Core Features)
* **Layout:** Três cards grandes com revelação via scroll.
* **Card 1 (Validação Cruzada):** Ícones de GPS e QR Code. Texto sobre a cerca virtual impenetrável.
* **Card 2 (Espelho Automatizado):** Gráfico minimalista de horas extras e normais se auto-preenchendo.
* **Card 3 (Auditoria 671):** Selo de conformidade, imutabilidade de dados.

### Sec 5: Demonstração Interativa (Tabs)
* **Controle (Alpine.js):** Tabs simples "Visão Colaborador" vs "Visão Gestor/RH".
* **Visão Colaborador:** Imagem/Mockup do leitor de QR Code. **Efeito Visual:** Uma barra horizontal (laser) escaneando o mockup infinitamente.
* **Visão Gestor/RH:** Imagem/Mockup de um dashboard de fechamento, evidenciando o ajuste de setor e horas.

### Sec 6: Timeline de Evolução (Confiança)
* **Layout:** Linha do tempo vertical inspirada na tela de "Versões/Changelog" do sistema.
* **Elementos:** Cards menores mostrando "Sempre atualizado com a lei", "Melhorias contínuas de performance", "Servidores cloud blindados".

### Sec 7: CTA Final & Footer
* **Layout:** Um banner largo (full-width) com fundo gradiente `#4F46E5` para `#1E1B4B`.
* **Copy:** "Sua gestão de ponto pronta para a nova era. Comece agora."
* **Ação:** Botão branco "Entrar no Sistema".
* **Rodapé Minimalista:** Menção "Desenvolvido por KL Tecnologia", links úteis e copyright.

## 5. Roteiro de Animações (GSAP & ScrollTrigger)
1. **Load inicial:** O relógio do Hero entra de baixo para cima (`y: 50, opacity: 0, duration: 1, ease: 'power3.out'`). O texto surge em `stagger` de 0.1s.
2. **Scroll dos Cards de Comparação:** À medida que entram na tela, as opções do "Jeito Antigo" perdem opacidade (0.4), enquanto as do "PontoFácil" pulam com destaque (`scale: 1.05`).
3. **Features em Stagger:** Os 3 cards da tríade sobem (`y: 40, opacity: 0`) em stagger de 0.2s assim que o topo da seção cruza 80% da tela.
4. **Laser Scanner:** Animação infinita (`yoyo: true, repeat: -1, duration: 1.5, ease: 'linear'`) movendo um `div` horizontal fino com sombra ciano sobre o card de QR Code da seção de Tabs.
5. **Responsividade:** O GSAP `matchMedia` deve desativar elevações 3D pesadas em telas `< 768px`, mantendo apenas `fade-ins` para não prejudicar performance em mobile.