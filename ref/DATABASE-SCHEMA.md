# DATABASE-SCHEMA.md — PontoFácil 2.0 (Instância Dedicada)

## Convenções Gerais
- **Arquitetura de Dados:** Instância Dedicada (Single-Tenant). Nenhuma tabela possui `tenant_id`.
- **Motor:** InnoDB
- **Collation:** `utf8mb4_unicode_ci`
- **Fuso Horário Padrão:** `America/Maceio` (GMT-3)
- **Integridade Referencial:** Tabelas com valor probatório e histórico fiscal NUNCA utilizam `cascadeOnDelete`; empregam `restrictOnDelete` para evitar perda de dados probatórios sob a Portaria 671/2021.

---

## 🏛️ Schema Ativo na Aplicação (v1.7.0)

### 1. `users`
Contas de acesso e autenticação no sistema.
- `id` (bigIncrements, PK)
- `name` (string)
- `email` (string, unique)
- `password` (string, hashed)
- `role` (string/enum: `'admin'`, `'manager'`, `'employee'`)
- `last_seen_version` (string, nullable) -> Versão do último changelog visualizado (ex: `'v1.7.0'`).
- `remember_token` (string, nullable)
- `created_at`, `updated_at` (timestamps)
- `deleted_at` (timestamp, softDeletes)

### 2. `sectors`
Departamentos e setores operacionais da organização (com suporte a Regras Híbridas de Ponto).
- `id` (bigIncrements, PK)
- `name` (string, unique) -> Nome do setor (ex: "Tecnologia", "Financeiro", "Operações", "Filial Ponta Verde").
- `description` (string, nullable)
- `manager_id` (foreignId -> `users.id`, nullable, on delete set null) -> Gestor responsável pelo setor.
- `qr_code_hash` (string, nullable) -> Hash exclusivo do QR Code deste setor (se null, recorre ao hash global da empresa).
- `latitude` (decimal: 10,8, nullable) -> Latitude específica do setor (se null, recorre à latitude global).
- `longitude` (decimal: 11,8, nullable) -> Longitude específica do setor (se null, recorre à longitude global).
- `allowed_radius_meters` (integer, nullable) -> Raio de tolerância em metros específico do setor.
- `created_at`, `updated_at` (timestamps)

### 3. `employees`
Ficha cadastral funcional e trabalhista do colaborador vinculada à conta de usuário e setor.
- `id` (bigIncrements, PK)
- `user_id` (foreignId -> `users.id`, restrict on delete, unique)
- `sector_id` (foreignId -> `sectors.id`, restrict on delete)
- `cpf` (string, 20, unique) -> Documento CPF do colaborador.
- `phone` (string, 20, nullable) -> Telefone de contato.
- `registration_number` (string, nullable) -> Matrícula funcional interna.
- `job_title` (string, nullable) -> Cargo / Função do servidor (ex: "Analista de Sistemas", "Assistente Administrativo").
- `contract_type` (string, nullable) -> Vínculo empregatício (ex: "Efetivo / Concursado", "Contrato Temporário", "Comissionado", "CLT").
- `workload` (string, nullable) -> Carga horária semanal contratual (ex: "40h semanais", "30h semanais", "20h semanais").
- `zone` (string, nullable) -> Zona de lotação geográfica (ex: "Urbana", "Rural").
- `created_at`, `updated_at` (timestamps)

### 4. `system_settings`
Parâmetros globais de configuração e parâmetros de segurança da empresa.
- `id` (bigIncrements, PK)
- `key` (string, unique) -> Chaves:
  - `qr_code_hash`: Hash criptográfico para o QR Code físico da matriz.
  - `company_latitude`: Latitude da sede/matriz (ex: `-9.665800`).
  - `company_longitude`: Longitude da sede/matriz (ex: `-35.735000`).
  - `allowed_radius_meters`: Raio de tolerância padrão em metros (padrão: `100`).
- `value` (text)
- `created_at`, `updated_at` (timestamps)

### 5. `time_entries`
Registro operacional de marcação de ponto.
- `id` (bigIncrements, PK)
- `user_id` (foreignId -> `users.id`, restrict on delete)
- `timestamp` (datetime) -> Horário oficial cravado unicamente pelo servidor (Fuso Maceió).
- `type` (enum/string: `'in'`, `'out'`) -> Tipo de batida (Entrada ou Saída).
- `latitude` (decimal: 10,8, nullable) -> Coordenada GPS capturada no momento da leitura.
- `longitude` (decimal: 11,8, nullable) -> Coordenada GPS capturada no momento da leitura.
- `accuracy` (integer, nullable) -> Precisão do GPS do dispositivo em metros.
- `is_manual` (boolean, default: false) -> Indica se a batida foi inserida/ajustada pelo RH.
- `created_at`, `updated_at` (timestamps)
- *Índices:* `(user_id, timestamp)`, `(timestamp)`

### 6. `time_adjustments` (Trilha de Auditoria)
Histórico permanente e obrigatório de qualquer alteração manual realizada por administradores.
- `id` (bigIncrements, PK)
- `time_entry_id` (foreignId -> `time_entries.id`, restrict on delete)
- `adjusted_by` (foreignId -> `users.id`, restrict on delete) -> Administrador responsável pela alteração.
- `old_timestamp` (datetime, nullable) -> Nulo em lançamentos retroativos de batidas esquecidas.
- `new_timestamp` (datetime) -> Novo horário atribuído à batida.
- `justification` (string, 255) -> Justificativa obrigatória documentada.
- `created_at`, `updated_at` (timestamps)

---

## 🏛️ Schema Alvo de Conformidade PontoFácil 2.0 (REP-P / PTRP)

Nas próximas etapas de evolução de conformidade integral da Portaria 671/2021 MTP, o modelo de dados é expandido mantendo a regra estrita de **Instância Dedicada (sem `tenant_id`)**:

```text
Company (1 por instalação)
   │
   └── Establishment (1..N por Company)
         ├── nsr_next (contador monotônico atômico com lock)
         ├── PunchEvent (ledger imutável REP-P - AFD)
         │      └── Receipt (comprovante PAdES)
         │
         ├── Sector (lotação)
         └── Employment (vínculo funcional)
                ├── TreatmentEvent (eventos de ajuste PTRP)
                ├── TimeBankAccount (ledger de banco de horas)
                └── ClosedPeriod (congelamento mensal de competência)
```

### 7. `companies` (Empresa Única da Instalação)
- `id` (bigIncrements, PK)
- `legal_name` (string, 150) -> Razão Social.
- `trade_name` (string, 100) -> Nome Fantasia.
- `cnpj` (string, 14, unique) -> CNPJ raiz da empresa.
- `rep_p_software_name` (string, 50) -> Nome do software REP-P registrado no INPI.
- `rep_p_software_version` (string, 20) -> Versão ativa do REP-P.
- `created_at`, `updated_at` (timestamps)

### 8. `establishments` (Matriz / Filiais com NSR Independente)
- `id` (bigIncrements, PK)
- `company_id` (foreignId -> `companies.id`, restrict on delete)
- `code` (string, 20) -> Código da filial / unidade.
- `name` (string, 100) -> Nome do estabelecimento.
- `identifier_type` (enum: `'cnpj'`, `'cpf'`, `'cno'`, `'caepf'`)
- `identifier_number` (string, 14) -> CNPJ ou CNO do local de trabalho.
- `address`, `city`, `state`, `postal_code` (strings)
- `nsr_next` (unsignedBigInteger, default: 1) -> **Contador monotônico atômico do NSR** exclusivo deste estabelecimento.
- `created_at`, `updated_at` (timestamps)

### 9. `punch_events` (Ledger Imutável do REP-P)
- `id` (uuid, PK)
- `establishment_id` (foreignId -> `establishments.id`, restrict on delete)
- `employee_id` (foreignId -> `employees.id`, restrict on delete)
- `nsr` (unsignedBigInteger) -> Número Sequencial de Registro (inviolável, sem saltos).
- `recorded_at` (dateTimeTz) -> Data e hora oficial cravada pelo servidor (UTC-3).
- `direction` (enum: `'E'`, `'S'`) -> Entrada ou Saída.
- `source` (enum: `'qr_code'`, `'mobile_app'`, `'web'`)
- `latitude`, `longitude`, `accuracy_meters` (cifrados em repouso)
- `raw_payload_hash` (string, 64) -> Hash SHA-256 do payload para auditoria.
- `created_at` (timestamp imutável)
- *Unique Constraint:* `(establishment_id, nsr)`

### 10. `treatment_events` (Eventos de Tratamento no PTRP)
- `id` (uuid, PK)
- `establishment_id` (foreignId -> `establishments.id`, restrict on delete)
- `employee_id` (foreignId -> `employees.id`, restrict on delete)
- `punch_event_id` (foreignId -> `punch_events.id`, nullable, restrict on delete)
- `event_type` (enum: `'inclusion'`, `'disregard'`, `'justification'`, `'medical_leave'`, `'allowance'`)
- `target_date` (date)
- `original_time`, `effective_time` (time, nullable)
- `justification_code` (string, 20) -> Código padronizado de ocorrência.
- `justification_text` (text) -> Descrição obrigatória do motivo.
- `treated_by_user_id` (foreignId -> `users.id`, restrict on delete)
- `created_at` (timestamp)

### 11. `closed_periods` (Fechamento Mensal Congelado)
- `id` (uuid, PK)
- `establishment_id` (foreignId -> `establishments.id`, restrict on delete)
- `period_year` (smallInteger), `period_month` (tinyInteger)
- `status` (enum: `'open'`, `'processing'`, `'closed'`, `'locked'`)
- `closed_at` (dateTimeTz, nullable)
- `closed_by_user_id` (foreignId -> `users.id`, nullable)
- `hash_fingerprint` (string, 64, nullable) -> Hash SHA-256 do fechamento.