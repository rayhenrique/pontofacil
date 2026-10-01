<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Gerenciar Setores')] class extends Component
{
    public $sectorId = null;
    public $name = '';
    public $description = '';
    public $manager_id = '';
    public $latitude = '';
    public $longitude = '';
    public $allowed_radius_meters = '';
    public $qr_code_hash = '';
    
    public $showModal = false;
    public $showQrModal = false;
    public $selectedQrHash = '';
    public $selectedSectorName = '';

    public function openCreateModal()
    {
        $this->reset([
            'sectorId', 'name', 'description', 'manager_id',
            'latitude', 'longitude', 'allowed_radius_meters', 'qr_code_hash'
        ]);
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $sector = Sector::findOrFail($id);
        $this->sectorId = $sector->id;
        $this->name = $sector->name;
        $this->description = $sector->description ?? '';
        $this->manager_id = $sector->manager_id ?? '';
        $this->latitude = $sector->latitude !== null ? (string)$sector->latitude : '';
        $this->longitude = $sector->longitude !== null ? (string)$sector->longitude : '';
        $this->allowed_radius_meters = $sector->allowed_radius_meters !== null ? (string)$sector->allowed_radius_meters : '';
        $this->qr_code_hash = $sector->qr_code_hash ?? '';
        $this->showModal = true;
    }

    public function generateSectorQrCode()
    {
        $this->qr_code_hash = Str::random(40);
    }

    public function clearSectorQrCode()
    {
        $this->qr_code_hash = '';
    }

    public function clearSectorLocation()
    {
        $this->latitude = '';
        $this->longitude = '';
        $this->allowed_radius_meters = '';
    }

    public function openQrModal($id)
    {
        $sector = Sector::findOrFail($id);
        $this->selectedSectorName = $sector->name;
        $this->selectedQrHash = $sector->qr_code_hash ?? '';
        $this->showQrModal = true;
        $this->dispatch('render-sector-qr', hash: $this->selectedQrHash);
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sectors', 'name')->ignore($this->sectorId)],
            'description' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'allowed_radius_meters' => 'nullable|integer|min:10|max:10000',
            'qr_code_hash' => 'nullable|string|max:255',
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'manager_id' => $this->manager_id ?: null,
            'latitude' => ($this->latitude !== '' && $this->latitude !== null) ? $this->latitude : null,
            'longitude' => ($this->longitude !== '' && $this->longitude !== null) ? $this->longitude : null,
            'allowed_radius_meters' => ($this->allowed_radius_meters !== '' && $this->allowed_radius_meters !== null) ? (int)$this->allowed_radius_meters : null,
            'qr_code_hash' => $this->qr_code_hash ?: null,
        ];

        if ($this->sectorId) {
            Sector::findOrFail($this->sectorId)->update($data);
            session()->flash('message', 'Setor atualizado com sucesso.');
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Setor Atualizado!',
                'message' => 'As informações e regras do setor foram atualizadas com sucesso.'
            ]);
        } else {
            Sector::create($data);
            session()->flash('message', 'Setor criado com sucesso.');
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Setor Cadastrado!',
                'message' => 'O novo setor foi registrado com sucesso.'
            ]);
        }

        $this->reset([
            'sectorId', 'name', 'description', 'manager_id',
            'latitude', 'longitude', 'allowed_radius_meters', 'qr_code_hash'
        ]);
        $this->showModal = false;
    }

    public function delete($id)
    {
        Sector::findOrFail($id)->delete();
        session()->flash('message', 'Setor removido.');
        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Setor Removido',
            'message' => 'O setor foi excluído com sucesso.'
        ]);
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

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8" x-data="sectorAdminComponent()" x-on:render-sector-qr.window="renderModalQrCode($event.detail.hash)">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gerenciar Setores</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Departamentos, áreas organizacionais e regras híbridas de ponto (GPS e QR Code)</p>
            </div>
            <button wire:click="openCreateModal" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
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
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Regra de Validação</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Responsável</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sectors as $sector)
                        <tr wire:key="{{ $sector->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $sector->id }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">{{ $sector->name }}</div>
                                @if($sector->description)
                                    <div class="text-xs text-gray-500 truncate max-w-xs">{{ $sector->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-wrap gap-1.5 items-center">
                                    @if($sector->hasCustomQrCode())
                                        <button wire:click="openQrModal({{ $sector->id }})" title="Clique para exibir ou imprimir o QR Code exclusivo" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 transition">
                                            <svg class="w-3.5 h-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                                            </svg>
                                            QR Setor
                                        </button>
                                    @endif

                                    @if($sector->hasCustomLocation())
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200" title="Latitude: {{ $sector->latitude }}, Longitude: {{ $sector->longitude }} (Raio: {{ $sector->allowed_radius_meters ?? 'Global' }}m)">
                                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                            </svg>
                                            GPS Setor ({{ $sector->allowed_radius_meters ? $sector->allowed_radius_meters . 'm' : 'Padrao' }})
                                        </span>
                                    @endif

                                    @if(!$sector->hasCustomQrCode() && !$sector->hasCustomLocation())
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                            Matriz (Padrão Global)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($sector->manager)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $sector->manager->name }} ({{ $sector->manager->role->label() }})
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Não definido</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($sector->hasCustomQrCode())
                                    <button wire:click="openQrModal({{ $sector->id }})" class="text-purple-600 hover:text-purple-900 font-medium mr-3">Ver QR</button>
                                @endif
                                <button wire:click="openEditModal({{ $sector->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium mr-3">Editar</button>
                                <button type="button" @click="confirmDeleteSector({{ $sector->id }}, '{{ addslashes($sector->name) }}')" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum setor cadastrado.<br>
                                <button wire:click="openCreateModal" class="mt-2 text-indigo-600 hover:text-indigo-900 font-semibold">Clique aqui para adicionar o primeiro setor.</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Setor (Mobile & Desktop Friendly) -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
        <!-- Backdrop overlay -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8">
                <div>
                    <h3 class="text-lg leading-6 font-bold text-gray-900 border-b pb-3" id="modal-title">
                        {{ $sectorId ? 'Editar Setor' : 'Cadastrar Novo Setor' }}
                    </h3>
                    <div class="mt-4">
                        <form wire:submit="save" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome do Setor</label>
                                <input type="text" wire:model="name" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required placeholder="Ex: Tecnologia da Informação ou Filial Ponta Verde">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Descrição <span class="text-gray-400 font-normal lowercase">(opcional)</span></label>
                                <textarea wire:model="description" rows="2" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Breve resumo sobre o departamento ou filial"></textarea>
                                @error('description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Responsável do Setor <span class="text-gray-400 font-normal lowercase">(opcional)</span></label>
                                <select wire:model="manager_id" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                    <option value="">Selecione um responsável</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }})</option>
                                    @endforeach
                                </select>
                                @error('manager_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Seção de Estrutura Híbrida Inteligente (Fallback) -->
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-4 mt-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <h4 class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        Regras de Ponto do Setor (Estrutura Híbrida)
                                    </h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 w-fit">Fallback Inteligente</span>
                                </div>
                                <p class="text-xs text-gray-600 leading-relaxed">
                                    Deixe em branco para usar automaticamente o <strong>QR Code e GPS globais da empresa</strong>. Caso preencha, o sistema validará este setor conforme suas coordenadas e/ou QR Code próprios.
                                </p>

                                <!-- QR Code do Setor -->
                                <div class="border-t border-indigo-100 pt-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            QR Code Próprio do Setor <span class="text-gray-400 font-normal lowercase">(opcional)</span>
                                        </label>
                                        @if($qr_code_hash)
                                            <span class="text-[11px] text-purple-700 font-semibold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">QR Code Ativo</span>
                                        @else
                                            <span class="text-[11px] text-gray-500 italic">Usando QR Code global da empresa</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-2 items-center">
                                        <input type="text" wire:model="qr_code_hash" readonly
                                            placeholder="Deixe em branco para usar o QR da Empresa"
                                            class="block w-full px-3 py-2 text-xs font-mono bg-white border border-gray-300 rounded-xl text-gray-700 focus:outline-hidden">
                                        <div class="flex gap-2 w-full sm:w-auto">
                                            <button type="button" wire:click="generateSectorQrCode"
                                                class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold whitespace-nowrap shadow-2xs transition">
                                                Gerar Próprio
                                            </button>
                                            @if($qr_code_hash)
                                                <button type="button" wire:click="clearSectorQrCode"
                                                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-xs font-semibold whitespace-nowrap transition">
                                                    Usar Global
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    @error('qr_code_hash') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Coordenadas GPS do Setor -->
                                <div class="border-t border-indigo-100 pt-3 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Geolocalização do Setor (GPS) <span class="text-gray-400 font-normal lowercase">(opcional)</span>
                                        </label>
                                        @if($latitude || $longitude || $allowed_radius_meters)
                                            <button type="button" wire:click="clearSectorLocation"
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                Limpar GPS (Usar Matriz)
                                            </button>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Latitude</label>
                                            <input type="text" wire:model="latitude" placeholder="Ex: -9.665800"
                                                class="block w-full px-3 py-2 text-xs font-mono bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                            @error('latitude') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Longitude</label>
                                            <input type="text" wire:model="longitude" placeholder="Ex: -35.735000"
                                                class="block w-full px-3 py-2 text-xs font-mono bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                            @error('longitude') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Raio (metros)</label>
                                            <input type="number" wire:model="allowed_radius_meters" min="10" max="10000" placeholder="Padrão: Empresa"
                                                class="block w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                            @error('allowed_radius_meters') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <button type="button" @click="detectLocation()"
                                        class="w-full inline-flex items-center justify-center px-3 py-2 bg-white hover:bg-gray-50 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-semibold shadow-2xs transition">
                                        <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        Capturar Minha Posição GPS Atual para este Setor
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t pt-4">
                                <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 px-4 py-2.5 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl px-5 py-2.5 bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                                    <span wire:loading.remove wire:target="save">{{ $sectorId ? 'Atualizar Setor' : 'Salvar Setor' }}</span>
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

    <!-- Modal de Visualização e Impressão do QR Code do Setor -->
    @if($showQrModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="qr-modal-title" role="dialog" aria-modal="true" wire:transition>
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="$set('showQrModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-center shadow-2xl transition-all sm:my-8">
                <h3 class="text-lg font-bold text-gray-900 mb-1" id="qr-modal-title">
                    QR Code do Setor: {{ $selectedSectorName }}
                </h3>
                <p class="text-xs text-gray-500 mb-4">Fixe este QR Code no departamento ou filial para registro de ponto exclusivo deste setor.</p>
                
                <div id="sector-print-area" class="flex flex-col items-center p-6 bg-gray-50 border border-gray-200 rounded-2xl text-center mb-5">
                    <div class="mb-3">
                        <span class="text-sm font-bold text-gray-900 tracking-tight">PontoFácil • {{ $selectedSectorName }}</span>
                        <p class="text-xs text-gray-500">Ponto Eletrônico por Setor</p>
                    </div>
                    
                    <canvas id="sector-qr-canvas" class="rounded-xl shadow-xs bg-white p-3 border border-gray-200"></canvas>
                    
                    <div class="mt-4 max-w-[260px]">
                        <p class="text-xs text-gray-400 font-mono break-all">{{ $selectedQrHash }}</p>
                        <p class="text-xs text-indigo-700 font-semibold mt-2">Exclusivo para colaboradores deste setor</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 justify-end">
                    <button type="button" wire:click="$set('showQrModal', false)" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition">
                        Fechar
                    </button>
                    <button type="button" @click="printSectorQr()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.672-2.152-1.34-3.08-1.528-2.122-4.004-3.52-6.88-3.749m12.35 15.029v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 15.75v6.75m15 0v-6.75a2.25 2.25 0 0 0-2.25-2.25H16.5m3.75 9H3.75" />
                        </svg>
                        Imprimir QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sectorAdminComponent', () => ({
                confirmDeleteSector(id, name) {
                    window.showModalConfirm({
                        title: 'Excluir Setor',
                        message: `Tem certeza que deseja excluir o setor "${name}"? Esta ação removerá o setor do sistema.`,
                        confirmText: 'Sim, Excluir',
                        cancelText: 'Cancelar',
                        isDanger: true,
                        onConfirm: () => {
                            @this.call('delete', id);
                        }
                    });
                },

                detectLocation() {
                    if (!navigator.geolocation) {
                        window.showModalAlert('warning', 'Geolocalização Indisponível', 'Geolocalização não é suportada pelo seu navegador.');
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            @this.set('latitude', pos.coords.latitude.toFixed(6));
                            @this.set('longitude', pos.coords.longitude.toFixed(6));
                            if (!@this.get('allowed_radius_meters')) {
                                @this.set('allowed_radius_meters', 100);
                            }
                            window.showModalAlert('success', 'Localização Capturada!', 'As coordenadas GPS do setor foram capturadas com sucesso.');
                        },
                        (err) => {
                            window.showModalAlert('error', 'Falha ao Obter Localização', 'Não foi possível obter a localização: ' + err.message);
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                },

                renderModalQrCode(hash) {
                    this.$nextTick(() => {
                        const canvas = document.getElementById('sector-qr-canvas');
                        if (!canvas || !window.QRCode || !hash) return;
                        window.QRCode.toCanvas(canvas, hash, {
                            width: 220,
                            margin: 1,
                            color: {
                                dark: '#1e1b4b',
                                light: '#ffffff'
                            }
                        }, (error) => {
                            if (error) console.error('Erro ao renderizar QR Code do setor:', error);
                        });
                    });
                },

                printSectorQr() {
                    window.print();
                }
            }));
        });
    </script>
</div>