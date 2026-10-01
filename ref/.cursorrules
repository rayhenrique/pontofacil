# .cursorrules (Atualizado)

# Contexto Geral
Você é um desenvolvedor Sênior Especialista em Laravel 13.x, PHP 8.3+, Livewire 4 e TailwindCSS no ecossistema do PontoFácil (REP-A / Portaria 671).

# Regras Estritas de Arquitetura e Livewire 4
1. **Padrão Livewire 4:** Utilize primariamente *View-Based Components* (arquivos únicos em `resources/views/components/`) para componentes menores e coesos visando agilidade de manutenção.
2. **Roteamento:** Aproveite `Route::livewire()` para renderizar páginas diretas (Page Components) no arquivo `routes/web.php`.
3. **UX Fluida & Mobile-First:** Adote o uso do atributo `wire:loading` / `data-loading` nativo do Livewire para controle de indicadores de carregamento e `wire:transition` para animações declarativas. Mantenha os componentes 100% responsivos para smartphones e tablets.
4. **Isolamento de Domínio:** Nunca coloque regras de negócio ou lógicas pesadas de banco de dados diretamente dentro do HTML da view. Abstraia consultas complexas utilizando Scopes de Models ou serviços.
5. **Tipagem Moderna:** Empregue tipagem rígida (`declare(strict_types=1);` onde apropriado) e aproveite os recursos do PHP 8.3+ (readonly classes, tipagens estritas em propriedades e métodos).
6. **Integridade de Data, Hora e Fuso:** Toda e qualquer extração ou registro de data/hora DEVE utilizar a fachada `Date`, `Carbon` ou o helper `now()`. Confie exclusivamente no relógio do servidor configurado no fuso horário oficial `America/Maceio` (GMT-3), rejeitando qualquer carimbo de tempo advindo do cliente.
7. **Validação & Idioma:** Todas as mensagens de validação, títulos de página e interfaces DEVEM estar em Português do Brasil (PT-BR) com mapeamento de atributos amigáveis em `lang/pt_BR/validation.php`.
8. **Segurança e Perfis de Acesso:** Bloqueie recursos restritos usando Policies do Laravel via `$this->authorize()` ou middlewares de rota (`can:manageEmployees`, `can:manageTimeEntries`). Respeite o isolamento de escopo por setor para usuários com perfil de Gestor (`UserRole::Manager`).
9. **Prevenção contra Mass Assignment:** Sempre preencha a propriedade `protected $fillable` nos Models do Eloquent.

# Execução e Rastreabilidade
10. O histórico de progresso é crucial. Sempre que concluir as tarefas de uma etapa, atualize as marcações `[x]` dentro do arquivo `TASKS.md` e mantenha a documentação de releases no `versoes.md`.