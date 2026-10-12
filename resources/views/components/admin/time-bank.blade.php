<?php

use App\Domain\PTRP\Actions\AdjustTimeBankAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Domain\Settlement\Services\WorkTimeSettlementManager;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\TreatmentEvent;
use App\Models\WorkTimeSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Extrato e Gestão do Banco de Horas')] class extends Component
{
    public $year;
    public $month;
    public $employeeId = '';
    public $typeFilter = '';
    public string $activeTab = 'summary'; // 'summary', 'transactions', 'balances', 'settlements'

    // Modal Ajuste Manual
    public $showAdjustModal = false;
    public $adjustEmployeeId = '';
    public $adjustType = 'manual_credit';
    public $adjustMinutes = 60;
    public $adjustDate = '';
    public $adjustReason = '';

    // Modal Fechamento de Competência
    public $showClosePeriodModal = false;
    public $closeNotes = '';

    // Modal Reabertura de Competência
    public $showReopenPeriodModal = false;
    public $reopenReason = '';

    // Modal de Quitação Documentada / Execução de Destinação
    public bool $showDischargeModal = false;
    public string $dischargeSettlementId = '';
    public string $dischargeExecutionDate = '';
    public string $dischargeDocRef = '';
    public string $dischargeNotes = '';
    public bool $dischargeFromTimeBank = false;
    public ?array $selectedSettlementData = null;

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->adjustDate = now()->toDateString();
        $this->dischargeExecutionDate = now()->toDateString();
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['summary', 'transactions', 'balances', 'settlements'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function openAdjustModal(?int $employeeId = null): void
    {
        $this->adjustEmployeeId = $employeeId ? (string) $employeeId : ($this->employeeId ?: (string) Employee::first()?->id);
        $this->adjustDate = now()->toDateString();
        $this->adjustMinutes = 60;
        $this->adjustReason = '';
        $this->adjustType = 'manual_credit';
        $this->showAdjustModal = true;
    }

    public function saveAdjustment(): void
    {
        $this->validate([
            'adjustEmployeeId' => 'required|exists:employees,id',
            'adjustType' => 'required|in:manual_credit,manual_debit',
            'adjustMinutes' => 'required|integer|min:1|max:6000',
            'adjustDate' => 'required|date',
            'adjustReason' => 'required|string|min:5|max:500',
        ]);

        $employee = Employee::findOrFail($this->adjustEmployeeId);
        $type = $this->adjustType === 'manual_credit'
            ? TimeBankTransactionType::ManualCredit
            : TimeBankTransactionType::ManualDebit;

        try {
            app(AdjustTimeBankAction::class)->execute(
                employee: $employee,
                type: $type,
                minutes: (int) $this->adjustMinutes,
                date: Carbon::parse($this->adjustDate),
                reason: $this->adjustReason,
                adminUser: Auth::user(),
            );

            $this->showAdjustModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Ajuste Manual Realizado!',
                'message' => 'O lançamento no banco de horas foi gravado com sucesso no extrato auditável.',
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Lançar Ajuste',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function openClosePeriodModal(): void
    {
        if (ClosedPeriod::isClosed((int) $this->year, (int) $this->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Competência Já Fechada',
                'message' => sprintf('A competência %02d/%04d já foi congelada anteriormente.', $this->month, $this->year),
                'buttonText' => 'Entendido',
            ]);
            return;
        }

        $this->closeNotes = '';
        $this->showClosePeriodModal = true;
    }

    public function confirmClosePeriod(): void
    {
        try {
            app(CloseMonthlyPeriodAction::class)->execute(
                year: (int) $this->year,
                month: (int) $this->month,
                closedBy: Auth::user(),
                notes: $this->closeNotes,
            );

            $this->showClosePeriodModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Competência Fechada com Sucesso!',
                'message' => sprintf('A competência %02d/%04d foi fechada e as regras de jornada foram aplicadas.', $this->month, $this->year),
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha no Fechamento',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function openReopenModal(): void
    {
        $this->reopenReason = '';
        $this->showReopenPeriodModal = true;
    }

    public function confirmReopenPeriod(): void
    {
        try {
            app(\App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction::class)->execute(
                year: (int) $this->year,
                month: (int) $this->month,
                reopenedBy: Auth::user(),
                reason: $this->reopenReason,
            );

            $this->showReopenPeriodModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Competência Reaberta!',
                'message' => sprintf('A competência %02d/%04d foi reaberta para ajustes.', $this->month, $this->year),
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha na Reabertura',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function approveSettlement(string $settlementId): void
    {
        try {
            $settlement = WorkTimeSettlement::with('employee')->findOrFail($settlementId);
            app(WorkTimeSettlementManager::class)->approveSettlement($settlement, Auth::user());

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Destinação Aprovada!',
                'message' => 'A destinação foi aprovada. Ela agora aguarda execução física e comprovante documental.',
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Aprovar',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function openDischargeModal(string $settlementId): void
    {
        $settlement = WorkTimeSettlement::with(['employee.user', 'policy'])->findOrFail($settlementId);
        $this->dischargeSettlementId = $settlementId;
        $this->dischargeExecutionDate = now()->toDateString();
        $this->dischargeDocRef = '';
        $this->dischargeNotes = '';
        $this->dischargeFromTimeBank = $settlement->policy?->operatesTimeBank() ?? false;

        $this->selectedSettlementData = [
            'id' => $settlement->id,
            'employee_name' => $settlement->employee->user->name ?? 'Colaborador #'.$settlement->employee_id,
            'formatted_minutes' => $settlement->formattedMinutes(),
            'type_label' => $settlement->settlement_type->label(),
            'reference_period' => $settlement->reference_period,
            'status' => $settlement->status->label(),
        ];

        $this->showDischargeModal = true;
    }

    public function saveDischarge(): void
    {
        $this->validate([
            'dischargeDocRef' => 'required|string|min:2|max:100',
            'dischargeExecutionDate' => 'required|date',
            'dischargeNotes' => 'nullable|string|max:500',
        ], [
            'dischargeDocRef.required' => 'Informe a referência documental comprobatória (termo de folga, acordo, portaria ou declaração).',
            'dischargeDocRef.min' => 'A referência documental deve ter pelo menos 2 caracteres.',
            'dischargeExecutionDate.required' => 'Informe a data em que a compensação foi faticamente realizada.',
        ]);

        try {
            $settlement = WorkTimeSettlement::with('employee')->findOrFail($this->dischargeSettlementId);
            app(WorkTimeSettlementManager::class)->confirmExecution(
                settlement: $settlement,
                actor: Auth::user(),
                executionDate: $this->dischargeExecutionDate,
                documentReference: $this->dischargeDocRef,
                notes: $this->dischargeNotes ?: null,
                dischargeFromTimeBank: $this->dischargeFromTimeBank,
            );

            $this->showDischargeModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Compensação Documentada com Sucesso!',
                'message' => 'A execução da destinação foi registrada com comprovante e seu status agora é Efetivado.',
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Registrar Compensação',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function with()
    {
        $employees = Employee::with(['user', 'sector.establishment'])->orderBy('id')->get();

        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $refPeriod = sprintf('%04d-%02d', $this->year, $this->month);

        // 1. Ledger de Transações
        $query = TimeBankTransaction::with(['account.employee.user', 'creator'])
            ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (! empty($this->employeeId)) {
            $query->whereHas('account', fn ($q) => $q->where('employee_id', $this->employeeId));
        }

        if (! empty($this->typeFilter)) {
            $query->where('type', $this->typeFilter);
        }

        $transactions = $query->orderBy('reference_date', 'desc')->orderBy('created_at', 'desc')->paginate(20);

        $totalCredits = TimeBankTransaction::whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->employeeId, fn ($q) => $q->whereHas('account', fn ($acc) => $acc->where('employee_id', $this->employeeId)))
            ->where('minutes', '>', 0)
            ->sum('minutes');

        $totalDebits = TimeBankTransaction::whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->employeeId, fn ($q) => $q->whereHas('account', fn ($acc) => $acc->where('employee_id', $this->employeeId)))
            ->where('minutes', '<', 0)
            ->sum('minutes');

        $isClosed = ClosedPeriod::isClosed((int) $this->year, (int) $this->month);
        $closedPeriodRecord = ClosedPeriod::findForPeriod((int) $this->year, (int) $this->month);
        $periodDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $policy = TimeBankPolicy::forDate($periodDate);

        // 2. Solicitações de Tratamento Aguardando Aprovação (RH Actionable)
        $pendingTreatmentsCount = TreatmentEvent::where('status', TreatmentEventStatus::Pending)
            ->whereBetween('effective_at', [$startDate, $endDate])
            ->count();

        $pendingTreatmentsList = TreatmentEvent::with(['employee.user', 'requester'])
            ->where('status', TreatmentEventStatus::Pending)
            ->whereBetween('effective_at', [$startDate, $endDate])
            ->orderBy('effective_at', 'desc')
            ->take(5)
            ->get();

        // 3. Destinações de Jornada (WorkTimeSettlement)
        $settlementsQuery = WorkTimeSettlement::with(['employee.user', 'policy'])
            ->where('reference_period', $refPeriod)
            ->when($this->employeeId, fn ($q) => $q->where('employee_id', $this->employeeId));

        $awaitingDischargesCount = (clone $settlementsQuery)
            ->whereIn('status', [
                WorkTimeSettlementStatus::Pending,
                WorkTimeSettlementStatus::Approved,
                WorkTimeSettlementStatus::AwaitingExecution,
            ])
            ->count();

        $allSettlements = (clone $settlementsQuery)
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get();

        // 4. Saldos por Colaborador & Modalidade de Política
        $settlementService = app(WorkTimeSettlementService::class);
        $employeeAccounts = TimeBankAccount::with('employee.user')->get()->keyBy('employee_id');

        $employeeBalances = $employees->map(function (Employee $emp) use ($employeeAccounts, $settlementService, $periodDate) {
            $pol = $settlementService->resolvePolicy($emp, $periodDate);
            $operates = $pol->operatesTimeBank();
            $acc = $employeeAccounts->get($emp->id);
            $bal = $acc ? $acc->balanceUntil($periodDate) : 0;

            return [
                'employee' => $emp,
                'policy' => $pol,
                'operates_bank' => $operates,
                'balance_minutes' => $bal,
                'balance_formatted' => $operates ? TimeBankAccount::formatMinutes($bal) : 'Não aplicável (sem banco)',
                'regime_label' => $emp->legal_regime?->label() ?? 'CLT',
            ];
        });

        // 5. Pendências de Jornada (batidas ímpares / dias com ocorrência aberta no mês)
        $incompleteDaysCount = PunchEvent::whereBetween('occurred_at_local', [$startDate, $endDate])
            ->selectRaw('employee_id, DATE(occurred_at_local) as pdate, count(*) as cnt')
            ->groupBy('employee_id', 'pdate')
            ->havingRaw('cnt % 2 != 0')
            ->get()
            ->count();

        // 6. Prontidão para Fechamento
        $closingReadiness = [
            'status' => $isClosed ? 'closed' : ($pendingTreatmentsCount > 0 ? 'blocked' : 'ready'),
            'label' => $isClosed
                ? sprintf('Competência Fechada (Snapshot v%d)', $closedPeriodRecord?->snapshot_version ?? 1)
                : ($pendingTreatmentsCount > 0
                    ? sprintf('Bloqueado (%d solicitação pendente)', $pendingTreatmentsCount)
                    : 'Pronta para Fechamento'),
            'description' => $isClosed
                ? 'Competência congelada formalmente. Não são permitidas novas alterações ordinárias.'
                : ($pendingTreatmentsCount > 0
                    ? 'Existem solicitações de ajuste de ponto ou atestados aguardando decisão da chefia/RH antes do fechamento.'
                    : 'Todas as solicitações de ajuste foram analisadas. Fechamento seguro habilitado.'),
        ];

        return [
            'employees' => $employees,
            'transactions' => $transactions,
            'totalCredits' => $totalCredits,
            'totalDebits' => $totalDebits,
            'isClosed' => $isClosed,
            'policy' => $policy,
            'pendingTreatmentsCount' => $pendingTreatmentsCount,
            'pendingTreatmentsList' => $pendingTreatmentsList,
            'awaitingDischargesCount' => $awaitingDischargesCount,
            'allSettlements' => $allSettlements,
            'employeeBalances' => $employeeBalances,
            'incompleteDaysCount' => $incompleteDaysCount,
            'closingReadiness' => $closingReadiness,
            'closedPeriodRecord' => $closedPeriodRecord,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <header class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-indigo-50 text-indigo-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Gestão de Jornada, Banco e Compensação</h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Acompanhe pendências, autorize destinações, confira saldos e realize fechamentos periódicos</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.settlement-policies') }}" class="min-h-[44px] inline-flex items-center px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded-xl text-xs font-bold transition shadow-xs gap-1.5" title="Configurar regras de apuração e banco de horas">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                <span>Políticas de Jornada</span>
            </a>

            @if(!$isClosed)
                <button wire:click="openClosePeriodModal" class="min-h-[44px] inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <span>Fechar Competência</span>
                </button>
            @else
                <span class="min-h-[44px] inline-flex items-center px-3 py-1.5 bg-slate-100 border border-slate-300 text-slate-800 rounded-xl text-xs font-bold gap-1.5 select-none">
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span>Fechada (v{{ $closedPeriodRecord->snapshot_version ?? 1 }})</span>
                </span>
                @if(Auth::user()->isAdmin())
                    <button wire:click="openReopenModal" class="min-h-[44px] inline-flex items-center px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 rounded-xl text-xs font-bold transition gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        <span>Reabrir</span>
                    </button>
                @endif
                <a href="{{ route('admin.fiscalizacao') }}" class="min-h-[44px] inline-flex items-center px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 rounded-xl text-xs font-bold transition gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                    <span>Fiscalização</span>
                </a>
            @endif

            <button wire:click="openAdjustModal" class="min-h-[44px] inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span>Ajuste Manual</span>
            </button>
        </div>
    </header>

    <!-- 1. RESUMO OPERACIONAL DO RH (6 ITENS ESSENCIAIS PARA AGIR) -->
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
            <div>
                <h2 class="text-xs sm:text-sm font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                    <span>Resumo Operacional para o Gestor / RH</span>
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Informações essenciais para tomada de decisão na competência {{ sprintf('%02d/%04d', $month, $year) }}.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $closingReadiness['status'] === 'ready' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($closingReadiness['status'] === 'blocked' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200') }}">
                    {{ $closingReadiness['label'] }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            {{-- 1. Pendências de Jornada --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">1. Pendências Jornada</span>
                <p class="text-xl font-bold font-mono {{ $incompleteDaysCount > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                    {{ $incompleteDaysCount }} {{ $incompleteDaysCount === 1 ? 'dia' : 'dias' }}
                </p>
                <p class="text-[11px] text-gray-500">
                    {{ $incompleteDaysCount > 0 ? 'Batidas incompletas no mês' : 'Nenhuma batida em aberto' }}
                </p>
            </div>

            {{-- 2. Solicitações a Aprovar --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">2. Solicitações RH</span>
                    @if($pendingTreatmentsCount > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    @endif
                </div>
                <p class="text-xl font-bold font-mono {{ $pendingTreatmentsCount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ $pendingTreatmentsCount }}
                </p>
                @if($pendingTreatmentsCount > 0)
                    <a href="{{ route('admin.treatment-requests') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 underline block">
                        Analisar solicitações →
                    </a>
                @else
                    <p class="text-[11px] text-gray-500">Nenhum atestado pendente</p>
                @endif
            </div>

            {{-- 3. Diferenças a Tratar --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">3. Diferenças a Tratar</span>
                <p class="text-xl font-bold font-mono text-gray-900">
                    {{ $awaitingDischargesCount }}
                </p>
                <button type="button" wire:click="setActiveTab('settlements')" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 underline block cursor-pointer">
                    Ver destinações →
                </button>
            </div>

            {{-- 4. Saldos de Banco (quando aplicáveis) --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">4. Banco de Horas</span>
                @php $netTb = (int)$totalCredits + (int)$totalDebits; @endphp
                <p class="text-xl font-bold font-mono {{ $netTb >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ TimeBankAccount::formatMinutes($netTb) }}
                </p>
                <p class="text-[11px] text-gray-500 truncate" title="{{ $policy?->enabled ? $policy->closing_mode->label() : 'Banco desativado' }}">
                    {{ $policy?->enabled ? $policy->closing_mode->label() : 'Sem banco ativo' }}
                </p>
            </div>

            {{-- 5. Destinações Aguardando Execução --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">5. Destinações Pendentes</span>
                <p class="text-xl font-bold font-mono text-amber-700">
                    {{ $awaitingDischargesCount }}
                </p>
                <button type="button" wire:click="setActiveTab('settlements')" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 underline block cursor-pointer">
                    Registrar compensação →
                </button>
            </div>

            {{-- 6. Prontidão para Fechamento --}}
            <div class="p-3 bg-gray-50/80 border border-gray-200 rounded-xl space-y-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">6. Competência</span>
                <p class="text-sm font-bold {{ $closingReadiness['status'] === 'ready' ? 'text-emerald-700' : ($closingReadiness['status'] === 'blocked' ? 'text-amber-700' : 'text-slate-700') }}">
                    {{ $closingReadiness['status'] === 'ready' ? 'Pronta' : ($closingReadiness['status'] === 'blocked' ? 'Pendente' : 'Fechada') }}
                </p>
                <p class="text-[11px] text-gray-500 truncate" title="{{ $closingReadiness['description'] }}">
                    {{ $closingReadiness['description'] }}
                </p>
            </div>
        </div>
    </section>

    <!-- 2. FILTROS DE COMPETÊNCIA E SELETOR DE ABAS OPERACIONAIS -->
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-3">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Mês</label>
                    <select wire:model.live="month" class="min-h-[44px] px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}">{{ sprintf('%02d - %s', $m, Carbon::create(2026, $m, 1)->translatedFormat('F')) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Ano</label>
                    <select wire:model.live="year" class="min-h-[44px] px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                        @foreach([2024, 2025, 2026, 2027] as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Colaborador</label>
                    <select wire:model.live="employeeId" class="min-h-[44px] px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 max-w-xs">
                        <option value="">Todos os Colaboradores</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->user->name }} (Matrícula: {{ $emp->registration_number ?? 'S/N' }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Abas Rápidas -->
            <div class="flex items-center gap-1.5 p-1 bg-gray-100 rounded-xl flex-wrap">
                <button type="button" 
                        wire:click="setActiveTab('summary')" 
                        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'summary' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    Ações do RH
                </button>
                <button type="button" 
                        wire:click="setActiveTab('transactions')" 
                        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'transactions' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    Extrato de Movimentações
                </button>
                <button type="button" 
                        wire:click="setActiveTab('balances')" 
                        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'balances' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    Saldos por Colaborador
                </button>
                <button type="button" 
                        wire:click="setActiveTab('settlements')" 
                        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'settlements' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    Destinações & Quitações
                </button>
            </div>
        </div>
    </section>

    <!-- 3. CONTEÚDO DAS ABAS -->

    {{-- ABA 1: RESUMO DE AÇÕES DO RH --}}
    @if($activeTab === 'summary')
        <div class="space-y-4">
            {{-- Pendências que impedem ou requerem atenção --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Bloco A: Solicitações de Ajuste Pendentes --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="text-xs sm:text-sm font-bold text-gray-900">Solicitações Aguardando Decisão</h3>
                        <a href="{{ route('admin.treatment-requests') }}" class="text-xs font-semibold text-indigo-600 hover:underline">Ver todas ({{ $pendingTreatmentsCount }})</a>
                    </div>
                    @forelse($pendingTreatmentsList as $pt)
                        <div class="p-2.5 bg-gray-50 rounded-xl flex items-center justify-between gap-2 text-xs">
                            <div>
                                <p class="font-bold text-gray-900">{{ $pt->employee->user->name ?? 'Colaborador' }}</p>
                                <p class="text-[11px] text-gray-500">{{ $pt->type->label() }} · Data: {{ $pt->effective_at->format('d/m/Y') }}</p>
                            </div>
                            <a href="{{ route('admin.treatment-requests') }}" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-[11px] font-bold">
                                Avaliar
                            </a>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 py-4 text-center">Nenhuma solicitação de tratamento pendente nesta competência.</p>
                    @endforelse
                </div>

                {{-- Bloco B: Destinações Pendentes de Execução / Compensação --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="text-xs sm:text-sm font-bold text-gray-900">Destinações Pendentes de Compensação</h3>
                        <button type="button" wire:click="setActiveTab('settlements')" class="text-xs font-semibold text-indigo-600 hover:underline cursor-pointer">Ver todas</button>
                    </div>
                    @forelse($allSettlements->take(5) as $st)
                        <div class="p-2.5 bg-gray-50 rounded-xl flex items-center justify-between gap-2 text-xs">
                            <div>
                                <p class="font-bold text-gray-900">{{ $st->employee->user->name ?? 'Colaborador' }}</p>
                                <p class="text-[11px] text-gray-500">{{ $st->settlement_type->label() }} · {{ $st->formattedMinutes() }}</p>
                            </div>
                            <div>
                                @if($st->status === WorkTimeSettlementStatus::Pending)
                                    <button type="button" wire:click="approveSettlement('{{ $st->id }}')" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
                                        Aprovar
                                    </button>
                                @elseif($st->status === WorkTimeSettlementStatus::Approved || $st->status === WorkTimeSettlementStatus::AwaitingExecution)
                                    <button type="button" wire:click="openDischargeModal('{{ $st->id }}')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
                                        Quitar
                                    </button>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-700">{{ $st->status->label() }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 py-4 text-center">Nenhuma destinação registrada na competência.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- ABA 2: EXTRATO DO LEDGER DE MOVIMENTAÇÕES --}}
    @if($activeTab === 'transactions')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden space-y-3">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Extrato Contábil de Movimentações</h3>
                    <p class="text-xs text-gray-500">Registros imutáveis de créditos, débitos e ajustes manuais do banco de horas.</p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-mono font-bold text-gray-700">{{ $transactions->total() }} registros</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3.5">Data Ref.</th>
                            <th class="px-4 py-3.5">Colaborador</th>
                            <th class="px-4 py-3.5">Tipo de Movimentação</th>
                            <th class="px-4 py-3.5 text-right">Crédito</th>
                            <th class="px-4 py-3.5 text-right">Débito</th>
                            <th class="px-4 py-3.5">Origem</th>
                            <th class="px-4 py-3.5">Justificativa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($transactions as $t)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3 font-mono font-medium text-gray-900 whitespace-nowrap">
                                    {{ $t->reference_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    {{ $t->account?->employee?->user?->name ?? 'Colaborador #' . $t->account?->employee_id }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $badgeClass = match($t->type) {
                                            TimeBankTransactionType::OvertimeCredit => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            TimeBankTransactionType::CompensationDebit => 'bg-rose-50 text-rose-700 border-rose-200',
                                            TimeBankTransactionType::ManualCredit => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            TimeBankTransactionType::ManualDebit => 'bg-amber-50 text-amber-700 border-amber-200',
                                            TimeBankTransactionType::MonthlyReset => 'bg-purple-50 text-purple-700 border-purple-200',
                                            default => 'bg-gray-50 text-gray-700 border-gray-200',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full font-bold text-[11px] border {{ $badgeClass }}">
                                        {{ $t->type->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 whitespace-nowrap">
                                    {{ $t->minutes > 0 ? '+' . TimeBankAccount::formatMinutes($t->minutes) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-rose-600 whitespace-nowrap">
                                    {{ $t->minutes < 0 ? TimeBankAccount::formatMinutes($t->minutes) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $t->creator?->name ?? 'Sistema' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $t->reason ?? $t->description }}">
                                    {{ $t->reason ?? $t->description }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                    Nenhuma movimentação de banco de horas encontrada para os filtros selecionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($transactions->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ABA 3: SALDOS POR COLABORADOR --}}
    @if($activeTab === 'balances')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Saldos Consolidados por Colaborador</h3>
                    <p class="text-xs text-gray-500">Visualização de saldo e política ativa. Colaboradores sem banco de horas não geram contas fictícias.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3.5">Colaborador</th>
                            <th class="px-4 py-3.5">Setor / Estabelecimento</th>
                            <th class="px-4 py-3.5">Regime Jurídico</th>
                            <th class="px-4 py-3.5">Modalidade Aplicável</th>
                            <th class="px-4 py-3.5 text-right">Saldo Atual</th>
                            <th class="px-4 py-3.5 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($employeeBalances as $eb)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-gray-900">{{ $eb['employee']->user->name ?? 'Colaborador #' . $eb['employee']->id }}</p>
                                    <p class="text-[11px] text-gray-500 font-mono">Matrícula: {{ $eb['employee']->registration_number ?? 'S/N' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $eb['employee']->sector?->name ?? 'Geral' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-800">
                                        {{ $eb['regime_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <span class="inline-flex items-center gap-1 font-medium">
                                        @if($eb['operates_bank'])
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ $eb['policy']->modality->label() }}</span>
                                        @else
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            <span class="text-gray-500">Sem banco de horas</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap {{ $eb['operates_bank'] ? ($eb['balance_minutes'] >= 0 ? 'text-emerald-700' : 'text-rose-700') : 'text-gray-400 font-sans' }}">
                                    {{ $eb['balance_formatted'] }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('timesheet', ['userId' => $eb['employee']->user_id, 'month' => $month, 'year' => $year]) }}" class="px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg">
                                        Ver Espelho
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ABA 4: DESTINAÇÕES E COMPENSAÇÕES (WORK_TIME_SETTLEMENTS) --}}
    @if($activeTab === 'settlements')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden space-y-3">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Operações de Destinação e Compensação de Horas</h3>
                    <p class="text-xs text-gray-500">Aprovações, folgas compensatórias e relatórios para folha com comprovação.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3.5">Colaborador</th>
                            <th class="px-4 py-3.5">Competência</th>
                            <th class="px-4 py-3.5">Tipo de Destinação</th>
                            <th class="px-4 py-3.5 text-right">Minutos</th>
                            <th class="px-4 py-3.5">Situação</th>
                            <th class="px-4 py-3.5">Documento de Comprovação</th>
                            <th class="px-4 py-3.5 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($allSettlements as $st)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    {{ $st->employee->user->name ?? 'Colaborador #' . $st->employee_id }}
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-600">
                                    {{ $st->reference_period }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ $st->settlement_type->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold {{ $st->minutes > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $st->formattedMinutes() }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClass = match($st->status) {
                                            WorkTimeSettlementStatus::Pending => 'bg-amber-50 text-amber-800 border-amber-200',
                                            WorkTimeSettlementStatus::Approved => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                            WorkTimeSettlementStatus::AwaitingExecution => 'bg-sky-50 text-sky-800 border-sky-200',
                                            WorkTimeSettlementStatus::Executed => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            WorkTimeSettlementStatus::Cancelled => 'bg-gray-100 text-gray-600 border-gray-200',
                                            WorkTimeSettlementStatus::Reversed => 'bg-rose-50 text-rose-800 border-rose-200',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusClass }}">
                                        {{ $st->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-gray-600">
                                    {{ $st->document_reference ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($st->status === WorkTimeSettlementStatus::Pending)
                                        <button type="button" wire:click="approveSettlement('{{ $st->id }}')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold cursor-pointer">
                                            Aprovar
                                        </button>
                                    @elseif($st->status === WorkTimeSettlementStatus::Approved || $st->status === WorkTimeSettlementStatus::AwaitingExecution)
                                        <button type="button" wire:click="openDischargeModal('{{ $st->id }}')" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold cursor-pointer">
                                            Registrar Compensação
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Concluído</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                    Nenhuma destinação de jornada cadastrada nesta competência.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- MODAL DE COMPENSAÇÃO DOCUMENTADA / EXECUÇÃO DE DESTINAÇÃO -->
    @if($showDischargeModal && $selectedSettlementData)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Registrar Compensação Documentada</h3>
                        <p class="text-xs text-gray-500">Comprovação de gozo de folga compensatória ou acordo de frequência.</p>
                    </div>
                    <button type="button" wire:click="$set('showDischargeModal', false)" class="text-gray-400 hover:text-gray-600 cursor-pointer">✕</button>
                </div>

                <div class="p-3 bg-gray-50 rounded-xl space-y-1 text-xs">
                    <p><span class="font-bold text-gray-700">Colaborador:</span> {{ $selectedSettlementData['employee_name'] }}</p>
                    <p><span class="font-bold text-gray-700">Destinação:</span> {{ $selectedSettlementData['type_label'] }} ({{ $selectedSettlementData['formatted_minutes'] }})</p>
                    <p><span class="font-bold text-gray-700">Competência:</span> {{ $selectedSettlementData['reference_period'] }}</p>
                </div>

                <form wire:submit="saveDischarge" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Referência Documental Obrigatória *</label>
                        <input type="text" wire:model="dischargeDocRef" class="w-full min-h-[44px] px-3 py-2 border rounded-xl" placeholder="Ex: Termo de Folga Nº 12, Acordo Individual de Compensação, Portaria 48" required />
                        @error('dischargeDocRef') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Data Efetiva da Compensação/Execução *</label>
                        <input type="date" wire:model="dischargeExecutionDate" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono" required />
                        @error('dischargeExecutionDate') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl flex items-center gap-2">
                        <input type="checkbox" id="dischargeFromTimeBank" wire:model="dischargeFromTimeBank" class="w-4 h-4 text-indigo-600 rounded border-gray-300" />
                        <label for="dischargeFromTimeBank" class="text-xs text-indigo-950 font-medium">
                            Dar baixa contábil compensatória no banco de horas do trabalhador
                        </label>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Observações do RH (Opcional)</label>
                        <textarea wire:model="dischargeNotes" rows="2" class="w-full px-3 py-2 border rounded-xl" placeholder="Observações sobre a compensação ou termo acordado..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showDischargeModal', false)" class="min-h-[44px] px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 shadow-sm cursor-pointer">
                            Confirmar Registro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL DE AJUSTE MANUAL -->
    @if($showAdjustModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-base font-bold text-gray-900">Novo Ajuste Manual no Banco de Horas</h3>
                    <button type="button" wire:click="$set('showAdjustModal', false)" class="text-gray-400 hover:text-gray-600 cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveAdjustment" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Colaborador</label>
                        <select wire:model="adjustEmployeeId" class="w-full min-h-[44px] px-3 py-2 border rounded-xl bg-white" required>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->user->name }} (CPF: {{ $emp->cpf ?? 'S/N' }})</option>
                            @endforeach
                        </select>
                        @error('adjustEmployeeId') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Tipo de Ajuste</label>
                            <select wire:model="adjustType" class="w-full min-h-[44px] px-3 py-2 border rounded-xl bg-white">
                                <option value="manual_credit">Crédito Manual (+)</option>
                                <option value="manual_debit">Débito Manual (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Minutos</label>
                            <input type="number" wire:model="adjustMinutes" min="1" max="6000" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono" placeholder="60" required />
                            @error('adjustMinutes') <span class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Data de Referência da Jornada</label>
                        <input type="date" wire:model="adjustDate" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono" required />
                        @error('adjustDate') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Motivo / Justificativa Obrigatória</label>
                        <textarea wire:model="adjustReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Descreva detalhadamente a motivação legal do ajuste..." required></textarea>
                        @error('adjustReason') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showAdjustModal', false)" class="min-h-[44px] px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 cursor-pointer">Cancelar</button>
                        <button type="submit" class="min-h-[44px] px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-sm cursor-pointer">Gravar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL FECHAR COMPETÊNCIA -->
    @if($showClosePeriodModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center gap-3 text-emerald-700 border-b pb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <h3 class="text-base font-bold text-gray-900">Fechar e Congelar Competência {{ sprintf('%02d/%04d', $month, $year) }}</h3>
                </div>

                <div class="space-y-3 text-xs text-gray-600 leading-relaxed">
                    <p>Ao fechar a competência:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>O espelho de ponto deste mês será <strong>congelado em snapshot auditado</strong> contra alterações ordinárias.</li>
                        @if($policy && $policy->enabled)
                            @if($policy->closing_mode === TimeBankClosingMode::MonthlyReset)
                                <li class="text-amber-700 font-bold">
                                    <strong>Compensação Mensal:</strong> O saldo residual da competência será registrado como pendente de destinação/compensação, sem perda do direito do colaborador.
                                </li>
                            @else
                                <li class="text-indigo-700 font-bold">
                                    <strong>Banco Acumulativo (Carry-Over):</strong> O saldo é transportado para a próxima competência de forma auditável.
                                </li>
                            @endif
                        @else
                            <li>Para colaboradores sem banco de horas, as diferenças apuradas permanecem registradas para tratamento em folha ou compensação acordada.</li>
                        @endif
                    </ul>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Anotações do Fechamento (Opcional)</label>
                        <textarea wire:model="closeNotes" rows="2" class="w-full px-3 py-2 border rounded-xl" placeholder="Observações do RH sobre a competência..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" wire:click="$set('showClosePeriodModal', false)" class="min-h-[44px] px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 cursor-pointer">Cancelar</button>
                    <button type="button" wire:click="confirmClosePeriod" class="min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 shadow-sm cursor-pointer">Confirmar Fechamento</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL REABRIR COMPETÊNCIA -->
    @if($showReopenPeriodModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center gap-3 text-amber-700 border-b pb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <h3 class="text-base font-bold text-gray-900">Reabrir Competência {{ sprintf('%02d/%04d', $month, $year) }}</h3>
                </div>

                <div class="space-y-3 text-xs text-gray-600 leading-relaxed">
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900">
                        <strong>Aviso de Auditoria:</strong> Ao reabrir a competência, as versões de snapshots anteriores permanecem registradas para integridade histórica. Um novo fechamento gerará a versão seguinte do snapshot.
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Justificativa Formal Obrigatória *</label>
                        <textarea wire:model="reopenReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Informe o motivo formal da reabertura (mínimo 10 caracteres)..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" wire:click="$set('showReopenPeriodModal', false)" class="min-h-[44px] px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 cursor-pointer">Cancelar</button>
                    <button type="button" wire:click="confirmReopenPeriod" class="min-h-[44px] px-5 py-2 bg-amber-600 text-white rounded-xl font-bold hover:bg-amber-700 shadow-sm cursor-pointer">Confirmar Reabertura</button>
                </div>
            </div>
        </div>
    @endif
</div>
