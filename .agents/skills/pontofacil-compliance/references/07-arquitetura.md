# 07 - Arquitetura Técnica, DDD & Design de Domínio (Single-Tenant)

---

## 1. O Modelo Fundamental de Domínio

O PontoFácil 2.0 assume estritamente uma arquitetura **Single-Tenant em Instância Dedicada**:

```text
UMA Instalação
      ↓
UMA Empresa (Company)
      ↓
N Estabelecimentos (Establishments)
      ↓
N Vínculos (Employments)
      ↓
N Fatos Brutos (PunchEvents)
      ↓
N Tratamentos (TreatmentEvents)
      ↓
N Apurações (CalculatedJourneys)
      ↓
N Espelhos de Ponto (Timesheets)
```

### 1.1 A Virada de Paradigma nos Dados
```text
MODELO LEGADO (MVP Inicial):
  TimeEntry (id, user_id, punched_at, type = in/out, is_manual)

NOVO MODELO (PontoFácil 2.0):
  ┌───────────────────────┐
  │      PunchEvent       │  -> O FATO: Marcação original imutável
  └───────────┬───────────┘
              │
              ▼
  ┌───────────────────────┐
  │    TreatmentEvent     │  -> A INTERPRETAÇÃO: Justificativa, inclusão ou desconsideração
  └───────────┬───────────┘
              │
              ▼
  ┌───────────────────────┐
  │   CalculatedJourney   │  -> O RESULTADO: Decomposição analítica em minutos
  └───────────┬───────────┘
              │
              ▼
  ┌───────────────────────┐
  │     PeriodClosure     │  -> O CONGELAMENTO: Snapshot mensal com hash e espelho oficial
  └───────────────────────┘
```

---

## 2. Recuperação Limpa da Empresa Atual (`CurrentCompany`)

Como cada instalação atende a uma única empresa cliente, evita-se espalhar hardcodes como `where('company_id', 1)`:

```php
namespace App\Domain\Company\Services;

use App\Models\Company;

class CurrentCompany
{
    protected static ?Company $instance = null;

    public static function get(): Company
    {
        if (static::$instance === null) {
            static::$instance = Company::firstOrFail();
        }

        return static::$instance;
    }
}
```

> **Atenção:** É proibido criar abstrações de multi-tenancy (`TenantContext`, `BelongsToTenant`, `TenantScope`, `TenantMiddleware`). A aplicação é simples e opera com a empresa local configurada no banco.

---

## 3. Estrutura Modular Orientada a Domínio (DDD) no Laravel

A lógica trabalhista e de compliance reside no domínio, não nos componentes Livewire ou controllers:

```text
app/
├── Domain/                           <-- Entidades puras, regras de negócio e contratos
│   ├── Company/                      (Company, CurrentCompany, Establishment, Sector)
│   ├── Employment/                   (Employee, Employment, Contract, JobTitle)
│   ├── TimeClock/                    (PunchEvent, NsrGeneratorService, Collector)
│   ├── TimeTreatment/                (TreatmentEvent, JustificationWorkflow)
│   ├── Scheduling/                   (WorkSchedule, ShiftPattern, HolidayHierarchy)
│   ├── TimeBank/                     (TimeBankAccount, BankLedgerService)
│   ├── Compliance/                   (Motor fiscal MTE)
│   │   ├── AFD/                      (AfdGeneratorInterface, AfdGenerator_2026_07_31)
│   │   ├── AEJ/                      (AejGeneratorInterface, AejExporter)
│   │   ├── Receipt/                  (PadesReceiptGenerator, ReceiptVerifier)
│   │   └── Signing/                  (ComplianceSigningService com ICP-Brasil)
│   └── Audit/                        (AuditEvent, AuditLedgerService)
│
├── Application/                      <-- Casos de uso da aplicação (Actions)
│   ├── Actions/TimeClock/
│   │   └── RegisterPunchAction.php   (Valida contexto, reserva NSR atômico, grava ledger e emite hash)
│   ├── Actions/TimeTreatment/
│   │   ├── RequestAdjustmentAction.php
│   │   └── ApproveAdjustmentAction.php
│   ├── Actions/Scheduling/
│   │   └── CalculateTimesheetAction.php
│   ├── Actions/Payroll/
│   │   └── ClosePeriodAction.php     (Congela competência e arquiva versões)
│   └── Actions/Compliance/
│       ├── GenerateAfdAction.php
│       ├── GenerateAejAction.php
│       └── GeneratePunchReceiptAction.php
│
├── Infrastructure/                   <-- Adaptadores externos, KMS, PDF, filas e DB
│   ├── Cryptography/
│   ├── Storage/
│   └── Notifications/
│
└── Livewire/                         <-- Camada de apresentação reativa pura
    ├── TimeClock/PunchModal.php
    ├── Worker/ReceiptsCenter.php
    ├── Admin/TimesheetManager.php
    └── Compliance/Dashboard.php
```

---

## 4. Requisitos de Performance e Concorrência

- **Tempo de Confirmação da Batida:** Percentil 95 (P95) **inferior a 2 segundos**.
- **Processamento Assíncrono:** A gravação do fato bruto em `punch_events` é síncrona e instantânea; a assinatura PAdES do PDF e os recálculos são despachados para filas em segundo plano (`queues`).
- **Índices Compostos no Banco:**
  ```sql
  -- Chave única do REP-P: sequencial estrito por estabelecimento
  ALTER TABLE punch_events ADD CONSTRAINT uq_est_nsr UNIQUE (establishment_id, nsr);

  -- Otimização para extração do AFD cronológico
  CREATE INDEX idx_est_time ON punch_events (establishment_id, occurred_at_utc);

  -- Otimização para apuração de espelho do trabalhador
  CREATE INDEX idx_emp_time ON punch_events (employment_id, occurred_at_utc);

  -- Validação rápida de idempotência
  CREATE INDEX idx_payload_hash ON punch_events (payload_hash);
  ```

---

## 5. Integrações e API Versionada: `/api/v1/`

O PontoFácil expõe uma API RESTful em cada instalação dedicada para interconexão com ERPs e softwares de folha de pagamento:
- `POST /api/v1/punches`: Coleta de batida externa autorizada;
- `GET /api/v1/employments/{id}/receipts`: Consulta de comprovantes;
- `GET /api/v1/reports/afd`: Extração do arquivo AFD assinado;
- `GET /api/v1/reports/aej`: Extração do arquivo AEJ consolidado;
- `POST /api/v1/periods/{id}/close`: Disparo de fechamento de folha.

> **Garantia de Compliance:** A API **não possui endpoints de edição de batidas originais**, mantendo a integridade inviolável da Portaria 671.
