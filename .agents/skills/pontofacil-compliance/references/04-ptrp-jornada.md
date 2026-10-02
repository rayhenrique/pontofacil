# 04 - PTRP: Motor de Tratamento, Jornadas & Apuração Analítica

---

## 1. Arquitetura do PTRP na Instalação Dedicada

O **Programa de Tratamento de Registro de Ponto (PTRP)** opera em uma camada desacoplada e posterior ao registrador de ponto, consumindo os dados da empresa na instalação local:

```text
┌─────────────────────────────────┐
│     Ledger punch_events         │ (Fatos brutos imutáveis)
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│     PTRP Treatment Engine       │ (Interpretação e regras versionadas)
│  - treatment_events             │
│  - work_schedule_assignments    │
│  - time_bank_accounts           │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│     Treated Journey Results     │ (Jornada apurada minuto a minuto)
├────────────────┬────────────────┤
│ AEJ Oficial    │ Espelho Ponto  │ Folha de Pagamento / ERP
└────────────────┴────────────────┘
```

> **Princípio Central:** O PTRP jamais altera ou remove um registro da tabela `punch_events`. Para ajustar uma ocorrência, ele gera um **evento de tratamento** que anota uma desconsideração lógica ou adiciona uma marcação manual compensatória, referenciando a justificativa e o responsável.

---

## 2. Eventos de Tratamento: `treatment_events`

Toda ação de gestão, justificativa de atraso ou inclusão manual é persistida como um evento auditável no banco de dados da empresa:

### 2.1 Tipos de Eventos Padronizados
- `manual_punch_added`: Marcação esquecida ou em contingência autorizada;
- `punch_disregarded`: Marcação duplicada ou indevida desconsiderada no cálculo;
- `absence_added`: Falta computada;
- `absence_justified`: Ausência abonada por atestado médico ou convocação judicial;
- `schedule_changed`: Alteração excepcional de turno ou horário;
- `bank_credit` / `bank_debit`: Lançamento de crédito ou desconto no banco de horas;
- `preassigned_interval`: Intervalo intrajornada pré-assinalado previsto em acordo;
- `exception_recorded`: Registro de anomalia no regime de ponto por exceção;
- `holiday_compensation`: Compensação de feriado trabalhado.

### 2.2 Estrutura do Evento de Tratamento (Sem Tenant)
```text
id                     ULID PRIMARY KEY
employment_id          BIGINT NOT NULL REFERENCES employments(id)
reference_punch_id     ULID nullable REFERENCES punch_events(id)
type                   VARCHAR(32) NOT NULL (Tipo do evento)

effective_at           TIMESTAMP WITH TIME ZONE NOT NULL (Data/hora de aplicação)
old_value_json         JSON nullable (Estado anterior)
new_value_json         JSON NOT NULL (Novo estado tratado)

reason_code            VARCHAR(16) NOT NULL (Código fiscal de ocorrência do MTE)
reason_text            TEXT NOT NULL (Justificativa informada)
attachment_path        VARCHAR(255) nullable (Atestado médico ou comprovante)

requested_by           BIGINT NOT NULL REFERENCES users(id) (Colaborador solicitante)
approved_by            BIGINT nullable REFERENCES users(id) (Gestor ou RH que aprovou)

created_at             TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

---

## 3. Workflow de Solicitações do Trabalhador

O colaborador pode justificar ausências ou esquecimentos diretamente pelo aplicativo:

```text
[Rascunho] -> (draft)
     ↓
[Enviado pelo Trabalhador] -> (submitted)
     ↓
[Em Análise pelo Gestor/RH] -> (under_review)
     ↓
[Decisão Final]:
   ├── Aprovado -> (approved)  ──> Dispara Treatment Event e reprocessa
   ├── Rejeitado -> (rejected)  ──> Notifica com motivo da recusa
   └── Cancelado -> (cancelled) ──> Desistência pelo próprio colaborador
```

---

## 4. Jornadas Contratuais e Escalas Versionadas

As regras de trabalho são modeladas em entidades flexíveis e versionadas no tempo, nunca em hardcode:
- `WorkSchedule`: Definição de grade semanal (5x2, 6x1, turnos rotativos, 12x36 conforme Art. 59-A da CLT);
- `WorkScheduleVersion`: Versionamento da grade com `effective_from` e `effective_to`;
- `ShiftPattern`: Horários previstos de entrada, intervalos e saídas por dia;
- `BreakRule`: Regras de intervalo intrajornada (15m para 4–6h, mínimo 1h para > 6h, Art. 71 CLT);
- `OvertimeRule`: Percentuais aplicáveis (50% dias úteis, 100% domingos/feriados ou índices sindicais).

---

## 5. Motor de Apuração Analítica em Minutos

O PontoFácil 2.0 decompõe rigorosamente a apuração de cada dia em **minutos analíticos individuais**:

```text
Minutos Contratuais     scheduled_minutes       Minutos previstos na escala
Minutos Trabalhados     worked_minutes          Tempo total com permanência efetiva
Minutos Ordinários      ordinary_minutes        Minutos normais cumpridos
Horas Extras            overtime_minutes        Sobrejornada diurna
Horas Noturnas          night_minutes           Minutos entre 22h e 5h com hora ficta
Extras Noturnas         night_overtime_minutes  Sobrejornada cumprida em horário noturno
Atrasos                 late_minutes            Minutos de atraso além da tolerância
Saídas Antecipadas      early_leave_minutes     Saída antes do término do expediente
Intervalo Cumprido      break_minutes           Duração real do descanso intrajornada
Intervalo Suprimido     missing_break_minutes   Intervalo descumprido (CLT Art. 71 § 4º)
Minutos de Falta        absence_minutes         Ausência em jornada prevista
Banco de Horas Crédito  bank_credit_minutes     Minutos direcionados ao banco de horas
Banco de Horas Débito   bank_debit_minutes      Minutos descontados do banco
Feriados / DSR          holiday_minutes / dsr   Incidência de repouso semanal remunerado
```

---

## 6. Regra Legal de Tolerâncias: Art. 58, § 1º da CLT

- **Regra:** Variações de até **5 minutos** em cada marcação, observado o limite diário de **10 minutos**, não são descontadas nem computadas como jornada extraordinária.
- **Princípio Arquitetural:** **A tolerância atua apenas na apuração final**, jamais alterando o dado original da batida:
  - *Exemplo:* Se o colaborador bateu às `07:57`, a marcação gravada em `punch_events` continua sendo rigorosamente `07:57`. O motor de apuração computa `08:00` apenas na soma de horas líquidas, sem modificar o fato histórico original.

---

## 7. Banco de Horas em Ledger Próprio: `time_bank_accounts`

O saldo de banco de horas não é um campo numérico simples sobrescrito; é um **extrato contábil de lançamentos**:

```text
id                 BIGINT AUTO_INCREMENT PRIMARY KEY
employment_id      BIGINT NOT NULL REFERENCES employments(id)
occurred_on        DATE NOT NULL (Data de referência da jornada)
movement_type      VARCHAR(16) NOT NULL (credit_he, debit_compensation, adjustment, expiration)
minutes            INTEGER NOT NULL (Valor em minutos com sinal positivo ou negativo)
source_id          ULID nullable (Aponta para o treatment_events ou apuração diária)
policy_id          BIGINT NOT NULL (Regra vigente de compensação: semestral ou anual)
expiration_date    DATE NOT NULL (Data fatal para quitação antes do pagamento em folha)
created_at         TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

---

## 8. Ciclo de Fechamento de Competência Mensal

```text
[Aberto (Open)] 
      ↓
[Revisão pelo RH / Gestor (Review)]
      ↓
[Conferência pelo Colaborador (Employee Review)]
      ↓
[Fechado e Congelado (Closed)]
      ↓
[Exportação para Folha / AEJ (Payroll Exported)]
```

Após o status `Closed`:
- A apuração e o Espelho de Ponto daquele mês tornam-se **completamente imutáveis**;
- Qualquer correção posterior exige **reabertura formal com justificativa auditável** em log forense;
- O histórico da versão anterior do espelho é arquivado e nunca substituído silenciosamente.
