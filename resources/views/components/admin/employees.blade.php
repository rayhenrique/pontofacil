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

new #[Layout('layouts.app')] #[Title('Gerenciar Funcionários')] class extends Component
{
    public $name = '';
    public $cpf = '';
    public $email = '';
    public $phone = '';
    public $sector_id = '';
    
    public $showModal = false;

    public function openCreateModal()
    {
        $this->reset(['name', 'cpf', 'email', 'phone', 'sector_id']);
        
        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $managedSectors = Sector::where('manager_id', $user->id)->get();
            if ($managedSectors->count() === 1) {
                $this->sector_id = (string) $managedSectors->first()->id;
            }
        }

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'cpf' => 'required|string|max:20|unique:employees,cpf',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'sector_id' => 'required|exists:sectors,id',
        ]);

        $user = Auth::user();
        if ($user->role === UserRole::Manager) {
            $isResponsible = Sector::where('id', $this->sector_id)
                ->where('manager_id', $user->id)
                ->exists();

            if (!$isResponsible) {
                $this->addError('sector_id', 'Você só tem autorização para cadastrar funcionários em setores onde você é o responsável.');
                return;
            }
        }

        DB::transaction(function () {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make('12345678'),
                'role' => UserRole::Employee,
            ]);

            Employee::create([
                'user_id' => $user->id,
                'sector_id' => $this->sector_id,
                'cpf' => $this->cpf,
                'phone' => $this->phone,
            ]);
        });

        $this->reset(['name', 'cpf', 'email', 'phone', 'sector_id']);
        $this->showModal = false;
        
        session()->flash('message', 'Funcionário e usuário criados com sucesso.');
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Funcionário Cadastrado!',
            'message' => 'O colaborador e o acesso de usuário foram criados com sucesso.'
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
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome & E-mail</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Setor</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">CPF</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Telefone</th>
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
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($employee->sector)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $employee->sector->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Sem setor</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono">{{ $employee->cpf }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $employee->phone ?: '-' }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button type="button" @click="window.showModalConfirm({
                                    title: 'Excluir Funcionário',
                                    message: 'Tem certeza que deseja excluir o colaborador {{ addslashes($employee->user->name ?? 'Colaborador') }}? Esta ação apagará o funcionário e o acesso de usuário dele permanentemente.',
                                    confirmText: 'Sim, Excluir',
                                    cancelText: 'Cancelar',
                                    isDanger: true,
                                    onConfirm: () => $wire.delete({{ $employee->id }})
                                })" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
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

    <!-- Modal Adicionar Funcionário (Mobile Friendly) -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <!-- Backdrop overlay -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-xl transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900 border-b pb-2" id="modal-title">
                        Cadastrar Novo Funcionário
                    </h3>
                    <p class="mt-2 text-xs text-gray-500">
                        O usuário será criado automaticamente. Senha inicial padrão: <strong class="text-indigo-600 font-mono">12345678</strong>
                    </p>

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
                                        Setor {{ $isManager ? '(Setores sob sua responsabilidade)' : '' }}
                                    </label>
                                    <select wire:model="sector_id" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required {{ $isManager && $managedSectorsCount === 0 ? 'disabled' : '' }}>
                                        <option value="">Selecione um setor</option>
                                        @foreach($sectors as $sector)
                                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('sector_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t pt-4">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2.5 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" @if($isManager && $managedSectorsCount === 0) disabled @endif class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="save">Salvar Funcionário</span>
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