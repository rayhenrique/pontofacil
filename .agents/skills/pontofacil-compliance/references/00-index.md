# 00 - Índice Geral, Matriz de Conformidade & Perguntas Auditáveis

---

## 1. Visão Geral do PontoFácil 2.0 (Instância Dedicada)

O **PontoFácil 2.0** é uma plataforma web de controle eletrônico de jornada com separação estrutural entre **REP-P (Registrador)** e **PTRP (Programa de Tratamento)**, desenvolvida sob a égide da **CLT**, da **Portaria MTP nº 671/2021** (incluindo os novos leiautes MTE publicados em 31/07/2026) e da **LGPD (Lei nº 13.709/2018)**.

A arquitetura do PontoFácil adota o princípio de **Instância Dedicada (Single-Tenant)**:
- 1 Instalação = 1 Empresa (`Company`) + 1 Banco de Dados Dedicado + 1 Domínio Próprio + 1 VPS.
- Não há e nunca haverá qualquer infraestrutura multi-tenant, tabelas compartilhadas, `tenant_id` ou middleware de chaveamento de banco.

---

## 2. Matriz de Cobertura das Seções do PRD 2.0

| Seção do PRD | Título do PRD 2.0 | Documento de Referência Técnica |
| :---: | :--- | :--- |
| **§ 1** | Visão do produto (REP-P vs PTRP) | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 2** | Problema que o produto resolve | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 3** | Princípios jurídicos de produto (Registro fiel) | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 4** | Decisão REP-P × REP-A | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 5** | Usuários e permissões (Perfis da instalação) | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 6** | Estrutura organizacional (`Company` e `Establishments`) | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 7** | Novo modelo de marcação — Ledger imutável | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 8** | Número Sequencial de Registro (NSR por estabelecimento) | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 9** | Registro de ponto (Fluxo de marcação) | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 10** | QR Code e geolocalização (Contexto vs Bloqueio) | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 11** | Privacidade — LGPD & Biometria | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) |
| **§ 12** | Comprovante de Registro de Ponto (Central de Comprovantes) | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 13** | Assinatura digital e criptografia regulatória (CAdES/PAdES) | [`05-afd-aej.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/05-afd-aej.md) |
| **§ 14** | AFD — Arquivo Fonte de Dados (MTE 2026) | [`05-afd-aej.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/05-afd-aej.md) |
| **§ 15** | PTRP — Motor de tratamento | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 16** | Solicitação de correção pelo trabalhador | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 17** | Modelo de eventos de tratamento (`treatment_events`) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 18** | Jornada contratual (Escalas versionadas, 12x36, etc.) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 19** | Motor de apuração (Minutos decompostos) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 20** | Tolerância de marcação (Art. 58 § 1º CLT na apuração) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 21** | Intervalos (Intrajornada e pré-assinalação) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 22** | Banco de horas (Ledger de créditos e débitos) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 23** | Instrumentos coletivos e regras versionadas | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 24** | Ponto por exceção (CLT Art. 74 § 4º) | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 25** | AEJ — Arquivo Eletrônico de Jornada (MTE 2026) | [`05-afd-aej.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/05-afd-aej.md) |
| **§ 26** | Espelho de Ponto Eletrônico | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 27** | Fechamento de competência (Ciclo congelado) | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 28** | Feriados (Calendário hierárquico) | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 29** | Multi-timezone (Armazenamento UTC com fuso local) | [`01-prd-produto.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/01-prd-produto.md) |
| **§ 30** | Comprovante, hash e verificabilidade pública | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 31** | Auditoria forte (`audit_events` sem tenant) | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) |
| **§ 32** | Correção crítica no banco: remoção de `cascadeOnDelete` | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) / [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) |
| **§ 33** | Exclusão lógica de funcionário | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) |
| **§ 34** | Segurança da aplicação e MFA | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) |
| **§ 35** | Resiliência, SLAs e backups isolados | [`06-lgpd-seguranca.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/06-lgpd-seguranca.md) |
| **§ 36** | Modo offline (Sincronização segura PWA) | [`08-roadmap.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/08-roadmap.md) |
| **§ 37** | Dashboard de conformidade regulatória | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 38** | Atestado Técnico e Termo de Responsabilidade | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 39** | Registro de software no INPI | [`02-rep-p.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/02-rep-p.md) |
| **§ 40** | Integrações e API versionada (`/api/v1/`) | [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) |
| **§ 41** | Relatórios trabalhistas vs gerenciais | [`04-ptrp-jornada.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/04-ptrp-jornada.md) |
| **§ 42** | UX da tela de ponto (Simplicidade máxima) | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 43** | Antifraude inteligente com score de risco | [`03-marcacoes-nsr.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/03-marcacoes-nsr.md) |
| **§ 44** | Requisitos de performance e concorrência | [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) |
| **§ 45** | Testes obrigatórios e Golden Files | [`05-afd-aej.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/05-afd-aej.md) |
| **§ 46** | Arquitetura DDD Laravel para instância dedicada | [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) |
| **§ 47** | Mudança fundamental no modelo de dados | [`07-arquitetura.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/07-arquitetura.md) |
| **§ 48** | Roadmap evolutivo em 5 fases (Single-Tenant) | [`08-roadmap.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/08-roadmap.md) |
| **§ 49** | Critérios para declarar “conforme REP-P” | [`00-index.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/00-index.md) |
| **§ 50** | Métrica de sucesso do produto (Perguntas Auditáveis) | [`00-index.md`](file:///c:/Users/rayhe/Downloads/pontofacil/.agents/skills/pontofacil-compliance/references/00-index.md) |

---

## 3. As 16 Perguntas Auditáveis da Marcação (§ 50 do PRD)

O PontoFácil 2.0 será considerado plenamente maduro quando qualquer marcação puder responder, de maneira programática e atestável:

1. **Quem marcou?** (Vínculo funcional e trabalhador identificado por CPF e UUID).
2. **Para qual empresa?** (`Company` da instalação atual identificada por Razão Social e CNPJ).
3. **Para qual estabelecimento?** (`Establishment` específico com CNPJ/CNO próprio).
4. **Quando?** (Timestamp UTC unívoco e data/hora local de ocorrência).
5. **Qual era o timezone?** (Timezone IANA, ex: `America/Maceio`, e offset `-03:00`).
6. **Qual é o NSR?** (Número Sequencial de Registro estritamente sequencial daquele estabelecimento).
7. **Qual coletor recebeu?** (ID do coletor, tipo mobile PWA, desktop ou quiosque).
8. **O dado original continua intacto?** (Imutabilidade garantida no ledger `punch_events`, sem update/delete).
9. **Qual é o hash?** (Hash SHA-256 da marcação encadeado com o registro anterior).
10. **Existe comprovante?** (PDF assinado eletronicamente com PAdES e link persistente).
11. **Houve tratamento?** (Indicação se a batida foi aceita integral, desconsiderada ou complementada).
12. **Quem tratou?** (ID e papel do gestor/RH responsável pela aprovação do evento).
13. **Por quê?** (Código legal e justificativa textual com documento anexo comprobatório).
14. **Qual regra estava vigente?** (Versão da política de jornada, escala e CCT aplicável na data).
15. **Qual resultado foi calculado?** (Minutos ordinários, extras 50%/100%, noturnos ou crédito no banco).
16. **Em qual AFD e AEJ está?** (Identificação dos arquivos fiscais emitidos para fiscalização).

---

## 4. Critérios para Declarar “Conforme REP-P” (§ 49 do PRD)

A documentação pública do PontoFácil não utilizará o termo *"integralmente aderente à Portaria 671"* até que os seguintes itens estejam finalizados e homologados:

- [ ] Requisitos REP-P implementados e certificados no INPI;
- [ ] Ledger imutável de marcações (`punch_events`);
- [ ] NSR monotônico por estabelecimento sem cálculo de `MAX()`;
- [ ] Comprovante de Registro de Ponto com assinatura PAdES;
- [ ] Exportador de AFD no leiaute vigente publicado pelo MTE em 31/07/2026;
- [ ] Assinatura digital CAdES (.p7s) do AFD;
- [ ] Motor de tratamento de ponto (PTRP) completo;
- [ ] Relatório Espelho de Ponto Eletrônico oficial;
- [ ] Exportador de AEJ no leiaute vigente com assinatura CAdES;
- [ ] Atestado Técnico e Termo de Responsabilidade eletrônico gerado pelo sistema;
- [ ] Bateria de testes de integridade e Golden Files aprovados no CI;
- [ ] Políticas de proteção de dados, registro de logs e RIPD elaborados.

*Até a homologação final, o sistema adota formalmente a designação: **"Sistema em desenvolvimento com arquitetura orientada aos requisitos da Portaria MTP nº 671/2021"**.*
