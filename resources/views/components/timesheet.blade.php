<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Enums\UserRole;

new #[Layout('layouts.app')] #[Title('Espelho de Ponto')] class extends Component
{
    public $month;
    public $year;
    public $userId;
    
    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;
        $this->userId = Auth::id();
    }

    public function with()
    {
        $isAdmin = Auth::user()->role === UserRole::Admin;
        
        // If not admin, force user ID to self
        if (!$isAdmin) {
            $this->userId = Auth::id();
        }

        $query = TimeEntry::with('user')
            ->where('user_id', $this->userId)
            ->whereYear('timestamp', $this->year)
            ->whereMonth('timestamp', $this->month)
            ->orderBy('timestamp', 'asc');

        $entries = $query->get();

        // Agrupar por dia
        $groupedEntries = $entries->groupBy(function($entry) {
            return Carbon::parse($entry->timestamp)->format('Y-m-d');
        });

        return [
            'groupedEntries' => $groupedEntries,
            'isAdmin' => $isAdmin,
            'users' => $isAdmin ? User::orderBy('name')->get() : collect([]),
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="text-2xl font-bold text-gray-900">Espelho de Ponto</h2>
            <a href="{{ route('home') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Voltar para Batida</a>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 pb-6 border-b border-gray-200">
            @if($isAdmin)
            <div>
                <label class="block text-sm font-medium text-gray-700">Funcionário</label>
                <select wire:model.live="userId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Mês</label>
                <select wire:model.live="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Ano</label>
                <select wire:model.live="year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    @for($i = now()->year - 2; $i <= now()->year; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- Timesheet Data -->
        <div class="space-y-4" data-loading-class="opacity-50" wire:transition>
            @forelse($groupedEntries as $date => $dayEntries)
                <div class="border rounded-md overflow-hidden" wire:key="day-{{ $date }}">
                    <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($date)->isoFormat('LL') }}
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        @foreach($dayEntries as $entry)
                            <li class="px-4 py-3 flex justify-between items-center" wire:key="entry-{{ $entry->id }}">
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $entry->type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $entry->type === 'in' ? 'Entrada' : 'Saída' }}
                                    </span>
                                    <span class="ml-2 text-sm text-gray-900 font-semibold">
                                        {{ \Carbon\Carbon::parse($entry->timestamp)->format('H:i') }}
                                    </span>
                                    @if($entry->is_manual)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Ajuste Manual
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 text-right">
                                    Lat: {{ number_format($entry->latitude, 4) }}, Lng: {{ number_format($entry->longitude, 4) }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500" wire:key="empty-state">
                    Nenhum registro encontrado para este período.
                </div>
            @endforelse
        </div>
    </div>
</div>