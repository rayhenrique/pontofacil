<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\CalendarEvent;
use App\Models\Establishment;
use App\Enums\CalendarEventType;
use App\Enums\CalendarEventScope;
use App\Enums\WorkBehavior;
use App\Domain\Calendar\Services\BrazilianHolidaysService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Calendário Laboral — Feriados & Pontos Facultativos')] class extends Component
{
    public $year;
    public $month;
    public $view = 'month'; // 'month' or 'list'
    public $filterType = '';
    public $filterEstablishment = '';

    // Modal de criação/edição
    public $showEventModal = false;
    public $editingEventId = null;
    public $eventName = '';
    public $eventDate = '';
    public $eventType = 'holiday';
    public $eventScope = 'national';
    public $eventState = '';
    public $eventCity = '';
    public $eventEstablishmentId = '';
    public $eventWorkBehavior = 'no_work_expected';
    public $eventAllDay = true;
    public $eventStartsAt = '';
    public $eventEndsAt = '';
    public $eventRequiresCompensation = false;
    public $eventLegalReference = '';
    public $eventNotes = '';

    // Modal de importação
    public $showImportModal = false;
    public $importYear = '';

    // Modal de sugestões (pontos facultativos)
    public $showSuggestionsModal = false;
    public $suggestions = [];
    public $selectedSuggestions = [];

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->importYear = now()->year;
    }

    public function previousMonth()
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth()
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function openCreateModal(?string $date = null)
    {
        $this->resetEventForm();
        $this->eventDate = $date ?? Carbon::createFromDate($this->year, $this->month, 1)->format('Y-m-d');
        $this->showEventModal = true;
    }

    public function openEditModal(string $eventId)
    {
        $event = CalendarEvent::find($eventId);
        if (! $event) {
            return;
        }

        $this->editingEventId = $event->id;
        $this->eventName = $event->name;
        $this->eventDate = $event->event_date->format('Y-m-d');
        $this->eventType = $event->type->value;
        $this->eventScope = $event->scope->value;
        $this->eventState = $event->state ?? '';
        $this->eventCity = $event->city ?? '';
        $this->eventEstablishmentId = $event->establishment_id ? (string) $event->establishment_id : '';
        $this->eventWorkBehavior = $event->work_behavior->value;
        $this->eventAllDay = $event->all_day;
        $this->eventStartsAt = $event->starts_at ?? '';
        $this->eventEndsAt = $event->ends_at ?? '';
        $this->eventRequiresCompensation = $event->requires_compensation;
        $this->eventLegalReference = $event->legal_reference ?? '';
        $this->eventNotes = $event->notes ?? '';
        $this->showEventModal = true;
    }

    public function saveEvent()
    {
        $this->validate([
            'eventName' => 'required|min:3',
            'eventDate' => 'required|date',
            'eventType' => 'required',
            'eventScope' => 'required',
            'eventWorkBehavior' => 'required',
        ]);

        $data = [
            'name' => $this->eventName,
            'event_date' => $this->eventDate,
            'type' => $this->eventType,
            'scope' => $this->eventScope,
            'state' => in_array($this->eventScope, ['state', 'municipal']) ? ($this->eventState ?: null) : null,
            'city' => $this->eventScope === 'municipal' ? ($this->eventCity ?: null) : null,
            'establishment_id' => $this->eventScope === 'establishment' && $this->eventEstablishmentId ? (int) $this->eventEstablishmentId : null,
            'work_behavior' => $this->eventWorkBehavior,
            'all_day' => $this->eventAllDay,
            'starts_at' => ! $this->eventAllDay ? ($this->eventStartsAt ?: null) : null,
            'ends_at' => ! $this->eventAllDay ? ($this->eventEndsAt ?: null) : null,
            'requires_compensation' => $this->eventRequiresCompensation,
            'legal_reference' => $this->eventLegalReference ?: null,
            'notes' => $this->eventNotes ?: null,
            'created_by' => Auth::id(),
        ];

        if ($this->editingEventId) {
            CalendarEvent::find($this->editingEventId)?->update($data);
        } else {
            CalendarEvent::create($data);
        }

        $this->showEventModal = false;
        $this->resetEventForm();
    }

    public function toggleActive(string $eventId)
    {
        $event = CalendarEvent::find($eventId);
        if ($event) {
            $event->update(['active' => ! $event->active]);
        }
    }

    public function deleteEvent(string $eventId)
    {
        CalendarEvent::find($eventId)?->delete();
    }

    public function openImportModal()
    {
        $this->importYear = $this->year;
        $this->showImportModal = true;
    }

    public function importNationalHolidays()
    {
        $service = new BrazilianHolidaysService;
        $count = $service->importNationalHolidays((int) $this->importYear, Auth::id());

        $this->showImportModal = false;
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Feriados Importados',
            'message' => sprintf('%d feriados nacionais legais importados para %d. Pontos facultativos NÃO foram importados — cadastre-os manualmente conforme decisão do RH.', $count, $this->importYear),
            'buttonText' => 'OK'
        ]);
    }

    public function openSuggestionsModal()
    {
        $service = new BrazilianHolidaysService;
        $this->suggestions = $service->getSuggestedOptionalDaysForYear((int) $this->year);
        $this->selectedSuggestions = [];
        $this->showSuggestionsModal = true;
    }

    public function toggleSelectAllSuggestions()
    {
        if (count($this->selectedSuggestions) === count($this->suggestions)) {
            $this->selectedSuggestions = [];
        } else {
            $this->selectedSuggestions = array_map('strval', array_keys($this->suggestions));
        }
    }

    public function importSelectedSuggestions()
    {
        $importedCount = 0;
        foreach ($this->selectedSuggestions as $index) {
            $idx = (int) $index;
            if (isset($this->suggestions[$idx])) {
                $suggestion = $this->suggestions[$idx];

                CalendarEvent::updateOrCreate(
                    [
                        'event_date' => $suggestion['date'],
                        'name' => $suggestion['name'],
                        'scope' => CalendarEventScope::National,
                    ],
                    [
                        'type' => CalendarEventType::OptionalDay,
                        'work_behavior' => WorkBehavior::OptionalNoWork,
                        'all_day' => true,
                        'active' => true,
                        'notes' => $suggestion['note'],
                        'created_by' => Auth::id(),
                    ]
                );
                $importedCount++;
            }
        }

        $this->showSuggestionsModal = false;
        $this->selectedSuggestions = [];
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Pontos Facultativos Importados',
            'message' => sprintf('%d ponto(s) facultativo(s) importado(s). Revise o comportamento (work_behavior) de cada um na lista.', $importedCount),
            'buttonText' => 'OK'
        ]);
    }

    private function resetEventForm()
    {
        $this->editingEventId = null;
        $this->eventName = '';
        $this->eventDate = '';
        $this->eventType = 'holiday';
        $this->eventScope = 'national';
        $this->eventState = '';
        $this->eventCity = '';
        $this->eventEstablishmentId = '';
        $this->eventWorkBehavior = 'no_work_expected';
        $this->eventAllDay = true;
        $this->eventStartsAt = '';
        $this->eventEndsAt = '';
        $this->eventRequiresCompensation = false;
        $this->eventLegalReference = '';
        $this->eventNotes = '';
    }

    public function rendering($view)
    {
        $query = CalendarEvent::whereYear('event_date', $this->year)
            ->whereMonth('event_date', $this->month)
            ->orderBy('event_date');

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->filterEstablishment) {
            $estId = (int) $this->filterEstablishment;
            $query->where(function ($q) use ($estId) {
                $q->whereNull('establishment_id')
                    ->orWhere('establishment_id', $estId);
            });
        }

        $events = $query->get();

        // Build calendar grid
        $startDate = Carbon::createFromDate($this->year, $this->month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $endDate->day;

        // Pad start to Monday (1) by default
        $startDow = $startDate->dayOfWeek; // 0=Sun
        $startDow = $startDow === 0 ? 6 : $startDow - 1; // Convert to Mon=0

        $calendarDays = [];
        for ($i = 0; $i < $startDow; $i++) {
            $calendarDays[] = null;
        }
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $this->year, $this->month, $d);
            $dayEvents = $events->filter(fn ($e) => $e->event_date->format('Y-m-d') === $dateStr);
            $calendarDays[] = ['day' => $d, 'date' => $dateStr, 'events' => $dayEvents];
        }

        $establishments = Establishment::all();
        $monthName = $startDate->translatedFormat('F Y');

        $view->with(compact('events', 'calendarDays', 'establishments', 'monthName'));
    }
}

?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Calendário Laboral</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Portaria 671/2021
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Feriados, Pontos Facultativos e Dias Especiais — Resolução Territorial e Motor PTRP</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="openImportModal"
                    type="button"
                    class="inline-flex items-center px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Importar Feriados
            </button>

            <button wire:click="openSuggestionsModal"
                    type="button"
                    class="inline-flex items-center px-3.5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-xs gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
                Sugestões Facultativos
            </button>

            <button wire:click="openCreateModal"
                    type="button"
                    class="inline-flex items-center px-3.5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-xs gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Novo Evento
            </button>
        </div>
    </div>

    {{-- Filtros e navegação --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <button wire:click="previousMonth" 
                    type="button"
                    class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition cursor-pointer"
                    title="Mês anterior">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>
            <span class="text-base sm:text-lg font-bold text-gray-900 min-w-[200px] text-center capitalize tracking-tight">
                {{ $monthName }}
            </span>
            <button wire:click="nextMonth" 
                    type="button"
                    class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition cursor-pointer"
                    title="Próximo mês">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <div>
                <select wire:model.live="filterType" class="px-3.5 py-2 text-xs font-semibold border border-gray-300 rounded-xl bg-white text-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Todos os tipos de evento</option>
                    @foreach(App\Enums\CalendarEventType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="filterEstablishment" class="px-3.5 py-2 text-xs font-semibold border border-gray-300 rounded-xl bg-white text-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Todos os estabelecimentos</option>
                    @foreach($establishments as $est)
                        <option value="{{ $est->id }}">{{ $est->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Legenda --}}
    <div class="flex flex-wrap items-center gap-2 px-1">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider mr-1">Legenda:</span>
        @foreach(App\Enums\CalendarEventType::cases() as $type)
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg {{ $type->badgeClass() }}">
                <span class="w-2 h-2 rounded-full {{ $type->dotColor() }}"></span>
                {{ $type->label() }}
            </span>
        @endforeach
    </div>

    {{-- Grade do Calendário --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="grid grid-cols-7 bg-gray-50/80 border-b border-gray-200/80">
            @foreach(['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'] as $dayName)
                <div class="py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                    <span class="hidden sm:inline">{{ $dayName }}</span>
                    <span class="sm:hidden">{{ mb_substr($dayName, 0, 3) }}</span>
                </div>
            @endforeach
        </div>
        <div class="grid grid-cols-7 divide-x divide-y divide-gray-200/70">
            @foreach($calendarDays as $cell)
                @if($cell === null)
                    <div class="min-h-[105px] sm:min-h-[115px] bg-gray-50/40"></div>
                @else
                    @php
                        $isToday = $cell['date'] === now()->format('Y-m-d');
                        $hasEvents = $cell['events']->isNotEmpty();
                    @endphp
                    <div class="min-h-[105px] sm:min-h-[115px] p-2 bg-white hover:bg-indigo-50/30 transition cursor-pointer group relative flex flex-col justify-between"
                         wire:click="openCreateModal('{{ $cell['date'] }}')">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 text-xs font-bold rounded-lg transition {{ $isToday ? 'bg-indigo-600 text-white shadow-xs' : ($hasEvents ? 'text-gray-900 font-extrabold bg-gray-100' : 'text-gray-600 group-hover:text-indigo-600') }}">
                                {{ $cell['day'] }}
                            </span>
                            @if($hasEvents)
                                <div class="flex items-center gap-1">
                                    @foreach($cell['events']->unique('type') as $evIcon)
                                        <span class="w-2 h-2 rounded-full {{ $evIcon->type->dotColor() }}" title="{{ $evIcon->type->label() }}"></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="space-y-1 mt-1.5 flex-1">
                            @foreach($cell['events']->take(2) as $ev)
                                <div class="text-[10px] sm:text-[11px] leading-snug px-1.5 py-0.5 rounded-md font-semibold truncate border cursor-pointer transition hover:opacity-80 {{ $ev->type->badgeClass() }} {{ ! $ev->active ? 'opacity-40 line-through' : '' }}"
                                     wire:click.stop="openEditModal('{{ $ev->id }}')"
                                     title="{{ $ev->name }} — {{ $ev->work_behavior->label() }}">
                                    {{ $ev->name }}
                                </div>
                            @endforeach
                            @if($cell['events']->count() > 2)
                                <span class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 block pl-0.5">
                                    +{{ $cell['events']->count() - 2 }} mais
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Lista de eventos do mês --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 bg-gray-50/70 border-b border-gray-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                <span>Eventos do Mês</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $events->count() }}
                </span>
            </h2>
        </div>
        @if($events->isEmpty())
            <div class="p-10 sm:p-12 text-center text-gray-500">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <p class="font-bold text-gray-800 text-sm">Nenhum evento cadastrado neste mês.</p>
                <p class="text-xs text-gray-500 mt-1">Clique em "Novo Evento" ou "Importar Feriados" para começar.</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($events as $ev)
                    <div class="px-4 sm:px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50/80 transition {{ ! $ev->active ? 'opacity-50' : '' }}">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="flex-shrink-0 w-12 text-center p-1.5 rounded-xl bg-gray-50 border border-gray-200">
                                <span class="text-lg font-black text-gray-900 leading-none">{{ $ev->event_date->format('d') }}</span>
                                <span class="block text-[10px] font-bold text-gray-500 uppercase mt-0.5">{{ $ev->event_date->translatedFormat('D') }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $ev->type->dotColor() }}"></span>
                                    <span class="font-bold text-gray-900 text-sm truncate">{{ $ev->name }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border {{ $ev->type->badgeClass() }}">{{ $ev->type->label() }}</span>
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 border border-gray-200">{{ $ev->scope->label() }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                    <span class="font-medium text-gray-700">{{ $ev->work_behavior->label() }}</span>
                                    @if(! $ev->all_day && $ev->starts_at && $ev->ends_at)
                                        <span class="text-gray-300">•</span>
                                        <span class="text-gray-600 font-mono">{{ substr($ev->starts_at, 0, 5) }}–{{ substr($ev->ends_at, 0, 5) }}</span>
                                    @endif
                                    @if($ev->requires_compensation)
                                        <span class="text-gray-300">•</span>
                                        <span class="text-amber-800 font-semibold">Requer compensação</span>
                                    @endif
                                    @if($ev->legal_reference)
                                        <span class="text-gray-300">•</span>
                                        <span class="text-indigo-600 font-medium">{{ $ev->legal_reference }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 self-end sm:self-center">
                            <button wire:click="openEditModal('{{ $ev->id }}')" 
                                    type="button"
                                    class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition cursor-pointer" 
                                    title="Editar">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <button wire:click="toggleActive('{{ $ev->id }}')" 
                                    type="button"
                                    class="p-1.5 {{ $ev->active ? 'text-amber-600 hover:text-amber-700 hover:bg-amber-50' : 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50' }} rounded-lg transition cursor-pointer" 
                                    title="{{ $ev->active ? 'Desativar' : 'Ativar' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $ev->active ? 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88' : 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' }}" />
                                </svg>
                            </button>
                            <button wire:click="deleteEvent('{{ $ev->id }}')" 
                                    type="button"
                                    wire:confirm="Deseja realmente excluir o evento '{{ $ev->name }}'?" 
                                    class="p-1.5 text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition cursor-pointer" 
                                    title="Excluir">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal Criar/Editar Evento --}}
    @if($showEventModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs px-4 py-6">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-200/80 w-full max-w-xl max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $editingEventId ? 'Editar Evento' : 'Novo Evento do Calendário' }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Feriado, Ponto Facultativo, Recesso ou Expediente Especial</p>
                    </div>
                    <button wire:click="$set('showEventModal', false)" type="button" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nome do Evento *</label>
                        <input wire:model="eventName" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Ex: Independência do Brasil">
                        @error('eventName') <span class="text-rose-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Data *</label>
                            <input wire:model="eventDate" type="date" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            @error('eventDate') <span class="text-rose-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Tipo *</label>
                            <select wire:model.live="eventType" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                @foreach(App\Enums\CalendarEventType::cases() as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Escopo Territorial *</label>
                            <select wire:model.live="eventScope" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                @foreach(App\Enums\CalendarEventScope::cases() as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if(in_array($eventScope, ['state', 'municipal']))
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">UF (Estado)</label>
                                <input wire:model="eventState" type="text" maxlength="2" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm uppercase focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Ex: AL">
                            </div>
                        @endif
                        @if($eventScope === 'municipal')
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Cidade (Município)</label>
                                <input wire:model="eventCity" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Ex: Maceió">
                            </div>
                        @endif
                        @if($eventScope === 'establishment')
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Estabelecimento</label>
                                <select wire:model="eventEstablishmentId" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <option value="">Selecione...</option>
                                    @foreach($establishments as $est)
                                        <option value="{{ $est->id }}">{{ $est->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    {{-- 20.18.4 / 20.18.5: Comportamento sobre a jornada --}}
                    <div class="bg-indigo-50/40 rounded-xl p-4 border border-indigo-100/80 space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-900 uppercase tracking-wider">Comportamento sobre a Jornada *</label>
                            <p class="text-xs text-gray-500 mt-0.5">Defina como este evento afeta o expediente dos trabalhadores.</p>
                        </div>
                        <div class="space-y-2">
                            @foreach(App\Enums\WorkBehavior::cases() as $wb)
                                <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition cursor-pointer {{ $eventWorkBehavior === $wb->value ? 'ring-2 ring-indigo-500 border-indigo-300' : '' }}">
                                    <input wire:model.live="eventWorkBehavior" type="radio" value="{{ $wb->value }}" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <span class="text-xs font-bold text-gray-900">{{ $wb->label() }}</span>
                                        <span class="block text-[11px] text-gray-500">{{ $wb->description() }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- 20.18.6: Horários (meio período) --}}
                    <div class="space-y-3 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model.live="eventAllDay" type="checkbox" class="text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="text-xs font-bold text-gray-700">Evento de Dia Inteiro</span>
                        </label>

                        @if(! $eventAllDay)
                            <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-xl border border-gray-200">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Início do Período</label>
                                    <input wire:model="eventStartsAt" type="time" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Fim do Período</label>
                                    <input wire:model="eventEndsAt" type="time" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                        @endif

                        <label class="flex items-center gap-2 cursor-pointer pt-1">
                            <input wire:model="eventRequiresCompensation" type="checkbox" class="text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="text-xs font-bold text-gray-700">Requer compensação futura pelo colaborador</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Referência Legal (20.18.16)</label>
                        <input wire:model="eventLegalReference" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Ex: Lei Federal nº 662/1949 ou Decreto nº ...">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Observações Internas</label>
                        <textarea wire:model="eventNotes" rows="2" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Notas adicionais de RH..."></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button wire:click="$set('showEventModal', false)" type="button" class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer">Cancelar</button>
                    <button wire:click="saveEvent" type="button" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">{{ $editingEventId ? 'Salvar Alterações' : 'Criar Evento' }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Importar Feriados Nacionais --}}
    @if($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs px-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-200/80 w-full max-w-md">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Importar Feriados Nacionais Legais</h3>
                        <p class="text-xs text-gray-500 mt-1">Importa somente feriados com fundamentação legal (Lei 662/1949, Lei 6.802/1980, Lei 14.759/2023). Pontos facultativos <strong class="text-amber-700">NÃO</strong> são importados.</p>
                    </div>
                    <button wire:click="$set('showImportModal', false)" type="button" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-6">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Ano de Referência</label>
                    <input wire:model="importYear" type="number" min="2020" max="2100" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button wire:click="$set('showImportModal', false)" type="button" class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer">Cancelar</button>
                    <button wire:click="importNationalHolidays" type="button" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">Importar Feriados</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Sugestões de Pontos Facultativos --}}
    @if($showSuggestionsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs px-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-200/80 w-full max-w-lg max-h-[85vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Pontos Facultativos Tradicionais — {{ $year }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Selecione quais pontos facultativos deseja importar. Cada um será importado como <strong class="text-amber-700">Sem expediente (facultativo)</strong> — ajuste o comportamento depois conforme a decisão do RH.</p>
                    </div>
                    <button wire:click="$set('showSuggestionsModal', false)" type="button" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="px-6 py-2.5 bg-gray-50/70 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">
                        {{ count($selectedSuggestions) }} de {{ count($suggestions) }} selecionado(s)
                    </span>
                    <button wire:click="toggleSelectAllSuggestions" type="button" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition cursor-pointer">
                        {{ count($selectedSuggestions) === count($suggestions) && count($suggestions) > 0 ? 'Desmarcar todos' : 'Selecionar todos' }}
                    </button>
                </div>
                <div class="p-6 space-y-2.5">
                    @foreach($suggestions as $index => $suggestion)
                        <label wire:key="sug-{{ $index }}" class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 hover:bg-amber-50/40 hover:border-amber-200 transition cursor-pointer">
                            <input wire:model.live="selectedSuggestions" type="checkbox" value="{{ $index }}" class="mt-1 text-amber-600 border-gray-300 rounded focus:ring-amber-500">
                            <div class="min-w-0">
                                <span class="text-sm font-bold text-gray-900">{{ $suggestion['name'] }}</span>
                                <span class="block text-xs font-semibold text-gray-500">{{ \Carbon\Carbon::parse($suggestion['date'])->format('d/m/Y') }}</span>
                                <span class="block text-xs text-amber-800 font-medium mt-0.5">{{ $suggestion['note'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button wire:click="$set('showSuggestionsModal', false)" type="button" class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer">Cancelar</button>
                    <button wire:click="importSelectedSuggestions" 
                            type="button" 
                            class="px-5 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer" 
                            {{ empty($selectedSuggestions) ? 'disabled' : '' }}>
                        Importar Selecionados ({{ count($selectedSuggestions) }})
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
