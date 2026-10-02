# 06 - Segurança da Informação, LGPD & Auditoria Forte

---

## 1. Isolamento Físico como Pilar Fundamental de Segurança e LGPD

A arquitetura de **Instância Dedicada (Single-Tenant)** fornece, por concepção estrutural (*Security & Privacy by Design*), o mais alto grau de segurança para a Lei Geral de Proteção de Dados (Lei nº 13.709/2018):

```text
Cliente A (ponto.empresa-a.com.br)         Cliente B (ponto.empresa-b.com.br)
         │                                          │
    [VPS Isolada]                              [VPS Isolada]
         │                                          │
├── Banco de Dados Dedicado                ├── Banco de Dados Dedicado
├── Filesystem / Storage Dedicado          ├── Filesystem / Storage Dedicado
├── Sessões e Usuários Dedicados           ├── Sessões e Usuários Dedicados
└── Backups Criptografados Próprios        └── Backups Criptografados Próprios
```

> **Garantia Arquitetural:** Não há risco de vazamento cruzado de dados entre diferentes empresas contratantes, pois os bancos e sistemas de arquivos são fisicamente e logicamente separados.

---

## 2. Princípios de Privacidade Aplicados ao Ponto

A **Autoridade Nacional de Proteção de Dados (ANPD)** estabelece que coordenadas de geolocalização e informações funcionais associadas a uma pessoa natural constituem **dados pessoais**.

### 2.1 Diretrizes para Coleta de Geolocalização (GPS)
1. **Coleta Pontual Exclusiva:** A localização geográfica é capturada única e exclusivamente no milissegundo em que o colaborador aciona o botão de confirmação de ponto.
2. **Proibição Absoluta de Rastreamento Contínuo:** O PontoFácil **NUNCA** rastreia o colaborador em segundo plano fora do ato da batida.
3. **Criptografia em Repouso:** As coordenadas de latitude e longitude são armazenadas com criptografia simétrica forte (**AES-256**) na tabela `punch_events`.
4. **Minimização de Acesso:** A visualização de mapas detalhados de batidas é restrita aos gestores autorizados da empresa, sendo todo acesso gravado na trilha de auditoria.

### 2.2 Tratamento de Biometria Facial
- O PontoFácil 2.0 **não adota reconhecimento facial como requisito padrão obrigatório**, pois biometria é dado pessoal sensível (Art. 11 da LGPD).
- Se ativado como módulo opcional pela empresa cliente:
  - Exige Relatório de Impacto à Proteção de Dados Pessoais (**RIPD / DPIA**);
  - Preferência por validação biométrica local no dispositivo (WebAuthn / Passkeys / hardware security enclave);
  - Não mantém repositórios desprotegidos de fotografias de colaboradores.

---

## 3. Correção Crítica de Integridade no Banco de Dados

> **Vulnerabilidade Identificada no Legado:** Migrations com `onDelete('cascade')` em chaves estrangeiras de usuários ou marcações permitem a destruição acidental ou criminosa de provas trabalhistas com valor probatório de até 30 anos.

### Diretriz Obrigatória no PontoFácil 2.0:
- Substituir todos os `cascadeOnDelete()` relacionados a marcações e auditoria por:
  ```php
  $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
  ```
- **Preservação Perpétua de Histórico:** O vínculo empregatício pode ser encerrado e o login desativado, mas os eventos de ponto registrados naquele período jamais podem ser apagados do banco de dados da empresa.

---

## 4. Exclusão Lógica e Ciclo de Vida do Funcionário

O cadastro de empregados **nunca é excluído fisicamente** (`DELETE`):
- Uso de máquina de estados para o vínculo:
  ```text
  active      (Colaborador ativo registrando ponto regularmente)
  leave       (Afastado por licença médica, maternidade, INSS)
  terminated  (Contrato rescindido - preservado para fins probatórios)
  archived    (Arquivado após transcurso de prazos prescricionais legais)
  ```
- Datas formais de contratação (`hired_at`) e rescisão (`terminated_at`) controladas para impedir batidas retroativas ou posteriores ao término do contrato.

---

## 5. Trilha de Auditoria Forte: `audit_events` (Sem Tenant)

Toda intervenção administrativa relevante na instalação é gravada em um **Ledger Forense de Auditoria**:

```text
id                     ULID PRIMARY KEY
actor_id               BIGINT NOT NULL REFERENCES users(id) (Usuário que executou a ação)
actor_role             VARCHAR(32) NOT NULL (admin, manager, employee, auditor)
event                  VARCHAR(64) NOT NULL (Ex: punch.created, timesheet.closed, user.role_changed)
resource_type          VARCHAR(64) NOT NULL (Ex: PunchEvent, WorkSchedule, TreatmentEvent)
resource_id            VARCHAR(64) NOT NULL

before_hash            VARCHAR(64) nullable (SHA-256 do estado anterior)
after_hash             VARCHAR(64) nullable (SHA-256 do estado modificado)

reason                 TEXT nullable (Justificativa informada)

ip_address             VARCHAR(45) NOT NULL
user_agent             TEXT NOT NULL

occurred_at            TIMESTAMP WITH TIME ZONE DEFAULT NOW()
previous_audit_hash    VARCHAR(64) (Encadeamento à prova de adulteração)
event_hash             VARCHAR(64) NOT NULL (Assinatura SHA-256 do registro)
```

Eventos obrigatoriamente auditados:
- Logins bem-sucedidos e falhas de autenticação;
- Alterações em escalas e jornadas contratuais;
- Solicitação, aprovação e rejeição de tratamentos de ponto;
- Fechamento e reabertura formal de competências de ponto;
- Geração e download de arquivos fiscais AFD e AEJ;
- Acesso ou consulta a dados sensíveis de geolocalização.

---

## 6. Segurança da Aplicação e Infraestrutura

- **Autenticação:** Hashing de senhas via **Argon2id** ou Bcrypt com custo elevado; autenticação em dois fatores (**MFA**) recomendada para administradores e RH da empresa.
- **Proteção Ativa:** Rate limiting em endpoints de autenticação e marcação para conter credential stuffing e ataques de força bruta.
- **Metas de Resiliência na VPS do Cliente:**
  - **Disponibilidade (SLA):** 99.9%+;
  - **RPO (Recovery Point Objective):** ≤ 15 minutos;
  - **RTO (Recovery Time Objective):** ≤ 1 hora;
  - Rotinas de backup diário do MySQL com envio criptografado para storage seguro secundário.
