# Histórico de Versões (Changelog)

## v2.2.0 (Atual)
- **Melhorias de Usabilidade, Impressão Limpa e Conformidade Operacional:**
  - **Busca em Tempo Real no Espelho de Ponto (`/timesheet`):** Campo de seleção de colaborador convertido em busca reativa instantânea por Nome ou CPF, com dropdown estilizado e filtro dinâmico.
  - **Impressão Exclusiva da Folha de Ponto (`/folha-ponto`):** Configuração de regras `@media print` para suprimir navegação, sidebar, filtros e menus, imprimindo estritamente a Folha de Ponto Oficial A4 pronta para assinatura física.
  - **Manual do Sistema Segmentado por Perfil (`/ajuda`):** Manual interativo categorizado por perfis de acesso (Colaborador, Gestor, Administrador RH e Auditor / Fiscal do Trabalho), com passo a passo das rotinas diárias e operacionais.
  - **Validação Pública em Destaque na Landing Page (`/`):** Links e badges adicionados na barra superior, menu mobile e rodapé direcionando para a rota pública `/verificar-comprovante`, permitindo a fiscais e auditores validarem a integridade do ponto sem login.
  - **Validação Cadastral & Máscaras em Funcionários (`/admin/employees`):** Máscaras reativas para CPF (`000.000.000-00`) e Telefone (`(82) 9 9999-9999`), validação de e-mail corporativo (`example@email.com`) e formatação visual padronizada do CPF na tabela.
  - **Recuperação de Permissão de Câmera e GPS (`/ponto`):** Modal amigável com orientações detalhadas de desbloqueio no navegador (Chrome, Safari iOS, Edge) caso o usuário recuse o acesso por engano, acompanhado de botão de reativação imediata ("Tentar Novamente").
  - **Correção da Persistência do QR Code nas Configurações (`/admin/settings`):** Isolamento do canvas com `wire:ignore` e escuta dos eventos de ciclo de vida do Livewire (`morph.updated` e `qr-code-regenerated`), garantindo que o QR Code permaneça visível mesmo ao alternar a chave do Banco de Horas.
  - **Sidebar Retrátil Otimizado para Tablets:** Toggle de recolhimento e expansão do sidebar com persistência de estado no `localStorage` (`pf_sidebar_collapsed`), facilitando a navegação em tablets e telas compactas.
  - **Cartaz Oficial de Impressão do QR Code (`/admin/settings`):** Impressão isolada exclusiva do cartaz institucional com dados da empresa, CNPJ, logotipo, instruções e QR Code de alta resolução para fixação na entrada do estabelecimento.

## v2.1.0
- **Calendário Laboral, Feriados e Pontos Facultativos (Fase 20.18):**
  - **Diferenciação Jurídica Rigorosa (Lei 9.093/1995 & Portarias Administrativas):** Feriados legais e pontos facultativos modelados com comportamentos distintos (`work_behavior`). Pontos facultativos não eliminam jornadas automaticamente nem são tratados como feriados sem decisão expressa do Admin/RH.
  - **Tipologia e Escopo Territorial Hierárquico:** Eventos classificados em Feriados (`HOLIDAY`), Pontos Facultativos (`OPTIONAL_DAY`), Recessos/Suspensões (`INSTITUTIONAL_CLOSURE`) e Expedientes Especiais (`SPECIAL_WORKDAY`), com resolução hierárquica por escopo: Estabelecimento -> Municipal -> Estadual -> Nacional.
  - **Suporte a Eventos Parciais (Meio Período):** Permite configurar eventos de meio período (ex: Quarta-feira de Cinzas até as 14h), recalculando a jornada restante com base na intersecção exata com os períodos da escala do trabalhador.
  - **Trabalho em Feriado (`holiday_minutes`):** Horas laboradas em feriados são apuradas separadamente como `holiday_minutes` no DTO da jornada, sem assumir automaticamente horas extras ou banco de horas sem política de convenção aplicável.
  - **Painel Administrativo do Calendário (`/admin/calendar`):** Visão de calendário mensal interativa, filtros por ano/tipo/estabelecimento, cadastro de novos eventos, formulário com fundamentação legal e importação inteligente de feriados nacionais móveis (Páscoa, Sexta-feira Santa, Carnaval sugerido).
- **Central de Fiscalização Trabalhista, Snapshots Imutáveis & Emissão do AEJ (Fase 21):**
  - **Fechamento de Competência Imutável:** Congelamento determinístico com hash SHA-256 canônico englobando colaboradores, escalas, jornadas apuradas, tratamentos, banco de horas e o calendário laboral da competência. Modificações futuras no calendário não alteram meses já encerrados.
  - **Emissão do AEJ (Arquivo Eletrônico de Jornada - Leiaute MTE 31/07/2026):** Geração do arquivo fiscal oficial padronizado (Registros Tipo 1, 2, 3, 4 e 5) com validação posicional e de integridade para a Inspeção do Trabalho.
  - **Modo Prévia vs. Oficial:** Prévia para conferência antes do fechamento e geração definitiva auditada vinculada ao hash da competência.
- **Identidade Visual Corporativa & Logomarca na Folha de Ponto:**
  - Configuração de Razão Social, Nome Fantasia, CNPJ/CNO e upload de logotipo em `/admin/settings`, renderizado no cabeçalho da Folha de Ponto A4 Oficial.
- **Suíte de Testes Automatizados Expandida:** `99 testes e 442 asserções 100% aprovados`.

## v2.0.0
- **Motor de Tratamento PTRP, Jornadas, Tolerância Legal & Banco de Horas Configurável (Fase 20):**
  - **Ledger de Eventos de Tratamento (`treatment_events`):** Registros inalteráveis em ULID para ajustes, batidas esquecidas manuais (`manual_punch_added`), desconsiderações de marcações indevidas (`punch_disregarded`) e abonos de faltas/atestados (`absence_justified`), preservando intacto o fato bruto em `punch_events`.
  - **Fluxo de Solicitações do Trabalhador & Gestão RH (`treatment-requests`):** Colaboradores solicitam ajustes com justificativa obrigatória e carimbo de auditoria; administradores e gestores analisam, aprovam ou rejeitam formalmente com justificativa registrada. Separação rigorosa de funções (trabalhador não pode autoaprovar sua solicitação).
  - **Jornada de Trabalho e Escalas Versionadas (`work_schedules`):** Modelagem desacoplada sem hardcode, com grade horária semanal, intervalos intrajornada, folgas contratuais e tolerâncias legais vinculadas ao colaborador.
  - **Motor de Apuração Analítica (`CalculateDailyJourneyAction`):** Combinação do fato bruto do REP + tratamentos aprovados + escala + diretrizes legais para apurar minutos previstos, trabalhados, ordinários, horas extras, atrasos, saídas antecipadas, intervalos e créditos/débitos para banco de horas.
  - **Tolerância Legal do Art. 58, § 1º da CLT:** Aplicada estritamente na camada de cálculo (até 5 min por batida, com limite de 10 min diários), sem qualquer alteração retroativa do horário registrado no `PunchEvent`.
  - **Banco de Horas em Ledger Imutável (`time_bank_accounts` e `time_bank_transactions`):** Saldo SEMPRE apurado a partir de `SUM(minutes)` de lançamentos auditáveis, jamais sobrescrito como campo numérico mutável.
  - **Configuração da Política de Banco de Horas (`time_bank_policies`):** Painel administrativo em `Admin → Configurações → Banco de Horas`, desativado por padrão e ativado formalmente pelo RH com escolha do modo de fechamento:
    - `CARRY_OVER`: Saldo acumulado transportado integralmente para a próxima competência sem movimentação artificial.
    - `MONTHLY_RESET`: Geração de lançamento compensatório contábil (`monthly_reset = -saldo`) no fechamento formal, zerando o saldo para o próximo mês sem destruir o histórico anterior (inclusive para saldos devedores).
  - **Processo Formal de Fechamento de Competência (`CloseMonthlyPeriodAction`):** Bloqueio estrito de zeramento automático por virada de calendário. O encerramento ocorre exclusivamente por ação formal do RH, congelando a competência em `closed_periods`.
  - **Extrato do Banco de Horas (`/admin/time-bank`):** Painel gerencial com filtros por mês, ano, funcionário e tipo de movimentação, com resumo de créditos, débitos, saldo líquido e modais para ajuste manual e fechamento.
  - **Integração no Espelho de Ponto (`timesheet`):** Card dinâmico de Banco de Horas apresentando saldo anterior, créditos, débitos, ajustes e saldo atual, além de modal direto para solicitação de tratamento pelo trabalhador.
  - **Suíte de Testes Automatizados Expandida:** `68 testes e 280 asserções 100% aprovados`.

## v1.9.0
- **Central de Comprovantes do Trabalhador, Gerador AFD (MTE 2026) & Validação Pública:**
  - **Tabela e Modelo `punch_receipts`:** Vínculo 1:1 rigoroso com `punch_events` via chave estrangeira com proteção de integridade (`restrictOnDelete`), armazenando código de verificação amigável (`PF-XXXX-XXXX-XXXX`), hash SHA-256 e metadados de assinatura.
  - **Central de Comprovantes do Trabalhador:** Painel interativo permanente (`/receipts`) para consulta e visualização de comprovantes de ponto por mês/ano, com busca por código de verificação, detalhes da marcação e download instantâneo.
  - **Motor de Geração de Comprovante em PDF (Sem bibliotecas externas):** Emissão de documento PDF 1.4 binário padronizado contendo dados da empresa empregadora, estabelecimento, trabalhador, CPF, data e horário local, fuso horário, NSR oficial, chave SHA-256 do ponto e link direto para verificação pública.
  - **Identificação Transparente de Desenvolvimento:** Comprovantes marcados expressamente com aviso de ambiente não assinado enquanto pendente certificado ICP-Brasil e registro definitivo no INPI.
  - **Arquitetura de Assinatura Desacoplada (`SigningServiceInterface`):** Interface de domínio limpa permitindo futura assinatura eletrônica PAdES/CAdES com certificado ICP-Brasil sem acoplar bibliotecas criptográficas externas ao core.
  - **Validador Público de Comprovantes (`/receipts/verify`):** Consulta pública por código de verificação que reconstrói e compara em tempo real o hash criptográfico contra o ledger inalterável de ponto, provando autenticidade a qualquer fiscal ou colaborador.
  - **Gerador Oficial de AFD (Portaria 671/2021 — Leiaute MTE 31/07/2026):**
    - Construído exclusivamente sobre os dados brutos inalterados do REP (`punch_events`), em conformidade absoluta com a proibição de uso de dados tratados (`treatment_events`).
    - Registro Tipo 1 (Cabeçalho: 236 posições), Tipo 3 (Marcação REP-P: 101 posições) e Tipo 9 (Trailer: 63 posições com totalizadores e CRC-32).
    - Suporte a filtros por estabelecimento e período temporal com download instantâneo no formato `.txt` formatado com quebras CRLF.
  - **Validador Interno do AFD (`AfdValidator`):** Verificador posicional que checa tipos de registro, tamanhos exatos de linha, monotonicidade cronológica de NSR, formato de datas/horas e consistência de totalizadores.
  - **Golden Tests Automatizados (`AfdGoldenTest`):** Testes com fixture de referência byte-a-byte prevenindo qualquer quebra de conformidade em atualizações futuras.
  - **Suíte de Testes Expandida:** `50 testes e 216 asserções 100% aprovados`.

## v1.8.0
- **Fundação Regulatória REP-P & Estabelecimentos com NSR Atômico (Portaria 671/2021 MTP):**
  - **Empresa Única da Instalação (`companies`):** Entidade de domínio central para a arquitetura de Instância Dedicada (Single-Tenant), armazenando dados cadastrais oficiais e identificação de registro no INPI.
  - **Estabelecimentos com NSR Monotônico Independente (`establishments`):** Matriz e filiais com CNPJ/CNO, endereço, timezone e contador monotônico atômico `nsr_next` protegido com lock pessimista (`lockForUpdate`), sem colisões sob concorrência e sem depender de `MAX(nsr)+1`.
  - **Ledger Imutável de Marcações (`punch_events`):** Registro inalterável com chave primária em ULID, timestamps em UTC e horário local, dados de GPS, fuso horário, hash SHA-256 da carga e encadeamento criptográfico com a marcação anterior (`previous_event_hash`).
  - **Trava Estrita de Imutabilidade:** O modelo `PunchEvent` bloqueia qualquer tentativa de `update()` ou `delete()` com exceção formal sob a legislação trabalhista brasileira.
  - **Serviços DDD de Domínio:** Implementação de `CurrentCompany`, `NsrGeneratorService` e `RecordPunchEventAction`.
  - **Integração na Batida de Ponto (`TimePunch`):** Gravação simultânea no ledger oficial REP-P e exibição imediata do NSR formatado (ex: `NSR #000000001`) no modal de confirmação ao colaborador.
  - **Suíte de Testes Expandida:** Suíte `RepPFoundationTest` adicionando 7 novos testes de concorrência, hash e imutabilidade (`38 testes e 155 asserções 100% aprovados`).

## v1.7.0
- **Cadastro Funcional do Colaborador & Integração da Folha de Ponto:**
  - Adição dos campos funcionais à tabela `employees`: Cargo (`job_title`), Vínculo (`contract_type`), Carga Horária Semanal (`workload`) e Zona (`zone`).
  - Atualização completa do Gerenciador de Funcionários (`employees.blade.php`) com suporte à visualização, criação e edição de colaboradores com listas inteligentes de sugestão.
  - Carregamento automático em tempo real de todos os dados funcionais na Folha de Ponto Oficial A4 (`folha-ponto.blade.php`) diretamente do perfil cadastrado do servidor.
- **Diretrizes e Arquitetura PontoFácil 2.0 (Instância Dedicada / Single-Tenant):**
  - Documentação normativa e de compliance integral em `.agents/skills/pontofacil-compliance/` segundo a Portaria 671/2021 MTP (leiautes MTE 2026), CLT e LGPD.
  - Fixação da regra arquitetural de Instância Dedicada: 1 Empresa cliente = 1 Domínio + 1 Banco de Dados + 1 VPS, expurgando qualquer conceito de multi-tenancy.
  - Especificação do ledger imutável `punch_events` (sem `tenant_id`), sequenciador atômico de NSR por estabelecimento (`nsr_next`), Central de Comprovantes com PAdES e separação estrita REP-P vs PTRP.
- **Suíte de Testes Expandida:**
  - Testes automatizados cobrindo a integridade dos dados cadastrais do servidor e sua injeção na folha de ponto (`31 testes e 128 asserções aprovados`).

## v1.6.0
- **Landing Page Cinematográfica & Página Inicial Oficial:**
  - Implementação da Landing Page moderna do PontoFácil com tipografia Swiss-Modern, tema de cores em tons de índigo e slate, animações cinematográficas de scroll via GSAP (Core + ScrollTrigger com `matchMedia` responsivo) e interatividade com Alpine.js.
  - Mockup de relógio em tempo real com precisão cirúrgica por segundo no fuso oficial de Brasília/Maceió (GMT-3) e simulação de batida instantânea.
  - Demonstração interativa com abas "Visão Colaborador" (com efeito de scanner laser contínuo) e "Visão Gestor/RH" (com métricas consolidadas e tabela auditada em tempo real).
  - A Landing Page agora é a página inicial padrão do sistema (`/`), com links institucionais para a KL Tecnologia (`https://kltecnologia.com`).
- **Relatório Oficial de Folha de Ponto de Funcionário:**
  - Novo módulo de impressão oficial de frequência em formato A4 idêntico ao modelo da Prefeitura Municipal de Teotônio Vilela / Secretaria Municipal de Saúde.
  - Tabela completa de 31 dias dividida em Horário Matutino e Horário Vespertino com demarcação automática de sábados e domingos.
  - Suporte ao modo duplo: preenchimento automático a partir das batidas eletrônicas do sistema ou geração de folha em branco com marcadores (`: `) para preenchimento manual.
  - Metadados customizáveis de Carga Horária, Cargo, Vínculo, Zona e Local/Setor com bloco de assinaturas regulamentares (Servidor, Coordenador, Responsável pelo Setor e Recursos Humanos).
- **Suíte de Testes Automatizados Expandida:**
  - Novos testes automatizados para a Landing Page (`LandingPageTest`) e para o relatório oficial de Folha de Ponto (`FolhaPontoTest`).

## v1.5.0
- **Estrutura Híbrida Inteligente de Setores (Fallback):**
  - Adicionados campos opcionais ao cadastro de cada Setor: QR Code próprio (`qr_code_hash`), Latitude (`latitude`), Longitude (`longitude`) e Raio permitido (`allowed_radius_meters`).
  - **Regra Inteligente com Fallback Automático:**
    - Se o setor tiver localização e/ou QR Code próprios preenchidos, o sistema valida rigorosamente a regra daquele setor/filial.
    - Se o setor deixar os campos em branco, o sistema recorre automaticamente (fallback transparente) ao QR Code e GPS globais da empresa (matriz).
  - **Interface Administrativa de Setores:** Painel com botões para captura direta do GPS do setor, gerador de QR Code exclusivo, visualização e impressão de QR Code por setor e badges informativos na listagem ("QR Setor", "GPS Setor", "Matriz / Padrão Global").
  - **Feedback Geográfico Contextual:** Mensagens de erro informam especificamente se a distância excedeu o limite em relação ao setor do colaborador ou à matriz da empresa.
- **Sistema Global de Modais Popups:**
  - Todas as notificações de alerta, sucesso, informação e erro agora abrem em popups modais elegantes com ícones animados e botões de ação dedicados.
  - Substituição de todas as janelas nativas de confirmação (`confirm()` / `wire:confirm`) por modais elegantes de dupla checagem com destaque visual para ações de exclusão.
- **Redesign do Módulo "Novidades e Versões":**
  - Transformação da exibição em linha do tempo visual contínua (timeline) com nós conectados, destaques VIP para a versão atual, cartões de melhorias estruturados e manual do usuário interativo.
- **Suíte de Testes Automatizados Expandida:** 21 testes e 71 asserções cobrindo cenários de fallback híbrido, regras restritivas por setor, renderização da timeline e notificações de versão.

## v1.4.0
- **Perfil de Gestor:** Novo nível de acesso que permite cadastrar e gerenciar colaboradores exclusivamente nos setores sob sua responsabilidade, com menu e políticas de autorização dedicadas.
- **Configurações da Empresa & QR Code:** Novo módulo administrativo com visualização, geração e impressão do QR Code físico para o estabelecimento, além de calibração das coordenadas GPS e raio permitido.
- **Correção Visual de Modais:** Resolução do efeito de desfoque/camada (backdrop blur) que deixava os modais ilegíveis, garantindo nitidez e legibilidade imediata nas ações de criação e edição.
- **Sistema 100% em PT-BR:** Tradução e localização completas das mensagens de validação com nomes amigáveis para campos, autenticação, paginação e datas/meses em português via Carbon.
- **Fuso Horário Oficial de Maceió (GMT-3):** Configuração do fuso `America/Maceio` para registro de ponto inviolável e relógio digital sincronizado em tempo real.
- **Espelho de Ponto & Cálculo de Horas Trabalhadas:** Totalização automática da jornada considerando múltiplos pares de batidas diárias (entrada, almoço, volta e saída), cálculo de horas trabalhadas por dia, identificador de jornada em andamento e cards de resumo mensal (Total trabalhado, Dias trabalhados, Média diária).
- **Comando de Dados de Teste:** Utilitário `php artisan ponto:test-data` para gerar e limpar (`--clean`) dados fictícios completos para validação rápida em ambiente de desenvolvimento.
- **Auditoria Aprimorada & Testes:** Correção de carregamento de relacionamento do administrador na trilha de auditoria e expansão da suíte de testes automatizados (`15 testes, 42 asserções`).

## v1.3.0
- **Interface Mobile-First:** Design 100% responsivo otimizado para celulares e tablets.
- **Menu Lateral Off-Canvas:** Gaveta deslizante suave com backdrop e botão de menu hambúrguer para dispositivos móveis.
- **Barra de Navegação Inferior (Bottom Tab Bar):** Acesso rápido aos botões Ponto, Espelho e Ajuda na palma da mão.
- **Relógio Digital em Tempo Real:** Visual moderno de relógio de ponto com atualização por segundo no fuso oficial de Brasília.
- **QR Code & GPS Otimizados para Celular:** Scanner com dimensões adaptativas, botões táteis ampliados e feedback instantâneo.
- **Tabelas Administrativas com Scroll Horizontal:** Todas as listagens (Setores, Funcionários, Usuários, Auditoria e Relatórios) adaptadas para telas estreitas sem quebra de layout.
- **Biblioteca QR Code Local:** Empacotamento direto no bundle JS do Vite, eliminando requisições CDN e avisos de Tracking Prevention.

## v1.2.0
- Painel Administrativo concluído com os 5 módulos operacionais.
- Gerenciamento completo de Funcionários e Usuários (com geração automática de credenciais).
- Relatórios Gerenciais com filtros de datas, setor e funcionário.
- Inclusão do campo "Responsável do Setor".

## v1.1.0
- Lançamento inicial do PontoFácil (REP-A).
- Implementação da validação dupla (QR Code + GPS).
- Módulo de Espelho de Ponto (Timesheet) para gestores e funcionários.
- Painel seguro para o RH realizar ajustes manuais.
- Criação e integração da trilha de auditoria para ajustes manuais.

## v0.9.0
- Versão Beta para testes internos.
- Testes iniciais com html5-qrcode.
