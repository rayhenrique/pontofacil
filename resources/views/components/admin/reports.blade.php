<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeEntry;
use App\Models\Sector;
use App\Models\Employee;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] #[Title('Relatórios Gerenciais')] class extends Component
{
    public $startDate;
    public $endDate;
    public $sectorId = '';
    public $employeeId = '';
    
    public $results = [];
    public $hasSearched = false;

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    public function generateReport()
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $query = TimeEntry::with(['user.employee.sector'])
            ->whereDate('timestamp', '>=', $this->startDate)
            ->whereDate('timestamp', '<=', $this->endDate);

        if (!empty($this->sectorId)) {
            $query->whereHas('user.employee', function($q) {
                $q->where('sector_id', $this->sectorId);
            });
        }

        if (!empty($this->employeeId)) {
            $query->whereHas('user.employee', function($q) {
                $q->where('id', $this->employeeId);
            });
        }

        $this->results = $query->orderBy('timestamp', 'asc')->get();
        $this->hasSearched = true;
    }

    public function with()
    {
        return [
            'sectors' => Sector::orderBy('name')->get(),
            'employees' => Employee::with('user')->get()->sortBy('user.name')
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Relatórios de Ponto</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Filtros avançados e conferência de jornadas</p>
            </div>
            <a href="{{ route('folha-ponto') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs sm:text-sm font-bold shadow-2xs transition">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span>Folha de Ponto Oficial (Modelo Prefeitura)</span>
            </a>
        </div>
        
        <form wire:submit="generateReport" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Data Inicial</label>
                    <input type="date" wire:model="startDate" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Data Final</label>
                    <input type="date" wire:model="endDate" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Setor</label>
                    <select wire:model="sectorId" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Todos os Setores</option>
                        @foreach($sectors as $sector)
                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Funcionário</label>
                    <select wire:model="employeeId" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Todos os Funcionários</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ optional($employee->user)->name ?? 'S/ Nome' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end pt-3 border-t mt-4">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 text-sm font-semibold shadow-sm transition">
                    <span wire:loading.remove wire:target="generateReport" class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Gerar Relatório
                    </span>
                    <span wire:loading wire:target="generateReport">Processando...</span>
                </button>
            </div>
        </form>
    </div>

    @if($hasSearched)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-gray-50">
            <h3 class="text-base font-bold text-gray-900">Resultados da Pesquisa</h3>
            <span class="inline-flex items-center self-start sm:self-auto px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                {{ count($results) }} registros encontrados
            </span>
        </div>
        
        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Data / Hora</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Funcionário</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Setor</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Origem</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($results as $entry)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono font-bold">
                                {{ Carbon::parse($entry->timestamp)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800">
                                {{ optional($entry->user)->name ?? 'Desconhecido' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ optional(optional(optional($entry->user)->employee)->sector)->name ?? '-' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold {{ $entry->type === 'in' || $entry->type === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $entry->type === 'in' || $entry->type === 'entrada' ? 'Entrada' : 'Saída' }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($entry->is_manual)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-800">
                                        Manual (RH)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                        QR Code + GPS
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">
                                Nenhum registro encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>