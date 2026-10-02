<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Sector;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Enums\UserRole;

new #[Layout('layouts.app')] #[Title('Espelho de Ponto')] class extends Component
{
    public $month;
    public $year;
    public $userId;

    public $showTreatmentModal = false;
    public $reqDate = '';
    public $reqTime = '08:00';
    public $reqType = 'manual_punch_added';
    public $reqReason = '';

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;
        $this->userId = Auth::id();
        $this->reqDate = now()->toDateString();
    }

    public function openTreatmentModal(?string $date = null)
    {
        $this->reqDate = $date ?? now()->toDateString();
        $this->reqTime = '08:00';
        $this->reqType = 'manual_punch_added';
        $this->reqReason = '';
        $this->showTreatmentModal = true;
    }

    public function submitTreatmentRequest()
    {
        $this->validate([
            'reqDate' => 'required|date',
            'reqTime' => 'required|date_format:H:i',
            'reqType' => 'required|in:manual_punch_added,absence_justified,punch_disregarded',
            'reqReason' => 'required|string|min:5|max:500',
        ]);

        $employee = Employee::where('user_id', $this->userId)->first();
        if (!$employee) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Perfil Incompleto',
                'message' => 'Colaborador não possui cadastro funcional ativo.',
                'buttonText' => 'OK'
            ]);
            return;
        }

        $typeEnum = match($this->reqType) {
            'manual_punch_added' => \App\Domain\PTRP\Enums\TreatmentEventType::ManualPunchAdded,
            'absence_justified' => \App\Domain\PTRP\Enums\TreatmentEventType::AbsenceJustified,
            'punch_disregarded' => \App\Domain\PTRP\Enums\TreatmentEventType::PunchDisregarded,
        };

        $effectiveAt = Carbon::parse($this->reqDate . ' ' . $this->reqTime);

        try {
            app(\App\Domain\PTRP\Actions\RequestTreatmentEventAction::class)->execute(
                employee: $employee,
                type: $typeEnum,
                effectiveAt: $effectiveAt,
                reasonText: $this->reqReason,
                requestedBy: Auth::user(),
            );

            $this->showTreatmentModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Solicitação Enviada com Sucesso!',
                'message' => 'Sua solicitação de tratamento/justificativa foi enviada ao RH para análise e aprovação.',
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Enviar Solicitação',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function with()
    {
        $user = Auth::user();
        $isAdmin = $user->role === UserRole::Admin;
        $isManager = $user->role === UserRole::Manager;
        
        $selectableUsers = collect([]);
        if ($isAdmin) {
            $selectableUsers = User::orderBy('name')->get();
        } elseif ($isManager) {
            $managedSectorIds = Sector::where('manager_id', $user->id)->pluck('id');
            $employeeUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');
            $allowedUserIds = $employeeUserIds->push($user->id)->unique();

            if (!in_array((int) $this->userId, $allowedUserIds->map(fn($id) => (int)$id)->toArray())) {
                $this->userId = $user->id;
            }

            $selectableUsers = User::whereIn('id', $allowedUserIds)->orderBy('name')->get();
        } else {
            $this->userId = $user->id;
        }

        $canSelectUser = $isAdmin || ($isManager && $selectableUsers->count() > 1);

        $query = TimeEntry::with('user')
            ->where('user_id', $this->userId)
            ->whereYear('timestamp', $this->year)
            ->whereMonth('timestamp', $this->month)
            ->orderBy('timestamp', 'asc');

        $entries = $query->get();

        // Agrupar por dia
        $groupedEntries = $entries->groupBy(function($entry) {
            return Carbon::parse($entry->timestamp)->format('Y-m-d');
        });

        $daysCalculated = [];
        $totalMonthMinutes = 0;

        foreach ($groupedEntries as $date => $dayEntries) {
            $metrics = $this->calculateDayMetrics($dayEntries);
            $daysCalculated[$date] = $metrics;
            $totalMonthMinutes += $metrics['minutes'];
        }

        $monthHours = intdiv($totalMonthMinutes, 60);
        $monthRemMinutes = $totalMonthMinutes % 60;
        $monthFormatted = sprintf('%dh %02dm', $monthHours, $monthRemMinutes);

        $workedDaysCount = count($groupedEntries);
        $avgMinutesPerDay = $workedDaysCount > 0 ? (int) round($totalMonthMinutes / $workedDaysCount) : 0;
        $avgFormatted = sprintf('%02dh %02dm', intdiv($avgMinutesPerDay, 60), $avgMinutesPerDay % 60);

        $employee = Employee::where('user_id', $this->userId)->first();
        $periodDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $policy = \App\Models\TimeBankPolicy::forDate($periodDate);

        $timeBankSummary = null;
        if ($policy && $policy->enabled && $employee) {
            $timeBankSummary = app(\App\Domain\PTRP\Services\TimeBankStatementService::class)
                ->getMonthlySummary($employee, (int) $this->year, (int) $this->month);
        }

        return [
            'groupedEntries' => $groupedEntries,
            'daysCalculated' => $daysCalculated,
            'totalMonthFormatted' => $monthFormatted,
            'workedDaysCount' => $workedDaysCount,
            'totalPunches' => $entries->count(),
            'avgFormatted' => $avgFormatted,
            'canSelectUser' => $canSelectUser,
            'users' => $selectableUsers,
            'timeBankSummary' => $timeBankSummary,
            'policy' => $policy,
            'employee' => $employee,
        ];
    }

    public function calculateDayMetrics($dayEntries): array
    {
        $totalMinutes = 0;
        $lastIn = null;
        $hasOpenInterval = false;

        $sorted = collect($dayEntries)->sortBy('timestamp');

        foreach ($sorted as $entry) {
            $time = Carbon::parse($entry->timestamp);
            if ($entry->type === 'in' || $entry->type === 'entrada') {
                $lastIn = $time;
            } elseif (($entry->type === 'out' || $entry->type === 'saida') && $lastIn) {
                $totalMinutes += $lastIn->diffInMinutes($time);
                $lastIn = null;
            }
        }

        if ($lastIn !== null) {
            $hasOpenInterval = true;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $formatted = sprintf('%02dh %02dm', $hours, $minutes);

        return [
            'minutes' => $totalMinutes,
            'hours' => $hours,
            'remMinutes' => $minutes,
            'formatted' => $formatted,
            'isOpen' => $hasOpenInterval,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Espelho de Ponto</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Histórico completo de registros de jornada e horas trabalhadas</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="openTreatmentModal" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-2xs transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    <span>Solicitar Ajuste</span>
                </button>
                <a href="{{ route('folha-ponto', ['userId' => $this->userId, 'month' => $this->month, 'year' => $this->year]) }}" 
                   class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs sm:text-sm font-bold shadow-2xs transition">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Imprimir Folha Oficial</span>
                </a>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    Bater Ponto
                </a>
            </div>
        </div>

        <!-- Filters (Responsive Mobile Stack) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6 pb-6 border-b border-gray-200">
            @if($canSelectUser)
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Colaborador</label>
                <select wire:model.live="userId" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Mês</label>
                <select wire:model.live="month" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ sprintf('%02d', $i) }} - {{ Carbon::create(null, $i, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Ano</label>
                <select wire:model.live="year" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = now()->year - 2; $i <= now()->year; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- Card Banco de Horas (Se ativado na política vigente) -->
        @if($policy && $policy->enabled && $timeBankSummary)
        <div class="bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 text-white rounded-2xl p-5 mb-6 shadow-md border border-indigo-800/60">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-800/80 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold tracking-tight text-white">Banco de Horas</h3>
                        <p class="text-[11px] text-indigo-300">Regra: {{ $policy->closing_mode->label() }}</p>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-indigo-300 tracking-wider block">Saldo Atual</span>
                    <span class="text-2xl font-black font-mono {{ $timeBankSummary->currentBalanceMinutes >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $timeBankSummary->formattedCurrentBalance() }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
                <div>
                    <span class="text-indigo-300 text-[10px] uppercase font-sans font-semibold block">Saldo Anterior</span>
                    <span class="font-bold text-slate-200 text-sm">{{ $timeBankSummary->formattedPreviousBalance() }}</span>
                </div>
                <div>
                    <span class="text-emerald-300 text-[10px] uppercase font-sans font-semibold block">Créditos do Mês</span>
                    <span class="font-bold text-emerald-400 text-sm">+{{ $timeBankSummary->formattedMonthCredits() }}</span>
                </div>
                <div>
                    <span class="text-rose-300 text-[10px] uppercase font-sans font-semibold block">Débitos do Mês</span>
                    <span class="font-bold text-rose-400 text-sm">{{ $timeBankSummary->formattedMonthDebits() }}</span>
                </div>
                <div>
                    <span class="text-amber-300 text-[10px] uppercase font-sans font-semibold block">Ajustes Manuais</span>
                    <span class="font-bold text-amber-400 text-sm">{{ $timeBankSummary->formattedMonthAdjustments() }}</span>
                </div>
            </div>

            @if($timeBankSummary->closingResetMinutes !== 0)
                <div class="mt-4 pt-3 border-t border-indigo-800/80 flex flex-wrap items-center justify-between gap-2 text-xs font-mono bg-indigo-950/60 p-2.5 rounded-xl">
                    <span class="text-purple-300 font-sans font-bold">Encerramento mensal / Zeramento formal:</span>
                    <span class="font-bold text-purple-300">{{ $timeBankSummary->formattedClosingReset() }}</span>
                    <span class="text-indigo-200 font-sans">Saldo transportado: <strong>00:00</strong></span>
                </div>
            @endif
        </div>
        @endif

        <!-- Monthly Summary KPI Cards -->
        @if($totalPunches > 0)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-indigo-900 uppercase tracking-wider">Total Trabalhado</p>
                    <p class="text-xl font-black text-indigo-950 font-mono">{{ $totalMonthFormatted }}</p>
                </div>
            </div>

            <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-emerald-900 uppercase tracking-wider">Dias Trabalhados</p>
                    <p class="text-xl font-black text-emerald-950 font-mono">{{ $workedDaysCount }} {{ $workedDaysCount === 1 ? 'dia' : 'dias' }}</p>
                </div>
            </div>

            <div class="bg-purple-50/60 border border-purple-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-purple-900 uppercase tracking-wider">Média por Dia</p>
                    <p class="text-xl font-black text-purple-950 font-mono">{{ $avgFormatted }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Timesheet Data -->
        <div class="space-y-4" data-loading-class="opacity-50" wire:transition>
            @forelse($groupedEntries as $date => $dayEntries)
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs" wire:key="day-{{ $date }}">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-800 capitalize flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            {{ Carbon::parse($date)->isoFormat('dddd, LL') }}
                        </h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if(isset($daysCalculated[$date]))
                                @if($daysCalculated[$date]['isOpen'])
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        {{ $daysCalculated[$date]['formatted'] }} (em andamento)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        {{ $daysCalculated[$date]['formatted'] }} trabalhadas
                                    </span>
                                @endif
                            @endif
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                {{ count($dayEntries) }} {{ count($dayEntries) === 1 ? 'registro' : 'registros' }}
                            </span>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($dayEntries as $entry)
                            <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-gray-50 transition" wire:key="entry-{{ $entry->id }}">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $entry->type === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $entry->type === 'in' ? 'Entrada' : 'Saída' }}
                                    </span>
                                    <span class="text-base text-gray-900 font-mono font-bold">
                                        {{ Carbon::parse($entry->timestamp)->format('H:i:s') }}
                                    </span>
                                    @if($entry->is_manual)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-800">
                                            Ajuste Manual
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 font-mono flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                    <span>Lat: {{ number_format($entry->latitude, 4) }}, Lng: {{ number_format($entry->longitude, 4) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500 bg-gray-50 rounded-xl" wire:key="empty-state">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <p class="text-sm font-medium text-gray-600">Nenhum registro encontrado para este período.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal Solicitação de Tratamento / Justificativa -->
    @if($showTreatmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Solicitar Ajuste ou Justificativa</h3>
                    </div>
                    <button wire:click="$set('showTreatmentModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit="submitTreatmentRequest" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Tipo de Solicitação</label>
                        <select wire:model="reqType" class="w-full px-3 py-2.5 border rounded-xl bg-white font-medium">
                            <option value="manual_punch_added">Inclusão de Batida Manual (Esquecimento)</option>
                            <option value="absence_justified">Abono de Falta / Atestado Médico</option>
                            <option value="punch_disregarded">Desconsideração de Marcação Indevida</option>
                        </select>
                        @error('reqType') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Data da Ocorrência</label>
                            <input type="date" wire:model="reqDate" class="w-full px-3 py-2 border rounded-xl font-mono" required>
                            @error('reqDate') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Horário Previsto</label>
                            <input type="time" wire:model="reqTime" class="w-full px-3 py-2 border rounded-xl font-mono" required>
                            @error('reqTime') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Justificativa e Motivo Legal (Obrigatório)</label>
                        <textarea wire:model="reqReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Descreva detalhadamente a justificativa para avaliação do RH/Gestor..." required></textarea>
                        @error('reqReason') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl text-indigo-900 leading-relaxed">
                        Sua solicitação será enviada ao RH com carimbo de auditoria e status <strong>Pendente</strong>, sendo aplicada à apuração somente após aprovação formal.
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showTreatmentModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-sm">Enviar Solicitação</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>