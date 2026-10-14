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
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Usuário Atualizado!',
                'message' => 'Os dados do usuário foram atualizados com sucesso.'
            ]);
        } else {
            User::create($data);
            session()->flash('message', 'Usuário criado com sucesso.');
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Usuário Cadastrado!',
                'message' => 'O novo usuário foi criado com sucesso.'
            ]);
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        if (auth()->id() == $id) {
            session()->flash('error', 'Você não pode excluir a si mesmo.');
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Operação Não Permitida',
                'message' => 'Você não pode excluir sua própria conta de usuário.'
            ]);
            return;
        }

        User::findOrFail($id)->delete();
        session()->flash('message', 'Usuário removido com sucesso.');
        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Usuário Removido',
            'message' => 'O usuário foi excluído do sistema com sucesso.'
        ]);
    }

    public string $search = '';

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function with()
    {
        $query = User::orderBy('name');
        if (! empty(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return [
            'users' => $query->get(),
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

        <!-- Barra de Busca -->
        <div class="mt-4 mb-4">
            <div class="relative max-w-md">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nome ou e-mail..."
                    class="w-full pl-9 pr-9 py-2 text-sm border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                    aria-label="Buscar usuário por nome ou e-mail"
                />
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(! empty($search))
                    <button
                        wire:click="clearSearch"
                        type="button"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 min-h-[44px] min-w-[44px] justify-center"
                        title="Limpar busca"
                        aria-label="Limpar busca"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
            <div wire:loading wire:target="search" class="pt-1.5 text-xs text-indigo-600 font-semibold" aria-live="polite">
                Filtrando usuários…
            </div>
        </div>

        <!-- Visualização Mobile (Cards Verticais - sm:hidden) -->
        <div class="sm:hidden space-y-3 mt-4">
            @forelse($users as $user)
                <div wire:key="mobile-user-{{ $user->id }}" class="bg-gray-50 rounded-xl p-4 border border-gray-200 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-gray-900 text-sm">
                                {{ $user->name }}
                                @if(auth()->id() == $user->id)
                                    <span class="ml-1 text-[11px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full font-semibold border border-indigo-200">(Você)</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $user->email }}</div>
                        </div>
                        <div>
                            @if($user->role === App\Enums\UserRole::Admin)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Administrador
                                </span>
                            @elseif($user->role === App\Enums\UserRole::Manager)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    Gestor
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                    Colaborador
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-200">
                        <button
                            wire:click="openEditModal({{ $user->id }})"
                            type="button"
                            class="inline-flex items-center justify-center px-3 py-2 text-xs font-semibold text-indigo-700 bg-white border border-gray-300 rounded-lg shadow-2xs hover:bg-gray-50 min-h-[44px]"
                        >
                            Editar
                        </button>
                        <button
                            type="button"
                            @click="window.showModalConfirm({
                                title: 'Excluir Usuário',
                                message: 'Tem certeza que deseja remover o usuário {{ addslashes($user->name) }} permanentemente? Ele perderá imediatamente o acesso ao sistema.',
                                confirmText: 'Sim, Excluir',
                                cancelText: 'Cancelar',
                                isDanger: true,
                                onConfirm: () => $wire.delete({{ $user->id }})
                            })"
                            class="inline-flex items-center justify-center px-3 py-2 text-xs font-semibold text-red-700 bg-white border border-red-200 rounded-lg shadow-2xs hover:bg-red-50 min-h-[44px]"
                        >
                            Excluir
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                    <p class="font-medium text-gray-700">Nenhum usuário localizado.</p>
                    @if(! empty($search))
                        <button wire:click="clearSearch" type="button" class="mt-2 text-xs font-semibold text-indigo-600 hover:underline min-h-[44px] inline-flex items-center">
                            Limpar busca para ver todos
                        </button>
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Tabela Desktop (hidden sm:block) -->
        <div class="hidden sm:block overflow-x-auto -mx-4 sm:mx-0 border border-gray-200 rounded-xl shadow-xs mt-4">
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
                        <tr wire:key="desktop-user-{{ $user->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                {{ $user->name }}
                                @if(auth()->id() == $user->id)
                                    <span class="ml-2 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full font-medium border border-indigo-200">(Você)</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($user->role === App\Enums\UserRole::Admin)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Administrador
                                    </span>
                                @elseif($user->role === App\Enums\UserRole::Manager)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        Gestor
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                        Colaborador
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="openEditModal({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium mr-3 min-h-[44px] inline-flex items-center">Editar</button>
                                <button type="button" @click="window.showModalConfirm({
                                    title: 'Excluir Usuário',
                                    message: 'Tem certeza que deseja remover o usuário {{ addslashes($user->name) }} permanentemente? Ele perderá imediatamente o acesso ao sistema.',
                                    confirmText: 'Sim, Excluir',
                                    cancelText: 'Cancelar',
                                    isDanger: true,
                                    onConfirm: () => $wire.delete({{ $user->id }})
                                })" class="text-red-600 hover:text-red-900 font-medium min-h-[44px] inline-flex items-center">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <p class="font-medium text-gray-700">Nenhum usuário encontrado.</p>
                                    @if(! empty($search))
                                        <button wire:click="clearSearch" type="button" class="text-xs font-semibold text-indigo-600 hover:underline min-h-[44px] inline-flex items-center">
                                            Limpar busca para ver todos
                                        </button>
                                    @endif
                                </div>
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
        <!-- Backdrop overlay -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8">
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
                                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
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