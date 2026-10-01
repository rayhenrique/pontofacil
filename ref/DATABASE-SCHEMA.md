# DATABASE-SCHEMA.md

## Convenções
- Motor: InnoDB
- Collation: `utf8mb4_unicode_ci`

## Tabelas

### 1. `users`
- `id` (bigIncrements)
- `name` (string)
- `email` (string, unique)
- `password` (string)
- `role` (enum: 'admin', 'employee')
- `remember_token`
- `timestamps`
- `softDeletes`

### 2. `system_settings`
*(Tabela simples de chave/valor para guardar dados globais da empresa, já que é single-tenant)*
- `id` (bigIncrements)
- `key` (string, unique) -> Ex: 'qr_code_hash', 'company_latitude', 'company_longitude', 'allowed_radius_meters'
- `value` (text)
- `timestamps`

### 3. `time_entries`
*(Guarda cada "batida" individualmente. O frontend agrupa por dia)*
- `id` (bigIncrements)
- `user_id` (foreignId -> users, cascade on delete)
- `timestamp` (datetime) -> O horário cravado pelo servidor.
- `type` (enum: 'in', 'out') -> Opcional no MVP, pode ser inferido por paridade.
- `latitude` (decimal, 10,8, nullable)
- `longitude` (decimal, 11,8, nullable)
- `accuracy` (integer, nullable) -> Precisão do GPS em metros.
- `is_manual` (boolean, default: false)
- `timestamps`
- *Índices:* `user_id`, `timestamp`

### 4. `time_adjustments` (Auditoria)
- `id` (bigIncrements)
- `time_entry_id` (foreignId -> time_entries, cascade on delete)
- `adjusted_by` (foreignId -> users, restrito aos admins)
- `old_timestamp` (datetime, nullable) -> Nulo se foi uma inserção manual do zero.
- `new_timestamp` (datetime)
- `justification` (string, 255)
- `timestamps`