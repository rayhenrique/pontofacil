# TASKS.md — Histórico de Tarefas e Roadmap do PontoFácil

---

## 🏁 Fases Concluídas e Homologadas

- [x] **Fase 1: Infraestrutura e Base Laravel 13**
  - [x] Criação do projeto Laravel 13 com PHP 8.3+.
  - [x] Instalação do Livewire 4 via `composer require livewire/livewire` e publicação do layout base.
  - [x] Configuração de `.env` para MySQL 8 (InnoDB, UTF-8).
  - [x] Migrations e Models para `system_settings`, `time_entries` e `time_adjustments`.
  - [x] Enum `UserRole` e Seeder padrão (Admin e parâmetros corporativos).

- [x] **Fase 2: Autenticação e Autorização Granular**
  - [x] Rotas autenticadas sob middleware `auth`.
  - [x] Enum `UserRole` (Admin, Manager, Employee) integrado ao Model `User`.
  - [x] Policies do Laravel (`UserPolicy`) para controle de acesso às rotas de RH e colaboradores.

- [x] **Fase 3: Core (Batida de Ponto - Frontend)**
  - [x] Componente Livewire View-Based `TimePunch` para colaboradores.
  - [x] Integração da biblioteca local `html5-qrcode` no bundle Vite (sem dependência de CDN).
  - [x] Captura de coordenadas GPS via Alpine.js e `navigator.geolocation`.
  - [x] Envio de payload assíncrono para o backend via `$wire`.

- [x] **Fase 4: Core (Batida de Ponto - Backend & Antifraude)**
  - [x] Validação criptográfica do hash do QR Code.
  - [x] Cálculo da distância entre o colaborador e a empresa via Fórmula de Haversine.
  - [x] Carimbo de tempo inviolável cravado unicamente pelo servidor no fuso `America/Maceio` (GMT-3).
  - [x] Feedback visual de validação de raio e confirmação instantânea.

- [x] **Fase 5: Dashboard e Espelho de Ponto (Timesheet)**
  - [x] Page Component Livewire `Timesheet` servido via `Route::livewire()`.
  - [x] Agrupamento de batidas por dia com totalização de horas trabalhadas.
  - [x] Filtros por mês e ano com segmentação por perfil (colaborador vê o seu, gestor vê sua equipe, admin vê todos).

- [x] **Fase 6: Ajuste Manual e Trilha de Auditoria**
  - [x] Interface para o RH registrar batidas esquecidas com justificativa formal obrigatória.
  - [x] Flag `is_manual = true` em `time_entries`.
  - [x] Gravação permanente e imutável na tabela `time_adjustments`.

- [x] **Fase 7: Módulos Administrativos**
  - [x] Módulo "Setores": CRUD de setores com atribuição de gestor responsável.
  - [x] Módulo "Funcionários": Cadastro de servidores vinculados a setores (com CPF e telefone).
  - [x] Módulo "Usuários": Gestão de credenciais e perfis de acesso.
  - [x] Módulo "Auditoria": Consulta à trilha de alterações manuais.
  - [x] Módulo "Relatórios": Filtros combinados por período, setor e funcionário.

- [x] **Fase 8: Interface Mobile-First & Otimização de Assets**
  - [x] Barra de navegação inferior fixa (Bottom Tab Bar) para smartphones.
  - [x] Gaveta lateral deslizante suave (Off-Canvas Drawer).
  - [x] Relógio digital em tempo real no padrão oficial de relógio de ponto físico.
  - [x] Compilação estática no Vite sem dependência de internet externa.

- [x] **Fase 9: Perfil de Gestor & Gestão de Equipe**
  - [x] Atribuição da role `UserRole::Manager`.
  - [x] Módulo "Gestão de Equipe" filtrando estritamente os colaboradores dos setores gerenciados (`manager_id`).
  - [x] Bloqueio de autorização em `save()` e `delete()` para evitar alterações indevidas.

- [x] **Fase 10: Localização Integral PT-BR & Fuso de Maceió**
  - [x] Arquivos de tradução em `lang/pt_BR` (validações, atributos, autenticação).
  - [x] Configuração global de timezone `America/Maceio` (GMT-3) no Laravel.
  - [x] Relógio digital com fuso travado em Maceió via Alpine.js.

- [x] **Fase 11: Configurações da Empresa & QR Code Físico**
  - [x] Módulo `/admin/settings` para o Administrador.
  - [x] Gerador de QR Code em tela com ferramenta de impressão em folha A4 para fixação no estabelecimento.
  - [x] Rotação segura do hash com 1 clique e calibração de GPS com auto-captura.

- [x] **Fase 12: Qualidade de Código & Testes Automatizados**
  - [x] Testes de autorização de gestores e isolamento de setor.
  - [x] Testes de traduções, validações e fuso horário `America/Maceio`.
  - [x] Testes de auditoria e configurações.

- [x] **Fase 13: Estrutura Híbrida Inteligente de Setores (Fallback)**
  - [x] Migration com campos opcionais em `sectors`: `qr_code_hash`, `latitude`, `longitude`, `allowed_radius_meters`.
  - [x] Métodos auxiliares `hasCustomLocation()` e `hasCustomQrCode()` no Model `Sector`.
  - [x] Algoritmo de fallback inteligente no registro de ponto: prioriza parâmetros do setor e recorre automaticamente à sede se nulos.
  - [x] Gerador de QR Code e captura de GPS exclusiva por setor na interface administrativa.

- [x] **Fase 14: Sistema Global de Modais Popups e Redesign de Novidades**
  - [x] Componente global `modal-feedback` para alertas, erros, sucesso e confirmações de exclusão sem uso de diálogos nativos do navegador.
  - [x] Redesign do módulo "Novidades e Versões" (`help.blade.php`) com timeline visual de nós conectados.
  - [x] Atualização do modal `version-notifier`.

- [x] **Fase 15: Landing Page Cinematográfica & Apresentação Institucional (v1.6.0)**
  - [x] Estrutura Blade modular em `resources/views/components/landing/`.
  - [x] Design Swiss-Modern SaaS com Tailwind CSS v4, tipografia limpa e contrastes sóbrios.
  - [x] Animações cinematográficas de scroll com GSAP 3.12 e ScrollTrigger.
  - [x] Mockup de relógio ao vivo no Hero e tabs interativas em Alpine.js (Visão Colaborador com simulação de laser scanner e Visão Gestor).
  - [x] Rodapé oficial com link institucional para a [KL Tecnologia](https://kltecnologia.com).

- [x] **Fase 16: Folha de Ponto A4 Oficial (Modelo Outubro)**
  - [x] Componente Livewire View-Based `folha-ponto` acessível em `/folha-ponto`.
  - [x] Layout fiel ao modelo administrativo oficial do RH.
  - [x] Cabeçalho institucional populado dinamicamente com dados funcionais do servidor.
  - [x] Grade mensal de 31 dias contendo turnos matutino e vespertino com coluna de ocorrências.
  - [x] Campos de assinatura formal do Servidor e da Chefia Imediata com local e data.
  - [x] Otimização para impressão A4 (`@media print`) limpa e sem quebras de página.

- [x] **Fase 17: Cadastro Funcional do Servidor & Lançamento v1.7.0**
  - [x] Migration adicionando `job_title`, `contract_type`, `workload`, `zone` em `employees`.
  - [x] Atualização do Model `Employee` e formulário modal de cadastro/edição em `admin/employees.blade.php`.
  - [x] Integração em tempo real entre o perfil do servidor e o cabeçalho da Folha de Ponto A4.
  - [x] Elevação da versão para **`v1.7.0`** com atualização de changelog, timeline e suíte de testes (31 testes, 128 asserções — 100% aprovados).

- [x] **Fase 18: Fundação REP-P & Estabelecimentos com NSR Atômico**
  - [x] Modelagem das tabelas `companies` (1 por instalação) e `establishments` (Matriz / Filiais com CNPJ/CNO).
  - [x] Implementação de contador monotônico de NSR (`nsr_next`) individual por estabelecimento, protegido com lock de transação (`lockForUpdate`).
  - [x] Tabela `punch_events` (ledger bruto imutável) com hash SHA-256 encadeado e integridade referencial `restrictOnDelete`.
  - [x] Serviços DDD: `CurrentCompany`, `NsrGeneratorService` e `RecordPunchEventAction`.
  - [x] Integração no registro de ponto `TimePunch` com emissão e exibição do NSR oficial.
  - [x] Suíte de testes automatizados dedicados (`RepPFoundationTest` - 7 novos testes, 38 testes no total).

---

## 🔮 Próximas Fases: Conformidade PontoFácil 2.0 (Instância Dedicada)

- [x] **Fase 19A: Comprovantes, AFD & Validação — MVP**
  - [x] Criar tabela/model `punch_receipts` com relacionamento 1:1 com `punch_events`.
  - [x] Implementar Central de Comprovantes do Trabalhador com acesso permanente ao histórico.
  - [x] Gerar comprovante em PDF contendo os dados disponíveis exigidos para REP-P: empresa, estabelecimento/local, trabalhador, CPF, data/hora, timezone, NSR, SHA-256 e código de verificação.
  - [x] Enquanto não houver certificado ICP-Brasil e registro INPI configurados, identificar o documento como comprovante de desenvolvimento/não assinado, sem declarar validade regulatória REP-P.
  - [x] Criar `SigningServiceInterface` desacoplada da geração do comprovante, preparando futura assinatura PAdES sem acoplar certificado ao domínio.
  - [x] Criar página de verificação do comprovante pelo `verification_code`, validando o hash contra o `PunchEvent`.
  - [x] Implementar gerador versionado do Arquivo Fonte de Dados (AFD) conforme o leiaute oficial MTE publicado em 31/07/2026.
  - [x] Permitir gerar AFD por estabelecimento e intervalo temporal.
  - [x] Garantir que o AFD seja construído exclusivamente a partir dos registros brutos do REP (`punch_events`), nunca de `treatment_events` ou dados tratados.
  - [x] Criar validador interno do AFD com verificação de tipos de registro, tamanhos, posições, NSR, datas, horários e demais regras do leiaute.
  - [x] Criar Golden Tests/fixtures conhecidos para o AFD, cobrindo arquivo válido, múltiplos funcionários, múltiplas marcações, estabelecimento distinto e casos inválidos.
  - [x] Garantir testes automatizados para autorização da Central de Comprovantes, integridade de hash e geração determinística do AFD.

- [ ] **Fase 19B: Assinaturas Oficiais REP-P — Dependências Externas**
  - [ ] Configurar número definitivo do registro do software PontoFácil no INPI.
  - [ ] Configurar certificado digital ICP-Brasil válido em armazenamento seguro.
  - [ ] Implementar `IcpBrasilSigningService`.
  - [ ] Assinar eletronicamente os comprovantes PDF no padrão PAdES.
  - [ ] Assinar AFD no padrão CAdES com arquivo `.p7s` detached.
  - [ ] Validar cadeia do certificado, validade, algoritmo e integridade das assinaturas.
  - [ ] Somente após esses requisitos, habilitar documentos como saída oficial REP-P.

- [x] **Fase 20: Motor de Tratamento PTRP, Jornada & Banco de Horas Configurável**
  - [x] **20.1 Eventos de Tratamento (`treatment_events`):** Tabela de ajustes, inclusões manuais, desconsiderações e abonos sem alterar `punch_events`. Tipos: `manual_punch_added`, `punch_disregarded`, `absence_added`, `absence_justified`, `classification_override` com status `pending`, `approved`, `rejected` e auditoria administrativa.
  - [x] **20.2 Jornada de Trabalho (`work_schedules`):** Modelagem de jornadas e escalas esperadas sem hardcode, suportando horários por dia da semana, intervalo intrajornada, folgas e vinculação ao `Employee`/`Employment`.
  - [x] **20.3 Motor de Apuração (`CalculateDailyJourneyAction`):** Combinação de `PunchEvents` originais + `TreatmentEvents` aprovados + `WorkSchedule` + `LaborPolicy` -> `CalculatedJourney` apurando minutos previstos, trabalhados, ordinários, extras, atrasos, saídas antecipadas, intervalos, ausências e créditos/débitos de banco.
  - [x] **20.4 Tolerância Legal (Art. 58 § 1º CLT):** Regra exclusivamente da camada de apuração (5 min por batida, até 10 min diários), mantendo a marcação bruta em `punch_events` estritamente inalterada.
  - [x] **20.5 Banco de Horas em Ledger Imutável:** Tabelas `time_bank_accounts` e `time_bank_transactions` (ULID) com saldo SEMPRE calculado via `SUM(minutes)`, nunca sobrescrito como campo numérico.
  - [x] **20.6 Configuração Admin/RH (`time_bank_policies`):** Painel em `Admin → Configurações → Banco de Horas` (desativado por padrão) com seleção de modo de fechamento: `CARRY_OVER` (acumular) ou `MONTHLY_RESET` (zerar).
  - [x] **20.7 a 20.10 Regras CARRY_OVER, MONTHLY_RESET e Fechamento Formal:**
    - `CARRY_OVER`: o saldo é transportado automaticamente para o próximo mês sem movimentação artificial.
    - `MONTHLY_RESET`: gera transação contábil compensatória de zeramento (`monthly_reset = -saldo`) preservando 100% do histórico anterior no ledger (inclusive saldo devedor/negativo).
    - Não zera por virada de calendário: zeramento ocorre exclusivamente no ato formal de **Fechar Competência** (`CloseMonthlyPeriodAction`).
  - [x] **20.11 Interface do Colaborador (Espelho de Ponto):** Exibição do card do Banco de Horas (saldo anterior, créditos, débitos, ajustes, saldo atual, encerramento mensal) e botão de solicitação de ajuste/justificativa.
  - [x] **20.12 a 20.15 Gestão Admin/RH, Extrato e Permissões:** Tela de extrato com filtros (`/admin/time-bank`), modal de ajuste manual (`AdjustTimeBankAction`), gestão de solicitações (`/admin/treatment-requests`) e autorização RBAC.
  - [x] **20.16 Auditoria Forense:** Registro estruturado de logs (`time_bank.enabled`, `time_bank.policy_changed`, `time_bank.monthly_reset`, `treatment.requested`, `treatment.approved`, `period.closed`).
  - [x] **20.17 Suíte de Testes Automatizados:** Cobertura de acúmulo, zeramento com histórico, saldo negativo, fechamento formal, tolerância legal e fluxo de aprovação/rejeição de tratamentos.

- [x] **Fase 21A: Fechamento de Competência, Snapshot Imutável & AEJ — MVP**
  - [x] **21.0 Auditoria e Correções Prévias Obrigatórias:**
    - [x] Corrigir `CloseMonthlyPeriodAction` para que em `MONTHLY_RESET` calcule o saldo utilizando `$account->balanceUntil($periodEndDate)` e NÃO `currentBalance()`, impedindo que movimentações posteriores contaminem o fechamento retroativo.
    - [x] Criar teste cobrindo fechamento retroativo (ex: Janeiro +08h, Fevereiro +03h; fechar Janeiro em Fevereiro deve aplicar reset considerando estritamente as +08h).
    - [x] Revisar integridade referencial: substituir `cascadeOnDelete()` por `restrictOnDelete()` ou `nullOnDelete()` nas tabelas históricas auditáveis (`time_bank_accounts`, `time_bank_transactions`, `closed_periods`, `treatment_events`), garantindo que a inativação ou exclusão de usuários/funcionários jamais destrua o histórico congelado.
  - [x] **21.1 Evolução de `closed_periods` (Tabela Existente):**
    - [x] Adicionar campos de integridade e métricas: `snapshot_version`, `snapshot_hash`, `employees_count`, `punches_count`, `treatments_count`, `generated_at`.
    - [x] Adicionar suporte a reabertura controlada com versionamento: `reopened_by`, `reopened_at`, `reopen_reason` (nunca apagar um fechamento anterior).
  - [x] **21.2 Snapshot por Trabalhador (`closed_period_employee_snapshots`):**
    - [x] Criar tabela com chave primária em ULID, `closed_period_id`, `employee_id`, `employee_snapshot` (JSON: dados cadastrais, cargo, vínculo, setor, estabelecimento), `schedule_snapshot` (JSON: escala, horários, tolerância), `journey_snapshot` (JSON: apuração analítica de horas, minutos, atrasos, faltas), `treatment_snapshot` (JSON: inclusões, desconsiderações, abonos), `time_bank_snapshot` (JSON: saldo anterior, créditos, débitos, reset, saldo final) e `snapshot_hash` (SHA-256).
  - [x] **21.3 Congelamento Real da Competência (`CloseMonthlyPeriodAction`):**
    - [x] Validar competência, verificar tratamentos pendentes, apurar todos os funcionários ativos, capturar escala e política vigentes, aplicar fechamento do banco até o fim do período, gerar snapshots individuais, calcular hash individual e calcular hash agregado determinístico da competência (`closed_period.snapshot_hash`).
  - [x] **21.4 Bloqueio por Tratamentos Pendentes:**
    - [x] Impedir o fechamento caso existam solicitações de tratamento com status `pending`, emitindo alerta informativo e link para o RH revisar as pendências em `/admin/treatment-requests`.
  - [x] **21.5 Bloqueio de Alterações após Fechamento:**
    - [x] Bloquear ajustes manuais, tratamentos, desconsiderações ou alterações de banco de horas retroativas com referência a uma competência congelada.
  - [x] **21.6 Reabertura Formal de Competência (`ReopenMonthlyPeriodAction`):**
    - [x] Fluxo exclusivo Admin/RH exigindo justificativa obrigatória, registrando `reopened_by`, `reopened_at`, `reopen_reason`, preservando o snapshot anterior e versionando o fechamento subsequente.
  - [x] **21.7 Gerador AEJ (Portaria 671/2021 — Leiaute MTE 31/07/2026):**
    - [x] Criar namespace `app/Domain/Compliance/AEJ/` com `AejGeneratorInterface`, `AejGenerator_2026_07_31`, `AejExportResult` e `AejValidator`.
  - [x] **21.8 Fonte de Dados do AEJ (PTRP):**
    - [x] Construir o AEJ a partir do snapshot congelado da competência (`ClosedPeriod` + `ClosedPeriodEmployeeSnapshots`), garantindo que alterações futuras no banco não modifiquem relatórios fiscais do passado.
  - [x] **21.9 Regra de Emissão do AEJ:**
    - [x] AEJ oficial emitido apenas para competências fechadas. Em competências abertas, permitir apenas prévia identificada como `"PRÉVIA — COMPETÊNCIA NÃO FECHADA"`.
  - [x] **21.10 Identificação de Documento de Desenvolvimento / Não Assinado:**
    - [x] Enquanto pendente certificado ICP-Brasil, identificar o AEJ gerado como `"AEJ GERADO — NÃO ASSINADO DIGITALMENTE (MODO DE DESENVOLVIMENTO)"`.
  - [x] **21.11 Interface de Assinatura Desacoplada (`SigningServiceInterface`):**
    - [x] Reutilizar/estender abstração de assinatura preparando `signDetached(content)` com fallback `UnsignedSigningService` para futura injeção de `IcpBrasilSigningService`.
  - [x] **21.12 Validador Interno do AEJ (`AejValidator`):**
    - [x] Validar tipos de registro, ordem, quantidade, tamanho, datas, horas, CPF, totalizadores, encoding e quebras de linha CRLF.
  - [x] **21.13 Golden Tests Automatizados do AEJ (`tests/Fixtures/AEJ/`):**
    - [x] Fixtures conhecidas byte-a-byte cobrindo escalas, intervalos, horas extras, faltas, abonos, desconsiderações, banco de horas e totalizadores.
  - [x] **21.14 Central de Fiscalização para o Empregador (`Admin → Fiscalização`):**
    - [x] Interface `/admin/fiscalizacao` para seleção de competência e estabelecimento, geração e download do Pacote de Fiscalização (ZIP contendo AFD, AEJ, Espelho de Ponto, Comprovantes, Extrato do Banco e Hashes), sem necessidade de login do Auditor Fiscal do Trabalho.
  - [x] **21.15 Perfil de Auditor Genérico (Opcional):**
    - [x] Perfil read-only para download e conferência de hashes, sem poderes operacionais e sem falsa alegação de vínculo com o MTE.
  - [x] **21.16 Suíte de Testes da Fase 21A:**
    - [x] Cobertura de snapshots imutáveis, hash determinístico, bloqueio pós-fechamento, bloqueio por solicitações pendentes, banco retroativo (`balanceUntil`), reabertura versionada e geração determinística do AEJ.
  - [x] **21.17 Auditoria Estruturada:**
    - [x] Eventos forenses: `period.closed`, `period.reopened`, `period.snapshot_generated`, `aej.generated`, `aej.validated`, `fiscal_package.generated`.

- [ ] **Fase 21B: Compliance Externo & Assinatura Digital Oficial — Dependências Externas**
  - [ ] Configurar certificado digital válido ICP-Brasil em storage seguro.
  - [ ] Implementar `IcpBrasilSigningService`.
  - [ ] Assinar o AEJ no padrão CAdES com arquivo `.p7s` detached.
  - [ ] Validar cadeia do certificado ICP-Brasil e carimbo de tempo.
  - [ ] Integrar número definitivo do registro INPI do software.
  - [ ] Habilitar status de documentos fiscais oficiais assinados com Atestado Técnico e Termo de Responsabilidade.