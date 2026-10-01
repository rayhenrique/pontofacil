<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sector;

new #[Layout('layouts.app')] #[Title('Gerenciar Setores')] class extends Component
{
    public $name = '';
    public $description = '';

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:sectors,name',
            'description' => 'nullable|string|max:255',
        ]);

        Sector::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'description']);
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
            'sectors' => Sector::orderBy('name')->get()
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Gerenciar Setores</h2>
        
        @if (session()->has('message'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-green-800 text-sm">
                {{ session('message') }}
            </div>
        @endif

        <form wire:submit="save" class="mb-8 flex gap-4 items-end border-b pb-8">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Nome do Setor</label>
                <input type="text" wire:model="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Descrição</label>
                <input type="text" wire:model="description" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium shadow-sm">
                    Adicionar
                </button>
            </div>
        </form>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($sectors as $sector)
                    <tr wire:key="{{ $sector->id }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $sector->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $sector->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $sector->description }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:click="delete({{ $sector->id }})" wire:confirm="Tem certeza?" class="text-red-600 hover:text-red-900">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Nenhum setor cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>