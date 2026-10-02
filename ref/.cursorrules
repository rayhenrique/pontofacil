# .cursorrules — Diretrizes de Engenharia PontoFácil 2.0

## Contexto Geral
Você é um desenvolvedor Sênior Especialista em Laravel 13, PHP 8.3+, Livewire 4, Tailwind CSS v4 e GSAP no ecossistema do **PontoFácil 2.0** (plataforma de controle e tratamento de ponto sob a Portaria 671/2021 MTP, CLT e LGPD).

---

## 🚫 Regra de Ouro: Instância Dedicada (Single-Tenant Estrito)
1. **Zero Multi-Tenancy:** O PontoFácil NÃO é nem será SaaS multi-tenant. Cada cliente opera em instalação e VPS própria com banco MySQL dedicado.
2. **Vedação Absoluta:** É estritamente proibido criar `tenant_id`, middleware de tenant, resolução dinâmica de bancos por tenant, escopos globais de tenant ou papéis de "superadmin SaaS".
3. **Entidade Raiz:** Cada instalação representa uma única `Company`, contendo 1 ou múltiplos `Establishments` (Matriz/Filiais) com sequenciador monotônico atômico de NSR (`nsr_next`).

---

## ⚖️ Diretrizes de Compliance (Portaria 671 MTP / REP-P & PTRP)
4. **Separação Rígida REP-P e PTRP:**
   - **REP-P (Registrador):** Grava a marcação original em ledger imutável (`punch_events`), emite Comprovante do Trabalhador assinado digitalmente (PAdES) e gera o arquivo fiscal AFD (.p7s CAdES). O registrador NUNCA possui regras de tolerância e JAMAIS bloqueia marcação de ponto (Art. 74/78 Portaria 671).
   - **PTRP (Tratamento):** Interpreta jornadas contratuais, registra eventos de tratamento (`treatment_events`) com justificativa obrigatória, aplica tolerância legal (Art. 58 § 1º CLT: máx 5 min por marcação / 10 min diários) exclusivamente na fase de apuração analítica, gerencia banco de horas, congela competências fechadas e gera o espelho de ponto e o AEJ (.p7s CAdES).
5. **A Marcação Original é Inviolável:** A batida bruta original NUNCA é editada ou excluída. Qualquer ajuste é um novo evento de tratamento imutável no PTRP.
6. **Zero Cascade Delete no Histórico Fiscal:** Tabelas com valor probatório (marcações, ajustes, vínculos, histórico de fechamento) utilizam `restrictOnDelete` para impedir perda irreversível de registros sob auditoria fiscal.

---

## 🛠️ Padrões de Código e Arquitetura Laravel 13 & Livewire 4
7. **Padrão Livewire 4:** Utilize primariamente *View-Based Components* (arquivos em `resources/views/components/`) para componentes de tela coesos e de alta produtividade.
8. **Roteamento:** Adote `Route::livewire()` para rotas de páginas diretas no arquivo `routes/web.php`.
9. **UX Fluida & Mobile-First:** Use `wire:loading` para feedback visual e `wire:transition` para microinterações. Mantenha os componentes 100% responsivos para smartphones, tablets e desktop.
10. **Segurança e Perfis de Acesso (RBAC):** Proteja rotas e ações utilizando Policies do Laravel (`UserPolicy`) ou middlewares (`can:manageEmployees`, etc.). Respeite o isolamento de escopo por setor para usuários com perfil de Gestor (`UserRole::Manager`).
11. **Integridade de Data, Hora e Fuso:** Toda e qualquer extração ou registro de data/hora DEVE utilizar o helper `now()` ou a fachada `Carbon`. Confie exclusivamente no relógio do servidor configurado no fuso oficial `America/Maceio` (GMT-3), rejeitando qualquer carimbo de tempo fornecido pelo cliente.
12. **Tipagem e Prevenção contra Mass Assignment:** Declare propriedades tipadas e utilize PHP 8 constructor promotion. Sempre defina a propriedade `protected $fillable` nos Models do Eloquent.
13. **Localização e Mensagens:** Todas as mensagens de validação, títulos e interfaces DEVEM estar em Português do Brasil (`pt_BR`) com atributos amigáveis em `lang/pt_BR/validation.php`.
14. **Rastreabilidade e Versões:** Ao concluir modificações significativas, atualize o arquivo `versoes.md`, o notificador `version-notifier.blade.php` e o arquivo `TASKS.md`.