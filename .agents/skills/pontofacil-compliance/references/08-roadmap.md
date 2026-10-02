# 08 - Roadmap Evolutivo & Fases de Implementação (Single-Tenant)

---

## 1. Visão Geral do Roadmap

O roadmap do **PontoFácil 2.0** é estruturado em **5 fases estratégicas**, focadas na evolução do produto em **Instância Dedicada (Single-Tenant)**:

```text
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     FASE 1      │ ──> │     FASE 2      │ ──> │     FASE 3      │ ──> │     FASE 4      │ ──> │     FASE 5      │
│ Fundação        │     │ REP-P &         │     │ PTRP & Motor    │     │ Recursos        │     │ Antifraude &    │
│ Regulatória     │     │ Criptografia    │     │ de Jornadas     │     │ Corporativos    │     │ Modo Offline    │
└─────────────────┘     └─────────────────┘     └─────────────────┘     └─────────────────┘     └─────────────────┘
```

---

## 2. Detalhamento das Fases

### Fase 1: Fundação Regulatória (Prioridade Crítica)
*Objetivo: Estabelecer a integridade de dados e remover qualquer risco de perda de prova trabalhista.*
- [ ] **Empresa e Estabelecimentos:** Modelagem da entidade `Company` única da instalação e de múltiplos estabelecimentos (`Establishment`), cada um com timezone IANA e sequência própria de NSR iniciando em 1 (`nsr_next`).
- [ ] **Vínculos Funcionais:** Cadastro de `Employment` com estados explícitos (`active`, `leave`, `terminated`, `archived`) e datas contratuais.
- [ ] **Ledger Imutável:** Criação da tabela `punch_events` (sem `tenant_id` e sem endpoints de update/delete).
- [ ] **NSR Atômico:** Implementação de gerador transacional concorrente via lock na linha do estabelecimento (sem `MAX(nsr)+1`).
- [ ] **Horário UTC e Offset:** Persistência de `occurred_at_utc` com timezone local preservado.
- [ ] **Correção Crítica no Banco:** Remoção imediata de `cascadeOnDelete()` em tabelas de usuários, marcações e ajustes, substituindo por `restrictOnDelete()`.
- [ ] **Central de Comprovantes:** Histórico completo de comprovantes disponibilizado de forma permanente ao colaborador.
- [ ] **Ledger de Auditoria:** Criação de `audit_events` com encadeamento de hash SHA-256.
- [ ] **Autorização Granular (RBAC):** Perfis `admin`, `manager`, `employee` e `auditor`.

---

### Fase 2: Certificação REP-P & Criptografia Regulatória
*Objetivo: Tornar o PontoFácil um Registrador Eletrônico de Ponto via Programa juridicamente inquestionável.*
- [ ] **Registro INPI:** Protocolo e obtenção do Certificado de Registro de Programa de Computador no INPI.
- [ ] **Assinatura Criptográfica:** Implementação do `ComplianceSigningService` com suporte a certificados ICP-Brasil (A1/A3).
- [ ] **Comprovante PAdES:** Emissão de comprovantes de registro em PDF assinados digitalmente.
- [ ] **Exportador de AFD (MTE 31/07/2026):** Módulo gerador versionado extraído puramente de `punch_events` com cálculo de CRC.
- [ ] **Assinatura CAdES:** Geração automática do arquivo `.p7s` acompanhante do AFD.
- [ ] **Verificação Pública:** Tela web para auditoria de comprovantes com conferência de hash SHA-256.
- [ ] **Atestado Técnico:** Geração eletrônica do Atestado Técnico e Termo de Responsabilidade nominal para a empresa da instalação (`Company`).
- [ ] **Dashboard de Conformidade:** Painel visual com monitoramento do status de INPI, certificados, AFD e integridade.

---

### Fase 3: PTRP & Motor de Tratamento de Jornadas
*Objetivo: Construir o motor avançado de cálculo e apuração analítica segundo a CLT e convenções sindicais.*
- [ ] **Contratos e Escalas Versionadas:** Modelagem de `WorkSchedule`, `ShiftPattern` e suporte a 5x2, 6x1 e 12x36 (Art. 59-A CLT).
- [ ] **Calendário Hierárquico de Feriados:** Herança de feriados Nacionais, Estaduais, Municipais e Facultativos.
- [ ] **Eventos de Tratamento (`treatment_events`):** Inclusões manuais, abonos com atestado e desconsiderações fundamentadas.
- [ ] **Workflow do Colaborador:** Solicitação de ajustes pelo app com acompanhamento de status (`submitted`, `under_review`, `approved`).
- [ ] **Motor de Apuração em Minutos:** Cálculo individualizado de minutos trabalhados, extras, noturnos (hora ficta), atrasos e intervalos suprimidos.
- [ ] **Tolerâncias da CLT:** Aplicação da tolerância de 5 min por batida / 10 min diários (Art. 58 § 1º CLT) apenas no resultado.
- [ ] **Banco de Horas Contábil:** Ledger `time_bank_accounts` com lançamentos auditados, prazos de validade e expiração.
- [ ] **Fechamento de Competência:** Congelamento mensal (`Closed`) e gestão de reabertura auditada.
- [ ] **Exportador de AEJ (MTE 31/07/2026):** Arquivo Eletrônico de Jornada oficial assinado digitalmente com CAdES (.p7s).
- [ ] **Espelho de Ponto Oficial:** Relatório eletrônico e modelo físico de impressão com detalhamento legal completo.

---

### Fase 4: Recursos Corporativos & Integrações
*Objetivo: Capacitar a instalação para integrações com sistemas legados de RH e folha.*
- [ ] **Múltiplos Estabelecimentos:** Gestão centralizada de filiais, postos de saúde e unidades remotas na mesma empresa.
- [ ] **API Versionada `/api/v1/`:** Endpoints para extração de relatórios fiscais e despacho de eventos via Webhooks.
- [ ] **Integração com Folha de Pagamento:** Conectores com sistemas de folha, contabilidade e ERPs da empresa.
- [ ] **Autenticação Avançada:** Suporte a SSO corporativo (SAML 2.0 / OIDC) e MFA mandatório para administradores na VPS.
- [ ] **Relatórios Gerenciais:** Painéis analíticos de absenteísmo, horas extras por setor e custo estimado de sobrejornada.

---

### Fase 5: Antifraude Avançado & Modo Offline
*Objetivo: Máxima resiliência operacional para locais remotos e detecção proativa de anomalias sem bloquear o trabalhador.*
- [ ] **Motor de Análise de Risco:** Score de risco por batida com base em geofence, precisão de sinal e histórico do colaborador.
- [ ] **QR Code Dinâmico Efêmero:** Validação com rotação periódica e assinatura criptográfica temporária no display do coletor.
- [ ] **PWA com Modo Offline:**
  - Armazenamento criptografado no dispositivo local com carimbo de tempo de hardware;
  - Fila de sincronização automática com validação de idempotência no servidor;
  - Registro de evento de transmissão offline conforme requisitos do leiaute AFD vigente.
