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
  - [x] Criar Enum `UserRole` (Admin, Manager, Employee) e implementá-lo no model `User`.
  - [x] Criar middleware e Policies (`UserPolicy`) para bloquear rotas de RH e restringir acessos.

- [x] **Fase 3: Core (Batida de Ponto - Frontend / API)**
  - [x] Criar um *View-Based Component* no Livewire 4 (`TimePunch`) para a tela inicial do colaborador.
  - [x] Integrar biblioteca JS local (`html5-qrcode`) no bundle do Vite para leitura do QR Code sem dependência de CDN.
  - [x] Acionar `navigator.geolocation.getCurrentPosition` via Alpine.js após a leitura.
  - [x] Enviar payload (hash lido + coords) para o backend Livewire usando `$wire`.

- [x] **Fase 4: Core (Batida de Ponto - Backend)**
  - [x] Validar se o hash lido corresponde a `system_settings` -> `qr_code_hash`.
  - [x] Calcular e validar a distância (Fórmula de Haversine) entre o usuário e a empresa.
  - [x] Salvar o `time_entry` utilizando `now()` do servidor para a coluna `timestamp`.
  - [x] Exibir feedback visual e tratamento de mensagens de sucesso ou erro no raio permitido.

- [x] **Fase 5: Dashboard e Espelho de Ponto**
  - [x] Criar Page Component do Livewire `Timesheet` servido via `Route::livewire()`.
  - [x] Agrupar batidas por dia utilizando Collections e permitir filtro por mês e ano.
  - [x] Permitir que Gestores selecionem membros da sua equipe e Admins selecionem qualquer funcionário.

- [x] **Fase 6: Ajuste Manual (RH)**
  - [x] Interface para o Admin adicionar batidas esquecidas com justificativa.
  - [x] Gravar registro na tabela `time_entries` marcando `is_manual = true`.
  - [x] Gravar obrigatoriamente um registro na tabela `time_adjustments` para trilha de auditoria.

- [x] **Fase 7: Módulos Administrativos**
  - [x] Módulo "Setor": CRUD de setores com designação de gestor responsável.
  - [x] Módulo "Funcionários": Cadastro de colaboradores vinculados a setores (com CPF e Telefone).
  - [x] Módulo "Usuários": Gerenciamento de credenciais e papéis (Admin, Gestor, Colaborador).
  - [x] Módulo "Auditoria": Visualização da trilha de auditoria (`time_adjustments`).
  - [x] Módulo "Relatórios": Filtros avançados por data, setor e colaborador.

- [x] **Fase 8: Interface Mobile-First & Otimização de Assets**
  - [x] Navegação inferior fixa (Bottom Tab Bar) para smartphones.
  - [x] Gaveta lateral deslizante suave (Off-Canvas Drawer) com botão de menu hambúrguer.
  - [x] Relógio digital em tempo real no padrão relógio de ponto físico.
  - [x] Empacotamento de assets no Vite sem conexões externas a CDNs.

- [x] **Fase 9: Perfil de Gestor & Gestão de Equipe**
  - [x] Adicionar `UserRole::Manager` ("Gestor").
  - [x] Módulo "Gestão de Equipe" (`/admin/employees`) filtrando apenas setores geridos pelo usuário logado.
  - [x] Bloqueio em `save()` e `delete()` impedindo alterações fora do setor responsável.

- [x] **Fase 10: Localização Integral PT-BR & Fuso Horário de Maceió**
  - [x] Arquivos de tradução em `lang/pt_BR` (validações, atributos amigáveis, autenticação, paginação).
  - [x] Configuração de locale `pt_BR` e timezone `America/Maceio` (GMT-3) no Laravel e Carbon.
  - [x] Relógio digital com fuso travado em Maceió via Alpine.js.

- [x] **Fase 11: Configurações da Empresa & QR Code**
  - [x] Módulo `/admin/settings` para Administradores.
  - [x] Exibição de QR Code em tela e ferramenta de impressão A4 para fixação na empresa.
  - [x] Rotação segura do hash do QR Code com 1 clique.
  - [x] Calibração de coordenadas GPS com botão de auto-captura.

- [x] **Fase 12: Suíte de Testes Automatizados & Qualidade de Código**
  - [x] Testes de autorização de gestores e isolamento de setor.
  - [x] Testes de traduções, validações em português e fuso horário `America/Maceio`.
  - [x] Testes de trilha de auditoria e permissões de configurações (`19 testes, 62 asserções`).

- [x] **Fase 13: Estrutura Híbrida Inteligente de Setores (Fallback)**
  - [x] Migration com campos opcionais em `sectors`: `qr_code_hash`, `latitude`, `longitude`, `allowed_radius_meters`.
  - [x] Atualização do Model `Sector` com casts, fillable e métodos auxiliares `hasCustomLocation()` e `hasCustomQrCode()`.
  - [x] Algoritmo de Fallback Inteligente no registro de ponto (`TimePunch`): prioriza regras exclusivas do setor do funcionário e recorre automaticamente à matriz da empresa se os campos estiverem em branco.
  - [x] Interface administrativa de Setores (`admin.sectors`) com captura de GPS do setor, gerador de QR Code exclusivo e impressão.
  - [x] Testes automatizados cobrindo cenários de fallback e isolamento geográfico/QR por setor (`HybridSectorPunchTest`).

- [x] **Fase 14: Sistema Global de Modais Popups e Redesign de Novidades & Versões**
  - [x] Componente global `modal-feedback`: Popups elegantes para mensagens de alerta, sucesso, erro e diálogos de confirmação de exclusão/ações destrutivas.
  - [x] Integração em todos os módulos (Bater Ponto, Setores, Funcionários, Usuários, Configurações e Ajuste Manual), substituindo banners estáticos e `wire:confirm` / `alert()` nativos do navegador.
  - [x] Redesign completo do módulo "Novidades e Versões" (`help.blade.php`): Linha do tempo visual (timeline) com nós conectados, destaque para a versão atual `v1.5.0`, cartões categorizados, pills e ícones.
  - [x] Atualização do modal `version-notifier` para `v1.5.0` com visual moderno de boas-vindas.
  - [x] Testes automatizados da nova camada modal e renderização do changelog (`21 testes, 71 asserções`).