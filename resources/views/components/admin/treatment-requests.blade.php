<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TreatmentEvent;
use App\Models\Employee;
use App\Models\Sector;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\RejectTreatmentEventAction;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Solicitações de Tratamento de Ponto')] class extends Component
{
    public $statusFilter = 'pending';
    public $typeFilter = 'all';
    public $sectorId = '';
    public $employeeId = '';

    // Modal Rejeição
    public $showRejectModal = false;
    public $rejectEventId = '';
    public $rejectionReason = '';

    public function approve(string $id)
    {
        $event = TreatmentEvent::findOrFail($id);

        try {
            app(ApproveTreatmentEventAction::class)->execute(
                event: $event,
                approvedBy: Auth::user(),
            );

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Solicitação Aprovada!',
                'message' => 'O evento de tratamento foi aprovado formalmente e incorporado ao espelho e apuração de jornada.',
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Aprovar',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function openRejectModal(string $id)
    {
        $this->rejectEventId = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function confirmReject()
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ]);

        $event = TreatmentEvent::findOrFail($this->rejectEventId);

        try {
            app(RejectTreatmentEventAction::class)->execute(
                event: $event,
                rejectedBy: Auth::user(),
                rejectionReason: $this->rejectionReason,
            );

            $this->showRejectModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Solicitação Recusada',
                'message' => 'A solicitação foi rejeitada e a justificativa legal foi arquivada para auditoria fiscal.',
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Recusar',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function with()
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $isManager = $user->isManager();

        // Determina setores e colaboradores visíveis
        if ($isAdmin) {
            $sectors = Sector::orderBy('name')->get();
            $empQuery = Employee::with('user', 'sector')->orderBy('id');
            if (!empty($this->sectorId)) {
                $empQuery->where('sector_id', $this->sectorId);
            }
            $employees = $empQuery->get();
        } else {
            $sectors = Sector::where('manager_id', $user->id)->orderBy('name')->get();
            $managedSectorIds = $sectors->pluck('id');
            $empQuery = Employee::with('user', 'sector')->whereIn('sector_id', $managedSectorIds)->orderBy('id');
            if (!empty($this->sectorId) && $managedSectorIds->contains((int)$this->sectorId)) {
                $empQuery->where('sector_id', $this->sectorId);
            }
            $employees = $empQuery->get();
        }

        // Query principal de eventos
        $query = TreatmentEvent::with([
            'employee.user',
            'employee.sector',
            'requester',
            'approver',
            'rejecter',
            'referencePunch'
        ])->latest('created_at');

        // Escopo do Gestor: apenas colaboradores dos setores sob sua gestão
        if ($isManager) {
            $managedSectorIds = $sectors->pluck('id');
            $query->whereHas('employee', function ($q) use ($managedSectorIds) {
                $q->whereIn('sector_id', $managedSectorIds);
            });
        }

        // Filtro por Status
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Filtro por Tipo de Evento
        if ($this->typeFilter && $this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        // Filtro por Setor
        if (!empty($this->sectorId)) {
            $query->whereHas('employee', function ($q) {
                $q->where('sector_id', $this->sectorId);
            });
        }

        // Filtro por Colaborador
        if (!empty($this->employeeId)) {
            $query->where('employee_id', $this->employeeId);
        }

        $events = $query->paginate(15);

        // Contagem de pendências de acordo com a regra de visualização
        $pendingCountQuery = TreatmentEvent::where('status', TreatmentEventStatus::Pending);
        if ($isManager) {
            $managedSectorIds = $sectors->pluck('id');
            $pendingCountQuery->whereHas('employee', function ($q) use ($managedSectorIds) {
                $q->whereIn('sector_id', $managedSectorIds);
            });
        }
        $pendingCount = $pendingCountQuery->count();

        return [
            'isAdmin' => $isAdmin,
            'isManager' => $isManager,
            'sectors' => $sectors,
            'employees' => $employees,
            'events' => $events,
            'pendingCount' => $pendingCount,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header com Papel e Escopo -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-2xl bg-indigo-50 text-indigo-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Solicitações de Tratamento de Ponto (PTRP)</h2>
                        @if($isAdmin)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                Coordenação de RH (Poder Total)
                            </span>
                        @elseif($isManager)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                Gestor Imediato (Operacional do Setor)
                            </span>
                        @endif
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        @if($isAdmin)
                            Visão global de todos os setores, auditoria final de atestados e autoridade universal de aprovação.
                        @else
                            Aprovação operacional de batidas esquecidas, desconsiderações e validação prévia de atestados do seu setor.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($pendingCount > 0)
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold shadow-xs">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>{{ $pendingCount }} {{ $pendingCount === 1 ? 'pendência aguardando análise' : 'pendências aguardando análise' }}</span>
                </div>
            @else
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    <span>Nenhuma solicitação pendente</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Regras do Modelo de Gestão Segregada (Banner Informativo) -->
    <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-2xl flex items-start gap-3 text-xs text-indigo-900 leading-relaxed">
        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
        <div class="space-y-1">
            <p class="font-bold text-indigo-950">Divisão de Responsabilidades (Portaria MTP 671 / PTRP):</p>
            <p>
                <strong>Gestor Imediato:</strong> aprova o operacional diário (batidas esquecidas, desconsiderações de batidas erradas/duplicadas e validação inicial de atestados dos seus setores). Não autoaprova solicitações próprias (segregação de funções).<br>
                <strong>Coordenação de RH:</strong> visão global de todos os setores, aprova na ausência/férias do gestor, aprova com exclusividade solicitações feitas por gestores e realiza a auditoria final dos atestados antes de rodar o Fechamento de Competência e gerar os arquivos fiscais (AEJ/AFD).
            </p>
        </div>
    </div>

    <!-- Filtros Inteligentes -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="pending">Apenas Pendentes</option>
                    <option value="approved">Aprovadas</option>
                    <option value="rejected">Recusadas / Rejeitadas</option>
                    <option value="all">Todas as Solicitações</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Tipo de Evento</label>
                <select wire:model.live="typeFilter" class="w-full px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Tipos</option>
                    <option value="manual_punch_added">Inclusão de Batida Manual (Esquecimento)</option>
                    <option value="punch_disregarded">Desconsideração de Marcação</option>
                    <option value="absence_justified">Atestado Médico / Abono de Falta</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Setor</label>
                <select wire:model.live="sectorId" class="w-full px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ $isAdmin ? 'Todos os Setores (Visão Global)' : 'Todos os Meus Setores' }}</option>
                    @foreach($sectors as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Colaborador</label>
                <select wire:model.live="employeeId" class="w-full px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos os Colaboradores</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">
                            {{ $emp->user->name }} 
                            @if($emp->sector) ({{ $emp->sector->name }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela de Solicitações -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3.5">Solicitação / Data</th>
                        <th class="px-4 py-3.5">Colaborador / Setor</th>
                        <th class="px-4 py-3.5">Tipo de Evento</th>
                        <th class="px-4 py-3.5">Data / Hora Efetiva</th>
                        <th class="px-4 py-3.5">Justificativa & Atestado</th>
                        <th class="px-4 py-3.5">Status & Auditoria</th>
                        <th class="px-4 py-3.5 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $e)
                        @php
                            $isMySelfRequest = ($e->requested_by === Auth::id()) || ($e->employee && $e->employee->user_id === Auth::id());
                            $requesterIsManager = $e->requester && $e->requester->isManager();
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Solicitação / Data -->
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                <span class="font-mono text-gray-900 block font-bold">{{ $e->created_at->format('d/m/Y H:i') }}</span>
                                <span class="text-[10px] text-gray-500 block">
                                    Por: <strong>{{ $e->requester?->name ?? 'Colaborador' }}</strong>
                                </span>
                                @if($requesterIsManager)
                                    <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                        ⚠️ Solicitado por Gestor (Exige RH)
                                    </span>
                                @endif
                            </td>

                            <!-- Colaborador / Setor -->
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">
                                    {{ $e->employee?->user?->name ?? 'Colaborador #' . $e->employee_id }}
                                </div>
                                <div class="text-[10px] text-gray-500 font-mono">
                                    CPF: {{ $e->employee?->cpf ?? 'S/N' }}
                                </div>
                                @if($e->employee?->sector)
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-100 text-gray-700">
                                        {{ $e->employee->sector->name }}
                                    </span>
                                @endif
                            </td>

                            <!-- Tipo de Evento -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-bold text-gray-800">
                                    {{ $e->type->label() }}
                                </span>
                                @if($e->referencePunch)
                                    <span class="text-[10px] text-gray-500 block font-mono">Ref. NSR #{{ sprintf('%09d', $e->referencePunch->nsr) }}</span>
                                @endif
                            </td>

                            <!-- Data / Hora Efetiva -->
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700 whitespace-nowrap">
                                {{ $e->effective_at->format('d/m/Y H:i') }}
                            </td>

                            <!-- Justificativa & Anexo de Atestado -->
                            <td class="px-4 py-3 text-gray-700 max-w-xs">
                                <p class="line-clamp-2" title="{{ $e->reason_text }}">{{ $e->reason_text }}</p>
                                
                                @if($e->attachment_path)
                                    <div class="mt-1.5">
                                        <a href="{{ route('treatment.attachment', $e->id) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition shadow-2xs" title="Visualizar documento anexo / atestado médico">
                                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                            Ver Atestado / Comprovante
                                        </a>
                                    </div>
                                @endif

                                @if($e->status === TreatmentEventStatus::Rejected && $e->rejection_reason)
                                    <p class="text-[11px] text-rose-600 mt-1 bg-rose-50 p-1.5 rounded-lg border border-rose-100">
                                        <strong>Motivo recusa:</strong> {{ $e->rejection_reason }}
                                    </p>
                                @endif
                            </td>

                            <!-- Status & Auditoria -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $stBadge = match($e->status) {
                                        TreatmentEventStatus::Pending => 'bg-amber-50 text-amber-800 border-amber-200',
                                        TreatmentEventStatus::Approved => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        TreatmentEventStatus::Rejected => 'bg-rose-50 text-rose-800 border-rose-200',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full font-bold text-[11px] border {{ $stBadge }}">
                                    {{ $e->status->label() }}
                                </span>

                                @if($e->status === TreatmentEventStatus::Approved && $e->approver)
                                    <div class="mt-1 text-[10px] text-gray-500">
                                        <span>Por: <strong>{{ $e->approver->name }}</strong></span>
                                        <span class="block text-gray-400">
                                            ({{ $e->approver->isAdmin() ? 'Coordenação de RH' : 'Gestor do Setor' }})
                                        </span>
                                    </div>
                                @elseif($e->status === TreatmentEventStatus::Rejected && $e->rejecter)
                                    <div class="mt-1 text-[10px] text-gray-500">
                                        <span>Por: <strong>{{ $e->rejecter->name }}</strong></span>
                                        <span class="block text-gray-400">
                                            ({{ $e->rejecter->isAdmin() ? 'Coordenação de RH' : 'Gestor do Setor' }})
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Ações -->
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($e->status === TreatmentEventStatus::Pending)
                                    @if($isManager && $isMySelfRequest)
                                        <span class="inline-block px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 text-[10px] font-bold border border-gray-200" title="Pela regra de segregação de funções, o Gestor não pode aprovar solicitações próprias. O RH deve aprovar.">
                                            Aguardando RH
                                        </span>
                                    @else
                                        <div class="inline-flex items-center gap-1.5">
                                            <button wire:click="approve('{{ $e->id }}')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-xs transition cursor-pointer">
                                                Aprovar
                                            </button>
                                            <button wire:click="openRejectModal('{{ $e->id }}')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg font-bold text-xs transition cursor-pointer">
                                                Recusar
                                            </button>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-[11px] text-gray-400 font-medium">Decidido em {{ $e->decided_at?->format('d/m/Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                Nenhuma solicitação de tratamento encontrada para o filtro selecionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $events->links() }}
            </div>
        @endif
    </div>

    <!-- Modal de Rejeição Formal -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-base font-bold text-rose-700">Recusar Solicitação de Tratamento</h3>
                    <button wire:click="$set('showRejectModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit="confirmReject" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Motivo da Recusa (Obrigatório para Auditoria Fiscal)</label>
                        <textarea wire:model="rejectionReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Informe formalmente o motivo da não aprovação (ex: divergência com as câmeras, documento ilegível, ausência sem amparo legal)..." required></textarea>
                        @error('rejectionReason') <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 shadow-sm">Confirmar Recusa</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

