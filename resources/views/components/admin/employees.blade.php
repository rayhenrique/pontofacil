<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Employee;
use App\Models\User;
use App\Models\Sector;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
    
    public $showModal = false;

    public function openCreateModal()
    {
        $this->reset(['employeeId', 'name', 'cpf', 'email', 'phone', 'sector_id']);
        $this->job_title = 'Servidor Público';
        $this->contract_type = 'Efetivo';
        $this->workload = '40h';
        $this->zone = 'Urbana';
        
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
        $employee = Employee::with(['user', 'sector'])->findOrFail($id);
        $user = Auth::user();

        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $employee->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (!$isResponsible) {
                abort(403, 'Você não tem permissão para editar funcionários fora dos setores sob sua gestão.');
            }
        }

        $this->employeeId = $employee->id;
        $this->name = $employee->user->name ?? '';
        $this->email = $employee->user->email ?? '';
        $this->cpf = $employee->cpf ?? '';
        $this->phone = $employee->phone ?? '';
        $this->sector_id = (string) $employee->sector_id;
        $this->job_title = $employee->job_title ?? 'Servidor Público';
        $this->contract_type = $employee->contract_type ?? 'Efetivo';
        $this->workload = $employee->workload ?? '40h';
        $this->zone = $employee->zone ?? 'Urbana';

        $this->showModal = true;
    }

    public function save()
    {
        $employee = $this->employeeId ? Employee::with('user')->findOrFail($this->employeeId) : null;
        $userId = $employee?->user_id;

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
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'nullable|string|max:20',
            'sector_id' => 'required|exists:sectors,id',
            'job_title' => 'required|string|max:150',
            'contract_type' => 'required|string|max:100',
            'workload' => 'required|string|max:50',
            'zone' => 'required|string|max:50',
        ]);

        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $this->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (!$isResponsible) {
                $this->addError('sector_id', 'Você só tem autorização para cadastrar/editar funcionários em setores onde você é o responsável.');
                return;
            }
        }

        DB::transaction(function () use ($employee) {
            if ($employee) {
                if ($employee->user) {
                    $employee->user->update([
                        'name' => $this->name,
                        'email' => $this->email,
                    ]);
                }

                $employee->update([
                    'sector_id' => $this->sector_id,
                    'cpf' => $this->cpf,
                    'phone' => $this->phone,
                    'job_title' => $this->job_title,
                    'contract_type' => $this->contract_type,
                    'workload' => $this->workload,
                    'zone' => $this->zone,
                ]);
            } else {
                $newUser = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => Hash::make('12345678'),
                    'role' => UserRole::Employee,
                ]);

                Employee::create([
                    'user_id' => $newUser->id,
                    'sector_id' => $this->sector_id,
                    'cpf' => $this->cpf,
                    'phone' => $this->phone,
                    'job_title' => $this->job_title,
                    'contract_type' => $this->contract_type,
                    'workload' => $this->workload,
                    'zone' => $this->zone,
                ]);
            }
        });

        $isEdit = (bool) $this->employeeId;
        $this->reset(['employeeId', 'name', 'cpf', 'email', 'phone', 'sector_id', 'job_title', 'contract_type', 'workload', 'zone']);
        $this->showModal = false;
        
        session()->flash('message', $isEdit ? 'Funcionário atualizado com sucesso.' : 'Funcionário e usuário criados com sucesso.');
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => $isEdit ? 'Dados Atualizados!' : 'Funcionário Cadastrado!',
            'message' => $isEdit ? 'Os dados funcionais do colaborador foram atualizados com sucesso.' : 'O colaborador e o acesso de usuário foram criados com sucesso.'
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

            if (!$isResponsible) {
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
            'message' => 'O colaborador e seu acesso de usuário foram removidos do sistema com sucesso.'
        ]);
    }

    public function with()
    {
        $user = Auth::user();
        $isManager = $user->role === UserRole::Manager;

        if ($isManager) {
            $managedSectors = Sector::where('manager_id', $user->id)->orderBy('name')->get();
            $managedSectorIds = $managedSectors->pluck('id')->toArray();

            return [
                'employees' => Employee::with(['user', 'sector'])
                    ->whereIn('sector_id', $managedSectorIds)
                    ->orderByDesc('id')
                    ->get(),
                'sectors' => $managedSectors,
                'isManager' => true,
                'managedSectorsCount' => $managedSectors->count(),
            ];
        }

        return [
            'employees' => Employee::with(['user', 'sector'])->orderByDesc('id')->get(),
            'sectors' => Sector::orderBy('name')->get(),
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
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">{{ $isManager ? 'Colaboradores dos setores sob sua gestão' : 'Colaboradores e cadastros da empresa' }}</p>
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
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Servidor & E-mail</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cargo & Vínculo</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Setor & Zona</th>
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
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                    {{ $employee->contract_type ?? 'Efetivo' }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($employee->sector)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $employee->sector->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Sem setor</span>
                                @endif
                                <div class="text-xs text-gray-400 mt-0.5">Zona {{ $employee->zone ?? 'Urbana' }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-semibold text-indigo-600">{{ $employee->workload ?? '40h' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $employee->cpf }}</div>
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

    <!-- Modal Adicionar/Editar Funcionário (Mobile Friendly) -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <!-- Backdrop overlay -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8">
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
                            Atualize os dados cadastrais, cargo, vínculo e carga horária para refletir na folha de ponto e espelhos.
                        </p>
                    @endif

                    @if($isManager && $managedSectorsCount === 0)
                        <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-rose-800 text-xs">
                            Você não possui setores associados como responsável e não pode cadastrar funcionários no momento.
                        </div>
                    @endif

                    <div class="mt-4">
                        <form wire:submit="save" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                    <input type="text" wire:model="name" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">E-mail (Login)</label>
                                    <input type="email" wire:model="email" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                    @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">CPF</label>
                                    <input type="text" wire:model="cpf" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="Apenas números">
                                    @error('cpf') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Telefone</label>
                                    <input type="text" wire:model="phone" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    @error('phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                        Setor de Lotação {{ $isManager ? '(Setores sob sua responsabilidade)' : '' }}
                                    </label>
                                    <select wire:model="sector_id" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required {{ $isManager && $managedSectorsCount === 0 ? 'disabled' : '' }}>
                                        <option value="">Selecione um setor</option>
                                        @foreach($sectors as $sector)
                                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('sector_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- CAMPOS FUNCIONAIS QUE ALIMENTAM A FOLHA DE PONTO -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Cargo / Função</label>
                                    <input type="text" list="cargos-list" wire:model="job_title" placeholder="Ex: Enfermeiro(a)" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                    <datalist id="cargos-list">
                                        <option value="Enfermeiro(a)">
                                        <option value="Médico(a)">
                                        <option value="Técnico(a) de Enfermagem">
                                        <option value="Agente Comunitário de Saúde (ACS)">
                                        <option value="Agente de Combate às Endemias (ACE)">
                                        <option value="Auxiliar de Saúde Bucal">
                                        <option value="Dentista / Odontólogo(a)">
                                        <option value="Auxiliar Administrativo">
                                        <option value="Recepcionista">
                                        <option value="Motorista">
                                        <option value="Coordenador(a)">
                                        <option value="Servidor Público">
                                    </datalist>
                                    @error('job_title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Vínculo</label>
                                    <select wire:model="contract_type" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
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
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Carga Horária Semanal</label>
                                    <select wire:model="workload" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                        <option value="40h">40h semanais</option>
                                        <option value="30h">30h semanais</option>
                                        <option value="24h">24h (Plantão)</option>
                                        <option value="20h">20h semanais</option>
                                        <option value="12x36">12x36 (Escala)</option>
                                    </select>
                                    @error('workload') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Zona</label>
                                    <select wire:model="zone" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                        <option value="Urbana">Urbana</option>
                                        <option value="Rural">Rural</option>
                                    </select>
                                    @error('zone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t pt-4">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2.5 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" @if($isManager && $managedSectorsCount === 0) disabled @endif class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
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