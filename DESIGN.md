# PontoFácil — Design System & Diretrizes Oficiais de UX/UI

> **Documento Oficial e Fonte Única de Verdade de UX, UI, Direção de Arte e Design System.**  
> Este documento estabelece os padrões normativos e práticos para todas as telas, componentes, fluxos de interação, páginas públicas e módulos autenticados do **PontoFácil (REP-P e PTRP sob a Portaria 671/2021 MTP)**.  
> Qualquer nova interface ou refatoração visual deve consultar e seguir rigorosamente as regras aqui consolidadas.

---

## 1. Princípios Fundamentais de Produto

O PontoFácil é um software empresarial focado em controle de ponto eletrônico, gestão de jornada, conformidade trabalhista e rotinas de Departamento Pessoal / Recursos Humanos. Ele lida com registros jurídicos e dados que fundamentam a folha de pagamento e auditorias trabalhistas.

### 1.1. Personalidade do Produto
A experiência visual e interativa deve transmitir:
* **CLAREZA:** Hierarquia visual inequívoca, leitura confortável e ausência de ruído decorativo.
* **PRECISÃO:** Horários, saldos e cálculos exibidos com exatidão matemática e tipografia tabular.
* **CONFIANÇA & RASTREABILIDADE:** Separação conceitual entre coleta de ponto e tratamento da jornada, deixando evidente a preservação do fato original.
* **SIMPLICIDADE OPERACIONAL:** Fluidez no registro diário para o colaborador e ferramentas objetivas de fechamento para o gestor.
* **SOBRIEDADE:** Visual corporativo maduro, contrastado e refinado, sem artifícios de marketing exagerado.

### 1.2. O Que o PontoFácil NÃO É
A interface do PontoFácil **NÃO DEVE** tentar parecer:
* Fintech de consumo ou aplicativo de cashback;
* Startup de inteligência artificial ou dashboard futurista com efeitos de ficção científica;
* Aplicativo lifestyle ou rede social corporativa;
* Template SaaS genérico construído a partir de bibliotecas prontas sem alma;
* Página de captura agressiva com promessas jurídicas milagrosas.

### 1.3. O Dado de Jornada Como Design
> **Princípio Central:** *"O próprio dado de jornada é a identidade visual do PontoFácil."*

Em vez de ilustrações abstratas ou ícones vazios, a identidade do sistema é expressa através dos seus próprios elementos operacionais:
* Horário exato (`08:01:32`);
* Tipo de marcação (`Entrada`, `Saída`, `Intervalo`);
* Número Sequencial de Registro (`NSR 000004281`);
* Comprovante de ponto (`PF-2026-8942-01`);
* Saldo de apuração diária (`+09m`, `-01m`, `+16m`);
* Eventos de auditoria e hashes de integridade (`SHA-256`);
* Linhas do espelho de ponto e arquivos fiscais regulamentares (`AFD` e `AEJ`).

---

## 2. Mobile-First Como Regra Obrigatória

Toda interface do PontoFácil — seja da landing page comercial ou dos módulos administrativos — deve ser concebida e construída a partir do menor viewport suportado.

### 2.1. Viewports de Validação Obrigatória
1. **320px:** Largura mínima crítica (dispositivos ultracompactos). Não pode haver overflow horizontal (`scrollWidth <= innerWidth`).
2. **360px:** Padrão Android compacto.
3. **375px / 390px / 430px:** Smartphones iOS e Android modernos.
4. **768px:** Tablets em modo retrato e dobráveis.
5. **1024px:** Tablets em modo paisagem e laptops compactos.
6. **1280px:** Monitores padrão corporativo (HD/WXGA).
7. **1440px+:** Desktops amplos e monitores Full HD/QHD.

### 2.2. Diretrizes Técnicas de Construção Mobile-First
* **Fluxo de Classes:** Comece definindo as classes padrão para mobile e adicione progressivamente `sm:`, `md:`, `lg:`, `xl:`. Nunca projete para desktop e tente comprimir com hacks posteriores.
* **Touch Targets Confortáveis:** Todos os botões, links de menu e controles interativos devem ter área de clique mínima de aproximadamente **44 × 44 pixels** (`min-h-[44px]` ou `min-h-[48px]`).
* **Zero Overflow Horizontal:** Nenhum elemento pode forçar rolagem lateral na janela principal. O corpo da página utiliza `overflow-x-hidden` apenas como camada de segurança; a estrutura deve ser intrinsecamente contida.
* **Sem Dependência de Hover:** Nenhuma informação crítica, ação primária ou feedback essencial pode depender do evento `:hover`, inexistente em telas sensíveis ao toque.
* **Tabelas e Dados Densos no Mobile:** Devem utilizar scroll horizontal contido (`overflow-x-auto`) com indicador de continuidade ou representação adaptada em listas/cartões para leitura rápida.

---

## 3. Paleta de Cores e Tokens Oficiais

A paleta é restrita, deliberada e funcional. Cor no PontoFácil comunica estado, hierarquia ou identidade — nunca decoração aleatória.

### 3.1. Escala da Marca (Índigo)
A cor institucional primária do sistema é o **Índigo**, transmitindo estabilidade, sobriedade e profissionalismo corporativo.

| Papel | Token Semântico | Classe Tailwind Direta | Hex / OKLCH Aproximado | Uso no Sistema |
|---|---|---|---|---|
| Fundo Suave | `brand-50` | `bg-indigo-50` | `#EEF2FF` | Superfícies ativas, badges leves, ícones de módulo |
| Borda Suave | `brand-100` | `border-indigo-100` | `#E0E7FE` | Contornos de badges, divisores destacados |
| Destaque Primário | `brand-500` | `text-indigo-500` | `#6366F1` | Indicadores de foco, detalhes secundários de marca |
| Ação Principal | `brand-600` | `bg-indigo-600` | `#4F46E5` | Botões primários, links ativos, brand mark |
| Hover Primário | `brand-700` | `hover:bg-indigo-700` | `#4338CA` | Estado hover de botões primários |
| Contraste Escuro | `brand-950` | `bg-indigo-950` | `#1E1B4B` | Topbar da sidebar, cabeçalhos de alta densidade |

> **Diretriz de Transição:** O sistema está migrando gradualmente de referências diretas (`indigo-600`, `indigo-50`) para tokens semânticos (`brand-600`, `brand-50`), já declarados no layout da landing e integráveis ao tema da aplicação. Não é permitida a criação de novos padrões baseados em outras famílias azuis como cor primária de marca.

### 3.2. Neutros Estruturais (Slate e Gray)
* **Landing Page:** Utiliza prioritariamente a escala **Slate** (`slate-50` a `slate-950`), oferecendo um tom neutro frio e elegante que harmoniza com o índigo.
* **Aplicação Autenticada:** O canvas principal utiliza `bg-gray-100` (`#F3F4F6`), cartões em `bg-white`, textos principais em `text-gray-900` (`#111827`), textos secundários em `text-gray-700` (`#374151`) e bordas em `border-gray-200/80`.
* **Superfícies Noturnas (Exceções Funcionais):** Apenas o mockup do Hero e o CTA final utilizam `bg-slate-950` para contraste de foco. A aplicação autenticada opera exclusivamente em tema claro unificado.

### 3.3. Cores de Estado e Funcionalidade (Semáforo de Jornada)
As cores funcionais possuem significado rigoroso:

| Estado | Família | Tokens Primários | Significado Operacional no PontoFácil |
|---|---|---|---|
| **Sucesso / Ativo** | `Emerald` | `text-emerald-700 bg-emerald-50 border-emerald-200` | Registro confirmado, jornada em andamento, comprovante validado, saldo positivo CLT |
| **Atenção / Pendência** | `Amber` | `text-amber-800 bg-amber-50 border-amber-200` | Ocorrência pendente, saldo negativo, tolerância CLT atingida, atestado aguardando análise |
| **Erro / Risco / Ação Crítica** | `Red / Rose` | `text-red-700 bg-red-50 border-red-200` | Marcação inconsistente, rejeição de justificativa, ação destrutiva, período fechado sem permissão |
| **Informativo / Apoio** | `Sky / Blue` | `text-sky-700 bg-sky-50 border-sky-200` | Informações de apoio, dados de versão, instruções contextuais |
| **Roxo / Purple** | `Purple` | *Uso Restrito* | **NÃO UTILIZAR COMO DECORAÇÃO PADRÃO.** Permitido apenas para diferenciar regimes específicos expressamente documentados (ex: banco de compensação especial / acordos sindicais) |

---

## 4. Tipografia Oficial

A tipografia do PontoFácil privilegia a rápida absorção de dados tabulares, clareza numérica e legibilidade em dispositivos móveis sob luz ambiente.

### 4.1. Famílias Tipográficas Efetivamente Utilizadas
* **Aplicação Autenticada:** Configurada diretamente no Tailwind CSS v4 `@theme` em `resources/css/app.css`:
  ```css
  @theme {
      --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
  }
  ```
* **Landing Page:** Configurada no cabeçalho de `resources/views/layouts/landing.blade.php`:
  * **Plus Jakarta Sans:** Títulos, botões e elementos de marca com forte geometria corporativa.
  * **Inter:** Textos descritivos e parágrafos de alta leiturabilidade.
* **Dados Monospaçados / Numerais Tabulares:** Família `font-mono` (`ui-monospace`, `Consolas`, `Menlo`) acompanhada de classes de alinhamento tabular (`font-mono-numbers`, `tabular-nums`) para:
  * Horários (`08:01:32`);
  * NSR (`#000004281`);
  * Hashes SHA-256 e códigos de comprovante (`PF-2026-8942-01`);
  * Registros dos arquivos fiscais AFD e AEJ.

### 4.2. Escala e Hierarquia Tipográfica
* **Display / Hero Título:** `text-2xl min-[360px]:text-[26px] sm:text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.2]`
* **H1 de Módulo Administrativo:** `text-xl sm:text-2xl font-bold text-gray-900 tracking-tight`
* **H2 de Seção:** `text-xl sm:text-2xl md:text-3xl font-bold text-slate-900 tracking-tight`
* **H3 de Subseção / Card Principal:** `text-base sm:text-lg font-bold text-slate-900 tracking-tight`
* **Texto de Corpo (Body):** `text-xs sm:text-sm md:text-base text-slate-600 leading-relaxed font-normal`
* **Labels de Formulário:** `text-xs font-bold text-gray-700 tracking-wide` (evitar uppercase indiscriminado quando dificultar leitura rápida)
* **Metadata & Microcopy:** `text-[11px] sm:text-xs text-slate-500 font-medium`
* **Regra de Peso:** O uso de `font-black` (peso 900) é desencorajado. Títulos utilizam `font-bold` (700) ou `font-extrabold` (800) em destaques principais.

---

## 5. Cantos Arredondados (Border Radius)

O border radius no PontoFácil estabelece uma hierarquia de agrupamento visual, e não um efeito decorativo genérico.

* **Controles Menores e Badges:** `rounded-lg` (8px). Usado em badges, tags de status e botões de ação em tabelas.
* **Botões e Inputs:** `rounded-xl` (12px). Padrão para botões primários, secundários, campos de texto e selects.
* **Cards Comuns e Módulos:** `rounded-xl` (12px) a `rounded-2xl` (16px). Padrão para cartões brancos do sistema e blocos de agrupamento.
* **Containers Protagonistas & Modais:** `rounded-2xl` (16px). Padrão para caixas modais de diálogo, o mockup do Hero e cartões com tabelas integradas.
* **Anti-Padrão:** Evitar `rounded-3xl` em todos os elementos. Cantos excessivamente circulares desperdiçam área útil e diminuem a densidade necessária em telas administrativas.

---

## 6. Sombras, Bordas e Elevação

O design system baseia-se prioritariamente em **bordas refinadas e contraste de superfície**, reservando sombras para apoiar a percepção de profundidade sem artificialismo.

* **Borda Padrão Corporativa:** `border border-slate-200/90` (landing) e `border border-gray-200/80` (sistema autenticado). Linha contínua, nítida e neutra.
* **Elevação Base (Cards e Blocos):** `shadow-2xs` ou `shadow-xs`. Proporciona ligeiro descolamento da superfície sem criar halos pesados.
* **Elevação Intermediária (Hover / Ação):** `shadow-sm` transitando suavemente em interações ativas (`hover:shadow-sm`).
* **Elevação Superior (Modais, Drawers e Menus Flutuantes):** `shadow-lg` ou `shadow-xl`. Exclusiva para elementos que realmente sobrepõem o conteúdo da página.
* **Anti-Padrão:** Proibido o uso indiscriminado de `shadow-2xl`, sombras coloridas fluorescentes (`shadow-indigo-500/50`) e efeitos de neon. Interfaces empresariais de DP exigem sobriedade.

---

## 7. Diretrizes de Gradientes

* **Gradiente de Texto:** **PROIBIDO** como padrão em títulos. Não utilizar `bg-gradient-to-r`, `bg-clip-text` e `text-transparent` em cabeçalhos normais. Destaques devem utilizar cor sólida (`text-indigo-600` ou `text-indigo-400`).
* **Gradientes de Superfície:** Permitidos apenas em dois cenários estritos:
  1. No fundo sutil da landing page (`radial-gradient` translúcido a 20% de opacidade) para criar profundidade de luz sem comprometer legibilidade;
  2. No mostrador digital do relógio do Hero (gradiente índigo sólido no número das horas), reproduzindo a estética funcional da tela real do colaborador.

---

## 8. Tratamento de Cards e Estrutura de Informação

> **Regra Estrutural:** *"Nem toda informação deve virar um card."*

A repetição mecânica de cards retangulares empobrece o ritmo da página e gera fadiga visual.

### 8.1. Quando Utilizar Card
* Agrupamento de dados de uma entidade concreta (ex: registro de um colaborador, resumo de um setor);
* Módulos independentes com ações próprias (ex: painel de controle do leitor QR Code, formulário isolado);
* Diálogos modais e caixas de diálogo.

### 8.2. Quando Evitar Card (Preferir Composição Contínua)
* **Processos e Fluxos:** Usar linha conectora, marcadores numerados e texto direto (ex: fluxo de 4 etapas em "Como Funciona");
* **Narrativa de Rastreabilidade:** Usar trilhas temporais conectadas verticalmente (ex: fluxo narrativo de segurança);
* **Comparações:** Usar matrizes divididas com fundo neutro compartilhado;
* **Dados Tabulares:** Usar tabela estruturada com divisores horizontais leves em vez de um card para cada linha.

---

## 9. Landing Page Comercial Oficial

A landing page do PontoFácil foi arquitetada para apresentar o produto com máxima clareza comercial e total fidelidade funcional.

### 9.1. Arquivos Reais da Landing Page
* Layout Base: `resources/views/layouts/landing.blade.php`
* View Principal: `resources/views/landing.blade.php`
* Componentes Modulares:
  * `resources/views/components/landing/navbar.blade.php`
  * `resources/views/components/landing/hero.blade.php`
  * `resources/views/components/landing/punch-lifecycle.blade.php`
  * `resources/views/components/landing/how-it-works.blade.php`
  * `resources/views/components/landing/comparison.blade.php`
  * `resources/views/components/landing/features.blade.php`
  * `resources/views/components/landing/security.blade.php`
  * `resources/views/components/landing/compliance.blade.php`
  * `resources/views/components/landing/interactive-preview.blade.php`
  * `resources/views/components/landing/cta-footer.blade.php`

*(Nota: O arquivo `welcome.blade.php` é legado do framework Laravel e NÃO representa a landing page do PontoFácil).*

### 9.2. Ordem de Renderização da Landing Page
1. **Navbar Flutuante:** Silenciosa, links textuais limpos, validação de comprovante e botão de acesso direto.
2. **Hero:** Proposta de valor central (*"Ponto eletrônico simples para o colaborador. Gestão completa para a empresa."*) com mockup demonstrativo da interface real de registro.
3. **Ciclo do Registro de Ponto:** Passo a passo técnico do que acontece quando o ponto é batido (coleta, preservação na ARP, emissão de comprovante e apuração no PTRP).
4. **Como Funciona:** Fluxo em 4 etapas conectadas (horizontal no desktop, vertical no mobile).
5. **Colaborador x Empresa:** Painel assimétrico demonstrando a experiência do trabalhador no celular vs a matriz gerencial do DP.
6. **Principais Recursos:** Espelho automatizado em tempo real com tabela de apuração CLT (`+09m`, `-01m`, `+16m`) acompanhado de 3 pilares secundários.
7. **Segurança e Rastreabilidade:** Trilha cronológica de auditoria ancorada por evento real (`08:01:32 • NSR 000004281 • SHA-256`).
8. **Portaria 671 / MTP:** Diagrama do pipeline funcional da norma (`REP-P → ARP → PTRP → Espelho/AEJ`), princípios de não sobrescrita e nota técnica de transparência regulatória.
9. **Demonstração da Interface:** Abas interativas alternando entre a visão do colaborador e o painel do gestor de RH.
10. **CTA Final & Rodapé Sóbrio:** Chamada operacional direta (*"Pronto para organizar a jornada da sua equipe?"*) e rodapé enxuto focado em navegação e suporte legal.

---

## 10. Eyebrows, Badges e Indicadores

Pills e badges devem ser usados para finalidades semânticas precisas, evitando o excesso de selos decorativos em títulos.

* **Categorias Editoriais de Seção:** Devem ser apresentadas como texto limpo em caixa alta com tracking amplo, sem caixa de fundo:
  * Exemplo: `01 — COMO FUNCIONA`, `03 — RECURSOS PRINCIPAIS`, `05 — PORTARIA 671 / MTP`.
* **Badges Funcionais Permitidos:**
  * Status da jornada (`Em jornada` com ponto pulsante verde);
  * Indicador de NSR (`NSR #000004281` em monospace com fundo leve);
  * Saldo apurado (`+09m` em verde, `-01m` em âmbar);
  * Categoria de auditoria ou tipo de comprovante fiscal.

---

## 11. Padrões de Navegação

### 11.1. Navbar da Landing Page
* **Desktop:**
  * Links textuais simples (`Recursos`, `Para empresas`, `Segurança`, `Portaria 671`) com hover suave em índigo;
  * Botão de validação de comprovante destacado com tom esmeralda suave e ícone semântico;
  * Botão de acesso primário (`Entrar`) com maior hierarquia visual;
  * Sem ícones decorativos redundantes nos links textuais padrão.
* **Mobile:**
  * Botão hambúrguer com área de toque mínima de 44x44px;
  * Gaveta dropdown acessível com fechamento por clique externo ou tecla `Escape`;
  * Links de navegação verticais com ícones monocromáticos em `currentColor` para facilitar leitura com o polegar;
  * CTA de entrada expandido em largura total (`w-full min-h-[48px]`).

### 11.2. Layout da Aplicação Autenticada
* **Desktop & Tablet (`resources/views/layouts/app.blade.php`):**
  * Sidebar lateral fixa à esquerda com transição de recolhimento para modo tablet:
    * Modo expandido: `md:w-64`
    * Modo recolhido: `md:w-20` (ícones centralizados com tooltips de apoio)
  * Divisão clara de escopo por papéis de usuário (Colaborador, Gestor, RH/Administrador, Auditor Fiscal);
  * Fundo escuro contínuo da barra lateral em `bg-indigo-900` com topo e rodapé da conta em `bg-indigo-950`.
* **Mobile no Sistema Autenticado:**
  * Topbar fixa em `bg-indigo-900` com botão de menu e identificação rápida do usuário;
  * Drawer lateral deslizante com backdrop escurecido (`backdrop-blur-xs`);
  * Barra de navegação inferior estilo aplicativo móvel (`fixed bottom-0 bg-white border-t border-gray-200`) com atalhos de alta frequência (*Ponto*, *Espelho*, *Ajuda*, *Menu*).

---

## 12. Botões e Ações

Todo botão no PontoFácil deve indicar com clareza o seu nível de prioridade e consequência da ação.

### 12.1. Hierarquia de Botões
* **Primary (Ação Principal):**
  * Classe: `bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold rounded-xl shadow-xs`
  * Uso: Registrar ponto, salvar configuração, aprovar ocorrência, avançar etapa.
  * Regra: No máximo uma ação primária em destaque por bloco ou modal.
* **Secondary / Neutral (Ação de Apoio):**
  * Classe: `bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 font-semibold rounded-xl`
  * Uso: Cancelar, voltar, exportar relatório secundário, limpar filtros.
* **Success (Ação Positiva Afirmativa):**
  * Classe: `bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs`
  * Uso: Confirmação explícita de importação, conciliação bem-sucedida, download de arquivo assinado.
* **Destructive / Danger (Ação Crítica):**
  * Classe: `bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl shadow-xs`
  * Uso: Excluir registro, rejeitar atestado, reabrir período fechado. Sempre exige diálogo de confirmação.

### 12.2. Estados Obrigatórios de Botões
Todo botão deve ter estilos declarados para:
* `default`
* `hover`
* `active` (leve redução de escala `active:scale-[0.99]`)
* `focus-visible` (`focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:outline-none`)
* `disabled` (`disabled:opacity-50 disabled:cursor-not-allowed`)
* `loading` (spinner discreto substituindo ou acompanhando o texto sem colapsar a largura do botão).

---

## 13. Formulários e Controles de Entrada

Formulários em um sistema de ponto manipulam datas, horários contratuais, justificativas e parâmetros legais. Devem prevenir erros antes que eles aconteçam.

* **Labels Sempre Visíveis:** Nunca utilize o atributo `placeholder` como substituto do rótulo do campo. O label deve estar sempre posicionado acima do input em `text-xs font-bold text-gray-700`.
* **Identificação de Campos Obrigatórios:** Indicar campos obrigatórios com asterisco visível (`*`).
* **Mensagens de Erro Conectadas:** Exibir a mensagem de erro imediatamente abaixo do controle correspondente em `text-xs font-semibold text-rose-600 mt-1`. O erro nunca deve ser sinalizado unicamente por cor de borda.
* **Focus Indicativo:** Todo controle interativo deve ter anel de foco bem delineado:
  `focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500`.
* **Adaptação Mobile:** Inputs numéricos e de horário devem invocar o teclado virtual correto (`inputmode="numeric"`, tipos `time` e `date`).

---

## 14. Tabelas e Exibição de Dados de Ponto

O espelho de ponto, a lista de colaboradores e as trilhas de auditoria são estruturalmente tabulares. Tabelas são cidadãs de primeira classe no PontoFácil.

### 14.1. Regras Visuais de Tabelas
* **Cabeçalho:** `bg-gray-50/80 text-gray-700 font-bold border-b border-gray-200 text-xs uppercase tracking-wider`.
* **Alinhamento:**
  * Textos e nomes: alinhados à esquerda;
  * Horários e marcações cronológicas: centralizados ou alinhados à esquerda com fonte mono;
  * Saldos, horas acumuladas e números: **alinhados à direita com numerais tabulares**;
  * Ações da linha (editar, excluir, auditar): **sempre na última coluna à direita**.
* **Linhas e Hover:** Linhas separadas por `divide-y divide-gray-100`, com realce sutil ao passar o cursor (`hover:bg-slate-50/70 transition-colors`).
* **Zebra Stripes:** Utilizar apenas em tabelas de auditoria densas que ultrapassem 10 colunas; em tabelas normais, preferir linhas brancas com divisores limpos.
* **Sticky Headers:** Tabelas extensas com rolagem vertical devem fixar o cabeçalho (`sticky top-0`).

### 14.2. Estratégia de Responsividade para Tabelas
* **Cenário A (Tabela de Espelho de Ponto):** Envolver a tabela em contêiner `overflow-x-auto` com borda contida para manter a fidelidade visual dos turnos de trabalho (Entrada 1, Saída 1, Entrada 2, Saída 2).
* **Cenário B (Listas Administrativas de Funcionários/Setores):** Em telas `< 640px`, reorganizar os registros em formato de cartão com campos empilhados para visualização vertical sem necessidade de arrastar a tela.

---

## 15. Sistema de Estados e Feedback

Toda ação relevante deve gerar um retorno imediato e compreensível para o usuário.

* **Mensagens Específicas:** Evitar mensagens genéricas como "Sucesso!" ou "Erro!". Preferir retornos operacionais como:
  * *"Batida registrada com sucesso às 08:01:32 (NSR 000004281)."*
  * *"Solicitação de ajuste enviada para análise da coordenação."*
  * *"Período de competência fechado e arquivo AEJ consolidado."*
* **Acessibilidade dos Estados:** Estados nunca devem ser transmitidos exclusivamente através de cores. Devem sempre combinar **Cor + Rótulo Textual** e, quando aplicável, um ícone de apoio ou ponto indicador.

---

## 16. Ícones e Elementos Gráficos

* **Padrão de Ícones:** SVG outline monocromático (estilo Heroicons v2 com `stroke-width="1.5"` a `2.0`).
* **Tamanhos Padronizados:**
  * Micro (inline em tabelas e metadados): `w-3.5 h-3.5` (14px) ou `w-4 h-4` (16px);
  * Padrão (botões e itens de menu): `w-5 h-5` (20px);
  * Destaque (cabeçalhos de módulo e cards protagonistas): `w-6 h-6` (24px).
* **Acessibilidade:** Ícones puramente decorativos devem conter `aria-hidden="true"`. Ícones que atuam como botões independentes devem conter `aria-label` ou texto auxiliar `sr-only`.
* **Proibição de Emojis:** É terminantemente proibido utilizar emojis coloridos como ícones de interface ou marcadores de status no sistema.

---

## 17. Diretrizes de Movimento e Animação (Motion)

Animações no PontoFácil servem para confirmar transições de estado, abrir gavetas e evidenciar leituras de sensores — nunca para ornamentar a página de maneira supérflua.

* **Duração Recomendada:** Entre `150ms` e `250ms` para microinterações (hover, foco, alternância de tabs). No máximo `350ms` para modais e drawers.
* **REGRA CRÍTICA DE VISIBILIDADE (Anti-Quebra de JS):**
  > **O conteúdo essencial do PontoFácil NUNCA deve depender da execução de JavaScript para ficar visível.**
  > Elementos NÃO DEVEM iniciar com `opacity: 0` inline ou via classes estáticas que causem tela branca caso scripts falhem, redes oscilem ou o navegador desative animações. GSAP e ScrollTrigger devem atuar estritamente como aprimoramento progressivo em elementos já renderizados e 100% visíveis.
* **Respeito à Preferência de Movimento Reduzido:**
  ```css
  @media (prefers-reduced-motion: reduce) {
      *, ::before, ::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
          scroll-behavior: auto !important;
      }
  }
  ```
  Scripts de animação devem checar `window.matchMedia('(prefers-reduced-motion: reduce)').matches` e encerrar imediatamente sem disparar timelines ou loops contínuos.

---

## 18. Acessibilidade (Padrão Mínimo WCAG 2.1 AA)

Como ferramenta institucional de uso diário por todos os colaboradores de uma organização, a acessibilidade é obrigatória.

* **Navegação por Teclado:** Toda ação acessível por mouse deve ser executável por teclado (`Tab`, `Shift+Tab`, `Enter`, `Space`, `Escape`).
* **Anéis de Foco Preservados:** Nunca remover o contorno de foco sem fornecer substituto equivalente. Usar `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2`.
* **Semântica ARIA Completa:**
  * Menus expansíveis utilizam `aria-expanded` dinâmico e `aria-controls`;
  * Abas utilizam estrutura com `role="tablist"`, `role="tab"` (`aria-selected`) e `role="tabpanel"`;
  * Modais utilizam `role="dialog"`, `aria-modal="true"` e foco preso no contêiner.
* **Skip Links:** A landing page e o layout autenticado incluem link oculto para leitores de tela: *"Pular para o conteúdo principal"* (`#hero` ou `#main-content`).
* **Contraste Mínimo:** Todos os textos de leitura devem cumprir o contraste mínimo de 4.5:1 contra seus fundos imediatos.

---

## 19. UX Writing e Tom de Voz

O PontoFácil comunica-se com a seriedade de um sistema de Departamento Pessoal e a empatia de uma ferramenta que valoriza o tempo do trabalhador.

### 19.1. Vocabulário a Evitar (Termos Proibidos)
* ❌ "Poder Total", "Superpoderes", "Revolucione sua empresa"
* ❌ "Nova era", "O futuro chegou", "Transformação mágica"
* ❌ "Implantação instantânea", "Zero cliques", "100% automático" (quando não for garantido)
* ❌ "Máxima segurança jurídica", "Proteção jurídica total", "Blindagem absoluta"
* ❌ "Inviolável", "100% seguro", "Impossível de fraudar", "Perpétuo"

### 19.2. Vocabulário Recomendado (Fatos Operacionais)
* ✔️ *"Organize a jornada da sua equipe em um só lugar."*
* ✔️ *"Registre, acompanhe e feche jornadas com menos retrabalho."*
* ✔️ *"Preservação do registro original com trilha auditada."*
* ✔️ *"Comprovante digital com dados de integridade disponibilizado na hora."*
* ✔️ *"Localização registrada como apoio à validação contextual."*
* ✔️ *"Gestores acompanham ocorrências antes do fechamento da folha."*

---

## 20. Conformidade Normativa e Regras Legais

O design do PontoFácil deve espelhar com exatidão o enquadramento técnico da **Portaria 671/2021 do Ministério do Trabalho e Previdência (MTP)**.

1. **Arquitetura Oficial:** O produto opera sob a arquitetura **REP-P + PTRP** (Registrador Eletrônico de Ponto por Programa + Programa de Tratamento de Registro de Ponto). Nunca referenciar o sistema como "REP-A".
2. **Declarações Transparentes:** Não declarar como concluído nenhum processo que esteja em trâmite.
   * Usar: *"Arquitetura técnica preparada para as diretrizes da Portaria 671/2021 MTP."*
   * Não declarar: "Homologado pelo MTE", "Certificado emitido pelo governo", "INPI concedido", "Assinatura ICP-Brasil ativa" (salvo quando documentalmente verificado).
3. **Geolocalização / Cerca Virtual:**
   * A coleta de coordenadas GPS serve exclusivamente como **evidência contextual e apoio antifraude** para a empresa.
   * A localização **NUNCA DEVE** ser comunicada como um bloqueio absoluto que impeça o trabalhador de bater o ponto fora do raio, respeitando o princípio da não restrição à marcação previsto na Portaria 671.
4. **Fuso Horário (Timezone):**
   * O fuso horário é **configurável por estabelecimento** (ex: `America/Maceio`, `America/Sao_Paulo`, `America/Manaus`).
   * É estritamente proibido fixar "Maceió GMT-3" como fuso horário obrigatório geral no Design System comercial da plataforma.

---

## 21. Densidade de Informação e Espaçamentos

* **Landing Page:** Utiliza ritmo respirável com espaçamentos contidos entre `py-10` e `py-16` no desktop (e `py-8` a `py-12` no mobile), evitando vazios excessivos que façam o usuário rolar páginas inteiras sem encontrar conteúdo.
* **Aplicação Autenticada:** Densidade informativa elevada. Margens de `p-3 sm:p-5 lg:p-6` para que gestores de DP consigam visualizar múltiplos registros sem rolagem desnecessária.
* **Princípio da Entrega Contínua:** *"A cada rolagem de tela, uma nova informação útil deve ser entregue."*

---

## 22. Estados Vazios (Empty States)

Telas sem dados não podem frustrar o usuário com mensagens vazias.

Todo Empty State deve fornecer:
1. Um ícone sutil de contexto;
2. Descrição clara do que está vazio (*"Nenhuma ocorrência pendente neste período."*);
3. Explicação rápida do motivo ou instrução clara da próxima ação possível (*"Quando um colaborador solicitar ajuste de batida, ele aparecerá aqui para aprovação."*);
4. Botão de ação direta quando cabível (*"Cadastrar primeiro funcionário"*).

---

## 23. Diálogos de Confirmação

* **Ações Triviais:** Não interromper o fluxo com perguntas desnecessárias (ex: salvar rascunho, fechar aba informativa).
* **Ações Críticas:** Exigem modal explícito de confirmação:
  * Fechamento mensal de competência de ponto;
  * Exclusão de cadastros de funcionários ou setores;
  * Reabertura de períodos fiscais auditados.
* O diálogo deve declarar abertamente a consequência prática: *"Esta ação consolidará os cálculos de horas e travará novas alterações retroativas para esta competência."*

---

## 24. Anti-Padrões Oficiais (O Que o PontoFácil NUNCA Faz)

1. ❌ **Nunca usar gradientes em textos** em títulos e cabeçalhos.
2. ❌ **Nunca transformar todas as seções em grids de cards isolados.**
3. ❌ **Nunca usar pílulas (badges) em todos os títulos de seção sem finalidade semântica.**
4. ❌ **Nunca usar a cor roxa (purple) apenas como elemento decorativo.**
5. ❌ **Nunca aplicar `shadow-2xl` ou `shadow-xl` em cartões comuns de conteúdo.**
6. ❌ **Nunca aplicar `rounded-3xl` em botões e elementos menores.**
7. ❌ **Nunca criar dashboards fictícios que não existam no sistema real.**
8. ❌ **Nunca iniciar elementos críticos com `opacity: 0` que dependam de script para aparecer.**
9. ❌ **Nunca depender de hover para exibir informações no celular.**
10. ❌ **Nunca usar promessas jurídicas superlativas ou claims falsos de homologação.**
11. ❌ **Nunca adicionar bibliotecas pesadas de CSS/JS sem necessidade comprovada.**
12. ❌ **Nunca apresentar siglas técnicas normativas antes de explicar o benefício operacional.**
13. ❌ **Nunca fixar fuso horário regional específico como se fosse a identidade global do produto.**
14. ❌ **Nunca permitir rolagem horizontal em qualquer largura a partir de 320px.**

---

## 25. Checklists de Qualidade para Novas Telas

### 25.1. Checklist Geral de Qualquer Nova Tela
- [ ] O layout foi desenhado mobile-first a partir de 320px?
- [ ] Foi testado em 320px, 360px, 390px, 768px, 1024px e 1280px+ sem overflow horizontal?
- [ ] A hierarquia de ações está evidente (no máximo uma ação primária em destaque)?
- [ ] O uso de cards é restrito a entidades e agrupamentos que realmente demandam isolamento?
- [ ] As cores empregadas têm significado estrito de marca ou estado (sem cores decorativas aleatórias)?
- [ ] As informações numéricas, horárias e códigos fiscais utilizam numerais tabulares / monospace?
- [ ] O foco de teclado (`focus-visible`) está visível em todos os controles?
- [ ] A navegação funciona sem uso do mouse?
- [ ] As mensagens de feedback são específicas e operacionais?
- [ ] Animações respeitam `prefers-reduced-motion` e o conteúdo aparece mesmo sem JavaScript?
- [ ] Se o logotipo for removido, a tela ainda é reconhecível como parte do PontoFácil?

### 25.2. Checklist Específico da Landing Page
- [ ] O Hero explica o produto nos primeiros 5 segundos de leitura?
- [ ] O produto real é apresentado antes de qualquer mockup conceitual?
- [ ] Os dados reais de jornada (horários, comprovantes, saldos, NSR) atuam como elementos visuais de design?
- [ ] As seções alternam ritmos de composição (fluxos, tabelas, interfaces, comparações)?
- [ ] Os badges são funcionais e as categorias editoriais utilizam texto limpo?
- [ ] Não há espaços vazios resultantes de animações que falharam ao carregar?
- [ ] O CTA final é direto, sóbrio e operacional?
- [ ] O rodapé é limpo, enxuto e sem jargões redundantes?

---

## 26. Governança e Evolução do Design System

1. **Fonte Única da Verdade:** Este arquivo (`DESIGN.md`) é a especificação soberana de UX/UI do PontoFácil.
2. **Reaproveitamento Antes da Invenção:** Ao criar novas telas, consulte as diretrizes deste documento e reutilize classes, espaçamentos e componentes já existentes.
3. **Decisões Deliberadas:** Nenhuma nova cor, efeito ou padrão visual deve ser introduzido sem justificativa de produto.
4. **Atualizações Normativas:** Alterações neste documento devem ser intencionais, registradas em changelog e validadas contra o código real da aplicação.
