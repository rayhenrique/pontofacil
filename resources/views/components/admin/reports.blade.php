<?php

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\SectorScopeService;
use App\Enums\AttendanceReviewStatus;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\Sector;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Relatório Mensal Consolidado de Frequência')] class extends Component
{
    public int $year;
    public int $month;
    public string $establishmentId = '';
    public string $sectorId = '';
    public string $employeeId = '';

    // Paginação da listagem (totais gerais sempre preservados)
    public int $page = 1;
    public int $perPage = 20;

    // Modal de Encaminhamento ao Setor de Pagamento
    public bool $showDispatchModal = false;
    public ?int $selectedLogId = null;
    public string $dispatchNotes = '';

    public function mount(): void
    {
        $this->year = (int) (request('year') ?? now()->year);
        $this->month = (int) (request('month') ?? now()->month);
        $this->establishmentId = (string) (request('establishmentId') ?? '');
        $this->sectorId = (string) (request('sectorId') ?? '');
        $this->employeeId = (string) (request('employeeId') ?? '');
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->page = 1;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->page = 1;
    }

    public function updatedEstablishmentId(): void
    {
        $this->page = 1;
    }

    public function updatedSectorId(): void
    {
        $this->page = 1;
    }

    public function updatedEmployeeId(): void
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

    public function openDispatchModal(?int $logId = null): void
    {
        if ($logId !== null) {
            $log = MonthlyAttendanceReportLog::findOrFail($logId);
            app(SectorScopeService::class)->authorizeReportLog(Auth::user(), $log);
        }

        $this->selectedLogId = $logId;
        $this->dispatchNotes = '';
        $this->showDispatchModal = true;
    }

    public function confirmDispatch(): void
    {
        $user = Auth::user();

        try {
            // Se logId não foi informado, registra emissão atual e despacha
            $reportData = $this->getReportData();
            $service = app(MonthlyAttendanceReportService::class);

            $log = $this->selectedLogId
                ? MonthlyAttendanceReportLog::findOrFail($this->selectedLogId)
                : $service->logReport($this->year, $this->month, $user, 'view', $reportData, [
                    'establishment_id' => $this->establishmentId ?: null,
                    'sector_id' => $this->sectorId ?: null,
                    'employee_id' => $this->employeeId ?: null,
                ]);

            $service->recordDispatchToPayroll($log, $user, $this->dispatchNotes);
        } catch (AuthorizationException $e) {
            $this->showDispatchModal = false;
            $this->selectedLogId = null;
            $this->dispatchNotes = '';

            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Acesso não autorizado.',
                'buttonText' => 'Fechar',
            ]);

            return;
        }

        $this->showDispatchModal = false;
        $this->selectedLogId = null;
        $this->dispatchNotes = '';

        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Encaminhamento Registrado',
            'message' => 'O encaminhamento do relatório de frequência ao setor de pagamento foi registrado no histórico com sucesso.',
            'buttonText' => 'OK',
        ]);
    }

    public function getReportData(): array
    {
        $service = app(MonthlyAttendanceReportService::class);
        $filters = [
            'establishment_id' => ! empty($this->establishmentId) ? (int) $this->establishmentId : null,
            'sector_id' => ! empty($this->sectorId) ? (int) $this->sectorId : null,
            'employee_id' => ! empty($this->employeeId) ? (int) $this->employeeId : null,
        ];

        return $service->getReportData($this->year, $this->month, Auth::user(), $filters);
    }

    public function formatMinutes(int $minutes, bool $withSign = false): string
    {
        $sign = $minutes < 0 ? '-' : ($withSign && $minutes > 0 ? '+' : '');
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        return sprintf('%s%02dh%02dm', $sign, $h, $m);
    }
};
?>

<div class="space-y-6">
    @php
        $user = Auth::user();
        $reportData = $this->getReportData();
        $isClosed = $reportData['is_closed'];
        $totals = $reportData['totals'];
        $rows = $reportData['rows'];

        // Carregar estabelecimentos e setores conforme permissão
        $establishments = \App\Models\Establishment::orderBy('name')->get();
        $sectorsQuery = \App\Models\Sector::query();
        if ($user->role === UserRole::Manager) {
            $sectorsQuery->where('manager_id', $user->id);
        }
        $sectors = $sectorsQuery->orderBy('name')->get();

        $employeesQuery = \App\Models\Employee::with('user');
        if ($user->role === UserRole::Manager) {
            $managedIds = $sectors->pluck('id');
            $employeesQuery->whereIn('sector_id', $managedIds);
        }
        $employees = $employeesQuery->get()->sortBy('user.name');

        // Logs de emissão da competência (restritos ao escopo do usuário)
        $recentLogsQuery = \App\Models\MonthlyAttendanceReportLog::with(['generator', 'dispatcher'])
            ->where('year', $year)
            ->where('month', $month);

        if ($user->role === UserRole::Manager) {
            $managedLogSectorIds = $sectors->pluck('id')->map(fn ($id) => (int) $id)->all();
            $recentLogsQuery->where(function ($query) use ($managedLogSectorIds, $user) {
                $query->whereIn('sector_id', $managedLogSectorIds)
                    ->orWhere(function ($sub) use ($user) {
                        $sub->whereNull('sector_id')->where('generated_by', $user->id);
                    });
            });
        }

        $recentLogs = $recentLogsQuery->orderBy('id', 'desc')->take(5)->get();

        $recentLogs = $recentLogs->filter(function ($log) use ($user) {
            if ($user->role !== UserRole::Manager) {
                return true;
            }

            return app(\App\Domain\Company\Services\SectorScopeService::class)->canAccessReportLog($user, $log);
        })->values();

        $downloadParams = [
            'year' => $year,
            'month' => $month,
            'establishment_id' => $establishmentId ?: null,
            'sector_id' => $sectorId ?: null,
            'employee_id' => $employeeId ?: null,
        ];
    @endphp

    <!-- Cabeçalho Principal e Ações de Exportação -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Relatório Mensal Consolidado de Frequência</h1>
                @if ($isClosed)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Fechamento Oficial (Snapshots Congelados)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Prévia Provisória (Competência em Aberto)
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Documento administrativo de apuração física de jornadas para encaminhamento ao setor responsável pelo pagamento. Sem lançamentos financeiros ou cálculos monetários.
            </p>
        </div>

        <!-- Seletor de Competência & Botões de Exportação -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Navegador de Mês -->
            <div class="flex items-center bg-gray-50 dark:bg-gray-700/60 rounded-xl p-1 border border-gray-200 dark:border-gray-600">
                <button wire:click="previousMonth" type="button" class="p-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition" title="Mês anterior">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <div class="px-3 py-0.5 text-center">
                    <span class="text-sm font-bold text-gray-900 dark:text-white capitalize">
                        {{ \Carbon\Carbon::createFromDate($year, $month, 1)->translatedFormat('F / Y') }}
                    </span>
                </div>
                <button wire:click="nextMonth" type="button" class="p-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition" title="Próximo mês">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>

            <!-- Exportar CSV UTF-8 -->
            <a
                href="{{ route('admin.reports.csv', $downloadParams) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-2xs transition"
                title="Download do arquivo CSV estruturado em UTF-8 para integração com a folha"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Exportar CSV
            </a>

            <!-- Imprimir / Salvar PDF -->
            <a
                href="{{ route('admin.reports.pdf', $downloadParams) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 shadow-2xs transition"
                title="Visualizar documento formatado para impressão ou PDF A4 Paisagem"
            >
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir / PDF
            </a>

            <!-- Atalho para Conferência Mensal -->
            <a
                href="{{ route('admin.monthly-review', ['year' => $year, 'month' => $month, 'sectorId' => $sectorId]) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 shadow-2xs transition"
                title="Ir para a tela de conferência mensal desta competência"
            >
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Conferência do Mês
            </a>

            <!-- Botão Encaminhar ao Pagamento -->
            <button
                wire:click="openDispatchModal"
                type="button"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-xs transition"
                title="Registrar formalmente o encaminhamento ao setor de folha/pagamento"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Registrar Encaminhamento
            </button>
        </div>
    </div>

    <!-- Banner Informativo: Fechado vs Provisório -->
    @if ($isClosed)
        <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 rounded-2xl p-4 text-xs text-emerald-900 dark:text-emerald-200 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <strong class="text-sm font-bold block">Relatório Oficial Definitivo da Competência {{ sprintf('%02d/%04d', $month, $year) }}</strong>
                    <span>
                        Apuração congelada em snapshots imutáveis em {{ \Carbon\Carbon::parse($reportData['closed_at'])->format('d/m/Y H:i') }} por {{ $reportData['closed_by_name'] }}.
                        Versão: <strong>V{{ $reportData['closed_version'] }}</strong>.
                    </span>
                    <div class="font-mono text-[11px] text-emerald-700 dark:text-emerald-400 mt-0.5 truncate max-w-2xl">
                        Hash SHA-256: {{ $reportData['closed_hash'] }}
                    </div>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <a href="{{ route('admin.monthly-review', ['year' => $year, 'month' => $month]) }}" class="font-semibold underline hover:text-emerald-950">
                    Ver Conferência Mensal
                </a>
            </div>
        </div>
    @else
        <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 rounded-2xl p-4 text-xs text-amber-900 dark:text-amber-200 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <strong class="text-sm font-bold block">Prévia Provisória — Competência em Aberto</strong>
                    <span>{{ $reportData['provisional_notice'] }}</span>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <a href="{{ route('admin.monthly-review', ['year' => $year, 'month' => $month]) }}" class="inline-flex items-center gap-1 font-bold text-indigo-700 dark:text-indigo-300 underline">
                    Ir para Conferência e Fechamento
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>
        </div>
    @endif

    <!-- Barra de Filtros Operacionais -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Estabelecimento -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Estabelecimento</label>
                <select wire:model.live="establishmentId" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Todos os Estabelecimentos</option>
                    @foreach($establishments as $est)
                        <option value="{{ $est->id }}">{{ $est->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Setor -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Setor / Lotação</label>
                <select wire:model.live="sectorId" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Todos os Setores Autorizados</option>
                    @foreach($sectors as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Colaborador -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Servidor Específico</label>
                <select wire:model.live="employeeId" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Todos os Servidores</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ optional($emp->user)->name ?? 'Servidor #' . $emp->id }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Atalho Folha Individual -->
            <div class="flex items-end">
                <a
                    href="{{ route('folha-ponto') }}"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-xl text-xs font-semibold transition"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Folha Individual (Espelho A4)
                </a>
            </div>
        </div>
        <div wire:loading class="pt-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400" aria-live="polite">
            Atualizando relatório…
        </div>
    </div>

    <!-- Cards de Totais Agregados da Competência -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Servidores</span>
            <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['total_employees'] }}</div>
            <span class="text-[10px] text-gray-400">Na seleção</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Carga Prevista</span>
            <div class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">{{ $this->formatMinutes($totals['scheduled_minutes']) }}</div>
            <span class="text-[10px] text-gray-400">Jornada contratual</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Trabalhado Real</span>
            <div class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">{{ $this->formatMinutes($totals['worked_minutes']) }}</div>
            <span class="text-[10px] text-gray-400">Horas físicas apuradas</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Diferença Líquida</span>
            <div class="text-xl font-bold font-mono mt-1">
                @if ($totals['balance_minutes'] > 0)
                    <span class="text-emerald-600 dark:text-emerald-400">+{{ $this->formatMinutes($totals['balance_minutes']) }}</span>
                @elseif ($totals['balance_minutes'] < 0)
                    <span class="text-rose-600 dark:text-rose-400">{{ $this->formatMinutes($totals['balance_minutes']) }}</span>
                @else
                    <span class="text-gray-500">00h00m</span>
                @endif
            </div>
            <span class="text-[10px] text-gray-400">Saldo apurado</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Horas Noturnas</span>
            <div class="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400 mt-1">{{ $this->formatMinutes($totals['night_minutes']) }}</div>
            <span class="text-[10px] text-gray-400">Entre 22h e 05h</span>
        </div>

        <div class="bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Plantões</span>
            <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['shifts_count'] }}</div>
            <span class="text-[10px] text-gray-400">12h / 24h realizados</span>
        </div>
    </div>

    <!-- Tabela Operacional de Alta Densidade (PTRP) -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-[11px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        <th class="py-3 px-3.5">Servidor / Vínculo</th>
                        <th class="py-3 px-3">Lotação / Escala</th>
                        <th class="py-3 px-2.5 text-right">Previsto</th>
                        <th class="py-3 px-2.5 text-right">Trabalhado</th>
                        <th class="py-3 px-2.5 text-right">Diferença</th>
                        <th class="py-3 px-2.5 text-right">Faltas</th>
                        <th class="py-3 px-2.5 text-right">Atrasos</th>
                        <th class="py-3 px-2.5 text-right">Noturno</th>
                        <th class="py-3 px-2 text-center">Plantões</th>
                        <th class="py-3 px-3">Conferência / Ressalva</th>
                        <th class="py-3 px-2.5 text-center">Espelho</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 font-sans">
                    @php
                        $totalRows = count($rows);
                        $totalPages = max(1, (int) ceil($totalRows / $this->perPage));
                        $currentPage = min(max(1, $this->page), $totalPages);
                        $pagedRows = array_slice($rows, ($currentPage - 1) * $this->perPage, $this->perPage);
                        $reportEmployeesMap = \App\Models\Employee::whereIn('id', collect($pagedRows)->pluck('employee_id')->all())
                            ->get(['id', 'user_id'])
                            ->keyBy('id');
                    @endphp
                    @forelse ($pagedRows as $r)
                        @php
                            $employeeModel = $reportEmployeesMap->get($r['employee_id']);
                            $userId = $employeeModel?->user_id;
                            $status = $r['review_status'];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                            <td class="py-3 px-3.5">
                                <div class="font-bold text-gray-900 dark:text-white">{{ $r['name'] }}</div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                    Matr.: {{ $r['registration_number'] }} • CPF: {{ $r['cpf'] }} • {{ $r['legal_regime'] }}
                                </div>
                            </td>

                            <td class="py-3 px-3">
                                <div class="font-medium text-gray-800 dark:text-gray-200">{{ $r['department'] }}</div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                    {{ $r['schedule_name'] }} ({{ $r['schedule_modality'] }})
                                </div>
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono text-gray-700 dark:text-gray-300">
                                {{ $this->formatMinutes($r['scheduled_minutes']) }}
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono font-bold text-gray-900 dark:text-white">
                                {{ $this->formatMinutes($r['worked_minutes']) }}
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono font-bold">
                                @if ($r['balance_minutes'] > 0)
                                    <span class="text-emerald-600 dark:text-emerald-400">+{{ $this->formatMinutes($r['balance_minutes']) }}</span>
                                @elseif ($r['balance_minutes'] < 0)
                                    <span class="text-rose-600 dark:text-rose-400">{{ $this->formatMinutes($r['balance_minutes']) }}</span>
                                @else
                                    <span class="text-gray-400">00h00m</span>
                                @endif
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                                {{ $r['absence_minutes'] > 0 ? $this->formatMinutes($r['absence_minutes']) : '—' }}
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                                {{ $r['delay_minutes'] > 0 ? $this->formatMinutes($r['delay_minutes']) : '—' }}
                            </td>

                            <td class="py-3 px-2.5 text-right font-mono text-indigo-600 dark:text-indigo-400 font-medium">
                                {{ $r['night_minutes'] > 0 ? $this->formatMinutes($r['night_minutes']) : '—' }}
                            </td>

                            <td class="py-3 px-2 text-center text-gray-700 dark:text-gray-300 font-mono">
                                {{ $r['shifts_count'] ?: '—' }}
                            </td>

                            <td class="py-3 px-3">
                                <div class="space-y-0.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $status->badgeClass() }}">
                                        {{ $r['review_status_label'] }}
                                    </span>
                                    @if (! empty($r['reservation_notes']))
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 italic truncate max-w-xs" title="{{ $r['reservation_notes'] }}">
                                            Obs: {{ $r['reservation_notes'] }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3 px-2.5 text-center">
                                @if ($userId)
                                    <a
                                        href="{{ route('timesheet', ['userId' => $userId, 'month' => $month, 'year' => $year]) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition inline-block"
                                        title="Abrir Espelho de Ponto Individual"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-10 text-center text-gray-500 dark:text-gray-400 text-sm">
                                Nenhum registro de servidor localizado para os filtros informados nesta competência.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($totalPages > 1)
        <div class="flex items-center justify-between gap-3 bg-white dark:bg-gray-800 px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <button
                wire:click="previousPage"
                type="button"
                @disabled($currentPage <= 1)
                class="px-4 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]"
            >
                ← Anterior
            </button>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400" aria-live="polite">
                Página {{ $currentPage }} de {{ $totalPages }} ({{ $totalRows }} servidores)
            </span>
            <button
                wire:click="nextPage"
                type="button"
                @disabled($currentPage >= $totalPages)
                class="px-4 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]"
            >
                Próxima →
            </button>
        </div>
    @endif

    <!-- Histórico de Emissões & Encaminhamento ao Setor de Pagamento -->
    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Histórico de Emissões e Encaminhamento ao Setor de Pagamento</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Rastreabilidade formal de disponibilização do relatório para a folha salarial.</p>
            </div>
            <button
                wire:click="openDispatchModal"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Registrar Novo Encaminhamento
            </button>
        </div>

        @if($recentLogs->isNotEmpty())
            <div class="divide-y divide-gray-100 dark:divide-gray-700 text-xs">
                @foreach ($recentLogs as $log)
                    <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900 dark:text-white uppercase">{{ $log->format }}</span>
                                <span class="text-gray-400">•</span>
                                <span class="text-gray-600 dark:text-gray-300">Gerado por {{ $log->generator?->name }} em {{ $log->created_at->format('d/m/Y H:i') }}</span>
                                @if ($log->is_provisional)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Provisório</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Oficial</span>
                                @endif
                            </div>
                            @if ($log->dispatched_to_payroll_at)
                                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Encaminhado ao setor de pagamento em {{ $log->dispatched_to_payroll_at->format('d/m/Y H:i') }} por {{ $log->dispatcher?->name }}
                                    @if ($log->dispatch_notes)
                                        — <em>"{{ $log->dispatch_notes }}"</em>
                                    @endif
                                </div>
                            @else
                                <div class="text-[11px] text-gray-400 italic">Disponibilizado para download interno. Não encaminhado formalmente.</div>
                            @endif
                        </div>

                        @if (! $log->dispatched_to_payroll_at)
                            <button
                                wire:click="openDispatchModal({{ $log->id }})"
                                type="button"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline"
                            >
                                Registrar Envio
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-xs text-gray-400 italic py-2">
                Nenhum registro de emissão gravado para esta competência ainda. Utilize os botões de exportação acima para gerar o arquivo.
            </div>
        @endif
    </div>

    <!-- MODAL DE ENCAMINHAMENTO EXPLÍCITO AO SETOR DE PAGAMENTO -->
    @if ($showDispatchModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Registrar Encaminhamento à Folha</h3>
                    <button wire:click="$set('showDispatchModal', false)" type="button" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>
                        Esta ação registra formalmente no histórico que o relatório consolidado de <strong>{{ sprintf('%02d/%04d', $month, $year) }}</strong> foi disponibilizado / transmitido ao setor responsável pelo pagamento.
                    </p>
                    <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl p-3 text-xs text-amber-800 dark:text-amber-300">
                        <strong>Transparência Operacional:</strong> O PontoFácil nunca simula entregas fictícias. O envio é registrado apenas sob confirmação humana expressa.
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Observações / Destinatário (Opcional)
                        </label>
                        <textarea
                            wire:model="dispatchNotes"
                            rows="2"
                            placeholder="Ex: Enviado por e-mail institucional ao RH/Folha para processamento mensal..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm p-2.5 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button wire:click="$set('showDispatchModal', false)" type="button" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 rounded-xl">
                        Cancelar
                    </button>
                    <button wire:click="confirmDispatch" type="button" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm">
                        Confirmar Encaminhamento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>