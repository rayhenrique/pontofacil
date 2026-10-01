# .cursorrules (Atualizado)

# Contexto Geral
Você é um desenvolvedor Sênior Especialista em Laravel 13.x, PHP 8.3+, Livewire 4 e TailwindCSS.

# Regras Estritas de Arquitetura e Livewire 4
1. **Padrão Livewire 4:** Utilize primariamente *View-Based Components* (arquivos únicos) para componentes menores e coesos visando agilidade de manutenção.
2. **Roteamento:** Aproveite `Route::livewire()` para renderizar páginas diretas (Page Components) no arquivo `web.php`.
3. **UX Fluida:** Adote o uso do atributo `data-loading` nativo do Livewire 4 para controle de indicadores de carregamento automáticos e `wire:transition` para animações declarativas na manipulação do DOM.
4. **Isolamento de Domínio:** Nunca coloque regras de negócio ou lógicas pesadas de banco de dados diretamente dentro do HTML da view. Abstraia consultas complexas utilizando Scopes de Models ou serviços.
5. **Tipagem Moderna:** Empregue tipagem rígida (`declare(strict_types=1);` onde apropriado) e aproveite os recursos do PHP 8.3+ (readonly classes, tipagens em propriedades).
6. **Integridade de Data e Hora:** Toda e qualquer extração ou registro de data/hora DEVE utilizar a fachada `Illuminate\Support\Facades\Date` ou o helper `now()`. Confie exclusivamente no relógio do servidor, rejeitando qualquer carimbo de tempo advindo do cliente.
7. **Validação:** Para entradas via Livewire, priorize os atributos de validação do Livewire 4 ou extraia para Form Objects nativos em cenários de múltiplos campos complexos.

# Segurança
8. Prevenção contra Mass Assignment: Sempre preencha a propriedade `$fillable` nos Models.
9. Proteção de Acesso: Bloqueie recursos restritos usando Policies do Laravel via `$this->authorize()`.

# Execução
10. O histórico de progresso é crucial. Sempre que concluir as tarefas de uma etapa, atualize as marcações `[x]` dentro do arquivo `TASKS.md`.