# PRD — PontoFácil 2.0
## Plataforma Web de Controle Eletrônico de Jornada — REP-P / PTRP (Instância Dedicada)

**Status:** Atualizado (v1.7.0 / PontoFácil 2.0)  
**Arquitetura:** Instância Dedicada (Single-Tenant estrito)  
**Stack Atual:** Laravel 13, Livewire 4, Alpine.js, Tailwind CSS v4, GSAP 3.12, MySQL 8+  
**Referência Regulatória Principal:** CLT + Portaria MTP nº 671/2021 (leiautes MTE 2026) + LGPD  
**Modalidade Operacional:** REP-P como padrão oficial; REP-A como modo opcional condicionado a Acordo Coletivo de Trabalho (ACT) ou Convenção Coletiva de Trabalho (CCT) válido e vigente.

---

## 1. Visão do Produto

O **PontoFácil 2.0** é uma plataforma corporativa web para registro, tratamento, auditoria e gestão de jornada de trabalho em conformidade integral com a legislação trabalhista brasileira (Portaria 671/2021 MTP).

O produto foi desenhado sob a premissa inegociável de **Instância Dedicada (Single-Tenant)**: cada empresa cliente possui sua instalação própria em VPS ou servidor exclusivo, com domínio próprio, banco de dados MySQL dedicado, arquivos isolados e credenciais seguras.

### Pilares Fundamentais:
1. **Separação Rígida REP-P e PTRP:**
   - **REP-P (Registrador Eletrônico de Ponto via Programa):** Responsável por capturar a marcação de ponto, gravar no ledger imutável (`punch_events`), emitir o Comprovante do Trabalhador assinado digitalmente e gerar o arquivo fiscal **AFD (Arquivo Fonte de Dados)** com assinatura digital CAdES (.p7s detached).
   - **PTRP (Programa de Tratamento de Registro de Ponto):** Responsável por interpretar jornadas, gerenciar escalas contratuais, registrar eventos de tratamento (`treatment_events` - ajustes, justificativas, abonos), apurar saldo minuto a minuto com tolerância legal (Art. 58 § 1º CLT), controlar o banco de horas, congelar o espelho na competência fechada e gerar o relatório oficial e o arquivo fiscal **AEJ (Arquivo Eletrônico de Jornada)**.
2. **Zero Multi-Tenancy:**
   - Vetada qualquer implementação de `tenant_id`, middleware de tenant, resolução dinâmica de bancos ou papéis de "superadmin SaaS".
   - Cada instalação representa uma única empresa jurídica (`Company`), contendo um ou múltiplos estabelecimentos (`Establishments` - Matriz e Filiais) com contadores monotônicos de NSR independentes e atômicos.
3. **Não-Bloqueio de Marcação:** O registrador nunca rejeita uma batida por motivo de horário, escala, falta de internet ou tolerância (Art. 74 e 78 da Portaria 671).
4. **Imutabilidade e Integridade:** A marcação original é inviolável. Qualquer correção ou ajuste ocorre exclusivamente no módulo PTRP mediante justificativa e preservação integral dos registros originais (`restrictOnDelete`).

---

## 2. Perfis de Acesso (RBAC)

- **Administrador / RH (`admin`):**
  - Acesso irrestrito a todos os módulos da instalação.
  - Gestão de Setores, Estabelecimentos, Colaboradores e Usuários.
  - Acesso ao Cadastro Funcional do Servidor e emissão da Folha de Ponto A4 Oficial (Modelo Outubro).
  - Gestão de configurações da empresa, calibração de GPS e emissão/rotação do QR Code corporativo.
  - Acesso ao Espelho de Ponto analítico, relatórios gerenciais, fechamento de folha e exportação de arquivos fiscais (AFD e AEJ).
  - Trilha de auditoria imutável e aplicação de tratamentos no PTRP com justificativa obrigatória.
- **Gestor de Setor (`manager`):**
  - Acesso ao registro de ponto próprio e espelho individual.
  - Módulo **"Gestão de Equipe"**: Cadastro, edição funcional e acompanhamento exclusivo dos colaboradores lotados nos setores sob sua responsabilidade (`manager_id`).
  - Consulta ao espelho de ponto e folhas de ponto dos membros da sua equipe.
- **Colaborador / Servidor (`employee`):**
  - Acesso direto ao registro de ponto diário (Smart Punch via QR Code e GPS).
  - Central de Comprovantes com acesso a todo o histórico de comprovantes eletrônicos assinados.
  - Consulta ao seu próprio Espelho de Ponto mensal (Timesheet).
  - Consulta à Central de Ajuda, manual interativo e timeline de versões.
- **Auditor Fiscal do Trabalho (`auditor`):**
  - Perfil de consulta exclusivo para fiscalização trabalhista, permitindo extração direta e imediata do AFD (do REP-P), do AEJ (do PTRP), do Atestado de Conformidade e do Relatório Espelho de Ponto.

---

## 3. Requisitos Funcionais (Core Features)

### 3.1. Módulo Registrador (REP-P / Smart Punch)
- **RF01 - Autenticação & Sessões:** Login seguro com e-mail corporativo, hash de senha criptográfico, proteção via Laravel Policies (`UserPolicy`) e Enums tipados (`UserRole`).
- **RF02 - Batida de Ponto com Validação Dupla (Smart Punch):**
  - Leitura ótica rápida de QR Code via scanner nativo no navegador (`html5-qrcode` empacotado localmente sem dependências externas).
  - Captura mandatória da geolocalização via `navigator.geolocation` do navegador do dispositivo.
  - Validação da distância em relação à empresa ou setor através da **Fórmula de Haversine**.
  - **Estrutura Híbrida de Setores (Fallback Inteligente):** Prioriza QR Code e raio de GPS específicos do setor do trabalhador; se nulos, recorre de forma transparente aos parâmetros globais da sede da empresa.
- **RF03 - Carimbo de Tempo Server-Side Inviolável:** O horário da marcação é obtido unicamente do relógio do servidor no fuso horário oficial `America/Maceio` (GMT-3), rejeitando integralmente qualquer horário do dispositivo cliente.
- **RF04 - Ledger Imutável de Marcações (`punch_events`):** Gravação com NSR monotônico contínuo por estabelecimento, timestamp oficial, dados brutos de GPS e hash de integridade SHA-256.
- **RF05 - Comprovante de Registro de Ponto do Trabalhador:** Emissão imediata de comprovante eletrônico em PDF contendo dados da empresa, dados do trabalhador, NSR, horário gravado e assinatura digital PAdES, acessível permanentemente na Central de Comprovantes.
- **RF06 - Emissão do Arquivo Fonte de Dados (AFD):** Geração exclusiva pelo REP-P em formato texto posicional (leiaute MTE atualizado em 31/07/2026), acompanhado de assinatura digital CAdES (.p7s detached).

### 3.2. Módulo de Tratamento de Ponto (PTRP)
- **RF07 - Cadastro Funcional Completo do Servidor:**
  - Armazenamento estruturado de: Cargo/Função, Vínculo Empregatício, Carga Horária Semanal, Zona de Lotação (Urbana/Rural), Setor, Matrícula, CPF e Telefone.
  - Modal dinâmico de cadastro e edição no painel administrativo de funcionários.
- **RF08 - Folha de Ponto A4 Oficial (Modelo Outubro):**
  - Relatório impresso de folha de ponto fiel ao padrão administrativo oficial do RH (`/folha-ponto`).
  - Cabeçalho institucional populado dinamicamente com os dados funcionais do servidor cadastrado no banco.
  - Grade de 31 dias com 4 colunas de marcação (Entrada e Saída da Manhã; Entrada e Saída da Tarde) e coluna de observações/ocorrências.
  - Rodapé oficial contendo local, data e campos de assinatura formal do Servidor e da Chefia Imediata.
  - Estilização para impressão A4 (`@media print`) limpa e sem quebras de página.
- **RF09 - Espelho de Ponto Analítico (Timesheet):** Apuração diária e mensal da jornada com agrupamento por dia, totalizadores de horas trabalhadas, dias cumpridos e média diária.
- **RF10 - Eventos de Tratamento Imutáveis (`treatment_events`):** Tratamento de ocorrências (inclusão de batida esquecida, abono médico, justificativa de falta) com indicação do gestor responsável e motivação legal, mantendo a marcação bruta intacta.
- **RF11 - Motor de Cálculo com Tolerância Legal:** Aplicação da tolerância do Art. 58 § 1º da CLT (5 minutos por marcação, até 10 minutos diários) exclusivamente na fase de apuração do PTRP.
- **RF12 - Banco de Horas:** Controle estruturado de créditos e débitos com compensação e validade contratual.
- **RF13 - Fechamento de Competência:** Congelamento formal do espelho de ponto ao final do mês, gerando hash do período e impedindo tratamentos retroativos sem destravamento auditado.
- **RF14 - Emissão do Arquivo Eletrônico de Jornada (AEJ):** Geração pelo PTRP em formato oficial MTE com assinatura digital CAdES (.p7s).

### 3.3. Interface, Gestão e Experiência
- **RF15 - Landing Page Cinematográfica (`/landing`):** Página de entrada com estética Swiss-Modern, animações GSAP, ScrollTrigger, tabs Alpine.js, timeline de versões e link institucional para KL Tecnologia.
- **RF16 - Sistema Global de Modais Popups:** Feedback visual rico e diálogos de confirmação de exclusão sem uso de alertas nativos do navegador.
- **RF17 - Gestão de Setores e QR Code Físico:** Ferramenta administrativa para geração e impressão de folhas A4 com o QR Code para fixação na entrada do estabelecimento.
- **RF18 - Central de Ajuda & Linha do Tempo de Versões:** Documentação interativa em cards e visualização do histórico de versões (`v1.0.0` a `v1.7.0`).

---

## 4. Requisitos Não-Funcionais

- **RNF01 - Isolamento por Instância:** Execução estritamente single-tenant. Cada cliente possui VPS própria, banco de dados isolado e domínio exclusivo.
- **RNF02 - Segurança & Criptografia:** Exigência mandatória de HTTPS em produção (requisito obrigatório para acesso a câmera e GPS). Senhas com bcrypt/argon2id.
- **RNF03 - Conformidade LGPD:** Coleta de GPS cifrada em repouso e limitada ao instante da batida (sem rastreamento contínuo). Proibição expressa de exclusão em cascata (`restrictOnDelete`) em tabelas com valor probatório trabalhista.
- **RNF04 - Responsividade Mobile-First:** Navegação fluida em smartphones via Bottom Tab Bar e Off-Canvas Drawer.
- **RNF05 - Fuso Horário e Idioma:** Fuso horário unificado `America/Maceio` (GMT-3) e interface 100% em Português do Brasil (`pt_BR`).
- **RNF06 - Independência de Redes Externas:** Scanner QR Code e scripts compilados localmente via Vite, sem dependência de CDNs de terceiros para garantir funcionamento em redes corporativas restritas.