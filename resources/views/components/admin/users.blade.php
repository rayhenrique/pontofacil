<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] #[Title('Gerenciar Usuários')] class extends Component
{
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $role = '';
    
    public $showModal = false;

    public function mount()
    {
        $this->role = UserRole::Admin->value;
    }

    public function openCreateModal()
    {
        $this->reset(['userId', 'name', 'email', 'password']);
        $this->role = UserRole::Admin->value;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = ''; // Don't show current password
        $this->showModal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'role' => 'required|string',
        ];

        // Se for criação, a senha é obrigatória. Se for edição, só valida se preenchida.
        if (!$this->userId) {
            $rules['password'] = 'required|string|min:8';
        } elseif (!empty($this->password)) {
            $rules['password'] = 'string|min:8';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => UserRole::from($this->role),
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            User::findOrFail($this->userId)->update($data);
            session()->flash('message', 'Usuário atualizado com sucesso.');
        } else {
            User::create($data);
            session()->flash('message', 'Usuário criado com sucesso.');
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        if (auth()->id() == $id) {
            session()->flash('error', 'Você não pode excluir a si mesmo.');
            return;
        }

        User::findOrFail($id)->delete();
        session()->flash('message', 'Usuário removido com sucesso.');
    }

    public function with()
    {
        return [
            'users' => User::orderBy('name')->get()
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gerenciar Usuários</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Credenciais de login e níveis de acesso</p>
            </div>
            <button wire:click="openCreateModal" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Adicionar Usuário
            </button>
        </div>
        
        @if (session()->has('message'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif
        
        @if (session()->has('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-x-auto -mx-4 sm:mx-0 border border-gray-200 rounded-xl shadow-xs mt-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">E-mail</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nível de Acesso</th>
                        <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr wire:key="{{ $user->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                {{ $user->name }}
                                @if(auth()->id() == $user->id)
                                    <span class="ml-2 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full font-medium">(Você)</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($user->role === App\Enums\UserRole::Admin)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Administrador
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Funcionário
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="openEditModal({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium mr-3">Editar</button>
                                <button wire:click="delete({{ $user->id }})" wire:confirm="Tem certeza que deseja remover este usuário permanentemente?" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum usuário encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Usuário (Mobile Friendly) -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900 border-b pb-2" id="modal-title">
                        {{ $userId ? 'Editar Usuário' : 'Cadastrar Novo Usuário' }}
                    </h3>
                    <div class="mt-4">
                        <form wire:submit="save" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                <input type="text" wire:model="name" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">E-mail (Login)</label>
                                <input type="email" wire:model="email" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nível de Acesso</label>
                                <select wire:model="role" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                                    @foreach(App\Enums\UserRole::cases() as $r)
                                        <option value="{{ $r->value }}">{{ $r->value === 'admin' ? 'Administrador' : 'Funcionário' }}</option>
                                    @endforeach
                                </select>
                                @error('role') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Senha {{ $userId ? '(Deixe em branco para não alterar)' : '' }}</label>
                                <input type="password" wire:model="password" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" {{ $userId ? '' : 'required' }}>
                                @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t pt-4">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2.5 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                                    <span wire:loading.remove wire:target="save">Salvar Usuário</span>
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