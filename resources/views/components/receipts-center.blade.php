<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PunchReceipt;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $selectedMonth = '';
    public string $selectedYear = '';
    public ?int $selectedUserId = null;

    public function mount()
    {
        $this->selectedMonth = now()->format('m');
        $this->selectedYear = now()->format('Y');

        if (Auth::user()->role === UserRole::Employee) {
            $this->selectedUserId = Auth::id();
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        $query = PunchReceipt::with([
            'punchEvent.establishment',
            'punchEvent.user',
            'punchEvent.employee',
        ]);

        // Restrição de acesso por papel
        if ($user->role === UserRole::Employee) {
            $query->whereHas('punchEvent', fn($q) => $q->where('user_id', $user->id));
        } elseif ($this->selectedUserId) {
            $query->whereHas('punchEvent', fn($q) => $q->where('user_id', $this->selectedUserId));
        }

        // Filtro por mês/ano
        if ($this->selectedMonth && $this->selectedYear) {
            $query->whereHas('punchEvent', function ($q) {
                $q->whereYear('occurred_at_local', $this->selectedYear)
                  ->whereMonth('occurred_at_local', $this->selectedMonth);
            });
        }

        // Busca por código ou NSR
        if (! empty($this->search)) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('verification_code', 'like', "%{$term}%")
                  ->orWhereHas('punchEvent', fn($sub) => $sub->where('nsr', 'like', "%{$term}%"));
            });
        }

        $receipts = $query->join('punch_events', 'punch_receipts.punch_event_id', '=', 'punch_events.id')
            ->orderBy('punch_events.occurred_at_local', 'desc')
            ->select('punch_receipts.*')
            ->paginate(15);

        // Lista de colaboradores para filtro caso seja Admin/Gestor
        $colleagues = [];
        if ($user->role === UserRole::Admin) {
            $colleagues = User::where('role', UserRole::Employee)->orderBy('name')->get();
        }

        return view('components.receipts-center', [
            'receipts' => $receipts,
            'colleagues' => $colleagues,
        ]);
    }
}; ?>

<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    
    <!-- Cabeçalho da Página -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider mb-2">
                Conformidade Portaria 671/2021 MTP
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                Central de Comprovantes do Trabalhador
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Histórico permanente de comprovantes eletrônicos de registro de ponto (REP-P).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a 
                href="{{ route('receipts.verify') }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                Auditar Código Público
            </a>
            @if(Auth::user()->role === App\Enums\UserRole::Admin)
                <a 
                    href="{{ route('admin.export-afd') }}" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition"
                >
                    <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Exportar AFD MTE
                </a>
            @endif
        </div>
    </div>

    <!-- Filtros e Barra de Pesquisa -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[240px]">
            <input 
                wire:model.live.debounce.300ms="search"
                type="text" 
                placeholder="Filtrar por Código (PF-...) ou NSR (#000000001)..." 
                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none"
            />
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="selectedMonth" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white">
                <option value="01">Janeiro</option>
                <option value="02">Fevereiro</option>
                <option value="03">Março</option>
                <option value="04">Abril</option>
                <option value="05">Maio</option>
                <option value="06">Junho</option>
                <option value="07">Julho</option>
                <option value="08">Agosto</option>
                <option value="09">Setembro</option>
                <option value="10">Outubro</option>
                <option value="11">Novembro</option>
                <option value="12">Dezembro</option>
            </select>

            <select wire:model.live="selectedYear" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
            </select>

            @if(count($colleagues) > 0)
                <select wire:model.live="selectedUserId" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white max-w-[180px]">
                    <option value="">Todos os Servidores</option>
                    @foreach($colleagues as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <!-- Tabela de Comprovantes -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                    <tr>
                        <th class="py-3 px-4">Data / Horário</th>
                        <th class="py-3 px-4">Operação</th>
                        <th class="py-3 px-4">NSR Monotônico</th>
                        @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                            <th class="py-3 px-4">Servidor</th>
                        @endif
                        <th class="py-3 px-4">Código de Autenticidade</th>
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
                            <td class="py-3 px-4 whitespace-nowrap font-mono font-black text-indigo-600">
                                #{{ $nsrStr }}
                            </td>
                            @if(Auth::user()->role !== App\Enums\UserRole::Employee)
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="font-semibold text-slate-900 block">{{ $ev->user->name }}</span>
                                    <span class="text-slate-400 text-[10px]">{{ $ev->employee?->cpf ?? 'Sem CPF' }}</span>
                                </td>
                            @endif
                            <td class="py-3 px-4 whitespace-nowrap font-mono font-bold text-slate-800">
                                {{ $r->verification_code }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    Ambiente Teste
                                </span>
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
                            <td colspan="{{ Auth::user()->role !== App\Enums\UserRole::Employee ? 7 : 6 }}" class="py-12 text-center text-slate-400">
                                Nenhum comprovante eletrônico encontrado para o período selecionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($receipts->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>

</div>
