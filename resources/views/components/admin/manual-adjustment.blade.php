<?php

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Traits\HandlesPointAdjustment;
use App\Models\Employee;
use App\Models\TreatmentEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Ajuste e Correção de Ponto (PTRP)')] class extends Component
{
    use HandlesPointAdjustment;

    public string $selectedEmployeeId = '';
    public string $selectedDate = '';
    public string $search = '';

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
        if (request()->has('employee_id')) {
            $this->selectedEmployeeId = (string) request('employee_id');
        }
        if (request()->has('date')) {
            $this->selectedDate = (string) request('date');
        }

        // Se veio colaborador e data por query param, abre o modal diretamente
        if ($this->selectedEmployeeId) {
            $this->openPointAdjustmentModal($this->selectedEmployeeId, $this->selectedDate);
        }
    }

    public function startAdjustment(int|string $employeeId, ?string $date = null): void
    {
        $targetDate = $date ?: $this->selectedDate;
        $this->openPointAdjustmentModal($employeeId, $targetDate);
    }

    public function onPointAdjustmentSaved($event): void
    {
        // Hook disparado quando um ajuste for registrado ou aprovado com sucesso
        $this->selectedEmployeeId = '';
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $actor = Auth::user();
        if (! $actor) {
            abort(401);
        }

        $query = Employee::with(['user', 'sector.establishment', 'workSchedule']);

        if ($actor->isManager()) {
            $scopeService = app(SectorScopeService::class);
            $query = $scopeService->scopeEmployeesQuery($actor, $query);
        } elseif (! $actor->isAdmin()) {
            $query->where('user_id', $actor->id);
        }

        if (! empty(trim($this->search))) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhere('cpf', 'like', "%{$term}%")
                    ->orWhere('registration_number', 'like', "%{$term}%");
            });
        }

        $employees = $query->orderBy('id', 'asc')->paginate(15);

        // Tratamentos recentes auditados
        $recentTreatments = TreatmentEvent::with(['employee.user', 'requester', 'approver'])
            ->where(function ($q) use ($actor) {
                if ($actor->isManager()) {
                    $managedIds = \App\Models\Sector::where('manager_id', $actor->id)->pluck('id');
                    $q->whereHas('employee', fn ($eq) => $eq->whereIn('sector_id', $managedIds));
                } elseif (! $actor->isAdmin()) {
                    $q->where('requested_by', $actor->id);
                }
            })
            ->latest('created_at')
            ->take(5)
            ->get();

        return [
            'employees' => $employees,
            'recentTreatments' => $recentTreatments,
        ];
    }
};
?>

<div class="max-w-6xl mx-auto py-3 sm:py-6 px-2 sm:px-6 lg:px-8 space-y-5">
    <!-- Cabeçalho Institucional -->
    <header class="bg-white rounded-2xl border border-gray-200/80 p-4 sm:p-6 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">
                        PTRP • Portaria 671 MTP
                    </span>
                    <span class="text-xs text-gray-400">Fluxo Único Auditado</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">
                    Ajustes e Correções de Ponto
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Central oficial do PTRP para inclusões de entrada/saída, desconsiderações e justificativas de ausência.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('timesheet') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 transition min-h-[44px]">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Ver Espelho</span>
                </a>
                <a href="{{ route('admin.monthly-review') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition min-h-[44px]">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Conferência Mensal</span>
                </a>
            </div>
        </div>

        <!-- Alerta Informativo de Compliance -->
        <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 flex items-start gap-2.5">
            <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <strong>Trilha de Auditoria do PTRP:</strong> Qualquer correção solicitada passa pelo fluxo seguro de eventos de tratamento (Portaria 671/2021 MTP). Marcações fiscais do REP-P permanecem imutáveis, e aprovações respeitam estritamente a segregação de funções.
            </div>
        </div>
    </header>

    <!-- Barra de Ação Rápida e Pesquisa -->
    <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2 relative">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por colaborador, CPF ou matrícula..."
                       class="w-full rounded-xl border border-gray-300 bg-white text-xs sm:text-sm py-2 pl-9 pr-4 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-h-[44px]">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <div>
                <input type="date"
                       wire:model.live="selectedDate"
                       class="w-full rounded-xl border border-gray-300 bg-white text-xs sm:text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-h-[44px]"
                       title="Data de referência para o ajuste">
            </div>
        </div>
    </div>

    <!-- Tabela de Colaboradores Elegíveis -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs overflow-hidden">
        <div class="px-4 sm:px-6 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                Colaboradores Autorizados para Ajuste
            </h2>
            <span class="text-xs text-gray-500">Data selecionada: <strong>{{ Carbon::parse($selectedDate)->format('d/m/Y') }}</strong></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="py-3 px-4">Colaborador</th>
                        <th class="py-3 px-4">Setor</th>
                        <th class="py-3 px-4">Escala de Trabalho</th>
                        <th class="py-3 px-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($employees as $emp)
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">{{ $emp->user?->name ?? "Colaborador #{$emp->id}" }}</div>
                                <div class="text-[11px] text-gray-500 font-mono">
                                    CPF: {{ substr($emp->cpf, 0, 3) }}.***.***-{{ substr($emp->cpf, -2) }} • Matr.: {{ $emp->registration_number ?: 'N/A' }}
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ $emp->sector?->name ?? 'Sem setor vinculado' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $emp->workSchedule?->name ?? 'Padrão Administrativo' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button type="button"
                                        wire:click="startAdjustment({{ $emp->id }})"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer min-h-[40px]">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    <span>Corrigir Ponto</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-500 text-xs">
                                Nenhum colaborador encontrado com os filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $employees->links() }}
            </div>
        @endif
    </div>

    <!-- Histórico Recente de Ajustes no PTRP -->
    @if($recentTreatments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-2xs p-4 sm:p-6 space-y-3">
            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                Últimos Ajustes Registrados no PTRP
            </h3>
            <div class="divide-y divide-gray-100">
                @foreach($recentTreatments as $tr)
                    <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="font-bold text-gray-900">{{ $tr->employee?->user?->name ?? 'Colaborador' }}</span>
                            <span class="text-gray-400">·</span>
                            <span class="text-gray-600">{{ $tr->type->label() }} em {{ $tr->effective_at?->format('d/m/Y') }}</span>
                            <p class="text-[11px] text-gray-500 mt-0.5">Motivo: {{ $tr->reason_text }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @php
                                $badgeClass = match($tr->status) {
                                    TreatmentEventStatus::Approved => 'bg-emerald-50 text-emerald-800 border border-emerald-200',
                                    TreatmentEventStatus::Rejected => 'bg-rose-50 text-rose-800 border border-rose-200',
                                    TreatmentEventStatus::Cancelled => 'bg-gray-100 text-gray-700 border border-gray-300',
                                    TreatmentEventStatus::Revoked => 'bg-purple-50 text-purple-800 border border-purple-200',
                                    default => 'bg-amber-50 text-amber-800 border border-amber-200',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                {{ $tr->status->label() }}
                            </span>
                            @if($tr->approver)
                                <span class="text-[10px] text-gray-400">por {{ $tr->approver->name }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- MODAL UNIFICADO: CORRIGIR PONTO (PORTARIA 671 MTP / PTRP) --}}
    @include('components.ptrp.point-adjustment-modal')
</div>