---
name: pontofacil-compliance
description: "Diretrizes normativas, arquiteturais e de compliance para o PontoFácil 2.0 (REP-P e PTRP) em arquitetura de Instância Dedicada (Single-Tenant), com base na Portaria 671/2021 MTP (leiautes MTE 2026), CLT e LGPD. Use sempre que implementar, auditar ou refatorar o ledger de marcações, NSR por estabelecimento, motor de cálculo de jornada, banco de horas, comprovantes PAdES, exportações AFD/AEJ CAdES ou arquitetura de domínio."
license: Proprietary
metadata:
  author: PontoFácil Team
  version: "2.0.0"
---

# PontoFácil 2.0 Compliance — Plataforma Web REP-P / PTRP (Instância Dedicada)

Este skill consolida os padrões normativos, jurídicos e arquiteturais do **PontoFácil 2.0**, estruturado sob a premissa fundamental de **Instância Dedicada (Single-Tenant)**: cada empresa cliente possui sua própria instalação independente, com domínio próprio, banco de dados isolado, arquivos próprios e configuração `.env` exclusiva.

---

## 🚫 Regra de Arquitetura: Zero Multi-Tenancy

O PontoFácil **NÃO é e NUNCA será multi-tenant**.

- **Isolamento por Instalação:** Cada cliente opera em seu próprio VPS / container com banco de dados dedicado.
- **Vedação Absoluta:** É estritamente proibido criar `tenant_id`, middleware de tenant, resolução dinâmica de bancos por tenant, escopos globais de tenant ou papéis de "superadmin SaaS".
- **Entidade Raiz:** A aplicação possui uma única empresa configurada por instalação (`Company`), acessível via serviço simples como `CurrentCompany::get()`.
- **Hierarquia de Domínio:**
  ```text
  Company (Empresa Única da Instalação)
     │
     ├── Establishment (Matriz / Filiais com NSR independente)
     │       │
     │       ├── Sector (Setor de Lotação)
     │       ├── Employment (Vínculo do Trabalhador)
     │       └── PunchEvent (Ledger Imutável)
     │
     └── Establishment
  ```

---

## 🎯 Separação Estrutural de Responsabilidades

O PontoFácil 2.0 separa rigidamente dois domínios independentes conforme determinação oficial do Ministério do Trabalho:

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│          REP-P (Registrador)             │      │          PTRP (Tratamento)               │
│                                          │      │                                          │
│  - Recebe e preserva marcação original   │      │  - Interpretação de jornadas contratuais │
│  - Ledger imutável (punch_events)        │      │  - Eventos de tratamento (ajustes/faltas)│
│  - Sequência monotônica NSR / Estabelec. │ ───> │  - Motor de apuração minuto a minuto     │
│  - Comprovante do Trabalhador (PAdES)    │      │  - Banco de horas (ledger de créditos)   │
│  - Emissão exclusiva do AFD (.p7s CAdES) │      │  - Fechamento de competência congelado   │
│  - Não possui regras de tolerância       │      │  - Emissão do AEJ e Espelho de Ponto     │
└──────────────────────────────────────────┘      └──────────────────────────────────────────┘
```

> **Regra de Ouro:** O **AFD** é gerado **exclusivamente pelo REP-P** a partir do ledger bruto de batidas. O **AEJ e o Espelho de Ponto** são gerados **pelo PTRP** a partir do processamento das jornadas e eventos de tratamento.

---

## 📂 Mapa de Referências do PontoFácil 2.0

| Arquivo | Escopo Principal |
| :--- | :--- |
| [`00-index.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/00-index.md) | Matriz de conformidade, glossário, critérios de conformidade e as 16 perguntas auditáveis. |
| [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) | Visão do produto, arquitetura de instância dedicada, entidades `Company` e `Establishment`, timezones e perfis. |
| [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) | Modalidade REP-P vs REP-A, instrumentos coletivos (CCT/ACT), ponto por exceção, registro INPI e Atestado Técnico. |
| [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) | Ledger imutável (`punch_events` sem tenant_id), NSR atômico por estabelecimento, Central de Comprovantes PAdES. |
| [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) | Motor PTRP, solicitações do trabalhador, `treatment_events`, escalas versionadas, apuração analítica e banco de horas. |
| [`05-afd-aej.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/05-afd-aej.md) | Layouts fiscais MTE (atualizados 31/07/2026), geradores versionados, assinaturas digitais CAdES/PAdES e golden tests. |
| [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) | Privacidade LGPD com isolamento de banco por cliente, GPS cifrado, `audit_events` com hash, eliminação de `cascadeOnDelete`. |
| [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) | Arquitetura DDD Laravel para instância dedicada, Actions, novo modelo de dados, deployment e atualizações por git. |
| [`08-roadmap.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/08-roadmap.md) | Roadmap evolutivo em 5 fases para o produto single-tenant dedicado. |

---

## ⚠️ Princípios Invioláveis de Implementação

1. **Instância Dedicada:** Cada cliente possui seu próprio banco e `.env`. Nenhuma tabela possui `tenant_id`.
2. **A Marcação Original Nunca é Editada nem Apagada:** Qualquer correção no PTRP cria um `TreatmentEvent` com justificativa, preservando intacto o `PunchEvent` original.
3. **Não-Bloqueio de Ponto:** Jamais impedir a marcação de ponto por motivo de escala, tolerância, excesso de horas ou GPS impreciso (Art. 74/78 Portaria 671).
4. **NSR Monotônico Seguro:** Sequência de 1 até N por estabelecimento gerada atomicamente com locks transacionais, sem depender de `MAX(nsr) + 1`.
5. **Comprovante Acessível:** Central de comprovantes com histórico completo do trabalhador (muito além das 48h mínimas) e assinatura PAdES.
6. **Zero Cascade Delete em Histórico Trabalhista:** Marcações e eventos passados são eternos e utilizam `restrictOnDelete`.
