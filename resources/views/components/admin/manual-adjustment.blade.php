<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeEntry;
use App\Models\TimeAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('layouts.app')] #[Title('Ajuste Manual de Ponto')] class extends Component
{
    public $userId = '';
    public $date = '';
    public $time = '';
    public $type = 'in';
    public $justification = '';
    
    public $message = '';
    public $status = '';
    
    public function mount()
    {
        $this->date = now()->format('Y-m-d');
        $this->time = now()->format('H:i');
    }
    
    public function with()
    {
        return [
            'users' => User::orderBy('name')->get()
        ];
    }
    
    public function saveAdjustment()
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'type' => 'required|in:in,out',
            'justification' => 'required|string|min:5|max:255',
        ]);
        
        $newTimestamp = Carbon::parse($this->date . ' ' . $this->time);
        
        // Criar o apontamento
        $entry = TimeEntry::create([
            'user_id' => $this->userId,
            'timestamp' => $newTimestamp,
            'type' => $this->type,
            'is_manual' => true,
        ]);
        
        // Registrar na trilha de auditoria
        TimeAdjustment::create([
            'time_entry_id' => $entry->id,
            'adjusted_by' => Auth::id(),
            'old_timestamp' => null, // Como é criação retroativa, não tinha horário antigo
            'new_timestamp' => $newTimestamp,
            'justification' => $this->justification,
        ]);
        
        $this->message = "Ajuste manual criado com sucesso e registrado na trilha de auditoria.";
        $this->status = 'success';
        
        // Limpar o formulário (menos data e hora para facilitar cadastros sequenciais)
        $this->justification = '';
    }
};
?>

<div class="max-w-3xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="text-2xl font-bold text-gray-900">Ajuste Manual de Ponto (RH)</h2>
            <a href="{{ route('timesheet') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Ver Espelho</a>
        </div>
        
        @if($message)
            <div class="mb-6 rounded-md p-4 {{ $status === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                <p class="text-sm font-medium">{{ $message }}</p>
            </div>
        @endif
        
        <form wire:submit="saveAdjustment" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Funcionário</label>
                    <select wire:model="userId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm border" required>
                        <option value="">Selecione um funcionário</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('userId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo de Batida</label>
                    <select wire:model="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm border" required>
                        <option value="in">Entrada</option>
                        <option value="out">Saída</option>
                    </select>
                    @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data</label>
                    <input type="date" wire:model="date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    @error('date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Horário</label>
                    <input type="time" wire:model="time" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    @error('time') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Justificativa da Auditoria</label>
                <textarea wire:model="justification" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Ex: Esquecimento, Falta de bateria no celular, etc." required></textarea>
                <p class="mt-2 text-sm text-gray-500">Esta justificativa ficará permanentemente gravada na trilha de auditoria, associada ao seu usuário ({{ Auth::user()->name }}).</p>
                @error('justification') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" data-loading style="display: none;">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span data-loading-remove>Salvar Ajuste Manual</span>
                    <span data-loading style="display: none;">Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>