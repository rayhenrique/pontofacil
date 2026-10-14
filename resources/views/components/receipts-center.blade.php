<?php

use App\Domain\Company\Services\SectorScopeService;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchReceipt;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $selectedMonth = '';
    public string $selectedYear = '';
    public $selectedUserId = null;
    public $selectedSectorId = null;
    public $selectedEstablishmentId = null;
    public string $selectedDirection = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('m');
        $this->selectedYear = now()->format('Y');

        if (Auth::user()?->role === UserRole::Employee) {
            $this->selectedUserId = Auth::id();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedYear(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedUserId($value): void
    {
        $this->selectedUserId = ($value === '' || $value === null) ? null : (int) $value;
        $this->resetPage();
    }

    public function updatedSelectedSectorId($value): void
    {
        $this->selectedSectorId = ($value === '' || $value === null) ? null : (int) $value;
        $this->selectedUserId = null;
        $this->resetPage();
    }

    public function updatedSelectedEstablishmentId($value): void
    {
        $this->selectedEstablishmentId = ($value === '' || $value === null) ? null : (int) $value;
        $this->selectedSectorId = null;
        $this->selectedUserId = null;
        $this->resetPage();
    }

    public function updatedSelectedDirection(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->selectedMonth = '';
        $this->selectedYear = '';
        $this->selectedSectorId = null;
        $this->selectedEstablishmentId = null;
        $this->selectedDirection = '';

        if (Auth::user()?->role !== UserRole::Employee) {
            $this->selectedUserId = null;
        }

        $this->resetPage();
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $scopeService = app(SectorScopeService::class);

        // Normalização de inputs nulos / strings vazias (Rule 12)
        $cleanUserId = ($this->selectedUserId === '' || $this->selectedUserId === null) ? null : (int) $this->selectedUserId;
        $cleanSectorId = ($this->selectedSectorId === '' || $this->selectedSectorId === null) ? null : (int) $this->selectedSectorId;
        $cleanEstId = ($this->selectedEstablishmentId === '' || $this->selectedEstablishmentId === null) ? null : (int) $this->selectedEstablishmentId;

        // Início da consulta unificada com Join para ordenação e performance indexada
        $query = PunchReceipt::query()
            ->join('punch_events', 'punch_receipts.punch_event_id', '=', 'punch_events.id')
            ->select('punch_receipts.*')
            ->with([
                'punchEvent.establishment',
                'punchEvent.user',
                'punchEvent.employee.sector',
            ]);

        // 1. Controle estrito de autorização por perfil (Rules 4 e 5)
        if ($user->role === UserRole::Employee) {
            // Colaborador vê estritamente seus próprios comprovantes
            $query->where('punch_events.user_id', $user->id);
            $cleanUserId = $user->id;
            $cleanSectorId = null;
            $cleanEstId = null;
        } elseif ($user->role === UserRole::Manager) {
            $managedSectorIds = $scopeService->managedSectorIds($user);
            $allowedUserIds = Employee::whereIn('sector_id', $managedSectorIds)
                ->pluck('user_id')
                ->push($user->id)
                ->unique()
                ->values();

            // Validação estrita contra ID forjado (Rule 5)
            if ($cleanUserId && ! $allowedUserIds->contains($cleanUserId)) {
                $cleanUserId = null;
            }

            if ($cleanSectorId && ! in_array($cleanSectorId, $managedSectorIds, true)) {
                $cleanSectorId = null;
            }

            if ($cleanEstId && ! $scopeService->canManageEstablishment($user, $cleanEstId)) {
                $cleanEstId = null;
            }

            if ($cleanUserId) {
                $query->where('punch_events.user_id', $cleanUserId);
            } else {
                $query->whereIn('punch_events.user_id', $allowedUserIds);
            }

            if ($cleanSectorId) {
                $query->whereHas('punchEvent.employee', fn ($eq) => $eq->where('sector_id', $cleanSectorId));
            }

            if ($cleanEstId) {
                $query->where('punch_events.establishment_id', $cleanEstId);
            }
        } else {
            // Admin / Auditor: conforme escopo da instalação
            if ($cleanUserId) {
                $query->where('punch_events.user_id', $cleanUserId);
            }

            if ($cleanSectorId) {
                $query->whereHas('punchEvent.employee', fn ($eq) => $eq->where('sector_id', $cleanSectorId));
            }

            if ($cleanEstId) {
                $query->where('punch_events.establishment_id', $cleanEstId);
            }
        }

        // 2. Filtro de tipo de registro (Sentido: Todos, Entrada, Saída) (Rule 2)
        if (in_array($this->selectedDirection, ['in', 'out'], true)) {
            $query->where('punch_events.direction', $this->selectedDirection);
        }

        // 3. Filtro de período indexável (Rule 8)
        if ($this->selectedYear !== '') {
            $year = (int) $this->selectedYear;
            if ($this->selectedMonth !== '') {
                $month = (int) $this->selectedMonth;
                $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
                $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateTimeString();
            } else {
                $start = Carbon::create($year, 1, 1)->startOfYear()->toDateTimeString();
                $end = Carbon::create($year, 12, 31)->endOfYear()->toDateTimeString();
            }
            $query->whereBetween('punch_events.occurred_at_local', [$start, $end]);
        } elseif ($this->selectedMonth !== '') {
            $query->whereMonth('punch_events.occurred_at_local', (int) $this->selectedMonth);
        }

        // 4. Pesquisa textual expandida combinada com AND (Rule 1 e 3)
        // OR apenas entre os campos da pesquisa textual
        if (! empty($this->search)) {
            $term = trim($this->search);
            $cleanNsr = ltrim($term, '#');
            $isNumericNsr = $cleanNsr !== '' && ctype_digit($cleanNsr);

            $query->where(function ($q) use ($term, $isNumericNsr, $cleanNsr) {
                $q->where('punch_receipts.verification_code', 'like', "%{$term}%")
                    ->orWhereHas('punchEvent.user', function ($uq) use ($term) {
                        $uq->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });

                if ($isNumericNsr) {
                    $q->orWhere('punch_events.nsr', (int) $cleanNsr);
                }
            });
        }

        $receipts = $query->orderBy('punch_events.occurred_at_local', 'desc')
            ->paginate(15);

        // 5. Carregamento sob demanda e protegido de listas para selects (Rule 11)
        $sectors = collect();
        $establishments = collect();
        $colleagues = collect();

        if ($user->role !== UserRole::Employee) {
            // Setores disponíveis
            if ($user->role === UserRole::Manager) {
                $managedSectorIds = $scopeService->managedSectorIds($user);
                $sectorQuery = Sector::whereIn('id', $managedSectorIds);
                if ($cleanEstId) {
                    $sectorQuery->where('establishment_id', $cleanEstId);
                }
                $sectors = $sectorQuery->orderBy('name')->get();

                // Estabelecimentos dos setores gerenciados
                $managedEstIds = Sector::whereIn('id', $managedSectorIds)->pluck('establishment_id')->unique();
                $establishments = Establishment::whereIn('id', $managedEstIds)->orderBy('name')->get();
            } else {
                $sectorQuery = Sector::query();
                if ($cleanEstId) {
                    $sectorQuery->where('establishment_id', $cleanEstId);
                }
                $sectors = $sectorQuery->orderBy('name')->get();

                $establishments = Establishment::orderBy('name')->get();
            }

            // Colaboradores disponíveis para seleção
            $colleagueQuery = User::query()->select(['id', 'name', 'email']);

            if ($user->role === UserRole::Manager) {
                $managedSectorIds = $scopeService->managedSectorIds($user);
                $targetSectorIds = $cleanSectorId ? [$cleanSectorId] : $managedSectorIds;

                $colleagueQuery->whereHas('employee', function ($eq) use ($targetSectorIds) {
                    $eq->whereIn('sector_id', $targetSectorIds);
                });
            } else {
                if ($cleanSectorId) {
                    $colleagueQuery->whereHas('employee', fn ($eq) => $eq->where('sector_id', $cleanSectorId));
                } elseif ($cleanEstId) {
                    $colleagueQuery->whereHas('employee.sector', fn ($sq) => $sq->where('establishment_id', $cleanEstId));
                }
            }

            $colleagues = $colleagueQuery->orderBy('name')->take(200)->get();

            // Garantir que usuário selecionado esteja no select mesmo se além do take
            if ($cleanUserId && ! $colleagues->contains('id', $cleanUserId)) {
                $selectedUserObj = User::select(['id', 'name', 'email'])->find($cleanUserId);
                if ($selectedUserObj) {
                    $colleagues->prepend($selectedUserObj);
                }
            }
        }

        $hasActiveFilters = $this->search !== ''
            || $this->selectedMonth !== ''
            || $this->selectedYear !== ''
            || ($cleanUserId !== null && $user->role !== UserRole::Employee)
            || $cleanSectorId !== null
            || $cleanEstId !== null
            || $this->selectedDirection !== '';

        return view('components.receipts-center', [
            'receipts' => $receipts,
            'colleagues' => $colleagues,
            'sectors' => $sectors,
            'establishments' => $establishments,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }
}; ?>

<div class="py-4 sm:py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-5">
    
    <!-- Cabeçalho da Página -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Central de Comprovantes do Trabalhador
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-[10px] font-bold uppercase tracking-wider">
                    Portaria 671/2021 MTP
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Histórico permanente de comprovantes eletrônicos de registro de ponto (REP-P).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a 
                href="{{ route('receipts.verify') }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs shadow-2xs transition min-h-[44px]"
            >
                <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                Auditar Código Público
            </a>
            @if(Auth::user()->role === App\Enums\UserRole::Admin)
                <a 
                    href="{{ route('admin.export-afd') }}" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-2xs transition min-h-[44px]"
                >
                    <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Exportar AFD MTE
                </a>
            @endif
        </div>
    </div>

    <!-- Bloco Unificado de Busca e Filtros -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3.5">
        
        <!-- Linha 1: Pesquisa Unificada em Destaque -->
        <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input 
                wire:model.live.debounce.300ms="search"
                type="text" 
                placeholder="Buscar por código (PF-...), NSR (#000000001), nome ou e-mail..." 
                class="w-full min-h-[44px] pl-10 pr-10 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none transition"
            />
            @if($search !== '')
                <button type="button"
                        wire:click="clearSearch"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer min-h-[44px] min-w-[44px] justify-center transition"
                        aria-label="Limpar pesquisa">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Linha 2: Filtros Secundários Compactos e Responsivos -->
        <div class="flex flex-wrap items-center gap-2.5 text-xs">
            
            <!-- Filtro Mês -->
            <select wire:model.live="selectedMonth" 
                    aria-label="Filtrar por Mês"
                    class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Meses</option>
                <option value="01">01 - Janeiro</option>
                <option value="02">02 - Fevereiro</option>
                <option value="03">03 - Março</option>
                <option value="04">04 - Abril</option>
                <option value="05">05 - Maio</option>
                <option value="06">06 - Junho</option>
                <option value="07">07 - Julho</option>
                <option value="08">08 - Agosto</option>
                <option value="09">09 - Setembro</option>
                <option value="10">10 - Outubro</option>
                <option value="11">11 - Novembro</option>
                <option value="12">12 - Dezembro</option>
            </select>

            <!-- Filtro Ano -->
            <select wire:model.live="selectedYear" 
                    aria-label="Filtrar por Ano"
                    class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Anos</option>
                @for($y = now()->year - 8; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <!-- Filtro Tipo de Registro (Entrada/Saída) -->
            <select wire:model.live="selectedDirection" 
                    aria-label="Tipo de Registro"
                    class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Registros</option>
                <option value="in">Entrada</option>
                <option value="out">Saída</option>
            </select>

            @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                <!-- Filtro Estabelecimento (se houver mais de 1) -->
                @if($establishments->count() > 1)
                    <select wire:model.live="selectedEstablishmentId" 
                            aria-label="Filtrar por Estabelecimento"
                            class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white max-w-[220px] truncate focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todos os Estabelecimentos</option>
                        @foreach($establishments as $est)
                            <option value="{{ $est->id }}">{{ $est->name }} ({{ $est->code }})</option>
                        @endforeach
                    </select>
                @endif

                <!-- Filtro Setor -->
                @if($sectors->isNotEmpty())
                    <select wire:model.live="selectedSectorId" 
                            aria-label="Filtrar por Setor"
                            class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white max-w-[200px] truncate focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todos os Setores</option>
                        @foreach($sectors as $sec)
                            <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                        @endforeach
                    </select>
                @endif

                <!-- Filtro Servidor / Colaborador -->
                @if($colleagues->isNotEmpty())
                    <select wire:model.live="selectedUserId" 
                            aria-label="Filtrar por Servidor"
                            class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-300 font-medium text-slate-700 bg-white max-w-[220px] truncate focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todos os Servidores</option>
                        @foreach($colleagues as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
            @endif

            <!-- Botão Limpar Filtros -->
            @if($hasActiveFilters)
                <button type="button"
                        wire:click="clearFilters"
                        class="min-h-[40px] inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs transition cursor-pointer"
                        title="Limpar todos os filtros aplicados">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Limpar Filtros</span>
                </button>
            @endif
        </div>

        <!-- Linha 3: Contador de Resultados -->
        <div class="flex items-center justify-between text-xs text-slate-500 pt-1 border-t border-slate-100">
            <div>
                <span class="font-bold text-slate-700 tabular-nums">{{ $receipts->total() }}</span> 
                <span>{{ $receipts->total() === 1 ? 'comprovante localizado' : 'comprovantes localizados' }}</span>
                @if($hasActiveFilters)
                    <span class="text-slate-400 font-medium ml-1">(com filtros ativos)</span>
                @endif
            </div>
            @if($receipts->hasPages())
                <span class="text-[11px] text-slate-400">
                    Página {{ $receipts->currentPage() }} de {{ $receipts->lastPage() }}
                </span>
            @endif
        </div>

    </div>

    <!-- Lista de Comprovantes (Desktop: Tabela / Mobile: Cartões) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <!-- VISUALIZAÇÃO DESKTOP: TABELA COM MENOS RUÍDO -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                    <tr>
                        <th class="py-3 px-4">Data / Horário</th>
                        <th class="py-3 px-4">Operação</th>
                        <th class="py-3 px-4">Identificadores (NSR / Código)</th>
                        @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                            <th class="py-3 px-4">Servidor</th>
                        @endif
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($receipts as $r)
                        @php
                            $ev = $r->punchEvent;
                            $nsrStr = str_pad((string)$ev->nsr, 9, '0', STR_PAD_LEFT);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 block">{{ $ev->occurred_at_local->format('d/m/Y') }}</span>
                                <span class="text-slate-500 text-[11px]">{{ $ev->occurred_at_local->format('H:i:s') }} ({{ $ev->timezone }})</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $ev->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $ev->direction === 'in' ? 'ENTRADA' : 'SAÍDA' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-mono font-bold text-indigo-600 block">#{{ $nsrStr }}</span>
                                <span class="font-mono text-[11px] text-slate-500 block">{{ $r->verification_code }}</span>
                            </td>
                            @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="font-semibold text-slate-900 block">{{ $ev->user->name }}</span>
                                    <span class="text-slate-400 text-[10px] block">
                                        {{ $ev->employee?->sector?->name ?? 'Geral' }} · {{ $ev->employee?->cpf ?? 'Sem CPF' }}
                                    </span>
                                </td>
                            @endif
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($r->signature_status === 'signed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Assinado ICP-Brasil
                                    </span>
                                @elseif($r->signature_status === 'signed_dev')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Assinatura Dev
                                    </span>
                                @elseif(app()->environment('production'))
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Pendente Certificado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Ambiente de Testes
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-right space-x-1">
                                <a 
                                    href="{{ route('receipts.print', $r->verification_code) }}" 
                                    target="_blank"
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Imprimir
                                </a>
                                <a 
                                    href="{{ route('receipts.pdf', $r->verification_code) }}" 
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition"
                                >
                                    PDF
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->role !== App\Enums\UserRole::Employee ? 6 : 5 }}" class="py-12 text-center text-slate-400">
                                @if($hasActiveFilters)
                                    Nenhum comprovante encontrado para os filtros selecionados.
                                @else
                                    Nenhum comprovante eletrônico encontrado para o período selecionado.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- VISUALIZAÇÃO MOBILE: CARTÕES ACESSÍVEIS SEM ROLAGEM HORIZONTAL -->
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($receipts as $r)
                @php
                    $ev = $r->punchEvent;
                    $nsrStr = str_pad((string)$ev->nsr, 9, '0', STR_PAD_LEFT);
                @endphp
                <article class="p-4 space-y-2.5 hover:bg-slate-50/60 transition">
                    <!-- Linha 1: Data, Hora e Sentido -->
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-sm font-bold text-slate-900">{{ $ev->occurred_at_local->format('d/m/Y') }}</span>
                            <span class="text-xs text-slate-500 ml-1 font-mono">{{ $ev->occurred_at_local->format('H:i:s') }}</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $ev->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $ev->direction === 'in' ? 'ENTRADA' : 'SAÍDA' }}
                        </span>
                    </div>

                    <!-- Linha 2: Servidor (se não for Employee) -->
                    @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                        <div class="bg-slate-50 rounded-lg p-2 text-xs">
                            <span class="font-semibold text-slate-900 block">{{ $ev->user->name }}</span>
                            <span class="text-slate-400 text-[10px] block">
                                {{ $ev->employee?->sector?->name ?? 'Geral' }} · {{ $ev->employee?->cpf ?? 'Sem CPF' }}
                            </span>
                        </div>
                    @endif

                    <!-- Linha 3: NSR, Código de Autenticidade e Status -->
                    <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                        <div class="space-y-0.5 font-mono">
                            <span class="font-bold text-indigo-600 block">NSR #{{ $nsrStr }}</span>
                            <span class="text-slate-500 text-[10px] block">{{ $r->verification_code }}</span>
                        </div>
                        <div>
                            @if($r->signature_status === 'signed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Assinado ICP-Brasil
                                </span>
                            @elseif($r->signature_status === 'signed_dev')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Assinatura Dev
                                </span>
                            @elseif(app()->environment('production'))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                    Pendente Certificado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    Ambiente de Testes
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Linha 4: Ações com target acessível (min 44px de altura) -->
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100">
                        <a 
                            href="{{ route('receipts.print', $r->verification_code) }}" 
                            target="_blank"
                            class="min-h-[44px] flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition"
                        >
                            Imprimir
                        </a>
                        <a 
                            href="{{ route('receipts.pdf', $r->verification_code) }}" 
                            class="min-h-[44px] flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition"
                        >
                            Baixar PDF
                        </a>
                    </div>
                </article>
            @empty
                <div class="py-12 text-center text-slate-400 p-4">
                    @if($hasActiveFilters)
                        <p class="text-xs">Nenhum comprovante encontrado para os filtros selecionados.</p>
                        <button type="button" 
                                wire:click="clearFilters" 
                                class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            Limpar filtros
                        </button>
                    @else
                        <p class="text-xs">Nenhum comprovante eletrônico encontrado para o período selecionado.</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if($receipts->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>

</div>
