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
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Ajuste Manual Realizado!',
            'message' => 'O apontamento retroativo foi inserido com sucesso e registrado na trilha de auditoria (Portaria 671).',
            'buttonText' => 'Entendido'
        ]);
        
        // Limpar o formulário (menos data e hora para facilitar cadastros sequenciais)
        $this->justification = '';
    }
};
?>

<div class="max-w-3xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Ajuste Manual de Ponto</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Correção de batidas esquecidas com justificativa</p>
            </div>
            <a href="{{ route('timesheet') }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                Ver Espelho
            </a>
        </div>
        
        @if($message)
            <div class="mb-6 rounded-xl p-4 {{ $status === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' }}">
                <p class="text-sm font-semibold">{{ $message }}</p>
            </div>
        @endif
        
        <form wire:submit="saveAdjustment" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Colaborador</label>
                    <select wire:model="userId" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                        <option value="">Selecione um funcionário</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('userId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Tipo de Batida</label>
                    <select wire:model="type" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                        <option value="in">Entrada</option>
                        <option value="out">Saída</option>
                    </select>
                    @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Horário</label>
                    <input type="time" wire:model="time" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                    @error('time') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Data</label>
                    <input type="date" wire:model="date" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                    @error('date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Justificativa da Auditoria</label>
                <textarea wire:model="justification" rows="3" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Ex: Esquecimento, bateria descarregada, etc." required></textarea>
                <p class="mt-1.5 text-xs text-gray-500">Esta justificativa será salva permanentemente na trilha de auditoria sob sua responsabilidade ({{ Auth::user()->name }}).</p>
                @error('justification') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            
            <div class="flex justify-end pt-3 border-t">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                    <span wire:loading.remove wire:target="saveAdjustment">Salvar Ajuste Manual</span>
                    <span wire:loading wire:target="saveAdjustment">Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>