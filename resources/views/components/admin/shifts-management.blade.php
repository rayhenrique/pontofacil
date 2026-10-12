<?php

use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Domain\WorkSchedule\Actions\SwapShiftsAction;
use App\Domain\WorkSchedule\Actions\ValidateShiftConflictsAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Gestão de Escalas e Plantões')] class extends Component
{
    public $selected_sector_id = '';

    public $selected_employee_id = '';

    public $year;

    public $month;

    // Modal de Nova Escala / Configuração por Modelos
    public $showScheduleModal = false;
    public $template = 'administrative'; // 'administrative', 'continuous', 'shift_12h', 'shift_24h'

    public $sched_name = 'Jornada Administrativa 40h';
    public $sched_tolerance = 5;
    public $sched_daily_tolerance = 10;

    // Administrativo
    public $admin_days = [1, 2, 3, 4, 5];
    public $admin_morning_start = '08:00';
    public $admin_morning_end = '12:00';
    public $admin_afternoon_start = '13:00';
    public $admin_afternoon_end = '17:00';

    // Corrido
    public $cont_days = [1, 2, 3, 4, 5];
    public $cont_start_time = '07:00';
    public $cont_end_time = '13:00';
    public $cont_break_minutes = 15;
    public $cont_deduct_break = false;

    // Plantão 12h
    public $p12_start_time = '07:00';
    public $p12_end_time = '19:00';
    public $p12_break_minutes = 60;
    public $p12_anchor_date = '';
    public $p12_is_night = false;

    // Plantão 24h
    public $p24_start_time = '07:00';
    public $p24_end_time = '07:00';
    public $p24_break_minutes = 120;
    public $p24_anchor_date = '';

    // Aplicação a Colaboradores
    public array $apply_employee_ids = [];
    public $apply_effective_from = '';
    public $apply_effective_until = '';
    public $apply_reason = 'Atribuição cadastral de modelo de escala';

    // Modal de Geração
    public $showGenerateModal = false;

    public $gen_employee_id = '';

    public $gen_schedule_id = '';

    public $gen_anchor_date = '';

    public $gen_start_date = '';

    public $gen_end_date = '';

    public $gen_include_off_days = true;

    // Modal de Edição
    public $showEditModal = false;

    public $edit_shift_id = null;

    public $edit_start_datetime = '';

    public $edit_end_datetime = '';

    public $edit_break_minutes = 60;

    public $edit_reason = '';

    // Modal de Permuta / Troca
    public $showSwapModal = false;

    public $swap_shift_id = null;

    public $swap_replacement_employee_id = '';

    public $swap_reason = '';

    public function mount()
    {
        $today = Carbon::today();
        $this->year = (int) $today->format('Y');
        $this->month = (int) $today->format('n');

        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $firstSector = Sector::where('manager_id', $user->id)->first();
            if ($firstSector) {
                $this->selected_sector_id = (string) $firstSector->id;
            }
        }
    }

    public function previousMonth()
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = (int) $date->format('Y');
        $this->month = (int) $date->format('n');
    }

    public function nextMonth()
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = (int) $date->format('Y');
        $this->month = (int) $date->format('n');
    }

    public function openGenerateModal()
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $this->gen_employee_id = $this->selected_employee_id;
        $this->gen_anchor_date = $startOfMonth->format('Y-m-d');
        $this->gen_start_date = $startOfMonth->format('Y-m-d');
        $this->gen_end_date = $endOfMonth->format('Y-m-d');
        $this->gen_include_off_days = true;

        if ($this->gen_employee_id) {
            $employee = Employee::find($this->gen_employee_id);
            $schedule = $employee?->getWorkScheduleForDate($startOfMonth);
            $this->gen_schedule_id = $schedule ? (string) $schedule->id : '';
        }

        $this->showGenerateModal = true;
    }

    public function generateCyclicShifts()
    {
        $this->validate([
            'gen_employee_id' => 'required|exists:employees,id',
            'gen_schedule_id' => 'required|exists:work_schedules,id',
            'gen_anchor_date' => 'required|date',
            'gen_start_date' => 'required|date',
            'gen_end_date' => 'required|date|after_or_equal:gen_start_date',
        ]);

        $employee = Employee::findOrFail($this->gen_employee_id);
        $schedule = WorkSchedule::findOrFail($this->gen_schedule_id);

        try {
            $generated = app(GenerateCyclicShiftsAction::class)->execute(
                employee: $employee,
                schedule: $schedule,
                anchorDate: $this->gen_anchor_date,
                startDate: $this->gen_start_date,
                endDate: $this->gen_end_date,
                assignedBy: Auth::user(),
                includeOffDays: (bool) $this->gen_include_off_days,
            );

            $this->showGenerateModal = false;
            $this->selected_employee_id = (string) $employee->id;

            $count = $generated->count();
            session()->flash('message', "Escala gerada com sucesso! {$count} jornadas/folgas programadas para o período.");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('gen_' . $key, $messages[0] ?? 'Erro ao gerar plantões.');
            }
        }
    }

    public function openEditModal($shiftId)
    {
        $shift = ShiftAssignment::findOrFail($shiftId);
        $this->edit_shift_id = $shift->id;
        $this->edit_start_datetime = $shift->start_at_local->format('Y-m-d\TH:i');
        $this->edit_end_datetime = $shift->end_at_local->format('Y-m-d\TH:i');
        $this->edit_break_minutes = $shift->break_minutes;
        $this->edit_reason = $shift->reason ?? '';
        $this->showEditModal = true;
    }

    public function updateShift()
    {
        $this->validate([
            'edit_start_datetime' => 'required|date',
            'edit_end_datetime' => 'required|date|after:edit_start_datetime',
            'edit_reason' => 'required|string|min:4',
        ]);

        $shift = ShiftAssignment::findOrFail($this->edit_shift_id);
        $startLocal = Carbon::parse($this->edit_start_datetime, $shift->timezone);
        $endLocal = Carbon::parse($this->edit_end_datetime, $shift->timezone);

        $shift->update([
            'start_at_local' => $startLocal,
            'end_at_local' => $endLocal,
            'start_at_utc' => $startLocal->copy()->setTimezone('UTC'),
            'end_at_utc' => $endLocal->copy()->setTimezone('UTC'),
            'break_minutes' => (int) $this->edit_break_minutes,
            'expected_work_minutes' => max(0, (int) $startLocal->diffInMinutes($endLocal) - (int) $this->edit_break_minutes),
            'crosses_midnight' => $startLocal->format('Y-m-d') !== $endLocal->format('Y-m-d'),
            'reason' => $this->edit_reason,
            'assigned_by' => Auth::id(),
        ]);

        $this->showEditModal = false;
        session()->flash('message', 'Plantão atualizado com sucesso.');
    }

    public function openSwapModal($shiftId)
    {
        $shift = ShiftAssignment::findOrFail($shiftId);
        $this->swap_shift_id = $shift->id;
        $this->swap_replacement_employee_id = '';
        $this->swap_reason = '';
        $this->showSwapModal = true;
    }

    public function executeSwap()
    {
        $this->validate([
            'swap_replacement_employee_id' => 'required|exists:employees,id',
            'swap_reason' => 'required|string|min:4',
        ]);

        $shift = ShiftAssignment::findOrFail($this->swap_shift_id);
        $replacement = Employee::findOrFail($this->swap_replacement_employee_id);

        try {
            app(SwapShiftsAction::class)->execute(
                originalShift: $shift,
                replacementEmployee: $replacement,
                reason: $this->swap_reason,
                assignedBy: Auth::user(),
            );

            $this->showSwapModal = false;
            session()->flash('message', 'Troca de plantão registrada e autorizada com sucesso.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('swap_' . $key, $messages[0]);
            }
        }
    }

    public function cancelShift($shiftId)
    {
        $shift = ShiftAssignment::findOrFail($shiftId);
        $shift->update([
            'status' => ShiftStatus::Cancelled,
            'reason' => 'Cancelado pela gestão',
        ]);
        session()->flash('message', 'Plantão cancelado.');
    }

    public function openCreateScheduleModal($initialTemplate = 'administrative')
    {
        $this->template = $initialTemplate;
        $this->updateTemplateDefaults();
        $this->apply_effective_from = Carbon::today()->format('Y-m-d');
        $this->apply_effective_until = '';
        $this->apply_employee_ids = $this->selected_employee_id ? [(int) $this->selected_employee_id] : [];
        $this->showScheduleModal = true;
    }

    public function updatedTemplate($value)
    {
        $this->updateTemplateDefaults();
    }

    protected function updateTemplateDefaults()
    {
        $today = Carbon::today()->format('Y-m-d');
        match ($this->template) {
            'administrative' => (function () {
                $this->sched_name = 'Jornada Administrativa 40h';
                $this->admin_days = [1, 2, 3, 4, 5];
                $this->admin_morning_start = '08:00';
                $this->admin_morning_end = '12:00';
                $this->admin_afternoon_start = '13:00';
                $this->admin_afternoon_end = '17:00';
            })(),
            'continuous' => (function () {
                $this->sched_name = 'Horário Corrido 30h';
                $this->cont_days = [1, 2, 3, 4, 5];
                $this->cont_start_time = '07:00';
                $this->cont_end_time = '13:00';
                $this->cont_break_minutes = 15;
                $this->cont_deduct_break = false;
            })(),
            'shift_12h' => (function () use ($today) {
                $this->sched_name = 'Plantão 12x36 Diurno';
                $this->p12_start_time = '07:00';
                $this->p12_end_time = '19:00';
                $this->p12_break_minutes = 60;
                $this->p12_anchor_date = $today;
                $this->p12_is_night = false;
            })(),
            'shift_24h' => (function () use ($today) {
                $this->sched_name = 'Plantão 24x72';
                $this->p24_start_time = '07:00';
                $this->p24_end_time = '07:00';
                $this->p24_break_minutes = 120;
                $this->p24_anchor_date = $today;
            })(),
            default => null,
        };
    }

    public function getPreviewDataProperty()
    {
        $action = app(ConfigureWorkScheduleAction::class);
        $data = match ($this->template) {
            'administrative' => [
                'days' => array_map('intval', (array) $this->admin_days),
                'morning_start' => $this->admin_morning_start,
                'morning_end' => $this->admin_morning_end,
                'afternoon_start' => $this->admin_afternoon_start,
                'afternoon_end' => $this->admin_afternoon_end,
            ],
            'continuous' => [
                'days' => array_map('intval', (array) $this->cont_days),
                'start_time' => $this->cont_start_time,
                'end_time' => $this->cont_end_time,
                'break_minutes' => (int) $this->cont_break_minutes,
                'deduct_break' => (bool) $this->cont_deduct_break,
            ],
            'shift_12h' => [
                'start_time' => $this->p12_start_time,
                'end_time' => $this->p12_end_time,
                'break_minutes' => (int) $this->p12_break_minutes,
                'is_night_shift' => (bool) $this->p12_is_night,
                'anchor_date' => $this->p12_anchor_date ?: Carbon::today()->format('Y-m-d'),
            ],
            'shift_24h' => [
                'start_time' => $this->p24_start_time,
                'end_time' => $this->p24_end_time,
                'break_minutes' => (int) $this->p24_break_minutes,
                'anchor_date' => $this->p24_anchor_date ?: Carbon::today()->format('Y-m-d'),
            ],
            default => [],
        };

        return $action->preview($this->template, $data, (int) $this->year, (int) $this->month);
    }

    public function saveSchedule()
    {
        $this->validate([
            'sched_name' => 'required|string|min:3|max:150',
            'apply_effective_from' => 'nullable|date',
            'apply_effective_until' => 'nullable|date|after_or_equal:apply_effective_from',
        ]);

        $action = app(ConfigureWorkScheduleAction::class);

        try {
            $schedule = match ($this->template) {
                'administrative' => $action->createAdministrative([
                    'name' => $this->sched_name,
                    'days' => array_map('intval', (array) $this->admin_days),
                    'morning_start' => $this->admin_morning_start,
                    'morning_end' => $this->admin_morning_end,
                    'afternoon_start' => $this->admin_afternoon_start,
                    'afternoon_end' => $this->admin_afternoon_end,
                    'tolerance_minutes' => (int) $this->sched_tolerance,
                    'daily_tolerance_minutes' => (int) $this->sched_daily_tolerance,
                ], Auth::user()),

                'continuous' => $action->createContinuous([
                    'name' => $this->sched_name,
                    'days' => array_map('intval', (array) $this->cont_days),
                    'start_time' => $this->cont_start_time,
                    'end_time' => $this->cont_end_time,
                    'break_minutes' => (int) $this->cont_break_minutes,
                    'deduct_break' => (bool) $this->cont_deduct_break,
                    'tolerance_minutes' => (int) $this->sched_tolerance,
                    'daily_tolerance_minutes' => (int) $this->sched_daily_tolerance,
                ], Auth::user()),

                'shift_12h' => $action->createShift12h([
                    'name' => $this->sched_name,
                    'start_time' => $this->p12_start_time,
                    'end_time' => $this->p12_end_time,
                    'break_minutes' => (int) $this->p12_break_minutes,
                    'anchor_date' => $this->p12_anchor_date ?: Carbon::today()->format('Y-m-d'),
                    'is_night_shift' => (bool) $this->p12_is_night,
                    'tolerance_minutes' => (int) $this->sched_tolerance,
                    'daily_tolerance_minutes' => (int) $this->sched_daily_tolerance,
                ], Auth::user()),

                'shift_24h' => $action->createShift24h([
                    'name' => $this->sched_name,
                    'start_time' => $this->p24_start_time,
                    'end_time' => $this->p24_end_time,
                    'break_minutes' => (int) $this->p24_break_minutes,
                    'anchor_date' => $this->p24_anchor_date ?: Carbon::today()->format('Y-m-d'),
                    'tolerance_minutes' => (int) $this->sched_tolerance,
                    'daily_tolerance_minutes' => (int) $this->sched_daily_tolerance,
                ], Auth::user()),

                default => throw new \Exception('Modelo não reconhecido'),
            };

            // Aplicação opcional a colaboradores
            $appliedInfo = '';
            if (! empty($this->apply_employee_ids) && ! empty($this->apply_effective_from)) {
                $res = $action->applyToEmployees(
                    schedule: $schedule,
                    employeeIds: array_map('intval', (array) $this->apply_employee_ids),
                    effectiveFrom: $this->apply_effective_from,
                    effectiveUntil: $this->apply_effective_until ?: null,
                    reason: $this->apply_reason ?: 'Atribuição cadastral de escala',
                    actor: Auth::user(),
                );

                $appliedInfo = sprintf(' Escala atribuída com sucesso a %d colaborador(es).', $res['applied_count']);
                if (! empty($res['errors'])) {
                    $appliedInfo .= ' Avisos: ' . implode('; ', $res['errors']);
                }
            }

            $this->showScheduleModal = false;
            session()->flash('message', "Escala '{$schedule->name}' configurada com sucesso!{$appliedInfo}");
        } catch (\Throwable $e) {
            $this->addError('sched_name', $e->getMessage());
        }
    }

    public function with()
    {
        $user = Auth::user();
        $isManager = $user->role === UserRole::Manager;

        $sectorsQuery = Sector::orderBy('name');
        if ($isManager) {
            $sectorsQuery->where('manager_id', $user->id);
        }
        $sectors = $sectorsQuery->get();

        $employeesQuery = Employee::with('user')->orderBy('id', 'desc');
        if ($this->selected_sector_id) {
            $employeesQuery->where('sector_id', $this->selected_sector_id);
        } elseif ($isManager) {
            $employeesQuery->whereIn('sector_id', $sectors->pluck('id'));
        }
        $employees = $employeesQuery->get();

        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Carregar plantões do período
        $shiftsQuery = ShiftAssignment::with(['employee.user', 'workSchedule', 'assignedByUser', 'swappedWithEmployee.user'])
            ->forPeriod($startOfMonth, $endOfMonth)
            ->orderBy('start_at_local', 'asc');

        if ($this->selected_employee_id) {
            $shiftsQuery->where('employee_id', $this->selected_employee_id);
        } elseif ($this->selected_sector_id) {
            $sectorEmployeeIds = Employee::where('sector_id', $this->selected_sector_id)->pluck('id');
            $shiftsQuery->whereIn('employee_id', $sectorEmployeeIds);
        } elseif ($isManager) {
            $shiftsQuery->whereIn('employee_id', $employees->pluck('id'));
        }

        $shifts = $shiftsQuery->get();

        // Validação de conflitos quando um colaborador específico está selecionado
        $conflictReport = null;
        if ($this->selected_employee_id) {
            $selectedEmp = Employee::find($this->selected_employee_id);
            if ($selectedEmp) {
                $conflictReport = app(ValidateShiftConflictsAction::class)->execute($selectedEmp, $shifts);
            }
        }

        $workSchedules = WorkSchedule::where('active', true)->orderBy('name')->get();

        return [
            'sectors' => $sectors,
            'employees' => $employees,
            'shifts' => $shifts,
            'conflictReport' => $conflictReport,
            'workSchedules' => $workSchedules,
            'monthName' => $startOfMonth->translatedFormat('F'),
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-2 sm:px-6 lg:px-8 space-y-6">
    <!-- CABEÇALHO -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Gestão de Escalas e Plantões</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Programação operacional de jornadas cíclicas, plantões noturnos e folgas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="openCreateScheduleModal" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white rounded-xl hover:bg-slate-800 text-sm font-semibold shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Nova Escala / Jornada
                </button>
                <button wire:click="openGenerateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Gerar Plantões / Ciclo
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-emerald-800 text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        <!-- BARRA DE FILTROS E COMPETÊNCIA -->
        <div class="mt-6 pt-5 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Setor</label>
                <select wire:model.live="selected_sector_id" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos os Setores</option>
                    @foreach($sectors as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Colaborador</label>
                <select wire:model.live="selected_employee_id" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos da equipe</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->user->name ?? 'Colaborador #'.$emp->id }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-1 lg:col-span-2 flex items-center justify-between sm:justify-end gap-2">
                <button wire:click="previousMonth" class="px-3 py-2 border border-slate-300 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                    ← Anterior
                </button>
                <span class="text-sm font-bold text-slate-800 capitalize font-mono">
                    {{ $monthName }} / {{ $year }}
                </span>
                <button wire:click="nextMonth" class="px-3 py-2 border border-slate-300 rounded-xl hover:bg-slate-50 text-xs font-semibold text-slate-700">
                    Próximo →
                </button>
            </div>
        </div>
    </div>

    <!-- RELATÓRIO DE CONFLITOS E AUDITORIA (QUANDO HOUVER) -->
    @if($conflictReport && ($conflictReport['has_critical_conflicts'] || count($conflictReport['warnings']) > 0))
        <div class="rounded-2xl border p-4 space-y-2 {{ $conflictReport['has_critical_conflicts'] ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
            <h4 class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-4 h-4 {{ $conflictReport['has_critical_conflicts'] ? 'text-rose-600' : 'text-amber-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                Auditoria de Escalas e Conflitos Detectados
            </h4>
            @foreach($conflictReport['conflicts'] as $c)
                <p class="text-xs text-rose-800 font-medium">• <strong>Conflito Crítico:</strong> {{ $c['message'] }}</p>
            @endforeach
            @foreach($conflictReport['warnings'] as $w)
                <p class="text-xs text-amber-800">• <strong>Alerta:</strong> {{ $w['message'] }}</p>
            @endforeach
        </div>
    @endif

    <!-- LISTAGEM OPERACIONAL DE PLANTÕES (DESKTOP E MOBILE) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800">
                Grade de Jornadas e Plantões ({{ $shifts->count() }} registros)
            </h3>
            <span class="text-xs text-slate-400">Timezone: America/Sao_Paulo</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Colaborador</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Período Programado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tipo & Escala</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status & Origem</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    @forelse($shifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}" class="hover:bg-slate-50/80 transition {{ $shift->is_day_off ? 'bg-slate-50/40 text-slate-400' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-bold text-slate-900">{{ $shift->employee->user->name ?? 'Colaborador #'.$shift->employee_id }}</div>
                                <div class="text-xs text-slate-500">{{ $shift->employee->job_title ?? 'Servidor Público' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($shift->is_day_off)
                                    <div class="text-xs font-semibold text-slate-500">{{ $shift->start_at_local->format('d/m/Y') }} — Folga</div>
                                @else
                                    <div class="text-sm font-bold text-slate-800 font-mono">
                                        {{ $shift->start_at_local->format('d/m H:i') }} → {{ $shift->end_at_local->format('d/m H:i') }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-0.5">
                                        <span class="font-mono">{{ $shift->durationInHours() }}h totais</span>
                                        @if($shift->is_night_shift)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-800 text-slate-100">Noturno</span>
                                        @endif
                                        @if($shift->crosses_midnight)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700">Cruza Meia-Noite</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $shift->is_day_off ? 'bg-slate-100 text-slate-600' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' }}">
                                    {{ $shift->shift_type->label() }}
                                </span>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $shift->workSchedule?->name ?? 'Escala não vinculada' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ match($shift->status) {
                                    ShiftStatus::Scheduled => 'bg-sky-50 text-sky-700 border border-sky-200',
                                    ShiftStatus::Confirmed => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                    ShiftStatus::Swapped => 'bg-purple-50 text-purple-700 border border-purple-200',
                                    ShiftStatus::Cancelled => 'bg-rose-50 text-rose-700 border border-rose-200',
                                    default => 'bg-slate-100 text-slate-700'
                                } }}">
                                    {{ $shift->status->label() }}
                                </span>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $shift->origin->label() }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-xs font-medium space-x-2">
                                @if(!$shift->is_day_off && $shift->status->isActive())
                                    <button wire:click="openEditModal({{ $shift->id }})" class="text-indigo-600 hover:text-indigo-900 font-semibold cursor-pointer">Editar</button>
                                    <button wire:click="openSwapModal({{ $shift->id }})" class="text-purple-600 hover:text-purple-900 font-semibold cursor-pointer">Permutar</button>
                                    <button wire:click="cancelShift({{ $shift->id }})" class="text-rose-600 hover:text-rose-900 cursor-pointer">Cancelar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">
                                Nenhum plantão ou jornada programada neste período.<br>
                                <button wire:click="openGenerateModal" class="mt-2 text-indigo-600 hover:text-indigo-800 font-semibold">Clique aqui para gerar o primeiro ciclo.</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL DE GERAÇÃO DE CICLO -->
    @if($showGenerateModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showGenerateModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-xl bg-white rounded-2xl p-6 shadow-2xl text-left space-y-4">
                <h3 class="text-lg font-bold text-slate-900 border-b pb-2">Gerador de Escalas Cíclicas e Plantões</h3>
                <form wire:submit="generateCyclicShifts" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Colaborador</label>
                        <select wire:model.live="gen_employee_id" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white" required>
                            <option value="">Selecione um colaborador</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->user->name ?? 'Colaborador #'.$emp->id }} ({{ $emp->job_title }})</option>
                            @endforeach
                        </select>
                        @error('gen_employee_id') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Modelo de Escala</label>
                        <select wire:model="gen_schedule_id" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white" required>
                            <option value="">Selecione a escala</option>
                            @foreach($workSchedules as $ws)
                                <option value="{{ $ws->id }}">{{ $ws->name }} ({{ $ws->modality?->label() ?? 'Semanal' }})</option>
                            @endforeach
                        </select>
                        @error('gen_schedule_id') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data Âncora</label>
                            <input type="date" wire:model="gen_anchor_date" class="block w-full px-2.5 py-2 text-xs border border-slate-300 rounded-xl font-mono" required>
                            @error('gen_anchor_date') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Início do Ciclo</label>
                            <input type="date" wire:model="gen_start_date" class="block w-full px-2.5 py-2 text-xs border border-slate-300 rounded-xl font-mono" required>
                            @error('gen_start_date') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fim do Ciclo</label>
                            <input type="date" wire:model="gen_end_date" class="block w-full px-2.5 py-2 text-xs border border-slate-300 rounded-xl font-mono" required>
                            @error('gen_end_date') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="gen_off" wire:model="gen_include_off_days" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <label for="gen_off" class="text-xs text-slate-700">Incluir registros explícitos de folga no calendário</label>
                    </div>

                    <div class="pt-4 border-t flex justify-end gap-2">
                        <button type="button" wire:click="$set('showGenerateModal', false)" class="px-4 py-2 border rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 shadow-sm">Gerar Ciclo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE EDIÇÃO DE PLANTÃO -->
    @if($showEditModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showEditModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl text-left space-y-4">
                <h3 class="text-lg font-bold text-slate-900 border-b pb-2">Editar Plantão Previsto</h3>
                <form wire:submit="updateShift" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Início Local</label>
                            <input type="datetime-local" wire:model="edit_start_datetime" class="block w-full px-2.5 py-2 text-xs border border-slate-300 rounded-xl font-mono" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Término Local</label>
                            <input type="datetime-local" wire:model="edit_end_datetime" class="block w-full px-2.5 py-2 text-xs border border-slate-300 rounded-xl font-mono" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Intervalo Previsto (minutos)</label>
                        <input type="number" wire:model="edit_break_minutes" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl font-mono" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Justificativa da Alteração</label>
                        <textarea wire:model="edit_reason" rows="3" placeholder="Informe o motivo administrativo da mudança de horário" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl" required></textarea>
                        @error('edit_reason') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-4 border-t flex justify-end gap-2">
                        <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 border rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700">Salvar Alteração</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE PERMUTA / TROCA -->
    @if($showSwapModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showSwapModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl text-left space-y-4">
                <h3 class="text-lg font-bold text-slate-900 border-b pb-2">Autorizar Permuta / Troca de Plantão</h3>
                <form wire:submit="executeSwap" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Colaborador Substituto</label>
                        <select wire:model="swap_replacement_employee_id" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white" required>
                            <option value="">Selecione o substituto</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->user->name ?? 'Colaborador #'.$emp->id }}</option>
                            @endforeach
                        </select>
                        @error('swap_replacement_employee_id') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Motivo / Requerimento da Permuta</label>
                        <textarea wire:model="swap_reason" rows="3" placeholder="Descreva o requerimento formal de permuta assinado entre os colaboradores" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl" required></textarea>
                        @error('swap_reason') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-4 border-t flex justify-end gap-2">
                        <button type="button" wire:click="$set('showSwapModal', false)" class="px-4 py-2 border rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700">Autorizar Troca</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE CRIAÇÃO / CONFIGURAÇÃO DE ESCALAS POR MODELO -->
    @if($showScheduleModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showScheduleModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-3 sm:p-4">
            <div class="relative z-10 w-full max-w-3xl bg-white rounded-3xl p-5 sm:p-7 shadow-2xl text-left space-y-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Configurar Jornada ou Escala</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Selecione o modelo desejado para criar a regra de frequência sem complicação.</p>
                    </div>
                    <button type="button" wire:click="$set('showScheduleModal', false)" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- SELETOR DE MODELOS INTUITIVOS -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Selecione o Modelo de Jornada</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <button type="button" wire:click="$set('template', 'administrative')"
                            class="p-3 text-left rounded-2xl border-2 transition flex flex-col justify-between {{ $template === 'administrative' ? 'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                            <div class="flex items-center justify-between">
                                <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">ADM</span>
                                @if($template === 'administrative')<span class="w-2 h-2 rounded-full bg-indigo-600"></span>@endif
                            </div>
                            <div class="mt-3">
                                <div class="text-xs font-bold text-slate-900">Administrativo</div>
                                <div class="text-[10px] text-slate-500 mt-0.5 leading-tight">2 turnos c/ intervalo (ex: 8h-12h/13h-17h)</div>
                            </div>
                        </button>

                        <button type="button" wire:click="$set('template', 'continuous')"
                            class="p-3 text-left rounded-2xl border-2 transition flex flex-col justify-between {{ $template === 'continuous' ? 'border-emerald-600 bg-emerald-50/60 ring-2 ring-emerald-600/20' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                            <div class="flex items-center justify-between">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">6H</span>
                                @if($template === 'continuous')<span class="w-2 h-2 rounded-full bg-emerald-600"></span>@endif
                            </div>
                            <div class="mt-3">
                                <div class="text-xs font-bold text-slate-900">Horário Corrido</div>
                                <div class="text-[10px] text-slate-500 mt-0.5 leading-tight">1 turno sem interrupção (ex: 7h-13h)</div>
                            </div>
                        </button>

                        <button type="button" wire:click="$set('template', 'shift_12h')"
                            class="p-3 text-left rounded-2xl border-2 transition flex flex-col justify-between {{ $template === 'shift_12h' ? 'border-blue-600 bg-blue-50/60 ring-2 ring-blue-600/20' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                            <div class="flex items-center justify-between">
                                <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">12h</span>
                                @if($template === 'shift_12h')<span class="w-2 h-2 rounded-full bg-blue-600"></span>@endif
                            </div>
                            <div class="mt-3">
                                <div class="text-xs font-bold text-slate-900">Plantão 12x36</div>
                                <div class="text-[10px] text-slate-500 mt-0.5 leading-tight">12h de serviço x 36h descanso (diurno/noturno)</div>
                            </div>
                        </button>

                        <button type="button" wire:click="$set('template', 'shift_24h')"
                            class="p-3 text-left rounded-2xl border-2 transition flex flex-col justify-between {{ $template === 'shift_24h' ? 'border-purple-600 bg-purple-50/60 ring-2 ring-purple-600/20' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                            <div class="flex items-center justify-between">
                                <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs">24h</span>
                                @if($template === 'shift_24h')<span class="w-2 h-2 rounded-full bg-purple-600"></span>@endif
                            </div>
                            <div class="mt-3">
                                <div class="text-xs font-bold text-slate-900">Plantão 24x72</div>
                                <div class="text-[10px] text-slate-500 mt-0.5 leading-tight">24h de serviço x 72h descanso (regime legal)</div>
                            </div>
                        </button>
                    </div>
                </div>

                <form wire:submit="saveSchedule" class="space-y-5">
                    <!-- NOME DA ESCALA -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nome Identificador da Escala</label>
                        <input type="text" wire:model="sched_name" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500" required>
                        @error('sched_name') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- FORMULÁRIO CONDICIONAL CONFORME MODELO -->
                    @if($template === 'administrative')
                    <!-- MODELO A: ADMINISTRATIVO -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-4">
                        <div class="text-xs font-bold text-slate-800 uppercase flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            Parâmetros da Jornada Administrativa
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Dias de Trabalho na Semana</label>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
                                @endphp
                                @foreach($weekdays as $dNum => $dLabel)
                                <label class="inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer transition {{ in_array($dNum, (array)$admin_days) ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-100' }}">
                                    <input type="checkbox" wire:model.live="admin_days" value="{{ $dNum }}" class="sr-only">
                                    {{ $dLabel }}
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Entrada Manhã</label>
                                <input type="time" wire:model.live="admin_morning_start" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Início Intervalo</label>
                                <input type="time" wire:model.live="admin_morning_end" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Fim Intervalo</label>
                                <input type="time" wire:model.live="admin_afternoon_start" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Saída Tarde</label>
                                <input type="time" wire:model.live="admin_afternoon_end" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($template === 'continuous')
                    <!-- MODELO B: HORÁRIO CORRIDO -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-4">
                        <div class="text-xs font-bold text-slate-800 uppercase flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                            Parâmetros do Horário Corrido
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Dias de Trabalho na Semana</label>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
                                @endphp
                                @foreach($weekdays as $dNum => $dLabel)
                                <label class="inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer transition {{ in_array($dNum, (array)$cont_days) ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-100' }}">
                                    <input type="checkbox" wire:model.live="cont_days" value="{{ $dNum }}" class="sr-only">
                                    {{ $dLabel }}
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Entrada Única</label>
                                <input type="time" wire:model.live="cont_start_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Saída Única</label>
                                <input type="time" wire:model.live="cont_end_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pausa/Intervalo (minutos)</label>
                                <input type="number" wire:model.live="cont_break_minutes" min="0" max="120" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" id="cont_deduct" wire:model.live="cont_deduct_break" class="rounded text-emerald-600 focus:ring-emerald-500">
                            <label for="cont_deduct" class="text-xs text-slate-700">Deduzir pausa da carga horária efetiva (se desmarcado, a pausa é remunerada/integrada)</label>
                        </div>
                    </div>
                    @endif

                    @if($template === 'shift_12h')
                    <!-- MODELO C: PLANTÃO 12H -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-4">
                        <div class="text-xs font-bold text-slate-800 uppercase flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            Parâmetros do Plantão 12x36
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Horário de Início</label>
                                <input type="time" wire:model.live="p12_start_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Horário de Término</label>
                                <input type="time" wire:model.live="p12_end_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo Intrajornada (min)</label>
                                <input type="number" wire:model.live="p12_break_minutes" min="0" max="120" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Data-Âncora do Primeiro Plantão</label>
                                <input type="date" wire:model.live="p12_anchor_date" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                                <span class="text-[10px] text-slate-500">Define o primeiro dia de trabalho para projeção do ciclo alternado 12x36.</span>
                            </div>
                            <div class="pt-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model.live="p12_is_night" class="rounded text-blue-600 focus:ring-blue-500">
                                    <span class="text-xs text-slate-700 font-medium">Plantão Noturno (atravessa meia-noite, ex: 19:00 às 07:00)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($template === 'shift_24h')
                    <!-- MODELO D: PLANTÃO 24H -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-4">
                        <div class="text-xs font-bold text-slate-800 uppercase flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                                Parâmetros do Plantão 24x72
                            </span>
                            <span class="text-[10px] px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md font-semibold">Exige Autorização Normativa</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Horário de Início</label>
                                <input type="time" wire:model.live="p24_start_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Horário de Término (dia seguinte)</label>
                                <input type="time" wire:model.live="p24_end_time" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo Regulamentar (min)</label>
                                <input type="number" wire:model.live="p24_break_minutes" min="0" max="240" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Data-Âncora do Primeiro Plantão</label>
                            <input type="date" wire:model.live="p24_anchor_date" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl" required>
                            <span class="text-[10px] text-slate-500">Define o primeiro dia de plantão (24h de serviço seguidas de 72h de descanso legal).</span>
                        </div>
                    </div>
                    @endif

                    <!-- PAINEL DE PRÉVIA EM TEMPO REAL -->
                    @php
                        $preview = $this->previewData;
                    @endphp
                    <div class="p-4 bg-indigo-50/60 rounded-2xl border border-indigo-100 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-950 uppercase tracking-wide">Prévia de Carga e Jornada</span>
                            <span class="text-[10px] text-indigo-700 font-medium">Competência atual: {{ $month }}/{{ $year }}</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1">
                            <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Carga Diária</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ sprintf('%02dh%02d', intdiv($preview['daily_minutes'] ?? 0, 60), ($preview['daily_minutes'] ?? 0) % 60) }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $preview['daily_minutes'] ?? 0 }} min</div>
                            </div>

                            <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Carga Semanal</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ sprintf('%02dh%02d', intdiv($preview['weekly_minutes'] ?? 0, 60), ($preview['weekly_minutes'] ?? 0) % 60) }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $preview['weekly_minutes'] ?? 0 }} min</div>
                            </div>

                            <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs col-span-2 sm:col-span-1">
                                <div class="text-[10px] text-indigo-900 uppercase font-semibold">Previsto no Mês Real</div>
                                <div class="text-sm font-bold text-indigo-700 mt-0.5">
                                    {{ sprintf('%02dh%02d', intdiv($preview['monthly_expected_minutes'] ?? 0, 60), ($preview['monthly_expected_minutes'] ?? 0) % 60) }}
                                </div>
                                <div class="text-[10px] text-indigo-500">Sem presunção fictícia de 200h</div>
                            </div>
                        </div>

                        @if(!empty($preview['notes']))
                        <div class="text-[11px] text-indigo-800 font-medium pt-1">
                            ℹ️ {{ $preview['notes'] }}
                        </div>
                        @endif
                    </div>

                    <!-- ATRIBUIÇÃO A COLABORADORES COM VIGÊNCIA -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                        <div class="text-xs font-bold text-slate-800 uppercase flex items-center justify-between">
                            <span>Atribuir a Colaboradores (Opcional)</span>
                            <span class="text-[10px] text-slate-500 font-normal">Preserva histórico sem sobrescrita</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Início da Vigência (Obrigatório se atribuir)</label>
                                <input type="date" wire:model="apply_effective_from" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Fim da Vigência (Em aberto se vazio)</label>
                                <input type="date" wire:model="apply_effective_until" class="block w-full px-2.5 py-1.5 text-xs font-mono border border-slate-300 rounded-xl">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Selecionar Colaboradores</label>
                            <div class="max-h-36 overflow-y-auto border border-slate-200 rounded-xl bg-white p-2 divide-y divide-slate-100">
                                @forelse($employees as $emp)
                                <label class="flex items-center gap-2 py-1 px-1.5 hover:bg-slate-50 rounded-lg text-xs cursor-pointer">
                                    <input type="checkbox" wire:model="apply_employee_ids" value="{{ $emp->id }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="font-medium text-slate-800">{{ $emp->user->name ?? 'Colaborador #'.$emp->id }}</span>
                                    <span class="text-[10px] text-slate-400">Matrícula: {{ $emp->registration_number ?? 'S/N' }}</span>
                                </label>
                                @empty
                                <div class="text-xs text-slate-400 p-2">Nenhum colaborador encontrado para o filtro.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showScheduleModal', false)" class="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 shadow-sm transition">
                            Salvar e Criar Escala
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
