# TASKS.md (Atualizado)

- [x] **Fase 1: Infraestrutura e Base Laravel 13**
  - [x] Executar `laravel new controle-ponto --git`.
  - [x] Instalar o Livewire 4 via `composer require livewire/livewire` e publicar o layout base.
  - [x] Configurar `.env` para MySQL 8.
  - [x] Criar migrations para `system_settings`, `time_entries` e `time_adjustments`.
  - [x] Atualizar migration de `users` adicionando a coluna `role`.
  - [x] Criar Models correspondentes com `$fillable` e relacionamentos estruturados.
  - [x] Criar um Seeder para popular um usuário Admin padrão e as configurações (latitude, longitude, hash inicial do QR Code).

- [x] **Fase 2: Autenticação e Autorização**
  - [x] Configurar rotas protegidas pelo middleware `auth`.
  - [x] Criar Enum `UserRole` (Admin, Employee) e implementá-lo no model `User`.
  - [x] Criar middleware ou Policies para bloquear rotas de RH para funcionários comuns.

- [x] **Fase 3: Core (Batida de Ponto - Frontend / API)**
  - [x] Criar um *View-Based Component* no Livewire 4 (`TimePunch`) para a tela inicial do funcionário, mantendo lógica e view no mesmo arquivo de forma limpa.
  - [x] Integrar biblioteca JS leve (`html5-qrcode`) no componente para ler o QR Code.
  - [x] Acionar `navigator.geolocation.getCurrentPosition` via Alpine.js após a leitura.
  - [x] Enviar payload (hash lido + coords) para o backend Livewire usando `$wire`.

- [x] **Fase 4: Core (Batida de Ponto - Backend)**
  - [x] Validar se o hash lido corresponde a `system_settings` -> `qr_code_hash`.
  - [x] Calcular e validar a distância (Fórmula de Haversine) entre o usuário e a empresa.
  - [x] Salvar o `time_entry` utilizando `now()` do servidor para a coluna `timestamp`.
  - [x] Utilizar a nova propriedade `data-loading` do Livewire 4 no frontend para exibir feedback visual automático sem criar variáveis de estado extras.

- [x] **Fase 5: Dashboard e Espelho de Ponto**
  - [x] Criar um Page Component do Livewire chamado `Timesheet` e servi-lo diretamente nas rotas usando `Route::livewire()`.
  - [x] Agrupar as batidas por dia utilizando Collections e implementar `wire:transition` para suavizar animações ao filtrar meses ou listas de usuários.

- [x] **Fase 6: Ajuste Manual (RH)**
  - [x] Interface para o Admin adicionar/editar um ponto.
  - [x] Ao salvar, atualizar/inserir em `time_entries` marcando `is_manual = true`.
  - [x] Gravar obrigatoriamente um registro na tabela `time_adjustments` com a justificativa.

- [x] **Fase 7: Módulos Administrativos**
  - [x] Módulo "Setor": CRUD de setores da empresa.
  - [x] Módulo "Funcionário": Gerenciamento de funcionários (vinculados a setores).
  - [x] Módulo "Usuários": Gerenciamento de credenciais e acessos (Admin/Empregado).
  - [x] Módulo "Auditoria": Visualização da trilha de auditoria (tabela `time_adjustments`).
  - [x] Módulo "Relatórios": Exportação e visualização de relatórios gerenciais consolidados.