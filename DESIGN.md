# 🎨 PontoFácil 2.0 — Guia Oficial de Design System & Diretrizes de Layout

> **Documento Normativo de Design, Layout, Tipografia e Cores.**  
> Este guia é a referência oficial e obrigatória para todas as telas, componentes, interfaces administrativas e páginas públicas do **PontoFácil 2.0 (REP-P / PTRP sob Portaria MTP nº 671/2021)**.  
> Qualquer nova tela ou refatoração visual deve seguir rigorosamente as regras aqui estabelecidas.

---

## 1. Princípios Fundamentais de Design

* **Swiss-Modern SaaS com Glassmorphism Suave:** Visual limpo, estruturado, profissional, minimalista e de alta densidade informativa, inspirado no design suíço contemporâneo.
* **Transparência e Confiança Forense:** Por ser um sistema de registro e fiscalização de jornada com valor probatório trabalhista, a interface deve transmitir precisão matemática, clareza jurídica e integridade inquestionável.
* **Tema Claro Unificado (Sem "Ilhas Escuras"):** A aplicação interna adota integralmente o tema claro (*light mode* corporativo) com cartões brancos e contraste nítido. Telas administrativas não devem conter blocos escuros isolados (`bg-slate-800`, `bg-slate-900`) que quebrem a continuidade visual.
* **Microinterações e Transições Delicadas:** Animações com durações entre `150ms` e `300ms`, estados de *hover* suaves, cantos arredondados contínuos (`rounded-xl` a `rounded-3xl`) e sombras sutis (`shadow-sm`, `shadow-xs`).
* **Responsividade Nativa:** Todos os elementos devem responder perfeitamente desde telas mobile de 360px (com drawer off-canvas e barra inferior) até monitores ultra-wide 4K.

---

## 2. Paleta de Cores e Tokens Semânticos

A paleta de cores é baseada na escala Tailscaled / OKLCH do Tailwind CSS v4, com ênfase no Índigo Corporativo e contrastes de alta legibilidade.

### 2.1. Canvas, Superfícies e Bordas

| Elemento | Token Tailwind | Hex Estimado | Aplicação |
| :--- | :--- | :--- | :--- |
| **Fundo da Aplicação (App Canvas)** | `bg-gray-100` | `#F3F4F6` | Fundo geral atrás do conteúdo no layout autenticado. |
| **Fundo da Landing Page** | `bg-slate-50` com mesh | `#F8FAFC` | Off-white com mesclas sutis em `#EEF2FF` e `#E0E7FE`. |
| **Cartões e Superfícies (Cards)** | `bg-white` | `#FFFFFF` | Cartões principais, modais, painéis e contêineres de dados. |
| **Superfície Secundária** | `bg-gray-50/70` / `bg-slate-50/80` | `#F9FAFB` | Cabeçalhos de tabela, linhas alternadas, caixas informativas. |
| **Bordas de Cartão e Divisores** | `border-gray-200/80` | `rgba(229,231,235,0.8)` | Borda padrão de todos os cartões brancos. |
| **Bordas Sutis de Divisão** | `divide-gray-100` / `border-gray-100` | `#F3F4F6` | Linhas internas entre registros de listas ou tabelas. |
| **Bordas de Inputs / Controles** | `border-gray-300` | `#D1D5DB` | Borda neutra de selects, caixas de texto e checkboxes. |

### 2.2. Cores da Marca (Brand Primary — Índigo)

| Papel | Token Tailwind | Hex Estimado | Aplicação |
| :--- | :--- | :--- | :--- |
| **Primary Base** | `bg-indigo-600` / `text-indigo-600` | `#4F46E5` | Botões de ação principal, links ativos, destaques da marca. |
| **Primary Hover** | `hover:bg-indigo-700` | `#4338CA` | Estado hover de botões primários. |
| **Primary Active** | `active:bg-indigo-800` | `#3730A3` | Estado de clique de botões primários. |
| **Soft Tint (Fundos Leves)** | `bg-indigo-50` / `text-indigo-700` | `#EEF2FF` / `#4338CA` | Ícones de cabeçalho, badges institucionais, seleções leves. |
| **Bordas Tinted** | `border-indigo-200` | `#C7D2FE` | Contorno para badges e caixas de destaque em índigo. |
| **Sidebar Desktop** | `bg-indigo-900` | `#312E81` | Menu lateral esquerdo do layout autenticado. |
| **Sidebar Brand Header** | `bg-indigo-950` | `#1E1B4B` | Topo do menu lateral com logo e rodapé de usuário. |

### 2.3. Cores Semânticas de Estado e Fiscalização

As cores de status seguem a convenção do semáforo funcional e de compliance trabalhista:

| Significado / Status | Cor de Destaque | Fundo de Badge / Card | Borda | Ponto Indicador |
| :--- | :--- | :--- | :--- | :--- |
| **Sucesso / Ativo / GPS Válido / Ponto Concluído** | `emerald-600` (`#059669`) | `bg-emerald-50 text-emerald-700` | `border-emerald-200` | `bg-emerald-500` |
| **Alerta / Ponto Facultativo / Pendência / Incompleto** | `amber-600` (`#D97706`) | `bg-amber-50 text-amber-800` | `border-amber-200` | `bg-amber-500` |
| **Perigo / Feriado / Exclusão / Erro / Inconsistência** | `rose-600` (`#E11D48`) | `bg-rose-50 text-rose-700` | `border-rose-200` | `bg-rose-500` |
| **Informativo / Expediente Especial / Ajuste** | `sky-600` (`#0284C7`) | `bg-sky-50 text-sky-700` | `border-sky-200` | `bg-sky-500` |
| **Recesso Administrativo / Especial** | `purple-600` (`#7C3AED`) | `bg-purple-50 text-purple-700` | `border-purple-200` | `bg-purple-500` |

### 2.4. Escala de Textos e Contrastes (WCAG AA/AAA)

* **Texto Principal (Headings e Títulos):** `text-gray-900` (`#111827`) ou `text-slate-900` (`#0F172A`).
* **Texto Secundário / Corpo:** `text-gray-700` (`#374151`) ou `text-slate-700`.
* **Texto de Apoio / Metadados:** `text-gray-500` (`#6B7280`).
* **Legendas em Caixa Alta (Microcopy):** `text-xs font-bold text-gray-700 uppercase tracking-wider`.
* **Texto Invertido (Sidebar / Botões):** `text-white` com suporte a `text-indigo-200` para subtextos na sidebar.
* **⚠️ REGRA DE CONTRASTE:** Jamais utilizar texto branco sobre fundo cinza claro ou texto cinza claro sobre fundo branco.

---

## 3. Tipografia Oficial

O PontoFácil utiliza a família **Instrument Sans** como fonte padrão do sistema, configurada no `@theme` do Tailwind CSS v4.

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}
```

### 3.1. Hierarquia Tipográfica

| Nível | Classes Tailwind | Uso / Contexto |
| :--- | :--- | :--- |
| **H1 (Título da Página)** | `text-xl sm:text-2xl font-bold text-gray-900 tracking-tight` | Título principal de cada módulo dentro do Card de Cabeçalho. |
| **H2 (Seção Principal)** | `text-base sm:text-lg font-bold text-gray-900` | Títulos de blocos, subtítulos de tabelas, seções de relatório. |
| **H3 (Subseção / Modal)** | `text-base font-semibold text-gray-900` | Títulos de modais de diálogo e agrupamentos de formulário. |
| **Corpo / Parágrafo** | `text-sm text-gray-700 leading-normal` | Textos explicativos, linhas de dados normais, descrições. |
| **Microcopy / Legendas** | `text-xs sm:text-sm text-gray-500` | Subtítulo do cabeçalho da página e dicas de campo. |
| **Rótulos de Campo (Labels)** | `text-xs font-bold text-gray-700 uppercase tracking-wider` | Labels acima de todos os inputs, selects e textareas. |
| **Badges / Etiquetas** | `text-xs font-semibold` ou `text-[10px] font-extrabold uppercase` | Selos normativos (ex: "Portaria 671/2021", "Leiaute MTE"). |
| **Códigos / Hash / NSR** | `font-mono text-xs sm:text-sm text-gray-700` | Hashes SHA-256, comprovantes PAdES, linhas AFD e AEJ. |

---

## 4. Estrutura de Layout e Contêineres

### 4.1. Layout da Aplicação Autenticada (`resources/views/layouts/app.blade.php`)

* **Menu Lateral (Desktop `md:flex`):**
  * Largura fixa de 16rem (`w-64`), fixo à esquerda (`fixed inset-y-0`).
  * Fundo `bg-indigo-900` com topo da marca em `bg-indigo-950`.
  * Links com ícones em linha: `px-3 py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition`.
  * Seções administrativas separadas por linha divisória `border-t border-indigo-800` com título em `text-xs font-semibold text-indigo-300 uppercase tracking-wider`.
* **Área de Conteúdo (Content Area):**
  * Deslocamento lateral no desktop: `md:pl-64`.
  * Margem responsiva: `p-3 sm:p-6 lg:p-8 pb-24 md:pb-8`.
  * Contêiner máximo interno de páginas: `max-w-7xl mx-auto space-y-6`.
* **Navegação Mobile:**
  * Topbar fixa `h-16 bg-indigo-900` com botão de menu hamburguer.
  * Drawer lateral com desfoque e transição suave (`bg-gray-900/80 backdrop-blur-xs`).
  * Barra inferior fixa estilo aplicativo (`bg-white border-t border-gray-200 shadow-lg`) com atalhos rápidos ("Ponto", "Espelho", "Folha", "Mais").

### 4.2. Layout da Landing Page (`resources/views/welcome.blade.php`)

* **Navbar Flutuante:** Fixa no topo com `bg-white/80 backdrop-blur-md border-b border-slate-200/60 shadow-xs`.
* **Seções Narrativas:** Espaçamento generoso `py-20 lg:py-28`, títulos com `text-3xl sm:text-5xl font-black text-slate-900 tracking-tight`.
* **Grid de Comparação:** Duas colunas contrastando "O Jeito Antigo" (tons pastéis avermelhados, riscos) vs. "PontoFácil 2.0" (índigo e esmeralda).
* **CTA Final:** Gradiente profundo de `from-indigo-600 to-indigo-950` com chamada para ação e botão luminoso.

---

## 5. Biblioteca de Componentes UI (Design Patterns)

### 5.1. Cartão de Cabeçalho de Módulo (Page Header Card)

Todo módulo principal deve iniciar com este cartão no topo da página:

```html
<div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <!-- Ícone em container temático -->
        <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-700">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <!-- SVG Icon -->
            </svg>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Nome do Módulo</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    Portaria 671/2021
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Descrição sucinta do objetivo e fundamentação legal do módulo.</p>
        </div>
    </div>

    <!-- Ações do cabeçalho -->
    <div class="flex flex-wrap items-center gap-2">
        <!-- Botões de ação -->
    </div>
</div>
```

### 5.2. Botões Padronizados

Os botões utilizam sempre cantos `rounded-xl`, tipografia `text-xs sm:text-sm font-bold`, transição rápida `transition`, cursor de clique explícito `cursor-pointer` e sombra sutil `shadow-xs`:

* **Ação Primária (Índigo):**
  ```html
  <button type="button" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl text-xs sm:text-sm font-bold transition shadow-xs gap-1.5 cursor-pointer">
      Salvar Alterações
  </button>
  ```
* **Ação Positiva / Sucesso (Esmeralda):**
  ```html
  <button type="button" class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-bold transition shadow-xs gap-1.5 cursor-pointer">
      Importar / Confirmar
  </button>
  ```
* **Ação de Alerta / Atenção (Âmbar):**
  ```html
  <button type="button" class="inline-flex items-center px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs sm:text-sm font-bold transition shadow-xs gap-1.5 cursor-pointer">
      Sugestões / Avisos
  </button>
  ```
* **Ação Destrutiva / Perigo (Rose):**
  ```html
  <button type="button" class="inline-flex items-center px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs sm:text-sm font-bold transition shadow-xs gap-1.5 cursor-pointer">
      Excluir Registro
  </button>
  ```
* **Ação Secundária / Cancelar (Neutro):**
  ```html
  <button type="button" class="inline-flex items-center px-4 py-2.5 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs sm:text-sm font-bold transition cursor-pointer">
      Cancelar / Voltar
  </button>
  ```
* **Botão de Ícone em Linha (Table Action):**
  ```html
  <button type="button" class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition cursor-pointer" title="Editar">
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><!-- SVG --></svg>
  </button>
  ```

### 5.3. Campos de Formulário (Inputs, Selects e Textareas)

* **Estrutura de Rótulo e Campo:**
  ```html
  <div>
      <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
          Nome do Estabelecimento *
      </label>
      <input type="text" 
             class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" 
             placeholder="Ex: Matriz Maceió">
      <!-- Mensagem de erro -->
      <span class="text-rose-600 text-xs font-semibold mt-1 block">O campo é obrigatório.</span>
  </div>
  ```
* **Selects:**
  ```html
  <select class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
      <option value="">Selecione uma opção...</option>
  </select>
  ```

### 5.4. Listas, Tabelas e Cartões de Dados

* **Contêiner da Tabela/Lista:** `bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden`
* **Cabeçalho da Tabela:** `px-4 sm:px-6 py-4 bg-gray-50/70 border-b border-gray-200/80`
* **Linhas de Dados:** `divide-y divide-gray-100`, hover suave com `hover:bg-gray-50/80 transition`
* **Estado Vazio (Empty State):**
  ```html
  <div class="p-10 sm:p-12 text-center text-gray-500">
      <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><!-- Icon --></svg>
      </div>
      <p class="font-bold text-gray-800 text-sm">Nenhum registro encontrado.</p>
      <p class="text-xs text-gray-500 mt-1">Utilize o botão acima para cadastrar um novo item.</p>
  </div>
  ```

### 5.5. Modais e Diálogos de Confirmação

Todos os modais devem utilizar backdrop escurecido com desfoque e cartão branco centralizado:

```html
<div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs px-4 py-6">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200/80 w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <!-- Header do Modal -->
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Título da Janela Modal</h3>
                <p class="text-xs text-gray-500 mt-0.5">Descrição explicativa da ação.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <!-- Conteúdo do Modal -->
        <div class="p-6 space-y-4">
            <!-- Campos e controles -->
        </div>

        <!-- Rodapé do Modal com Ações -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer">
                Cancelar
            </button>
            <button type="button" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">
                Salvar Dados
            </button>
        </div>
    </div>
</div>
```

---

## 6. Diretrizes Especiais da Landing Page

Conforme especificado em [`ref/especifica_o_da_landing_page.md`](file:///c:/Users/rayhe/Downloads/pontofacil/ref/especifica_o_da_landing_page.md):

1. **Relógio Digital ao Vivo (Hero):**
   * Deve exibir horas, minutos e segundos no fuso horário oficial de Maceió (`America/Maceio`).
   * Indicador com ponto verde pulsante (`animate-ping bg-emerald-400`).
2. **Scanner Laser Interativo:**
   * Demonstração de leitura de QR Code do colaborador com animação de feixe de laser horizontal suave.
3. **Crédito Obrigatório no Rodapé:**
   * O rodapé institucional deve conter de forma explícita e legível:
     `Desenvolvido por KL Tecnologia (https://kltecnologia.com)` junto ao indicador do fuso de Maceió e direitos reservados.

---

## 7. Regras Anti-Padrão (O Que NUNCA Fazer)

* ❌ **NUNCA crie fundos escuros (`bg-slate-800`, `bg-slate-900`) no miolo do sistema.** A aplicação interna é estritamente *light mode* com cartões brancos.
* ❌ **NUNCA utilize texto cinza claro ou branco sobre fundos claros.** O contraste deve sempre respeitar nível AA/AAA (`text-gray-900` para títulos, `text-gray-700` para corpo).
* ❌ **NUNCA use botões sem cantos arredondados (`rounded-none` ou cantos vivos de 2px).** O padrão do sistema é `rounded-xl` para botões e `rounded-2xl` para cartões.
* ❌ **NUNCA esqueça a classe `cursor-pointer` em elementos interativos**, incluindo botões, labels clicáveis e linhas com evento `wire:click`.
* ❌ **NUNCA crie tabelas largas que quebrem telas mobile.** Sempre adicione contêiner com `overflow-x-auto` ou reorganize registros em cartões empilhados para telas `< 640px`.
* ❌ **NUNCA crie formulários sem `focus:ring-2 focus:ring-indigo-500`.** O foco visual é indispensável para acessibilidade e navegação por teclado.
