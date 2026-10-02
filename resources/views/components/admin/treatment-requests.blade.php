<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TreatmentEvent;
use App\Models\Employee;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\RejectTreatmentEventAction;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Solicitações de Tratamento de Ponto')] class extends Component
{
    public $statusFilter = 'pending';
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
                'message' => 'O evento de tratamento foi aprovado e agora afeta a apuração analítica da jornada.',
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
                'message' => 'A solicitação foi rejeitada e a justificativa da recusa foi arquivada.',
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
        $employees = Employee::with('user')->orderBy('id')->get();

        $query = TreatmentEvent::with(['employee.user', 'requester', 'approver', 'rejecter', 'referencePunch'])
            ->latest('created_at');

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->employeeId)) {
            $query->where('employee_id', $this->employeeId);
        }

        $events = $query->paginate(15);
        $pendingCount = TreatmentEvent::where('status', TreatmentEventStatus::Pending)->count();

        return [
            'employees' => $employees,
            'events' => $events,
            'pendingCount' => $pendingCount,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-indigo-50 text-indigo-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Solicitações de Tratamento de Ponto (PTRP)</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Gestão de batidas manuais esquecidas, desconsiderações e justificativas de ausência</p>
                </div>
            </div>
        </div>

        @if($pendingCount > 0)
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>{{ $pendingCount }} {{ $pendingCount === 1 ? 'solicitação pendente' : 'solicitações pendentes' }}</span>
            </div>
        @endif
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                <select wire:model.live="statusFilter" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="pending">Apenas Pendentes</option>
                    <option value="approved">Aprovadas</option>
                    <option value="rejected">Rejeitadas</option>
                    <option value="all">Todas as Solicitações</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Colaborador</label>
                <select wire:model.live="employeeId" class="px-3 py-2 text-xs font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 max-w-xs">
                    <option value="">Todos os Colaboradores</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->user->name }} (CPF: {{ $emp->cpf ?? 'S/N' }})</option>
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
                        <th class="px-4 py-3.5">Colaborador</th>
                        <th class="px-4 py-3.5">Tipo de Evento</th>
                        <th class="px-4 py-3.5">Data / Hora Efetiva</th>
                        <th class="px-4 py-3.5">Justificativa do Trabalhador</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $e)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                <span class="font-mono text-gray-900 block font-bold">{{ $e->created_at->format('d/m/Y H:i') }}</span>
                                <span class="text-[10px] text-gray-400">Por: {{ $e->requester?->name }}</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ $e->employee?->user?->name ?? 'Colaborador #' . $e->employee_id }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-bold text-gray-800">
                                    {{ $e->type->label() }}
                                </span>
                                @if($e->referencePunch)
                                    <span class="text-[10px] text-gray-400 block font-mono">Ref. NSR #{{ sprintf('%09d', $e->referencePunch->nsr) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700 whitespace-nowrap">
                                {{ $e->effective_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 max-w-xs">
                                <p class="line-clamp-2" title="{{ $e->reason_text }}">{{ $e->reason_text }}</p>
                                @if($e->status === TreatmentEventStatus::Rejected && $e->rejection_reason)
                                    <p class="text-[11px] text-rose-600 mt-1"><strong>Motivo recusa:</strong> {{ $e->rejection_reason }}</p>
                                @endif
                            </td>
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
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($e->status === TreatmentEventStatus::Pending)
                                    <div class="inline-flex items-center gap-1.5">
                                        <button wire:click="approve('{{ $e->id }}')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-xs transition">
                                            Aprovar
                                        </button>
                                        <button wire:click="openRejectModal('{{ $e->id }}')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg font-bold text-xs transition">
                                            Recusar
                                        </button>
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-400">Decidido</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
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

    <!-- Modal de Rejeição -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-base font-bold text-rose-700">Recusar Solicitação de Tratamento</h3>
                    <button wire:click="$set('showRejectModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit="confirmReject" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Motivo da Recusa (Obrigatório)</label>
                        <textarea wire:model="rejectionReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Informe formalmente o motivo da não aprovação..." required></textarea>
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
