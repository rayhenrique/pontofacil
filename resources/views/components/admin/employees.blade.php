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

new #[Layout('layouts.app')] #[Title('Gerenciar Funcionários')] class extends Component
{
    public $name = '';
    public $cpf = '';
    public $email = '';
    public $phone = '';
    public $sector_id = '';
    
    public $showModal = false;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'cpf' => 'required|string|max:20|unique:employees,cpf',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'sector_id' => 'required|exists:sectors,id',
        ]);

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
    }

    public function delete($id)
    {
        $employee = Employee::findOrFail($id);
        $user = $employee->user;
        $employee->delete();
        if ($user) {
            $user->delete();
        }
        
        session()->flash('message', 'Funcionário e acesso removidos.');
    }

    public function with()
    {
        return [
            'employees' => Employee::with(['user', 'sector'])->orderByDesc('id')->get(),
            'sectors' => Sector::orderBy('name')->get()
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gerenciar Funcionários</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Colaboradores e cadastros da empresa</p>
            </div>
            <button wire:click="$set('showModal', true)" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Adicionar Funcionário
            </button>
        </div>
        
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
                                <button wire:click="delete({{ $employee->id }})" wire:confirm="Tem certeza? Isso apagará o funcionário e o acesso de usuário dele!" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum funcionário cadastrado.<br>
                                <button wire:click="$set('showModal', true)" class="mt-2 text-indigo-600 hover:text-indigo-900 font-semibold">Clique aqui para adicionar o primeiro.</button>
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
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900 border-b pb-2" id="modal-title">
                        Cadastrar Novo Funcionário
                    </h3>
                    <p class="mt-2 text-xs text-gray-500">
                        O usuário será criado automaticamente. Senha inicial padrão: <strong class="text-indigo-600 font-mono">12345678</strong>
                    </p>
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
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Setor</label>
                                    <select wire:model="sector_id" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
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
                                <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
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