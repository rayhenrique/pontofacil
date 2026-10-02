# 🎬 PontoFácil 2.0 — Cinematic Landing Page Specification

## 1. Visão Geral
**Objetivo:** Criar uma experiência de rolagem imersiva e cinematográfica para apresentar a plataforma web PontoFácil 2.0.  
**Promessa Central:** "Do Caos da Folha à Precisão em 1 Segundo. O fim das planilhas manuais e o controle total sob a Portaria 671."  
**Conversão (CTA):** Acesso direto à tela de login/sistema funcional (`/login`).  
**Referência Regulatória:** Portaria MTP nº 671/2021 (REP-P / PTRP e REP-A sob CCT/ACT).  

## 2. Público-Alvo e Tom
* **Quem:** Gestores de RH, diretores de PMEs, gestores públicos municipais e departamentos de departamento pessoal.
* **Dor:** Fechamento de folha caótico, ponto manual fraudável, biometria analógica quebrada, risco de passivos trabalhistas.
* **Sentimento Projetado:** Alívio imediato, transparência inquestionável, precisão matemática, modernidade suíça e ausência total de burocracia.

## 3. Design System & Estética
**Vibe:** Swiss-Modern SaaS com Glassmorphism Suave.
* **Fundo (Canvas):** Off-white (`#F8FAFC` / `bg-slate-50`) com `mesh gradients` super sutis no fundo mesclando branco e violeta muito claro (`#EEF2FF`, `#E0E7FE`).
* **Cores Principais:**
  * **Brand Primary:** `#4F46E5` (Índigo vibrante) / Hover: `#4338CA`.
  * **Dark Accent / Executivo:** `#1E1B4B` / `#0F172A` (Textos principais e fundos de contraste).
  * **Success / GPS Active:** `#10B981` (Para indicadores "Ao Vivo" e selos de validação).
* **Tipografia:** 
  * Principal: `Inter` ou `Plus Jakarta Sans`. 
  * Títulos: Pesados e limpos (`tracking-tight`, `font-bold`).
  * Microcopys: Discretas (`text-xs`, `uppercase`, `tracking-widest`).
* **Superfícies:** Cards predominantemente brancos (`#FFFFFF`), bordas ultra finas (`border-slate-200/60`), cantos arredondados macios (`rounded-2xl` a `rounded-3xl`), sombras leves (`shadow-sm`, `shadow-md`) e glassmorphism pontual (`backdrop-blur-md`, `bg-white/80`).

## 4. Estrutura Narrativa (Sequência de Seções)

### Sec 1: Navbar Flutuante
* **Layout:** Fixa no topo, glassmorphism (`backdrop-blur-md`).
* **Elementos:** Logo PontoFácil à esquerda, badge "Portaria 671 / MTP (REP-P & PTRP)" discreta no centro, botão "Acessar Sistema" (Índigo) à direita.

### Sec 2: Hero Section ("A Nova Era do Ponto")
* **Copy:** "Chega de planilhas manuais. Seu fechamento de folha em minutos, não em dias."
* **Sub-copy:** "Controle de ponto eletrônico inteligente. Validação por QR Code e geolocalização exata, 100% aderente à Portaria 671 (REP-P / PTRP) e emissão oficial de Folha A4."
* **CTAs:** "Acessar PontoFácil" (Principal) e "Falar com Consultor" (Secundário/Ghost).
* **Elemento Visual Central:** Mockup interativo reproduzindo a UI real do aplicativo: Relógio digital rodando a hora ao vivo no fuso de Maceió (HH:MM:SS), pulsando indicador verde ativo.

### Sec 3: O Conflito (Jeito Antigo vs. PontoFácil)
* **Layout:** Grid de duas colunas comparativas.
* **O Jeito Antigo:** Tons pastéis avermelhados/cinzas. Texto riscado: "Planilhas manuais quebradas", "Esquecimento de bater o ponto", "Cálculo manual passível de erros", "Risco de multas do MTE".
* **Com o PontoFácil 2.0:** Tons de índigo e verde. "1 toque na câmera com GPS auditado", "Espelho calculado na hora", "Folha de Ponto A4 Oficial pré-preenchida", "Conformidade integral Portaria 671".

### Sec 4: Tríade de Poder (Core Features)
* **Layout:** Três cards grandes com revelação via scroll.
* **Card 1 (Validação Cruzada Antifraude):** Ícones de GPS e QR Code. Cerca virtual com Fórmula de Haversine e fallback inteligente matriz/setor.
* **Card 2 (Folha de Ponto A4 & Espelho Automatizado):** Impressão A4 fiel ao modelo do RH com cadastro funcional do servidor e totalização instantânea.
* **Card 3 (Conformidade Portaria 671):** Selo REP-P e PTRP, trilha imutável de auditoria e carimbo oficial de tempo.

### Sec 5: Demonstração Interativa (Tabs)
* **Controle (Alpine.js):** Tabs simples "Visão Colaborador" vs "Visão Gestor/RH".
* **Visão Colaborador:** Mockup do leitor de QR Code com animação GSAP de laser scanner horizontal em loop contínuo.
* **Visão Gestor/RH:** Mockup da gestão de equipe, cadastro funcional do servidor e emissão de folha oficial.

### Sec 6: Timeline de Evolução (Confiança e Versões)
* **Layout:** Linha do tempo vertical apresentando a evolução contínua da plataforma, destacando a versão ativa (`v1.7.0`) e o marco do modelo oficial A4 e conformidade de instância dedicada.

### Sec 7: CTA Final & Footer Institucional
* **Layout:** Banner largo em gradiente `#4F46E5` para `#1E1B4B`.
* **Copy:** "Sua gestão de ponto pronta para a nova era. Comece agora."
* **Ação:** Botão "Entrar no Sistema" direcionando para `/login`.
* **Rodapé Minimalista:** Menção com hiperlink obrigatório: **Desenvolvido por [KL Tecnologia](https://kltecnologia.com)**, badge de fuso horário `America/Maceio` e direitos reservados.

## 5. Roteiro de Animações (GSAP & ScrollTrigger)
1. **Load inicial:** O relógio do Hero entra de baixo para cima (`y: 50, opacity: 0, duration: 1, ease: 'power3.out'`). O texto surge em `stagger` de 0.1s.
2. **Scroll dos Cards de Comparação:** À medida que entram na tela, as opções do "Jeito Antigo" perdem opacidade (0.4), enquanto as do "PontoFácil" pulam com destaque (`scale: 1.05`).
3. **Features em Stagger:** Os 3 cards da tríade sobem (`y: 40, opacity: 0`) em stagger de 0.2s assim que o topo da seção cruza 80% da tela.
4. **Laser Scanner:** Animação infinita (`yoyo: true, repeat: -1, duration: 1.5, ease: 'linear'`) movendo um feixe horizontal fino sobre o card do scanner.
5. **Responsividade:** O GSAP `matchMedia` desativa elevações 3D pesadas em telas `< 768px`, preservando fluidez e performance mobile.