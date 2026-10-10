<?php

use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkloadModality;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Gerenciar Funcionários')] class extends Component
{
    public $employeeId = null;

    public $name = '';

    public $cpf = '';

    public $email = '';

    public $phone = '';

    public $sector_id = '';

    public $job_title = 'Servidor Público';

    public $contract_type = 'Efetivo';

    public $workload = '40h';

    public $zone = 'Urbana';

    // Jornada e Vínculo Jurídico
    public $legal_regime = 'clt';

    public $daily_workload_hours = '8';

    public $weekly_workload_hours = '40';

    public $monthly_workload_hours = '200';

    public $workload_modality = 'fixed';

    public $is_variable_workload = false;

    // Legislação aplicável (estatutários)
    public $normative_jurisdiction = 'municipal';

    public $normative_entity = '';

    public $normative_reference = '';

    public $normative_act_number = '';

    public $normative_effective_from = '';

    public $normative_effective_until = '';

    public $normative_notes = '';

    // Escala versionada
    public $work_schedule_id = '';

    public $initial_work_schedule_id = '';

    public $schedule_effective_from = '';

    public $schedule_reason = 'Atribuição inicial de escala';

    public $showModal = false;

    public function openCreateModal()
    {
        $this->reset([
            'employeeId', 'name', 'cpf', 'email', 'phone', 'sector_id',
            'normative_entity', 'normative_reference', 'normative_act_number',
            'normative_effective_from', 'normative_effective_until', 'normative_notes',
        ]);

        $this->job_title = 'Servidor Público';
        $this->contract_type = 'Efetivo';
        $this->workload = '40h';
        $this->zone = 'Urbana';

        $this->legal_regime = 'clt';
        $this->daily_workload_hours = '8';
        $this->weekly_workload_hours = '40';
        $this->monthly_workload_hours = '200';
        $this->workload_modality = 'fixed';
        $this->is_variable_workload = false;
        $this->normative_jurisdiction = 'municipal';

        $this->schedule_effective_from = Carbon::today()->format('Y-m-d');
        $this->schedule_reason = 'Atribuição cadastral inicial de escala';

        $firstSchedule = WorkSchedule::where('active', true)->first();
        $this->work_schedule_id = $firstSchedule ? (string) $firstSchedule->id : '';
        $this->initial_work_schedule_id = '';

        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $managedSectors = Sector::where('manager_id', $user->id)->get();
            if ($managedSectors->count() === 1) {
                $this->sector_id = (string) $managedSectors->first()->id;
            }
        }

        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $employee = Employee::with(['user', 'sector', 'workScheduleAssignments.workSchedule'])->findOrFail($id);
        $user = Auth::user();

        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $employee->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (! $isResponsible) {
                abort(403, 'Você não tem permissão para editar funcionários fora dos setores sob sua gestão.');
            }
        }

        $this->employeeId = $employee->id;
        $this->name = $employee->user->name ?? '';
        $this->email = $employee->user->email ?? '';

        $rawCpf = preg_replace('/\D/', '', $employee->cpf ?? '');
        $this->cpf = strlen($rawCpf) === 11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $rawCpf)
            : ($employee->cpf ?? '');

        $rawPhone = preg_replace('/\D/', '', $employee->phone ?? '');
        if (strlen($rawPhone) === 11) {
            $this->phone = preg_replace('/(\d{2})(\d{1})(\d{4})(\d{4})/', '($1) $2 $3-$4', $rawPhone);
        } elseif (strlen($rawPhone) === 10) {
            $this->phone = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $rawPhone);
        } else {
            $this->phone = $employee->phone ?? '';
        }

        $this->sector_id = (string) $employee->sector_id;
        $this->job_title = $employee->job_title ?? 'Servidor Público';
        $this->contract_type = $employee->contract_type ?? 'Efetivo';
        $this->workload = $employee->workload ?? '40h';
        $this->zone = $employee->zone ?? 'Urbana';

        // Jornada e Vínculo
        $this->legal_regime = $employee->legal_regime?->value ?? ($employee->legal_regime ?? 'clt');
        $this->daily_workload_hours = $employee->daily_workload_minutes ? (string) round($employee->daily_workload_minutes / 60, 1) : '';
        $this->weekly_workload_hours = $employee->weekly_workload_minutes ? (string) round($employee->weekly_workload_minutes / 60, 1) : '';
        $this->monthly_workload_hours = $employee->monthly_workload_minutes ? (string) round($employee->monthly_workload_minutes / 60, 1) : '';
        $this->workload_modality = $employee->workload_modality?->value ?? ($employee->workload_modality ?? 'fixed');
        $this->is_variable_workload = (bool) $employee->is_variable_workload;

        // Legislação
        $this->normative_jurisdiction = $employee->normative_jurisdiction ?? 'municipal';
        $this->normative_entity = $employee->normative_entity ?? '';
        $this->normative_reference = $employee->normative_reference ?? '';
        $this->normative_act_number = $employee->normative_act_number ?? '';
        $this->normative_effective_from = $employee->normative_effective_from?->format('Y-m-d') ?? '';
        $this->normative_effective_until = $employee->normative_effective_until?->format('Y-m-d') ?? '';
        $this->normative_notes = $employee->normative_notes ?? '';

        // Escala
        $currentSchedule = $employee->getWorkScheduleForDate();
        $this->work_schedule_id = $currentSchedule ? (string) $currentSchedule->id : (string) ($employee->work_schedule_id ?? '');
        $this->initial_work_schedule_id = $this->work_schedule_id;
        $this->schedule_effective_from = Carbon::today()->format('Y-m-d');
        $this->schedule_reason = 'Alteração cadastral de escala';

        $this->showModal = true;
    }

    public function save()
    {
        $employee = $this->employeeId ? Employee::with('user')->findOrFail($this->employeeId) : null;
        $userId = $employee?->user_id;

        $this->email = strtolower(trim($this->email));

        $this->validate([
            'name' => 'required|string|max:255',
            'cpf' => [
                'required',
                'string',
                'max:20',
                Rule::unique('employees', 'cpf')->ignore($this->employeeId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'nullable|string|max:25',
            'sector_id' => 'required|exists:sectors,id',
            'job_title' => 'required|string|max:150',
            'contract_type' => 'required|string|max:100',
            'legal_regime' => 'required|string',
            'workload' => 'required|string|max:50',
            'zone' => 'required|string|max:50',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'schedule_effective_from' => 'nullable|date',
            'normative_effective_from' => 'nullable|date',
            'normative_effective_until' => 'nullable|date|after_or_equal:normative_effective_from',
        ], [
            'email.email' => 'O campo e-mail deve ser um endereço válido (exemplo: example@email.com).',
            'email.regex' => 'O campo e-mail deve ser um endereço válido (exemplo: example@email.com).',
            'cpf.required' => 'O CPF do funcionário é obrigatório.',
            'cpf.unique' => 'Este CPF já está cadastrado para outro funcionário.',
            'email.unique' => 'Este e-mail já está em uso por outro usuário no sistema.',
            'normative_effective_until.after_or_equal' => 'A vigência final do ato não pode ser anterior à data de início.',
        ]);

        $cleanCpf = preg_replace('/\D/', '', $this->cpf);
        if (strlen($cleanCpf) !== 11) {
            $this->addError('cpf', 'O CPF deve conter exatamente 11 dígitos no formato 000.000.000-00.');

            return;
        }

        if (! empty($this->phone)) {
            $cleanPhone = preg_replace('/\D/', '', $this->phone);
            if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 11) {
                $this->addError('phone', 'O telefone deve estar no formato (82) 9 9999-9999 ou (82) 9999-9999.');

                return;
            }
        }

        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $this->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (! $isResponsible) {
                $this->addError('sector_id', 'Você só tem autorização para cadastrar/editar funcionários em setores onde você é o responsável.');

                return;
            }
        }

        $dailyMinutes = is_numeric($this->daily_workload_hours) && (float) $this->daily_workload_hours > 0
            ? (int) round((float) $this->daily_workload_hours * 60)
            : null;
        $weeklyMinutes = is_numeric($this->weekly_workload_hours) && (float) $this->weekly_workload_hours > 0
            ? (int) round((float) $this->weekly_workload_hours * 60)
            : null;
        $monthlyMinutes = is_numeric($this->monthly_workload_hours) && (float) $this->monthly_workload_hours > 0
            ? (int) round((float) $this->monthly_workload_hours * 60)
            : null;

        $targetEmployee = null;

        try {
            DB::transaction(function () use ($employee, $dailyMinutes, $weeklyMinutes, $monthlyMinutes, &$targetEmployee) {
                $employeeData = [
                    'sector_id' => $this->sector_id,
                    'cpf' => $this->cpf,
                    'phone' => $this->phone,
                    'job_title' => $this->job_title,
                    'contract_type' => $this->contract_type,
                    'legal_regime' => $this->legal_regime,
                    'workload' => $this->workload,
                    'daily_workload_minutes' => $dailyMinutes,
                    'weekly_workload_minutes' => $weeklyMinutes,
                    'monthly_workload_minutes' => $monthlyMinutes,
                    'workload_modality' => $this->workload_modality,
                    'is_variable_workload' => (bool) $this->is_variable_workload,
                    'normative_jurisdiction' => $this->normative_jurisdiction ?: null,
                    'normative_entity' => $this->normative_entity ?: null,
                    'normative_reference' => $this->normative_reference ?: null,
                    'normative_act_number' => $this->normative_act_number ?: null,
                    'normative_effective_from' => $this->normative_effective_from ?: null,
                    'normative_effective_until' => $this->normative_effective_until ?: null,
                    'normative_notes' => $this->normative_notes ?: null,
                    'zone' => $this->zone,
                ];

                if ($employee) {
                    if ($employee->user) {
                        $employee->user->update([
                            'name' => $this->name,
                            'email' => $this->email,
                        ]);
                    }

                    $employee->update($employeeData);
                    $targetEmployee = $employee;
                } else {
                    $newUser = User::create([
                        'name' => $this->name,
                        'email' => $this->email,
                        'password' => Hash::make('12345678'),
                        'role' => UserRole::Employee,
                    ]);

                    $employeeData['user_id'] = $newUser->id;
                    $targetEmployee = Employee::create($employeeData);
                }

                // Aplicação da Escala Versionada se informada
                if (! empty($this->work_schedule_id)) {
                    $schedule = WorkSchedule::find($this->work_schedule_id);
                    $shouldAssign = ! $employee ||
                        $this->work_schedule_id !== $this->initial_work_schedule_id ||
                        $targetEmployee->workScheduleAssignments()->count() === 0;

                    if ($schedule && $shouldAssign) {
                        app(AssignWorkScheduleAction::class)->execute(
                            employee: $targetEmployee,
                            schedule: $schedule,
                            effectiveFrom: $this->schedule_effective_from ?: Carbon::today(),
                            effectiveUntil: null,
                            reason: $this->schedule_reason ?: 'Atribuição administrativa de escala',
                            assignedBy: Auth::user(),
                        );
                    }
                }
            });
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $field = str_contains($key, 'effective_from') ? 'schedule_effective_from' : 'work_schedule_id';
                $this->addError($field, $messages[0] ?? 'Erro ao atribuir escala.');
            }

            return;
        }

        $isEdit = (bool) $this->employeeId;
        $this->reset([
            'employeeId', 'name', 'cpf', 'email', 'phone', 'sector_id',
            'job_title', 'contract_type', 'workload', 'zone',
            'legal_regime', 'daily_workload_hours', 'weekly_workload_hours',
            'monthly_workload_hours', 'workload_modality', 'is_variable_workload',
            'normative_entity', 'normative_reference', 'normative_act_number',
            'normative_effective_from', 'normative_effective_until', 'normative_notes',
            'work_schedule_id', 'initial_work_schedule_id', 'schedule_effective_from',
        ]);
        $this->showModal = false;

        session()->flash('message', $isEdit ? 'Funcionário atualizado com sucesso.' : 'Funcionário e usuário criados com sucesso.');
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => $isEdit ? 'Dados Atualizados!' : 'Funcionário Cadastrado!',
            'message' => $isEdit ? 'Os dados funcionais, regime e escala foram atualizados com sucesso.' : 'O colaborador, regime e escala foram cadastrados com sucesso.',
        ]);
    }

    public function delete($id)
    {
        $employee = Employee::findOrFail($id);
        $user = Auth::user();

        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $employee->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (! $isResponsible) {
                abort(403, 'Você não tem permissão para excluir funcionários fora dos setores sob sua gestão.');
            }
        }

        $userToDelete = $employee->user;
        $employee->delete();
        if ($userToDelete) {
            $userToDelete->delete();
        }

        session()->flash('message', 'Funcionário e acesso removidos.');
        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Funcionário Removido',
            'message' => 'O colaborador e seu acesso de usuário foram removidos do sistema com sucesso.',
        ]);
    }

    public function with()
    {
        $user = Auth::user();
        $isManager = $user->role === UserRole::Manager;
        $workSchedules = WorkSchedule::where('active', true)->orderBy('name')->get();
        $legalRegimes = LegalRegime::cases();

        if ($isManager) {
            $managedSectors = Sector::where('manager_id', $user->id)->orderBy('name')->get();
            $managedSectorIds = $managedSectors->pluck('id')->toArray();

            return [
                'employees' => Employee::with(['user', 'sector', 'workScheduleAssignments.workSchedule'])
                    ->whereIn('sector_id', $managedSectorIds)
                    ->orderByDesc('id')
                    ->get(),
                'sectors' => $managedSectors,
                'workSchedules' => $workSchedules,
                'legalRegimes' => $legalRegimes,
                'isManager' => true,
                'managedSectorsCount' => $managedSectors->count(),
            ];
        }

        return [
            'employees' => Employee::with(['user', 'sector', 'workScheduleAssignments.workSchedule'])->orderByDesc('id')->get(),
            'sectors' => Sector::orderBy('name')->get(),
            'workSchedules' => $workSchedules,
            'legalRegimes' => $legalRegimes,
            'isManager' => false,
            'managedSectorsCount' => null,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gerenciar Funcionários</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">{{ $isManager ? 'Colaboradores dos setores sob sua gestão' : 'Colaboradores, regimes jurídicos e escalas de trabalho' }}</p>
            </div>
            <button wire:click="openCreateModal" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Adicionar Funcionário
            </button>
        </div>

        @if($isManager && $managedSectorsCount === 0)
            <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-800 text-sm flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <div>
                    <p class="font-bold">Aviso de Gestão</p>
                    <p class="text-xs text-amber-700 mt-0.5">Seu perfil é de Gestor, porém você ainda não foi designado como responsável por nenhum setor. Solicite ao Administrador (RH) que vincule seu usuário a um setor para que você possa cadastrar colaboradores.</p>
                </div>
            </div>
        @endif
        
        @if (session()->has('message'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        <div class="overflow-x-auto -mx-4 sm:mx-0 border border-gray-200 rounded-xl shadow-xs mt-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Colaborador & E-mail</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Regime & Vínculo</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Setor & Escala</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Carga / CPF</th>
                        <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($employees as $employee)
                        <tr wire:key="{{ $employee->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">{{ $employee->user->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $employee->user->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-800">{{ $employee->job_title ?? 'Servidor Público' }}</div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $employee->legal_regime === \App\Enums\LegalRegime::CLT ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                        {{ $employee->legal_regime?->label() ?? 'Legado / Não definido' }}
                                    </span>
                                    <span class="text-xs text-gray-400">• {{ $employee->contract_type ?? 'Efetivo' }}</span>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($employee->sector)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $employee->sector->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Sem setor</span>
                                @endif
                                @php
                                    $activeSchedule = $employee->getWorkScheduleForDate();
                                @endphp
                                <div class="text-xs font-medium text-slate-600 mt-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $activeSchedule?->name ?? 'Sem escala atribuída' }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-semibold text-indigo-600 font-mono">{{ $employee->getWorkloadDescription() }}</div>
                                @php
                                    $rawCpfTable = preg_replace('/\D/', '', $employee->cpf ?? '');
                                    $displayCpf = strlen($rawCpfTable) === 11 
                                        ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $rawCpfTable) 
                                        : $employee->cpf;
                                @endphp
                                <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $displayCpf }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button type="button" wire:click="openEditModal({{ $employee->id }})" class="text-indigo-600 hover:text-indigo-900 font-semibold cursor-pointer">Editar</button>
                                <button type="button" @click="window.showModalConfirm({
                                    title: 'Excluir Funcionário',
                                    message: 'Tem certeza que deseja excluir o colaborador {{ addslashes($employee->user->name ?? 'Colaborador') }}? Esta ação apagará o funcionário e o acesso de usuário dele permanentemente.',
                                    confirmText: 'Sim, Excluir',
                                    cancelText: 'Cancelar',
                                    isDanger: true,
                                    onConfirm: () => $wire.delete({{ $employee->id }})
                                })" class="text-red-600 hover:text-red-900 font-medium cursor-pointer">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum funcionário cadastrado{{ $isManager ? ' nos seus setores' : '' }}.<br>
                                @if(!$isManager || $managedSectorsCount > 0)
                                    <button wire:click="openCreateModal" class="mt-2 text-indigo-600 hover:text-indigo-900 font-semibold">Clique aqui para adicionar o primeiro.</button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Funcionário com Seção de Jornada e Vínculo -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

        <div class="flex min-h-screen items-center justify-center p-3 sm:p-4 text-center">
            <div class="relative z-10 w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white p-5 sm:p-6 text-left shadow-2xl transition-all my-4 sm:my-8 max-h-[92vh] overflow-y-auto"
                 x-data="{
                     formatCpf(el) {
                         let v = el.value.replace(/\D/g, '').slice(0, 11);
                         if (v.length > 9) v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
                         else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{1,3})$/, '$1.$2.$3');
                         else if (v.length > 3) v = v.replace(/^(\d{3})(\d{1,3})$/, '$1.$2');
                         el.value = v;
                         $wire.set('cpf', v, false);
                     },
                     formatPhone(el) {
                         let v = el.value.replace(/\D/g, '').slice(0, 11);
                         if (v.length > 10) v = v.replace(/^(\d{2})(\d{1})(\d{4})(\d{4})$/, '($1) $2 $3-$4');
                         else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{1,4})$/, '($1) $2-$3');
                         else if (v.length > 2) v = v.replace(/^(\d{2})(\d{1,4})$/, '($1) $2');
                         else if (v.length > 0) v = v.replace(/^(\d{1,2})$/, '($1');
                         el.value = v;
                         $wire.set('phone', v, false);
                     }
                 }">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900 border-b pb-2" id="modal-title">
                        {{ $employeeId ? 'Editar Dados do Funcionário' : 'Cadastrar Novo Funcionário' }}
                    </h3>
                    @if(!$employeeId)
                        <p class="mt-2 text-xs text-gray-500">
                            O usuário será criado automaticamente. Senha inicial padrão: <strong class="text-indigo-600 font-mono">12345678</strong>
                        </p>
                    @else
                        <p class="mt-2 text-xs text-gray-500">
                            Atualize os dados funcionais, regime jurídico e escala versionada do colaborador.
                        </p>
                    @endif

                    @if($isManager && $managedSectorsCount === 0)
                        <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-rose-800 text-xs">
                            Você não possui setores associados como responsável e não pode cadastrar funcionários no momento.
                        </div>
                    @endif

                    <div class="mt-4">
                        <form wire:submit="save" class="space-y-5">
                            <!-- BLOCO 1: IDENTIFICAÇÃO E ACESSO -->
                            <div>
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    Identificação e Contato
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                        <input type="text" wire:model="name" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                        @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">E-mail (Login)</label>
                                        <input type="email" wire:model="email" @input="$event.target.value = $event.target.value.toLowerCase().trim()" placeholder="example@email.com" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">CPF</label>
                                        <input type="text" wire:model="cpf" @input="formatCpf($event.target)" maxlength="14" placeholder="000.000.000-00" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono" required>
                                        @error('cpf') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Telefone</label>
                                        <input type="text" wire:model="phone" @input="formatPhone($event.target)" maxlength="17" placeholder="(82) 9 9999-9999" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono">
                                        @error('phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                            Setor de Lotação {{ $isManager ? '(Setores sob sua responsabilidade)' : '' }}
                                        </label>
                                        <select wire:model="sector_id" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required {{ $isManager && $managedSectorsCount === 0 ? 'disabled' : '' }}>
                                            <option value="">Selecione um setor</option>
                                            @foreach($sectors as $sector)
                                                <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('sector_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- BLOCO 2: JORNADA E VÍNCULO (NOVO PADRÃO ESTRUTURADO) -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Jornada e Vínculo
                                        </h4>
                                        <p class="text-[11px] text-slate-500">Regime jurídico, modalidades de carga horária e escala de trabalho.</p>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-700">PTRP / Portaria 671</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Regime Jurídico</label>
                                        <select wire:model.live="legal_regime" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium" required>
                                            <option value="clt">CLT (Consolidação das Leis do Trabalho)</option>
                                            <option value="federal_statutory">Estatutário Federal</option>
                                            <option value="state_statutory">Estatutário Estadual</option>
                                            <option value="municipal_statutory">Estatutário Municipal</option>
                                            <option value="other">Outro Regime Específico</option>
                                        </select>
                                        @error('legal_regime') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Tipo de Vínculo</label>
                                        <select wire:model="contract_type" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                            <option value="Efetivo">Efetivo (Concursado)</option>
                                            <option value="Contratado">Contratado (Processo Seletivo / PSS)</option>
                                            <option value="Comissionado">Comissionado</option>
                                            <option value="Temporário">Temporário</option>
                                            <option value="Estagiário">Estagiário</option>
                                            <option value="CLT">CLT</option>
                                        </select>
                                        @error('contract_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Cargo / Função</label>
                                        <input type="text" list="cargos-list" wire:model="job_title" placeholder="Ex: Enfermeiro(a)" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                        <datalist id="cargos-list">
                                            <option value="Enfermeiro(a)">
                                            <option value="Médico(a)">
                                            <option value="Técnico(a) de Enfermagem">
                                            <option value="Agente Comunitário de Saúde (ACS)">
                                            <option value="Agente de Combate às Endemias (ACE)">
                                            <option value="Auxiliar Administrativo">
                                            <option value="Servidor Público">
                                        </datalist>
                                        @error('job_title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Zona</label>
                                        <select wire:model="zone" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                            <option value="Urbana">Urbana</option>
                                            <option value="Rural">Rural</option>
                                        </select>
                                        @error('zone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- SUB-BLOCO LEGISLAÇÃO APLICÁVEL (Exibido para Estatutários ou Outro) -->
                                @if(in_array($legal_regime, ['state_statutory', 'municipal_statutory', 'other', 'federal_statutory']))
                                <div class="bg-white rounded-xl border border-slate-200 p-3.5 space-y-3">
                                    <h5 class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Legislação e Referência Normativa do Regime
                                    </h5>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Ente Federativo / Órgão</label>
                                            <input type="text" wire:model="normative_entity" placeholder="Ex: Prefeitura Municipal de Maceió" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Referência Normativa / Estatuto</label>
                                            <input type="text" wire:model="normative_reference" placeholder="Ex: Lei Complementar Municipal nº 1.234/2015" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Número e Identificação do Ato</label>
                                            <input type="text" wire:model="normative_act_number" placeholder="Ex: Decreto nº 9.876/2020" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Início da Vigência do Ato</label>
                                            <input type="date" wire:model="normative_effective_from" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- SUB-BLOCO CARGA HORÁRIA ESTRUTURADA -->
                                <div class="bg-white rounded-xl border border-slate-200 p-3.5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h5 class="text-xs font-bold text-slate-800">Carga Horária Estruturada</h5>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="is_var" wire:model.live="is_variable_workload" class="rounded text-indigo-600 focus:ring-indigo-500">
                                            <label for="is_var" class="text-xs text-slate-700 font-medium">Meta mensal variável por escala</label>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Horas Semanais</label>
                                            <input type="number" step="0.5" wire:model="weekly_workload_hours" placeholder="40" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Horas Diárias</label>
                                            <input type="number" step="0.5" wire:model="daily_workload_hours" placeholder="8" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Meta Mensal (h)</label>
                                            <input type="number" step="0.5" wire:model="monthly_workload_hours" placeholder="200" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono" {{ $is_variable_workload ? 'disabled' : '' }}>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Texto Legado</label>
                                            <input type="text" wire:model="workload" placeholder="40h" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono">
                                        </div>
                                    </div>
                                </div>

                                <!-- SUB-BLOCO ESCALA DE TRABALHO E VIGÊNCIA HISTÓRICA -->
                                <div class="bg-white rounded-xl border border-slate-200 p-3.5 space-y-3">
                                    <h5 class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        Escala de Trabalho & Vigência Versionada
                                    </h5>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Modelo de Escala</label>
                                            <select wire:model="work_schedule_id" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-medium">
                                                <option value="">Selecione uma escala</option>
                                                @foreach($workSchedules as $schedule)
                                                    <option value="{{ $schedule->id }}">{{ $schedule->name }} ({{ $schedule->modality?->label() ?? 'Semanal' }})</option>
                                                @endforeach
                                            </select>
                                            @error('work_schedule_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Início da Vigência da Escala</label>
                                            <input type="date" wire:model="schedule_effective_from" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono">
                                            @error('schedule_effective_from') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-indigo-50/70 border border-indigo-200/80 p-2.5 text-xs text-indigo-900 flex items-start gap-2">
                                        <svg class="w-4 h-4 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>Esta mudança será aplicada a partir da data informada. Escalas anteriores serão preservadas no histórico para apurações passadas.</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t pt-4">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" @if($isManager && $managedSectorsCount === 0) disabled @endif class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="save">{{ $employeeId ? 'Salvar Alterações' : 'Salvar Funcionário' }}</span>
                                    <span wire:loading wire:target="save">Salvando...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>