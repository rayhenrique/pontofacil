<?php

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Enums\AttendanceReviewStatus;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Sector;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Conferência Mensal de Frequência')] class extends Component
{
    public int $year;
    public int $month;
    public string $sectorId = '';
    public string $search = '';
    public string $statusFilter = 'all';

    // Paginação da listagem (totais gerais sempre preservados)
    public int $page = 1;
    public int $perPage = 20;

    // Seleção em lote
    public array $selectedIds = [];
    public bool $selectAll = false;

    // Modal de Conferência Individual
    public bool $showReviewModal = false;
    public ?int $reviewingEmployeeId = null;
    public ?array $reviewingData = null;
    public string $reservationNotes = '';

    // Modal de Conferência em Lote
    public bool $showBatchModal = false;

    // Modal de Fechamento de Competência
    public bool $showCloseModal = false;
    public string $closeNotes = '';

    // Modal de Reabertura Auditada
    public bool $showReopenModal = false;
    public string $reopenReason = '';

    public function mount(): void
    {
        $this->year = (int) (request('year') ?? now()->year);
        $this->month = (int) (request('month') ?? now()->month);
        $this->sectorId = (string) (request('sectorId') ?? '');
        $this->search = (string) (request('search') ?? '');
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
    }

    public function updatedSectorId(): void
    {
        $this->page = 1;
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedStatusFilter(): void
    {
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page = $this->page + 1;
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $overview = $this->getOverview();
            $eligible = [];
            foreach ($overview['items'] as $item) {
                // Seleciona elegíveis sem ocorrências pendentes e não fechados
                if (! $item['is_closed'] && ! $item['has_unresolved_occurrences']) {
                    $eligible[] = $item['employee_id'];
                }
            }
            $this->selectedIds = $eligible;
        } else {
            $this->selectedIds = [];
        }
    }

    public function openReviewModal(int $employeeId): void
    {
        $employee = Employee::with(['user', 'sector', 'workSchedule'])->findOrFail($employeeId);
        app(SectorScopeService::class)->authorizeEmployee(Auth::user(), $employee);

        $this->reviewingEmployeeId = $employeeId;
        $service = app(MonthlyAttendanceReviewService::class);
        $this->reviewingData = $service->resolveEmployeeReviewData($employee, $this->year, $this->month);
        $this->reservationNotes = $this->reviewingData['reservation_notes'] ?? '';
        $this->showReviewModal = true;
    }

    public function confirmSingleReview(): void
    {
        if (! $this->reviewingEmployeeId) {
            return;
        }

        $employee = Employee::findOrFail($this->reviewingEmployeeId);

        try {
            app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                employee: $employee,
                year: $this->year,
                month: $this->month,
                reviewer: Auth::user(),
                reservationNotes: $this->reservationNotes,
            );

            $this->showReviewModal = false;
            $this->reviewingEmployeeId = null;
            $this->reviewingData = null;
            $this->reservationNotes = '';

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Conferência Registrada',
                'message' => 'A frequência do servidor foi confirmada com sucesso para a competência selecionada.',
                'buttonText' => 'OK',
            ]);
        } catch (AuthorizationException $e) {
            $this->showReviewModal = false;
            $this->reviewingEmployeeId = null;
            $this->reviewingData = null;
            $this->reservationNotes = '';

            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Acesso não autorizado.',
                'buttonText' => 'Fechar',
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Atenção na Conferência',
                'message' => $e->getMessage(),
                'buttonText' => 'Corrigir',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro Inesperado',
                'message' => 'Não foi possível registrar a conferência. Tente novamente.',
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function openBatchModal(): void
    {
        if (empty($this->selectedIds)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Nenhum Servidor Selecionado',
                'message' => 'Selecione ao menos um servidor na lista para executar a conferência em lote.',
                'buttonText' => 'Entendido',
            ]);
            return;
        }

        $this->showBatchModal = true;
    }

    public function confirmBatchReview(): void
    {
        try {
            $result = app(ReviewMonthlyAttendanceAction::class)->reviewBatch(
                employeeIds: array_map('intval', $this->selectedIds),
                year: $this->year,
                month: $this->month,
                reviewer: Auth::user(),
            );

            $this->showBatchModal = false;
            $this->selectedIds = [];
            $this->selectAll = false;

            $message = sprintf(
                'Foram conferidos %d servidor(es) elegíveis com sucesso.',
                $result['reviewed_count']
            );

            if ($result['skipped_count'] > 0) {
                $message .= sprintf(
                    ' %d servidor(es) foram desconsiderados por possuírem ocorrências pendentes e devem ser conferidos individualmente com ressalva.',
                    $result['skipped_count']
                );
            }

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Conferência em Lote Concluída',
                'message' => $message,
                'buttonText' => 'OK',
            ]);
        } catch (AuthorizationException $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Acesso não autorizado.',
                'buttonText' => 'Fechar',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro na Conferência em Lote',
                'message' => 'Não foi possível concluir a conferência em lote. Tente novamente.',
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function openCloseModal(): void
    {
        $overview = $this->getOverview();

        if (! empty($overview['blocking_reasons'])) {
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Fechamento Bloqueado',
                'message' => "Não é possível fechar a competência ordinariamente:\n• " . implode("\n• ", $overview['blocking_reasons']),
                'buttonText' => 'Revisar Pendências',
            ]);
            return;
        }

        $this->closeNotes = '';
        $this->showCloseModal = true;
    }

    public function confirmClosePeriod(): void
    {
        if (! Auth::user()->isAdmin()) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Apenas usuários com perfil Administrador/RH podem realizar o fechamento formal da competência.',
                'buttonText' => 'Fechar',
            ]);
            return;
        }

        try {
            app(CloseMonthlyPeriodAction::class)->execute(
                year: $this->year,
                month: $this->month,
                closedBy: Auth::user(),
                notes: $this->closeNotes,
            );

            $this->showCloseModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Competência Fechada e Congelada!',
                'message' => sprintf('A competência %02d/%04d foi congelada com sucesso. Os snapshots imutáveis foram consolidados.', $this->month, $this->year),
                'buttonText' => 'OK',
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha no Fechamento',
                'message' => $e->getMessage(),
                'buttonText' => 'Voltar',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha no Fechamento',
                'message' => 'Não foi possível fechar a competência. Tente novamente.',
                'buttonText' => 'Voltar',
            ]);
        }
    }

    public function openReopenModal(): void
    {
        $this->reopenReason = '';
        $this->showReopenModal = true;
    }

    public function confirmReopenPeriod(): void
    {
        if (! Auth::user()->isAdmin()) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Apenas administradores podem reabrir competências.',
                'buttonText' => 'Fechar',
            ]);
            return;
        }

        try {
            app(ReopenMonthlyPeriodAction::class)->execute(
                year: $this->year,
                month: $this->month,
                reopenedBy: Auth::user(),
                reason: $this->reopenReason,
            );

            $this->showReopenModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Competência Reaberta com Auditoria',
                'message' => sprintf('A competência %02d/%04d foi reaberta formalmente para conferências e ajustes.', $this->month, $this->year),
                'buttonText' => 'OK',
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha na Reabertura',
                'message' => $e->getMessage(),
                'buttonText' => 'Corrigir',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha na Reabertura',
                'message' => 'Não foi possível reabrir a competência. Tente novamente.',
                'buttonText' => 'Corrigir',
            ]);
        }
    }

    protected function getOverview(): array
    {
        $service = app(MonthlyAttendanceReviewService::class);
        $sectorId = ! empty($this->sectorId) ? (int) $this->sectorId : null;
        return $service->getCompetenceOverview($this->year, $this->month, Auth::user(), $sectorId);
    }

    public function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '+';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        return sprintf('%s%02dh%02dm', $sign, $h, $m);
    }

    public function formatHours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02dh%02d', $h, $m);
    }
};
?>

<div class="space-y-6">
    @php
        $user = Auth::user();
        $overview = $this->getOverview();
        $isClosed = $overview['is_closed'];
        $items = collect($overview['items'])->filter(function ($item) {
            if ($this->statusFilter !== 'all' && $item['status']->value !== $this->statusFilter) {
                return false;
            }
            if (! empty($this->search)) {
                $term = mb_strtolower($this->search);
                $name = mb_strtolower($item['name'] ?? '');
                $cpf = mb_strtolower($item['cpf'] ?? '');
                $reg = mb_strtolower($item['registration_number'] ?? '');
                return str_contains($name, $term) || str_contains($cpf, $term) || str_contains($reg, $term);
            }
            return true;
        });

        // Setores acessíveis pelo usuário
        $sectorsQuery = Sector::query();
        if ($user->role === UserRole::Manager) {
            $sectorsQuery->where('manager_id', $user->id);
        }
        $availableSectors = $sectorsQuery->orderBy('name')->get();

        // Paginação da listagem (totais do cabeçalho sempre sobre o conjunto filtrado)
        $totalFiltered = $items->count();
        $totalPages = max(1, (int) ceil($totalFiltered / $this->perPage));
        $currentPage = min(max(1, $this->page), $totalPages);
        $pagedItems = $items->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

        // Identificadores exibidos em uma única consulta (sem N+1 por linha).
        $employeesMap = Employee::whereIn('id', $pagedItems->pluck('employee_id')->all())
            ->get(['id', 'user_id'])
            ->keyBy('id');
    @endphp

    <!-- Cabeçalho Principal e Navegação de Competência -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Conferência Mensal de Frequência</h1>
                @if ($isClosed)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Mês Fechado (Snapshot Congelado)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Competência Aberta para Conferência
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Homologação de escalas, conferência de batidas e fechamento auditado independente do banco de horas.
            </p>
        </div>

        <!-- Seletor de Competência & Ação Principal -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center bg-gray-50 dark:bg-gray-700/60 rounded-lg p-1 border border-gray-200 dark:border-gray-600">
                <button
                    wire:click="previousMonth"
                    type="button"
                    class="p-2 rounded-md hover:bg-white dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition"
                    title="Mês anterior"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="px-4 py-1 text-center">
                    <span class="text-base font-bold text-gray-900 dark:text-white capitalize">
                        {{ Carbon::createFromDate($year, $month, 1)->translatedFormat('F / Y') }}
                    </span>
                </div>
                <button
                    wire:click="nextMonth"
                    type="button"
                    class="p-2 rounded-md hover:bg-white dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition"
                    title="Próximo mês"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <a
                href="{{ route('admin.reports', ['year' => $year, 'month' => $month, 'sectorId' => $sectorId]) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 shadow-xs transition"
                title="Visualizar ou exportar relatório consolidado de frequência desta competência"
            >
                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Relatório Consolidado</span>
            </a>

            @if ($user->isAdmin())
                @if ($isClosed)
                    <button
                        wire:click="openReopenModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reabrir Mês (Auditado)
                    </button>
                @else
                    <button
                        wire:click="openCloseModal"
                        type="button"
                        @class([
                            'inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm transition',
                            'bg-indigo-600 hover:bg-indigo-700 text-white focus:ring-2 focus:ring-indigo-500' => $overview['can_close'],
                            'bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' => ! $overview['can_close'],
                        ])
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Concluir conferência e fechar mês
                    </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Cards de Resumo Operacional (Sem excesso de dashboard) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total de Servidores</span>
            <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">{{ $overview['total_employees'] }}</div>
            <span class="text-xs text-gray-500 dark:text-gray-400">Na competência apurada</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/50 shadow-sm">
            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Conferidos / Regulares</span>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $overview['reviewed_count'] }}</div>
            <span class="text-xs text-emerald-600/70 dark:text-emerald-400/70">Aprovados sem ressalva</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-blue-200 dark:border-blue-800/50 shadow-sm">
            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Com Ressalva</span>
            <div class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ $overview['reviewed_with_reservation_count'] }}</div>
                            <span class="text-xs text-blue-600/70 dark:text-blue-400/70">Conferidos c/ justificativa</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-rose-200 dark:border-rose-800/50 shadow-sm">
            <span class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Pendências p/ Fechamento</span>
            <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">
                {{ $overview['pending_count'] + $overview['with_occurrences_count'] }}
            </div>
            <span class="text-xs text-rose-600/70 dark:text-rose-400/70">
                {{ $overview['with_occurrences_count'] }} c/ ocorrência pendente
            </span>
        </div>
    </div>

    <!-- Alertas de Condições de Fechamento -->
    @if (! $isClosed && ! empty($overview['blocking_reasons']))
        <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 rounded-xl p-4 text-sm text-amber-900 dark:text-amber-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="space-y-1">
                <p class="font-bold">Condições para conclusão do fechamento mensal:</p>
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach ($overview['blocking_reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Barra de Filtros Compactos e Ações em Lote -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 flex-1">
                <!-- Filtro Setor -->
                <select
                    wire:model.live="sectorId"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">Todos os Setores Autorizados</option>
                    @foreach ($availableSectors as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                    @endforeach
                </select>

                <!-- Filtro Status -->
                <select
                    wire:model.live="statusFilter"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="all">Todos os Status</option>
                    <option value="pending">Aguardando conferência</option>
                    <option value="with_occurrences">Com ocorrência pendente</option>
                    <option value="reviewed">Conferido</option>
                    <option value="reviewed_with_reservation">Conferido com ressalva</option>
                </select>

                <!-- Campo Busca -->
                <div class="relative flex-1 min-w-[200px]">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar por servidor, CPF ou matrícula..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 pl-9 pr-3 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Botão de Ação em Lote -->
            @if (! $isClosed)
                <div class="flex items-center gap-2">
                    <button
                        wire:click="openBatchModal"
                        type="button"
                        @disabled(empty($selectedIds))
                        @class([
                            'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition',
                            'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800' => ! empty($selectedIds),
                            'bg-gray-100 text-gray-400 border border-gray-200 dark:bg-gray-800 dark:border-gray-700 cursor-not-allowed' => empty($selectedIds),
                        ])
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Conferir Selecionados ({{ count($selectedIds) }})
                    </button>
                </div>
            @endif
        </div>
        <div wire:loading class="pt-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400" aria-live="polite">
            Atualizando conferência…
        </div>
    </div>

    <!-- VISÃO DESKTOP: Tabela Operacional de Alta Densidade -->
    <div class="hidden md:block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        @if (! $isClosed)
                            <th class="py-3 px-4 w-10 text-center">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectAll"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                    title="Selecionar todos os servidores elegíveis"
                                />
                            </th>
                        @endif
                        <th class="py-3 px-4">Servidor / Matrícula</th>
                        <th class="py-3 px-4">Setor / Escala</th>
                        <th class="py-3 px-3 text-right">Previstas</th>
                        <th class="py-3 px-3 text-right">Trabalhadas</th>
                        <th class="py-3 px-3 text-right">Diferença</th>
                        <th class="py-3 px-4">Status / Ocorrências</th>
                        <th class="py-3 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($pagedItems as $item)
                        @php
                            $employee = $employeesMap->get($item['employee_id']);
                            $userId = $employee?->user_id;
                            $status = $item['status'];
                            $isEligibleForBatch = ! $item['is_closed'] && ! $item['has_unresolved_occurrences'];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                            @if (! $isClosed)
                                <td class="py-3.5 px-4 text-center">
                                    @if ($isEligibleForBatch)
                                        <input
                                            type="checkbox"
                                            value="{{ $item['employee_id'] }}"
                                            wire:model.live="selectedIds"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    @else
                                        <span class="inline-block w-4 h-4 text-gray-300 dark:text-gray-600" title="Possui ocorrências pendentes: exige conferência individual com ressalva">—</span>
                                    @endif
                                </td>
                            @endif

                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    CPF: {{ $item['cpf'] }} • Matr.: {{ $item['registration_number'] ?: 'N/A' }}
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="text-gray-900 dark:text-gray-200">{{ $item['department'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item['schedule_name'] }}
                                </div>
                            </td>

                            <td class="py-3.5 px-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                {{ $this->formatHours($item['scheduled_minutes']) }}
                            </td>

                            <td class="py-3.5 px-3 text-right font-mono font-semibold text-gray-900 dark:text-white">
                                {{ $this->formatHours($item['worked_minutes']) }}
                            </td>

                            <td class="py-3.5 px-3 text-right font-mono font-bold">
                                @if ($item['balance_minutes'] > 0)
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ $this->formatMinutes($item['balance_minutes']) }}</span>
                                @elseif ($item['balance_minutes'] < 0)
                                    <span class="text-rose-600 dark:text-rose-400">{{ $this->formatMinutes($item['balance_minutes']) }}</span>
                                @else
                                    <span class="text-gray-500">00h00m</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $status->badgeClass() }}">
                                        {{ $status->label() }}
                                    </span>

                                    @if ($item['occurrences_count'] > 0)
                                        <div class="text-xs text-rose-600 dark:text-rose-400 font-medium">
                                            {{ $item['occurrences_count'] }} ocorrência(s) registrada(s)
                                        </div>
                                    @endif

                                    @if (! empty($item['reviewed_by_name']))
                                        <div class="text-[11px] text-gray-400 dark:text-gray-500">
                                            Por {{ $item['reviewed_by_name'] }} em {{ $item['reviewed_at'] ? Carbon::parse($item['reviewed_at'])->format('d/m H:i') : '—' }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($userId)
                                        <a
                                            href="{{ route('timesheet', ['userId' => $userId, 'month' => $month, 'year' => $year]) }}"
                                            target="_blank"
                                            class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                                            title="Abrir Espelho de Ponto"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    @endif

                                    @if (! $isClosed)
                                        <button
                                            wire:click="openReviewModal({{ $item['employee_id'] }})"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ ! empty($item['stale']) ? 'Revisar novamente' : 'Conferir' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isClosed ? 7 : 8 }}" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                Nenhum colaborador localizado com os filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- VISÃO MOBILE-FIRST: Cards Otimizados para Celular/Tablet -->
    <div class="block md:hidden space-y-3">
        @forelse ($pagedItems as $item)
            @php
                $employee = $employeesMap->get($item['employee_id']);
                $userId = $employee?->user_id;
                $status = $item['status'];
            @endphp
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Matr.: {{ $item['registration_number'] ?: '—' }} • {{ $item['department'] }}
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 {{ $status->badgeClass() }}">
                        {{ $status->label() }}
                    </span>
                </div>

                <!-- Dados de Horas e Escala -->
                <div class="grid grid-cols-3 gap-2 bg-gray-50 dark:bg-gray-700/40 p-2.5 rounded-lg text-xs font-mono">
                    <div>
                        <span class="text-gray-400 uppercase text-[10px] block">Previsto</span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $this->formatHours($item['scheduled_minutes']) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 uppercase text-[10px] block">Trabalhado</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $this->formatHours($item['worked_minutes']) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 uppercase text-[10px] block">Diferença</span>
                        @if ($item['balance_minutes'] > 0)
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $this->formatMinutes($item['balance_minutes']) }}</span>
                        @elseif ($item['balance_minutes'] < 0)
                            <span class="font-bold text-rose-600 dark:text-rose-400">{{ $this->formatMinutes($item['balance_minutes']) }}</span>
                        @else
                            <span class="text-gray-500">00h00m</span>
                        @endif
                    </div>
                </div>

                <!-- Ocorrências / Justificativas -->
                @if ($item['occurrences_count'] > 0)
                    <div class="text-xs text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30 p-2 rounded-lg border border-rose-200 dark:border-rose-800">
                        <strong>{{ $item['occurrences_count'] }} ocorrência(s):</strong>
                        <ul class="list-disc pl-4 mt-1 space-y-0.5">
                            @foreach ($item['occurrences'] as $occ)
                                <li>{{ $occ }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Ações Contextuais -->
                <div class="flex items-center justify-end gap-2 pt-1 border-t border-gray-100 dark:border-gray-700">
                    @if ($userId)
                        <a
                            href="{{ route('timesheet', ['userId' => $userId, 'month' => $month, 'year' => $year]) }}"
                            target="_blank"
                            class="px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg"
                        >
                            Ver Espelho
                        </a>
                    @endif

                    @if (! $isClosed)
                        <button
                            wire:click="openReviewModal({{ $item['employee_id'] }})"
                            type="button"
                            class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm"
                        >
                            {{ ! empty($item['stale']) ? 'Revisar novamente' : 'Conferir Frequência' }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 p-8 rounded-xl text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                Nenhum colaborador localizado com os filtros aplicados.
            </div>
        @endforelse
    </div>

    <!-- Paginação da listagem -->
    @if ($totalPages > 1)
        <div class="flex items-center justify-between gap-3 bg-white dark:bg-gray-800 px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <button
                wire:click="previousPage"
                type="button"
                @disabled($currentPage <= 1)
                class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]"
            >
                ← Anterior
            </button>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400" aria-live="polite">
                Página {{ $currentPage }} de {{ $totalPages }} ({{ $totalFiltered }} servidores)
            </span>
            <button
                wire:click="nextPage"
                type="button"
                @disabled($currentPage >= $totalPages)
                class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]"
            >
                Próxima →
            </button>
        </div>
    @endif

    <!-- MODAL DE CONFERÊNCIA INDIVIDUAL -->
    @if ($showReviewModal && $reviewingData)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Conferir Frequência do Servidor</h3>
                    <button wire:click="$set('showReviewModal', false)" type="button" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="bg-gray-50 dark:bg-gray-700/50 p-3.5 rounded-xl space-y-1">
                        <div class="font-bold text-gray-900 dark:text-white">{{ $reviewingData['name'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $reviewingData['department'] }} • Escala: {{ $reviewingData['schedule_name'] }}
                        </div>
                        <div class="flex items-center gap-4 text-xs font-mono pt-1 text-gray-700 dark:text-gray-300">
                            <span>Previsto: <strong>{{ $this->formatHours($reviewingData['scheduled_minutes']) }}</strong></span>
                            <span>Trabalhado: <strong>{{ $this->formatHours($reviewingData['worked_minutes']) }}</strong></span>
                            <span>Diferença: <strong>{{ $this->formatMinutes($reviewingData['balance_minutes']) }}</strong></span>
                        </div>
                    </div>

                    @if ($reviewingData['occurrences_count'] > 0)
                        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl p-3.5 space-y-1 text-xs text-rose-800 dark:text-rose-300">
                            <strong class="font-semibold block">Ocorrências pendentes identificadas:</strong>
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($reviewingData['occurrences'] as $occ)
                                    <li>{{ $occ }}</li>
                                @endforeach
                            </ul>
                            <p class="text-[11px] pt-1 text-rose-700 dark:text-rose-400 italic">
                                * É obrigatório registrar uma justificativa formal/ressalva fundamentada abaixo para concluir a conferência deste servidor.
                            </p>
                        </div>
                    @endif

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Ressalva / Justificativa da Chefia ou RH
                            @if ($reviewingData['has_unresolved_occurrences'])
                                <span class="text-rose-500 font-bold">* (Obrigatória)</span>
                            @else
                                <span class="text-gray-400 font-normal lowercase">(opcional)</span>
                            @endif
                        </label>
                        <textarea
                            wire:model="reservationNotes"
                            rows="3"
                            placeholder="Descreva a fundamentação caso haja ocorrências ou observações normativas..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm p-3 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button
                        wire:click="$set('showReviewModal', false)"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmSingleReview"
                        type="button"
                        class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition"
                    >
                        Confirmar Frequência
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL DE CONFERÊNCIA EM LOTE -->
    @if ($showBatchModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirmação de Conferência em Lote</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Você selecionou <strong>{{ count($selectedIds) }}</strong> servidor(es) para homologação rápida de frequência.
                </p>
                <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl p-3 text-xs text-blue-800 dark:text-blue-300 space-y-1">
                    <p class="font-semibold">Regra de Segurança Operacional:</p>
                    <p>Somente servidores elegíveis sem ocorrências pendentes serão confirmados. Qualquer servidor com batidas incompletas ou pendências será ignorado do lote para análise individual.</p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-3">
                    <button
                        wire:click="$set('showBatchModal', false)"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmBatchReview"
                        type="button"
                        class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition"
                    >
                        Confirmar Lote Agora
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL DE FECHAMENTO DE COMPETÊNCIA -->
    @if ($showCloseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Concluir e Fechar Competência</h3>
                    <button wire:click="$set('showCloseModal', false)" type="button" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>
                        O fechamento formal consolida e <strong>congela a apuração</strong> e as <strong>conferências mensais</strong> de todos os {{ $overview['total_employees'] }} servidores em snapshots imutáveis com assinatura hash SHA-256.
                    </p>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Notas de Fechamento (Opcional)
                        </label>
                        <textarea
                            wire:model="closeNotes"
                            rows="2"
                            placeholder="Observações do RH sobre a competência fechada..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm p-3 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button
                        wire:click="$set('showCloseModal', false)"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmClosePeriod"
                        type="button"
                        class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition"
                    >
                        Fechar e Congelar Mês
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL DE REABERTURA AUDITADA -->
    @if ($showReopenModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Reabertura Formal de Competência</h3>
                    <button wire:click="$set('showReopenModal', false)" type="button" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>
                        A reabertura permite novos apontamentos, tratamentos e conferências na competência fechada. Os snapshots históricos anteriores permanecem íntegros para fins de fiscalização e auditoria.
                    </p>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Justificativa Formal Obrigatória <span class="text-rose-500">* (Mínimo 10 caracteres)</span>
                        </label>
                        <textarea
                            wire:model="reopenReason"
                            rows="3"
                            placeholder="Informe a motivação jurídica/operacional para reabertura da competência..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm p-3 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button
                        wire:click="$set('showReopenModal', false)"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmReopenPeriod"
                        type="button"
                        class="px-5 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-sm transition"
                    >
                        Confirmar Reabertura Auditada
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
