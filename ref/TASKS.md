# TASKS.md — Histórico de Tarefas e Roadmap do PontoFácil

---

## 🏁 Fases Concluídas e Homologadas

- [x] **Fase 1: Infraestrutura e Base Laravel 13**
  - [x] Criação do projeto Laravel 13 com PHP 8.3+.
  - [x] Instalação do Livewire 4 via `composer require livewire/livewire` e publicação do layout base.
  - [x] Configuração de `.env` para MySQL 8 (InnoDB, UTF-8).
  - [x] Migrations e Models para `system_settings`, `time_entries` e `time_adjustments`.
  - [x] Enum `UserRole` e Seeder padrão (Admin e parâmetros corporativos).

- [x] **Fase 2: Autenticação e Autorização Granular**
  - [x] Rotas autenticadas sob middleware `auth`.
  - [x] Enum `UserRole` (Admin, Manager, Employee) integrado ao Model `User`.
  - [x] Policies do Laravel (`UserPolicy`) para controle de acesso às rotas de RH e colaboradores.

- [x] **Fase 3: Core (Batida de Ponto - Frontend)**
  - [x] Componente Livewire View-Based `TimePunch` para colaboradores.
  - [x] Integração da biblioteca local `html5-qrcode` no bundle Vite (sem dependência de CDN).
  - [x] Captura de coordenadas GPS via Alpine.js e `navigator.geolocation`.
  - [x] Envio de payload assíncrono para o backend via `$wire`.

- [x] **Fase 4: Core (Batida de Ponto - Backend & Antifraude)**
  - [x] Validação criptográfica do hash do QR Code.
  - [x] Cálculo da distância entre o colaborador e a empresa via Fórmula de Haversine.
  - [x] Carimbo de tempo inviolável cravado unicamente pelo servidor no fuso `America/Maceio` (GMT-3).
  - [x] Feedback visual de validação de raio e confirmação instantânea.

- [x] **Fase 5: Dashboard e Espelho de Ponto (Timesheet)**
  - [x] Page Component Livewire `Timesheet` servido via `Route::livewire()`.
  - [x] Agrupamento de batidas por dia com totalização de horas trabalhadas.
  - [x] Filtros por mês e ano com segmentação por perfil (colaborador vê o seu, gestor vê sua equipe, admin vê todos).

- [x] **Fase 6: Ajuste Manual e Trilha de Auditoria**
  - [x] Interface para o RH registrar batidas esquecidas com justificativa formal obrigatória.
  - [x] Flag `is_manual = true` em `time_entries`.
  - [x] Gravação permanente e imutável na tabela `time_adjustments`.

- [x] **Fase 7: Módulos Administrativos**
  - [x] Módulo "Setores": CRUD de setores com atribuição de gestor responsável.
  - [x] Módulo "Funcionários": Cadastro de servidores vinculados a setores (com CPF e telefone).
  - [x] Módulo "Usuários": Gestão de credenciais e perfis de acesso.
  - [x] Módulo "Auditoria": Consulta à trilha de alterações manuais.
  - [x] Módulo "Relatórios": Filtros combinados por período, setor e funcionário.

- [x] **Fase 8: Interface Mobile-First & Otimização de Assets**
  - [x] Barra de navegação inferior fixa (Bottom Tab Bar) para smartphones.
  - [x] Gaveta lateral deslizante suave (Off-Canvas Drawer).
  - [x] Relógio digital em tempo real no padrão oficial de relógio de ponto físico.
  - [x] Compilação estática no Vite sem dependência de internet externa.

- [x] **Fase 9: Perfil de Gestor & Gestão de Equipe**
  - [x] Atribuição da role `UserRole::Manager`.
  - [x] Módulo "Gestão de Equipe" filtrando estritamente os colaboradores dos setores gerenciados (`manager_id`).
  - [x] Bloqueio de autorização em `save()` e `delete()` para evitar alterações indevidas.

- [x] **Fase 10: Localização Integral PT-BR & Fuso de Maceió**
  - [x] Arquivos de tradução em `lang/pt_BR` (validações, atributos, autenticação).
  - [x] Configuração global de timezone `America/Maceio` (GMT-3) no Laravel.
  - [x] Relógio digital com fuso travado em Maceió via Alpine.js.

- [x] **Fase 11: Configurações da Empresa & QR Code Físico**
  - [x] Módulo `/admin/settings` para o Administrador.
  - [x] Gerador de QR Code em tela com ferramenta de impressão em folha A4 para fixação no estabelecimento.
  - [x] Rotação segura do hash com 1 clique e calibração de GPS com auto-captura.

- [x] **Fase 12: Qualidade de Código & Testes Automatizados**
  - [x] Testes de autorização de gestores e isolamento de setor.
  - [x] Testes de traduções, validações e fuso horário `America/Maceio`.
  - [x] Testes de auditoria e configurações.

- [x] **Fase 13: Estrutura Híbrida Inteligente de Setores (Fallback)**
  - [x] Migration com campos opcionais em `sectors`: `qr_code_hash`, `latitude`, `longitude`, `allowed_radius_meters`.
  - [x] Métodos auxiliares `hasCustomLocation()` e `hasCustomQrCode()` no Model `Sector`.
  - [x] Algoritmo de fallback inteligente no registro de ponto: prioriza parâmetros do setor e recorre automaticamente à sede se nulos.
  - [x] Gerador de QR Code e captura de GPS exclusiva por setor na interface administrativa.

- [x] **Fase 14: Sistema Global de Modais Popups e Redesign de Novidades**
  - [x] Componente global `modal-feedback` para alertas, erros, sucesso e confirmações de exclusão sem uso de diálogos nativos do navegador.
  - [x] Redesign do módulo "Novidades e Versões" (`help.blade.php`) com timeline visual de nós conectados.
  - [x] Atualização do modal `version-notifier`.

- [x] **Fase 15: Landing Page Cinematográfica & Apresentação Institucional (v1.6.0)**
  - [x] Estrutura Blade modular em `resources/views/components/landing/`.
  - [x] Design Swiss-Modern SaaS com Tailwind CSS v4, tipografia limpa e contrastes sóbrios.
  - [x] Animações cinematográficas de scroll com GSAP 3.12 e ScrollTrigger.
  - [x] Mockup de relógio ao vivo no Hero e tabs interativas em Alpine.js (Visão Colaborador com simulação de laser scanner e Visão Gestor).
  - [x] Rodapé oficial com link institucional para a [KL Tecnologia](https://kltecnologia.com).

- [x] **Fase 16: Folha de Ponto A4 Oficial (Modelo Outubro)**
  - [x] Componente Livewire View-Based `folha-ponto` acessível em `/folha-ponto`.
  - [x] Layout fiel ao modelo administrativo oficial do RH.
  - [x] Cabeçalho institucional populado dinamicamente com dados funcionais do servidor.
  - [x] Grade mensal de 31 dias contendo turnos matutino e vespertino com coluna de ocorrências.
  - [x] Campos de assinatura formal do Servidor e da Chefia Imediata com local e data.
  - [x] Otimização para impressão A4 (`@media print`) limpa e sem quebras de página.

- [x] **Fase 17: Cadastro Funcional do Servidor & Lançamento v1.7.0**
  - [x] Migration adicionando `job_title`, `contract_type`, `workload`, `zone` em `employees`.
  - [x] Atualização do Model `Employee` e formulário modal de cadastro/edição em `admin/employees.blade.php`.
  - [x] Integração em tempo real entre o perfil do servidor e o cabeçalho da Folha de Ponto A4.
  - [x] Elevação da versão para **`v1.7.0`** com atualização de changelog, timeline e suíte de testes (31 testes, 128 asserções — 100% aprovados).

- [x] **Fase 18: Fundação REP-P & Estabelecimentos com NSR Atômico**
  - [x] Modelagem das tabelas `companies` (1 por instalação) e `establishments` (Matriz / Filiais com CNPJ/CNO).
  - [x] Implementação de contador monotônico de NSR (`nsr_next`) individual por estabelecimento, protegido com lock de transação (`lockForUpdate`).
  - [x] Tabela `punch_events` (ledger bruto imutável) com hash SHA-256 encadeado e integridade referencial `restrictOnDelete`.
  - [x] Serviços DDD: `CurrentCompany`, `NsrGeneratorService` e `RecordPunchEventAction`.
  - [x] Integração no registro de ponto `TimePunch` com emissão e exibição do NSR oficial.
  - [x] Suíte de testes automatizados dedicados (`RepPFoundationTest` - 7 novos testes, 38 testes no total).

---

## 🔮 Próximas Fases: Conformidade PontoFácil 2.0 (Instância Dedicada)

- [ ] **Fase 19: Comprovante de Registro do Trabalhador & Exportação AFD**
  - [ ] Geração do Comprovante de Ponto do Trabalhador em PDF assinado com PAdES (acessível permanentemente na Central de Comprovantes).
  - [ ] Exportação do Arquivo Fonte de Dados (AFD) posicional conforme leiaute oficial MTE atualizado em 31/07/2026.
  - [ ] Assinatura digital padrão CAdES (.p7s detached).
  - [ ] Golden tests com fixtures de validação do leiaute fiscal.

- [ ] **Fase 20: Motor de Tratamento PTRP, Tolerância Legal & Banco de Horas**
  - [ ] Tabela `treatment_events` para registro imutável de ajustes, abonos e justificativas (sem alterar a batida bruta original).
  - [ ] Motor analítico de apuração minuto a minuto aplicando a regra legal de tolerância do Art. 58 § 1º da CLT (5 min por batida, até 10 min diários).
  - [ ] Ledger do Banco de Horas (`time_bank_accounts` e `time_bank_transactions`) com validade contratual e compensação.

- [ ] **Fase 21: Fechamento de Competência Mensal & Exportação AEJ**
  - [ ] Tabela `closed_periods` com congelamento de espelho de ponto e hash de integridade do período.
  - [ ] Exportação do Arquivo Eletrônico de Jornada (AEJ) no leiaute oficial MTE com assinatura digital CAdES (.p7s).
  - [ ] Perfil dedicado de Auditor Fiscal do Trabalho para emissão e download direto dos arquivos comprobatórios.