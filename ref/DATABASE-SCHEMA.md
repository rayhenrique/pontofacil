# DATABASE-SCHEMA.md (Atualizado)

## Convenções
- Motor: InnoDB
- Collation: `utf8mb4_unicode_ci`
- Fuso Horário Padrão: `America/Maceio` (GMT-3)

---

## Tabelas

### 1. `users`
Contas de acesso ao sistema (Admin, Gestor e Colaborador).
- `id` (bigIncrements, PK)
- `name` (string)
- `email` (string, unique)
- `password` (string, hashed)
- `role` (string/enum: `'admin'`, `'manager'`, `'employee'`)
- `last_seen_version` (string, nullable) -> Controla a exibição do modal de novidades/changelog.
- `remember_token` (string, nullable)
- `created_at`, `updated_at` (timestamps)
- `deleted_at` (timestamp, softDeletes)

### 2. `sectors`
Departamentos e setores operacionais da organização (com suporte a Regras Híbridas de Ponto).
- `id` (bigIncrements, PK)
- `name` (string, unique) -> Nome do setor (ex: "Tecnologia", "Financeiro", "Operações", "Filial Ponta Verde").
- `description` (string, nullable)
- `manager_id` (foreignId -> `users.id`, nullable, on delete set null) -> Usuário com perfil Gestor responsável por este setor.
- `qr_code_hash` (string, nullable) -> Hash exclusivo do QR Code deste setor (se null, usa o QR Code global da empresa).
- `latitude` (decimal: 10,8, nullable) -> Latitude específica do setor/filial (se null, usa a latitude global da empresa).
- `longitude` (decimal: 11,8, nullable) -> Longitude específica do setor/filial (se null, usa a longitude global da empresa).
- `allowed_radius_meters` (integer, nullable) -> Raio de tolerância em metros específico do setor (se null, usa o raio global da empresa).
- `created_at`, `updated_at` (timestamps)

### 3. `employees`
Dados cadastrais trabalhistas vinculados à conta de usuário e ao setor.
- `id` (bigIncrements, PK)
- `user_id` (foreignId -> `users.id`, cascade on delete, unique)
- `sector_id` (foreignId -> `sectors.id`, cascade on delete)
- `cpf` (string, 20, unique) -> Documento do colaborador.
- `phone` (string, 20, nullable) -> Telefone de contato.
- `registration_number` (string, nullable) -> Matrícula interna.
- `created_at`, `updated_at` (timestamps)

### 4. `system_settings`
Configurações globais e parâmetros de segurança antifraude da matriz corporativa.
- `id` (bigIncrements, PK)
- `key` (string, unique) -> Chaves:
  - `qr_code_hash`: Hash criptográfico aleatório gerado para o QR Code físico da empresa.
  - `company_latitude`: Latitude da sede (ex: `-9.665800`).
  - `company_longitude`: Longitude da sede (ex: `-35.735000`).
  - `allowed_radius_meters`: Raio máximo de tolerância em metros (padrão: `100`).
- `value` (text)
- `created_at`, `updated_at` (timestamps)

### 5. `time_entries`
Registro inviolável de cada apontamento de jornada (REP-A / Portaria 671).
- `id` (bigIncrements, PK)
- `user_id` (foreignId -> `users.id`, cascade on delete)
- `timestamp` (datetime) -> Horário oficial cravado unicamente pelo servidor (Fuso Maceió).
- `type` (enum/string: `'in'`, `'out'`) -> Tipo de batida (Entrada ou Saída).
- `latitude` (decimal: 10,8, nullable) -> Coordenada GPS capturada no momento da leitura.
- `longitude` (decimal: 11,8, nullable) -> Coordenada GPS capturada no momento da leitura.
- `accuracy` (integer, nullable) -> Precisão do GPS do dispositivo em metros.
- `is_manual` (boolean, default: false) -> Indica se a batida foi inserida/ajustada pelo RH.
- `created_at`, `updated_at` (timestamps)
- *Índices:* `user_id`, `timestamp`

### 6. `time_adjustments` (Trilha de Auditoria)
Histórico permanente e obrigatório de qualquer alteração manual realizada por administradores.
- `id` (bigIncrements, PK)
- `time_entry_id` (foreignId -> `time_entries.id`, cascade on delete)
- `adjusted_by` (foreignId -> `users.id`) -> Administrador responsável pela alteração.
- `old_timestamp` (datetime, nullable) -> Nulo em lançamentos retroativos de batidas esquecidas.
- `new_timestamp` (datetime) -> Novo horário atribuído à batida.
- `justification` (string, 255) -> Justificativa obrigatória documentada.
- `created_at`, `updated_at` (timestamps)