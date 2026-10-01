<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sector;
use App\Models\User;

new #[Layout('layouts.app')] #[Title('Gerenciar Setores')] class extends Component
{
    public $name = '';
    public $description = '';
    public $manager_id = '';
    
    public $showModal = false;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:sectors,name',
            'description' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        Sector::create([
            'name' => $this->name,
            'description' => $this->description,
            'manager_id' => $this->manager_id ?: null,
        ]);

        $this->reset(['name', 'description', 'manager_id']);
        $this->showModal = false;
        
        session()->flash('message', 'Setor criado com sucesso.');
    }

    public function delete($id)
    {
        Sector::findOrFail($id)->delete();
        session()->flash('message', 'Setor removido.');
    }

    public function with()
    {
        return [
            'sectors' => Sector::with('manager')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get()
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gerenciar Setores</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Departamentos e áreas organizacionais</p>
            </div>
            <button wire:click="$set('showModal', true)" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Adicionar Setor
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
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Descrição</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Responsável</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sectors as $sector)
                        <tr wire:key="{{ $sector->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $sector->id }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $sector->name }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $sector->description ?: '-' }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($sector->manager)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $sector->manager->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Não definido</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="delete({{ $sector->id }})" wire:confirm="Tem certeza que deseja excluir o setor {{ $sector->name }}?" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum setor cadastrado.<br>
                                <button wire:click="$set('showModal', true)" class="mt-2 text-indigo-600 hover:text-indigo-900 font-semibold">Clique aqui para adicionar o primeiro setor.</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar Setor (Mobile Friendly) -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                        Cadastrar Novo Setor
                    </h3>
                    <div class="mt-4">
                        <form wire:submit="save" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome do Setor</label>
                                <input type="text" wire:model="name" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="Ex: Tecnologia da Informação">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Descrição <span class="text-gray-400 font-normal lowercase">(opcional)</span></label>
                                <textarea wire:model="description" rows="2" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Breve resumo sobre o departamento"></textarea>
                                @error('description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Responsável do Setor <span class="text-gray-400 font-normal lowercase">(opcional)</span></label>
                                <select wire:model="manager_id" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                    <option value="">Selecione um responsável</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                                @error('manager_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2.5 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                                    <span wire:loading.remove wire:target="save">Salvar Setor</span>
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