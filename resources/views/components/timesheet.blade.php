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

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Espelho de Ponto</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Histórico completo de registros de jornada</p>
            </div>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Bater Ponto
            </a>
        </div>

        <!-- Filters (Responsive Mobile Stack) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6 pb-6 border-b border-gray-200">
            @if($isAdmin)
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Funcionário</label>
                <select wire:model.live="userId" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Mês</label>
                <select wire:model.live="month" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ sprintf('%02d', $i) }} - {{ Carbon::create(null, $i, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Ano</label>
                <select wire:model.live="year" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = now()->year - 2; $i <= now()->year; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- Timesheet Data -->
        <div class="space-y-4" data-loading-class="opacity-50" wire:transition>
            @forelse($groupedEntries as $date => $dayEntries)
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs" wire:key="day-{{ $date }}">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-800 capitalize flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            {{ Carbon::parse($date)->isoFormat('dddd, LL') }}
                        </h3>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                            {{ count($dayEntries) }} {{ count($dayEntries) === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($dayEntries as $entry)
                            <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-gray-50 transition" wire:key="entry-{{ $entry->id }}">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $entry->type === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $entry->type === 'in' ? 'Entrada' : 'Saída' }}
                                    </span>
                                    <span class="text-base text-gray-900 font-mono font-bold">
                                        {{ Carbon::parse($entry->timestamp)->format('H:i:s') }}
                                    </span>
                                    @if($entry->is_manual)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-800">
                                            Ajuste Manual
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 font-mono flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                    <span>Lat: {{ number_format($entry->latitude, 4) }}, Lng: {{ number_format($entry->longitude, 4) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500 bg-gray-50 rounded-xl" wire:key="empty-state">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <p class="text-sm font-medium text-gray-600">Nenhum registro encontrado para este período.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>