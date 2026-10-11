<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeBankAccount;
use App\Models\TimeBankTransaction;
use App\Models\TimeBankPolicy;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Actions\AdjustTimeBankAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Extrato e Gestão do Banco de Horas')] class extends Component
{
    public $year;
    public $month;
    public $employeeId = '';
    public $typeFilter = '';

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

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->adjustDate = now()->toDateString();
    }

    public function openAdjustModal(?int $employeeId = null)
    {
        $this->adjustEmployeeId = $employeeId ? (string) $employeeId : ($this->employeeId ?: (string) Employee::first()?->id);
        $this->adjustDate = now()->toDateString();
        $this->adjustMinutes = 60;
        $this->adjustReason = '';
        $this->adjustType = 'manual_credit';
        $this->showAdjustModal = true;
    }

    public function saveAdjustment()
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
                'message' => 'O lançamento no banco de horas foi gravado com sucesso no ledger auditável.',
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Lançar Ajuste',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function openClosePeriodModal()
    {
        if (ClosedPeriod::isClosed((int) $this->year, (int) $this->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Competência Já Fechada',
                'message' => sprintf('A competência %02d/%04d já foi congelada anteriormente.', $this->month, $this->year),
                'buttonText' => 'Entendido'
            ]);
            return;
        }

        $this->closeNotes = '';
        $this->showClosePeriodModal = true;
    }

    public function confirmClosePeriod()
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
                'message' => sprintf('A competência %02d/%04d foi fechada e as regras de banco de horas foram aplicadas.', $this->month, $this->year),
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha no Fechamento',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function openReopenModal()
    {
        $this->reopenReason = '';
        $this->showReopenPeriodModal = true;
    }

    public function confirmReopenPeriod()
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
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha na Reabertura',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function with()
    {
        $employees = Employee::with('user')->orderBy('id')->get();

        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();

        $query = TimeBankTransaction::with(['account.employee.user', 'creator'])
            ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (!empty($this->employeeId)) {
            $query->whereHas('account', fn($q) => $q->where('employee_id', $this->employeeId));
        }

        if (!empty($this->typeFilter)) {
            $query->where('type', $this->typeFilter);
        }

        $transactions = $query->orderBy('reference_date', 'desc')->orderBy('created_at', 'desc')->paginate(20);

        // Resumo
        $totalCredits = TimeBankTransaction::whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->employeeId, fn($q) => $q->whereHas('account', fn($acc) => $acc->where('employee_id', $this->employeeId)))
            ->where('minutes', '>', 0)
            ->sum('minutes');

        $totalDebits = TimeBankTransaction::whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->employeeId, fn($q) => $q->whereHas('account', fn($acc) => $acc->where('employee_id', $this->employeeId)))
            ->where('minutes', '<', 0)
            ->sum('minutes');

        $isClosed = ClosedPeriod::isClosed((int) $this->year, (int) $this->month);
        $periodDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $policy = TimeBankPolicy::forDate($periodDate);

        return [
            'employees' => $employees,
            'transactions' => $transactions,
            'totalCredits' => $totalCredits,
            'totalDebits' => $totalDebits,
            'isClosed' => $isClosed,
            'policy' => $policy,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-indigo-50 text-indigo-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Extrato do Banco de Horas (PTRP)</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Ledger contábil auditável com controle de horas extras, compensações e fechamento mensal</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.settlement-policies') }}" class="inline-flex items-center px-3.5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded-xl text-xs font-bold transition shadow-xs gap-1.5" title="Configurar regras de apuração, banco e quitação">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                Políticas de Destinação
            </a>

            @if(!$isClosed)
                <button wire:click="openClosePeriodModal" class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    Fechar Competência
                </button>
            @else
                <span class="inline-flex items-center px-3 py-1.5 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-bold gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Fechada (v{{ $closedPeriod->snapshot_version ?? 1 }})
                </span>
                @if(Auth::user()->isAdmin())
                    <button wire:click="openReopenModal" class="inline-flex items-center px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 rounded-xl text-xs font-bold transition gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        Reabrir
                    </button>
                @endif
                <a href="{{ route('admin.fiscalizacao') }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 rounded-xl text-xs font-bold transition gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                    Fiscalização
                </a>
            @endif

            <button wire:click="openAdjustModal" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Ajuste Manual
            </button>
        </div>
    </div>

    <!-- Filtros e Status da Política -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Mês</label>
                    <select wire:model.live="month" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}">{{ sprintf('%02d - %s', $m, \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F')) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Ano</label>
                    <select wire:model.live="year" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                        @foreach([2025, 2026, 2027] as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Colaborador</label>
                    <select wire:model.live="employeeId" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 max-w-xs">
                        <option value="">Todos os Colaboradores</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->user->name }} (Matrícula: {{ $emp->registration_number ?? 'S/N' }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Tipo</label>
                    <select wire:model.live="typeFilter" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todos os Tipos</option>
                        <option value="overtime_credit">Crédito de Horas Extras</option>
                        <option value="compensation_debit">Débito por Compensação</option>
                        <option value="manual_credit">Crédito Manual</option>
                        <option value="manual_debit">Débito Manual</option>
                        <option value="monthly_reset">Zeramento Mensal</option>
                    </select>
                </div>
            </div>

            <!-- Badge Política Vigente -->
            <div class="text-right">
                <span class="text-[11px] font-bold text-gray-400 block uppercase">Regra Vigente</span>
                @if($policy && $policy->enabled)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $policy->closing_mode === TimeBankClosingMode::CarryOver ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $policy->closing_mode->label() }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                        Banco de Horas Desativado
                    </span>
                @endif
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
            <div class="p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl">
                <span class="text-[11px] font-bold text-emerald-800 uppercase">Créditos do Período</span>
                <p class="text-lg font-black text-emerald-700 mt-0.5">+{{ \App\Models\TimeBankAccount::formatMinutes((int)$totalCredits) }}</p>
            </div>
            <div class="p-3 bg-rose-50/70 border border-rose-100 rounded-xl">
                <span class="text-[11px] font-bold text-rose-800 uppercase">Débitos do Período</span>
                <p class="text-lg font-black text-rose-700 mt-0.5">{{ \App\Models\TimeBankAccount::formatMinutes((int)$totalDebits) }}</p>
            </div>
            <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl">
                <span class="text-[11px] font-bold text-indigo-800 uppercase">Saldo Líquido</span>
                @php $net = (int)$totalCredits + (int)$totalDebits; @endphp
                <p class="text-lg font-black {{ $net >= 0 ? 'text-indigo-700' : 'text-rose-700' }} mt-0.5">
                    {{ \App\Models\TimeBankAccount::formatMinutes($net) }}
                </p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                <span class="text-[11px] font-bold text-slate-600 uppercase">Movimentações</span>
                <p class="text-lg font-black text-slate-800 mt-0.5">{{ $transactions->total() }} registros</p>
            </div>
        </div>
    </div>

    <!-- Tabela do Ledger -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3.5">Data Ref.</th>
                        <th class="px-4 py-3.5">Colaborador</th>
                        <th class="px-4 py-3.5">Tipo de Movimentação</th>
                        <th class="px-4 py-3.5 text-right">Crédito</th>
                        <th class="px-4 py-3.5 text-right">Débito</th>
                        <th class="px-4 py-3.5">Responsável / Origem</th>
                        <th class="px-4 py-3.5">Justificativa / Motivo</th>
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
                                {{ $t->minutes > 0 ? '+' . \App\Models\TimeBankAccount::formatMinutes($t->minutes) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-rose-600 whitespace-nowrap">
                                {{ $t->minutes < 0 ? \App\Models\TimeBankAccount::formatMinutes($t->minutes) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $t->creator?->name ?? 'Sistema (Automático)' }}
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

    <!-- Modal de Ajuste Manual -->
    @if($showAdjustModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Novo Ajuste Manual no Banco de Horas</h3>
                    <button wire:click="$set('showAdjustModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit="saveAdjustment" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Colaborador</label>
                        <select wire:model="adjustEmployeeId" class="w-full px-3 py-2 border rounded-xl bg-white" required>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->user->name }} (CPF: {{ $emp->cpf ?? 'S/N' }})</option>
                            @endforeach
                        </select>
                        @error('adjustEmployeeId') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Tipo de Ajuste</label>
                            <select wire:model="adjustType" class="w-full px-3 py-2 border rounded-xl bg-white">
                                <option value="manual_credit">Crédito Manual (+)</option>
                                <option value="manual_debit">Débito Manual (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Minutos</label>
                            <input type="number" wire:model="adjustMinutes" min="1" max="6000" class="w-full px-3 py-2 border rounded-xl font-mono" placeholder="60" required>
                            @error('adjustMinutes') <span class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Data de Referência da Jornada</label>
                        <input type="date" wire:model="adjustDate" class="w-full px-3 py-2 border rounded-xl font-mono" required>
                        @error('adjustDate') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Motivo / Justificativa Obrigatória</label>
                        <textarea wire:model="adjustReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Descreva detalhadamente a motivação legal do ajuste..." required></textarea>
                        @error('adjustReason') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showAdjustModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-sm">Gravar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Fechar Competência -->
    @if($showClosePeriodModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center gap-3 text-emerald-700 border-b pb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <h3 class="text-lg font-bold text-gray-900">Fechar e Congelar Competência {{ sprintf('%02d/%04d', $month, $year) }}</h3>
                </div>

                <div class="space-y-3 text-xs text-gray-600 leading-relaxed">
                    <p>Ao fechar a competência:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>O espelho de ponto deste mês será <strong>congelado</strong> contra alterações ordinárias.</li>
                        @if($policy && $policy->enabled)
                            @if($policy->closing_mode === TimeBankClosingMode::MonthlyReset)
                                <li class="text-amber-700 font-bold">
                                    <strong>Política MONTHLY_RESET:</strong> O sistema criará lançamentos compensatórios contábeis de zeramento no ledger para cada trabalhador com saldo, iniciando o mês seguinte em 00:00 sem apagar o histórico.
                                </li>
                            @else
                                <li class="text-indigo-700 font-bold">
                                    <strong>Política CARRY_OVER:</strong> O saldo positivo ou negativo atual será transportado integralmente para a próxima competência sem movimentações artificiais.
                                </li>
                            @endif
                        @else
                            <li>O banco de horas está desativado na política vigente.</li>
                        @endif
                    </ul>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Anotações do Fechamento (Opcional)</label>
                        <textarea wire:model="closeNotes" rows="2" class="w-full px-3 py-2 border rounded-xl" placeholder="Observações do RH sobre a competência..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" wire:click="$set('showClosePeriodModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="button" wire:click="confirmClosePeriod" class="px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 shadow-sm">Confirmar Fechamento</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Reabrir Competência -->
    @if($showReopenPeriodModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center gap-3 text-amber-700 border-b pb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <h3 class="text-lg font-bold text-gray-900">Reabrir Competência {{ sprintf('%02d/%04d', $month, $year) }}</h3>
                </div>

                <div class="space-y-3 text-xs text-gray-600 leading-relaxed">
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900">
                        <strong>Aviso Importante:</strong> Ao reabrir a competência, os snapshots históricos anteriores serão mantidos permanentemente no banco para integridade e auditoria. Após realizar os ajustes necessários, você poderá fechar novamente gerando uma nova versão do snapshot.
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Justificativa Formal Obrigatória *</label>
                        <textarea wire:model="reopenReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Informe o motivo formal da reabertura (mínimo 10 caracteres)..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" wire:click="$set('showReopenPeriodModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="button" wire:click="confirmReopenPeriod" class="px-5 py-2 bg-amber-600 text-white rounded-xl font-bold hover:bg-amber-700 shadow-sm">Confirmar Reabertura</button>
                </div>
            </div>
        </div>
    @endif
</div>
