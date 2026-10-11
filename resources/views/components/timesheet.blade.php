<?php

use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Espelho de Ponto')] class extends Component
{
    use WithFileUploads;

    public $month;
    public $year;
    public $userId;

    public string $userSearch = '';

    public $showTreatmentModal = false;
    public $reqType = 'manual_punch_added';
    public $reqDate = '';
    public $reqTime = '08:00';
    public $reqReasonCategory = 'medical_certificate';
    public $reqReason = '';
    public $reqAttachment = null;
    public $reqReferencePunchId = null;
    public array $availablePunchesForDate = [];
    public bool $isSubmitting = false;

    // Modal de Detalhes da Decisão do Tratamento
    public bool $showTreatmentDetailsModal = false;
    public ?array $viewingTreatment = null;

    public function mount(?int $userId = null, ?int $month = null, ?int $year = null): void
    {
        $this->month = $month ?? (int) (request('month') ?? now()->month);
        $this->year = $year ?? (int) (request('year') ?? now()->year);

        $targetId = $userId ?? (int) request('userId', Auth::id());
        $this->validateAuthorizedUserId($targetId);
        $this->userId = $targetId;
        $this->reqDate = now()->toDateString();
    }

    public function updatingUserId($value): void
    {
        $this->validateAuthorizedUserId((int) $value);
    }

    /**
     * Validação rigorosa de autorização no servidor (anti-tampering de Livewire).
     * - Colaborador: visualiza unicamente o seu próprio espelho.
     * - Gestor: visualiza somente colaboradores dos setores geridos por ele.
     * - Admin: visualiza colaboradores da empresa/instalação dedicada.
     */
    protected function validateAuthorizedUserId(int $targetUserId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        if ($targetUserId === (int) $currentUser->id) {
            return;
        }

        if ($currentUser->role === UserRole::Admin) {
            if (! User::where('id', $targetUserId)->exists()) {
                abort(404, 'Colaborador não encontrado.');
            }
            return;
        }

        if ($currentUser->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $allowedUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');

            if (! $allowedUserIds->contains($targetUserId)) {
                abort(403, 'Acesso não autorizado aos dados deste colaborador.');
            }
            return;
        }

        abort(403, 'Acesso restrito ao próprio espelho de ponto.');
    }

    public function selectUser(int $id): void
    {
        $this->validateAuthorizedUserId($id);
        $this->userId = $id;
        $this->userSearch = '';
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate((int) $this->year, (int) $this->month, 1)->subMonth();
        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate((int) $this->year, (int) $this->month, 1)->addMonth();
        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function openTreatmentModal(?string $date = null, ?string $type = null, ?string $punchId = null): void
    {
        $this->validateAuthorizedUserId((int) $this->userId);

        $parsedDate = $date ? Carbon::parse($date) : now();
        if (ClosedPeriod::isClosed($parsedDate->year, $parsedDate->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Período Fechado',
                'message' => sprintf('A competência %02d/%04d encontra-se fechada e congelada. Solicitações retroativas não são permitidas.', $parsedDate->month, $parsedDate->year),
                'buttonText' => 'Entendido',
            ]);
            return;
        }

        $this->reqDate = $date ?? now()->toDateString();
        $this->reqType = $type ?? ($punchId ? 'punch_disregarded' : 'manual_punch_added');
        $this->reqTime = '08:00';
        $this->reqReasonCategory = 'medical_certificate';
        $this->reqReason = '';
        $this->reqAttachment = null;
        $this->isSubmitting = false;

        $this->loadAvailablePunchesForDate();

        if ($punchId) {
            $this->reqReferencePunchId = $punchId;
        } elseif ($this->reqType === 'punch_disregarded' && ! empty($this->availablePunchesForDate)) {
            $this->reqReferencePunchId = $this->availablePunchesForDate[0]['id'];
        } else {
            $this->reqReferencePunchId = null;
        }

        $this->resetErrorBag();
        $this->showTreatmentModal = true;
    }

    public function openTreatmentModalForPunch(string $dateOrPunchId, ?string $punchId = null): void
    {
        if ($punchId === null) {
            $punch = PunchEvent::find($dateOrPunchId);
            $date = $punch ? $punch->occurred_at_local->format('Y-m-d') : now()->toDateString();
            $this->openTreatmentModal($date, 'punch_disregarded', $dateOrPunchId);
        } else {
            $this->openTreatmentModal($dateOrPunchId, 'punch_disregarded', $punchId);
        }
    }

    public function closeTreatmentModal(): void
    {
        $this->showTreatmentModal = false;
        $this->isSubmitting = false;
    }

    public function updatedReqDate($value): void
    {
        $this->loadAvailablePunchesForDate();
        if ($this->reqType === 'punch_disregarded' && ! empty($this->availablePunchesForDate)) {
            $this->reqReferencePunchId = $this->availablePunchesForDate[0]['id'];
        } else {
            $this->reqReferencePunchId = null;
        }
    }

    public function updatedReqType($value): void
    {
        if ($value === 'punch_disregarded') {
            $this->loadAvailablePunchesForDate();
            if (! empty($this->availablePunchesForDate) && ! $this->reqReferencePunchId) {
                $this->reqReferencePunchId = $this->availablePunchesForDate[0]['id'];
            }
        } elseif ($value === 'absence_justified') {
            if (! $this->reqReasonCategory) {
                $this->reqReasonCategory = 'medical_certificate';
            }
        }
    }

    public function loadAvailablePunchesForDate(): void
    {
        if (! $this->reqDate) {
            $this->availablePunchesForDate = [];
            return;
        }

        $employee = Employee::where('user_id', $this->userId)->first();
        if (! $employee) {
            $this->availablePunchesForDate = [];
            return;
        }

        $dateStr = Carbon::parse($this->reqDate)->toDateString();

        $punches = \App\Models\PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $this->userId);
        })
            ->whereDate('occurred_at_local', $dateStr)
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        if ($punches->isEmpty()) {
            $legacy = \App\Models\TimeEntry::where('user_id', $this->userId)
                ->whereDate('timestamp', $dateStr)
                ->orderBy('timestamp', 'asc')
                ->get();

            $this->availablePunchesForDate = $legacy->map(fn ($e) => [
                'id' => (string) $e->id,
                'time' => Carbon::parse($e->timestamp)->format('H:i'),
                'direction' => $e->type === 'in' ? 'Entrada' : 'Saída',
                'label' => sprintf('%s às %s (Registro Histórico)', $e->type === 'in' ? 'Entrada' : 'Saída', Carbon::parse($e->timestamp)->format('H:i:s')),
            ])->all();
            return;
        }

        $this->availablePunchesForDate = $punches->map(fn ($p) => [
            'id' => (string) $p->id,
            'time' => $p->occurred_at_local->format('H:i'),
            'direction' => $p->direction === 'in' ? 'Entrada' : 'Saída',
            'label' => sprintf('%s às %s (NSR #%s)', $p->direction === 'in' ? 'Entrada' : 'Saída', $p->occurred_at_local->format('H:i:s'), str_pad((string)$p->nsr, 6, '0', STR_PAD_LEFT)),
        ])->all();
    }

    public function submitTreatmentRequest(): void
    {
        $this->validateAuthorizedUserId((int) $this->userId);

        $currentUser = Auth::user();
        // Colaborador comum não pode solicitar ajuste em nome de terceiros
        if ($currentUser->role === UserRole::Employee && (int) $this->userId !== (int) $currentUser->id) {
            abort(403, 'Você não possui permissão para solicitar ajustes para outro colaborador.');
        }

        if ($this->isSubmitting) {
            return;
        }
        $this->isSubmitting = true;

        $rules = [
            'reqDate' => 'required|date',
            'reqType' => 'required|in:manual_punch_added,absence_justified,punch_disregarded',
            'reqReason' => 'required|string|min:5|max:500',
        ];

        if ($this->reqType === 'manual_punch_added') {
            $rules['reqTime'] = 'required|date_format:H:i';
            $rules['reqAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        } elseif ($this->reqType === 'absence_justified') {
            $rules['reqReasonCategory'] = 'required|in:medical_certificate,medical_appointment,bereavement,wedding,blood_donation,court_summons,other';

            // Requisito 5: Obrigatoriedade estrita de documentos para atestados médicos (conforme política)
            $requiresAttachment = in_array($this->reqReasonCategory, ['medical_certificate'], true);
            if ($requiresAttachment) {
                $rules['reqAttachment'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
            } else {
                $rules['reqAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
            }
        } elseif ($this->reqType === 'punch_disregarded') {
            $rules['reqReferencePunchId'] = 'required|string';
            $rules['reqAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $messages = [
            'reqReason.required' => 'A justificativa é obrigatória para análise do RH/Gestor.',
            'reqReason.min' => 'A justificativa deve conter pelo menos 5 caracteres explicativos.',
            'reqReason.max' => 'A justificativa não pode ultrapassar 500 caracteres.',
            'reqTime.required' => 'Informe o horário previsto para a batida esquecida.',
            'reqTime.date_format' => 'Horário em formato inválido (HH:MM).',
            'reqReferencePunchId.required' => 'Selecione uma marcação existente do dia para desconsiderar.',
            'reqAttachment.required' => 'O anexo comprobatório (atestado médico) é obrigatório para este motivo legal.',
            'reqAttachment.mimes' => 'O anexo deve ser um documento nos formatos PDF, JPG, JPEG ou PNG.',
            'reqAttachment.max' => 'O anexo não pode ultrapassar 5MB.',
        ];

        try {
            $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSubmitting = false;
            throw $e;
        }

        $parsedDate = Carbon::parse($this->reqDate);
        if (ClosedPeriod::isClosed($parsedDate->year, $parsedDate->month)) {
            $this->isSubmitting = false;
            $this->addError('reqDate', 'Esta competência encontra-se formalmente fechada e congelada fiscalmente.');
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Competência Fechada',
                'message' => 'Esta competência encontra-se formalmente fechada e congelada fiscalmente. Solicitações retroativas não são permitidas.',
                'buttonText' => 'Fechar',
            ]);
            return;
        }

        $employee = Employee::where('user_id', $this->userId)->first();
        if (! $employee) {
            $this->isSubmitting = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Perfil Incompleto',
                'message' => 'Colaborador não possui cadastro funcional ativo para vincular o tratamento.',
                'buttonText' => 'OK',
            ]);
            return;
        }

        $typeEnum = match ($this->reqType) {
            'manual_punch_added' => TreatmentEventType::ManualPunchAdded,
            'absence_justified' => TreatmentEventType::AbsenceJustified,
            'punch_disregarded' => TreatmentEventType::PunchDisregarded,
        };

        $referencePunchId = null;
        $reasonCode = null;
        $newValueJson = null;

        if ($this->reqType === 'manual_punch_added') {
            $effectiveAt = Carbon::parse($this->reqDate . ' ' . $this->reqTime);
            $newValueJson = [
                'suggested_time' => $this->reqTime,
                'effective_date' => $this->reqDate,
            ];
        } elseif ($this->reqType === 'absence_justified') {
            // Requisito 1: Não exigir artificialmente horário de batida para justificar o dia inteiro
            $effectiveAt = Carbon::parse($this->reqDate)->startOfDay();
            $reasonCode = $this->reqReasonCategory;
            $newValueJson = [
                'reason_category' => $this->reqReasonCategory,
                'period_type' => 'full_day',
            ];
        } else {
            // punch_disregarded
            $referencePunchId = $this->reqReferencePunchId;
            $punch = \App\Models\PunchEvent::find($referencePunchId);
            if ($punch) {
                $effectiveAt = $punch->occurred_at_local;
                $newValueJson = [
                    'original_nsr' => $punch->nsr,
                    'original_time' => $punch->occurred_at_local->format('H:i:s'),
                    'direction' => $punch->direction,
                ];
            } else {
                $legacy = \App\Models\TimeEntry::find($referencePunchId);
                if ($legacy) {
                    $effectiveAt = Carbon::parse($legacy->timestamp);
                    $newValueJson = [
                        'legacy_entry_id' => $legacy->id,
                        'original_time' => Carbon::parse($legacy->timestamp)->format('H:i:s'),
                    ];
                } else {
                    $effectiveAt = Carbon::parse($this->reqDate . ' 12:00:00');
                }
            }
        }

        // Armazenamento em disco privado (local) para proteger documentos sensíveis (LGPD)
        $attachmentPath = null;
        if ($this->reqAttachment) {
            $attachmentPath = $this->reqAttachment->store('treatment_attachments', 'local');
        }

        try {
            app(\App\Domain\PTRP\Actions\RequestTreatmentEventAction::class)->execute(
                employee: $employee,
                type: $typeEnum,
                effectiveAt: $effectiveAt,
                reasonText: $this->reqReason,
                requestedBy: $currentUser,
                referencePunchId: $referencePunchId,
                newValueJson: $newValueJson,
                attachmentPath: $attachmentPath,
                reasonCode: $reasonCode,
            );

            $this->showTreatmentModal = false;
            $this->reqAttachment = null;
            $this->reqReason = '';
            $this->isSubmitting = false;

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Solicitação Enviada com Sucesso!',
                'message' => 'Sua solicitação de ajuste foi registrada com status Pendente e enviada para análise da chefia imediata/RH.',
                'buttonText' => 'Entendido',
            ]);
        } catch (\DomainException $de) {
            // Erros de regra de domínio (duplicidade ou período fechado): mantém dados preenchidos
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $this->isSubmitting = false;

            if ($this->reqType === 'punch_disregarded') {
                $this->addError('reqReferencePunchId', $de->getMessage());
            } else {
                $this->addError('reqReason', $de->getMessage());
            }

            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Atenção na Solicitação',
                'message' => $de->getMessage(),
                'buttonText' => 'Revisar',
            ]);
        } catch (\Throwable $e) {
            // Em caso de falha técnica inesperada, remove arquivo temporário e não expõe stack trace
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $this->isSubmitting = false;

            \Illuminate\Support\Facades\Log::error('timesheet.treatment_submit_error', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Requisito 6: Mensagem de erro amigável ao usuário
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Enviar Solicitação',
                'message' => 'Não foi possível enviar a solicitação. Tente novamente.',
                'buttonText' => 'Fechar',
            ]);
        }
    }

    public function viewTreatmentDetails(string $treatmentId): void
    {
        $this->validateAuthorizedUserId((int) $this->userId);

        $treatment = \App\Models\TreatmentEvent::with(['requester', 'approver', 'rejecter', 'referencePunch'])->find($treatmentId);
        if (! $treatment) {
            return;
        }

        $this->viewingTreatment = [
            'id' => $treatment->id,
            'type_label' => $treatment->type->label(),
            'status' => $treatment->status->value,
            'status_label' => match ($treatment->status) {
                \App\Domain\PTRP\Enums\TreatmentEventStatus::Pending => 'Pendente de Análise',
                \App\Domain\PTRP\Enums\TreatmentEventStatus::Approved => 'Aprovada',
                \App\Domain\PTRP\Enums\TreatmentEventStatus::Rejected => 'Rejeitada',
            },
            'effective_date' => $treatment->effective_at->format('d/m/Y'),
            'effective_time' => $treatment->effective_at->format('H:i'),
            'reason_code' => $treatment->reason_code,
            'reason_text' => $treatment->reason_text,
            'rejection_reason' => $treatment->rejection_reason,
            'decided_at' => $treatment->decided_at ? $treatment->decided_at->format('d/m/Y H:i') : null,
            'decided_by' => $treatment->approver?->name ?? $treatment->rejecter?->name,
            'requested_by' => $treatment->requester?->name,
            'has_attachment' => ! empty($treatment->attachment_path),
        ];

        $this->showTreatmentDetailsModal = true;
    }

    public function closeTreatmentDetailsModal(): void
    {
        $this->showTreatmentDetailsModal = false;
        $this->viewingTreatment = null;
    }

    protected function maskCpf(?string $cpf): string
    {
        $clean = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-**';
        }

        return substr($clean, 0, 3) . '.***.***-' . substr($clean, -2);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->validateAuthorizedUserId((int) $this->userId);
        $currentUser = Auth::user();

        $isAdmin = $currentUser->role === UserRole::Admin;
        $isManager = $currentUser->role === UserRole::Manager;

        $targetUser = User::with('employee.sector')->findOrFail($this->userId);

        // Busca autorizada com paginação e máscara de CPF (sem expor lista total no frontend)
        $canSelectUser = $isAdmin || $isManager;
        $searchResults = collect([]);

        if ($canSelectUser && $this->userSearch !== '') {
            $term = trim($this->userSearch);
            $cleanTerm = preg_replace('/\D/', '', $term);

            $query = User::with('employee')
                ->where(function ($q) use ($term, $cleanTerm) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");

                    if ($cleanTerm !== '') {
                        $q->orWhereHas('employee', fn ($eq) => $eq->where('cpf', 'like', "%{$cleanTerm}%"));
                    }
                });

            if ($isManager) {
                $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
                $allowedIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id')->push($currentUser->id);
                $query->whereIn('id', $allowedIds);
            }

            $searchResults = $query->orderBy('name')
                ->take(15)
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'masked_cpf' => $this->maskCpf($u->employee?->cpf),
                    'job_title' => $u->employee?->job_title ?? ($u->role ? $u->role->label() : 'Colaborador'),
                ]);
        }

        // Resolução Oficial via Domínio PTRP
        $ptrpData = app(TimesheetJourneyService::class)->resolveMonthData(
            targetUser: $targetUser,
            year: (int) $this->year,
            month: (int) $this->month
        );

        return array_merge($ptrpData, [
            'canSelectUser' => $canSelectUser,
            'targetUser' => $targetUser,
            'searchResults' => $searchResults,
        ]);
    }
};
?>

<div class="max-w-7xl mx-auto py-3 sm:py-6 px-2 sm:px-6 lg:px-8 space-y-4 sm:space-y-6">

    {{-- 1. CABEÇALHO CORPORATIVO COM HIERARQUIA DE AÇÕES --}}
    <header class="bg-white rounded-2xl border border-gray-200/80 p-4 sm:p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Espelho de Ponto</h1>
                    @if($isClosedPeriod)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-300">
                            Competência Fechada (Snapshot v{{ $snapshotVersion }})
                        </span>
                    @endif
                </div>
                <p class="text-xs sm:text-sm text-gray-500 mt-1">
                    Consulte marcações, acompanhe sua jornada e solicite correções.
                </p>
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 text-xs text-gray-600">
                    <div class="w-2 h-2 rounded-full {{ $isClosedPeriod ? 'bg-slate-400' : 'bg-emerald-500' }}"></div>
                    <span>Histórico auditado sob o PTRP (Portaria 671/2021 MTP) · Colaborador: <strong class="text-gray-900 font-semibold">{{ $targetUser->name }}</strong></span>
                </div>
            </div>

            {{-- Ações em Hierarquia (Mobile Friendly) --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-gray-100">
                @if(! $isClosedPeriod)
                    <button wire:click="openTreatmentModal" 
                            type="button"
                            class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 cursor-pointer">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Solicitar ajuste</span>
                    </button>
                @else
                    <span class="min-h-[44px] inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-gray-100 text-gray-500 rounded-xl text-xs font-semibold border border-gray-200 select-none" 
                          title="Competência formalmente fechada pelo DP/RH. Solicitações bloqueadas.">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span>Período Fechado</span>
                    </span>
                @endif

                <div class="flex items-center gap-2">
                    <a href="{{ route('folha-ponto', ['userId' => $this->userId, 'month' => $this->month, 'year' => $this->year]) }}" 
                       class="flex-1 sm:flex-initial min-h-[44px] inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 active:bg-gray-100 text-gray-700 border border-gray-300 rounded-xl text-xs sm:text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span>Imprimir folha de ponto</span>
                    </a>

                    <a href="{{ route('home') }}" 
                       class="min-h-[44px] inline-flex items-center justify-center gap-1 px-3 py-2 text-indigo-600 hover:text-indigo-800 text-xs sm:text-sm font-semibold rounded-xl hover:bg-indigo-50/60 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        <span>Bater Ponto</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    {{-- 2. FILTRO DE COMPETÊNCIA E BUSCA SEGURA DE COLABORADOR --}}
    <section class="bg-white rounded-2xl border border-gray-200/80 p-3 sm:p-4 shadow-2xs">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            
            {{-- Navegador de Competência: [Anterior] Mês de Ano [Próximo] --}}
            <div class="flex items-center justify-between gap-1 sm:gap-2 bg-gray-50 border border-gray-200 rounded-xl p-1">
                <button type="button" 
                        wire:click="previousMonth" 
                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 rounded-lg text-gray-600 hover:text-indigo-600 hover:bg-white active:bg-gray-100 transition cursor-pointer"
                        aria-label="Mês anterior"
                        title="Mês anterior">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="flex items-center gap-2 px-2">
                    <span class="text-xs sm:text-sm font-bold text-gray-900 capitalize font-mono tabular-nums">
                        {{ Carbon::createFromDate((int) $this->year, (int) $this->month, 1)->translatedFormat('F \d\e Y') }}
                    </span>
                </div>

                <button type="button" 
                        wire:click="nextMonth" 
                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 rounded-lg text-gray-600 hover:text-indigo-600 hover:bg-white active:bg-gray-100 transition cursor-pointer"
                        aria-label="Próximo mês"
                        title="Próximo mês">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                {{-- Alternativa direta de seleção rápida de Mês e Ano (Desktop) --}}
                <div class="hidden md:flex items-center gap-1.5 pl-2 border-l border-gray-200">
                    <select wire:model.live="month" class="text-xs py-1.5 px-2 border border-gray-300 rounded-lg bg-white text-gray-700 font-medium focus:ring-2 focus:ring-indigo-500" aria-label="Mês de apuração">
                        @for($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }} - {{ Carbon::create(null, $i, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                    <select wire:model.live="year" class="text-xs py-1.5 px-2 border border-gray-300 rounded-lg bg-white text-gray-700 font-medium focus:ring-2 focus:ring-indigo-500" aria-label="Ano de apuração">
                        @for($i = now()->year - 8; $i <= now()->year + 1; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- Alternativa direta em Mobile (Selects Compactos) --}}
            <div class="grid grid-cols-2 gap-2 md:hidden">
                <div>
                    <label class="sr-only">Mês</label>
                    <select wire:model.live="month" class="w-full min-h-[44px] text-xs py-2 px-3 border border-gray-300 rounded-xl bg-white text-gray-700 font-medium focus:ring-2 focus:ring-indigo-500">
                        @for($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }} - {{ Carbon::create(null, $i, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="sr-only">Ano</label>
                    <select wire:model.live="year" class="w-full min-h-[44px] text-xs py-2 px-3 border border-gray-300 rounded-xl bg-white text-gray-700 font-medium focus:ring-2 focus:ring-indigo-500">
                        @for($i = now()->year - 8; $i <= now()->year + 1; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- Filtro de Colaborador (Exibido somente para Admin ou Gestor Autorizado) --}}
            @if($canSelectUser)
            <div class="w-full lg:max-w-md relative" x-data="{ open: false }">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="userSearch"
                           @focus="open = true"
                           @click.outside="open = false"
                           placeholder="Buscar colaborador: {{ $targetUser->name }}"
                           class="block w-full min-h-[44px] pl-9 pr-9 py-2 text-xs sm:text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" />
                    @if($userSearch !== '')
                        <button type="button"
                                wire:click="$set('userSearch', '')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                                aria-label="Limpar busca">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>

                {{-- Autocomplete Dropdown Seguro com CPF Mascarado --}}
                @if($searchResults->isNotEmpty() || $userSearch !== '')
                <div x-show="open && $wire.userSearch.length > 0"
                     x-cloak
                     class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto bg-white rounded-xl shadow-xl border border-gray-200 py-1 text-sm divide-y divide-gray-100">
                    @forelse($searchResults as $u)
                        <button type="button"
                                wire:click="selectUser({{ $u['id'] }})"
                                @click="open = false"
                                class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 transition hover:bg-gray-50 cursor-pointer {{ (int)$userId === (int)$u['id'] ? 'bg-indigo-50/80 font-semibold' : '' }}">
                            <div class="truncate">
                                <p class="text-xs sm:text-sm font-medium text-gray-900 truncate">{{ $u['name'] }}</p>
                                <p class="text-[11px] text-gray-500 truncate">{{ $u['job_title'] }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ $u['masked_cpf'] }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="px-4 py-3 text-xs text-gray-500 text-center">
                            Nenhum colaborador autorizado com "<span class="font-medium">{{ $userSearch }}</span>".
                        </div>
                    @endforelse
                </div>
                @endif
            </div>
            @endif
        </div>
    </section>

    {{-- 3. BANCO DE HORAS (SE ATIVADO NA POLÍTICA VIGENTE E OPERADO PELO VÍNCULO) --}}
    @if(($operatesTimeBank ?? false) && $timeBankSummary && !($timeBankSummary->isHistoricalLimitation && empty($timeBankSummary->transactions)))
    <section x-data="{ showReconciliation: false }" class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 shadow-2xs border border-slate-800 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-white tracking-tight">Banco de Horas</h2>
                    <p class="text-[11px] text-slate-400">
                        Regra de apuração: {{ $timeBankSummary->policyName ?? ($policy?->closing_mode?->label() ?? 'Banco de Horas Ativo') }}
                    </p>
                </div>
            </div>

            <div class="text-left sm:text-right">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">
                    {{ $isClosedPeriod ? 'Saldo Fechado da Competência' : 'Saldo ao Final da Competência' }}
                </span>
                <span class="text-xl sm:text-2xl font-bold font-mono tabular-nums {{ $timeBankSummary->closingBalanceMinutes >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $timeBankSummary->formattedClosingBalance() }}
                </span>
                @if(!$isClosedPeriod && $timeBankSummary->todayBalanceMinutes !== $timeBankSummary->closingBalanceMinutes)
                    <span class="text-[10px] text-slate-400 block font-sans">
                        Saldo geral atual: <strong class="font-mono text-slate-300">{{ $timeBankSummary->formattedTodayBalance() }}</strong>
                    </span>
                @endif
            </div>
        </div>

        {{-- Grid de Movimentações: 2 colunas no celular, até 6 no desktop --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs font-mono">
            {{-- 1. Saldo Anterior --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-slate-400 text-[10px] uppercase font-sans font-semibold block">Saldo Anterior</span>
                <span class="font-bold text-slate-200 text-sm tabular-nums mt-0.5 block">
                    {{ $timeBankSummary->formattedPreviousBalance() }}
                </span>
            </div>

            {{-- 2. Créditos do Mês --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-emerald-400 text-[10px] uppercase font-sans font-semibold block">Créditos</span>
                <span class="font-bold text-emerald-400 text-sm tabular-nums mt-0.5 block">
                    +{{ $timeBankSummary->formattedMonthCredits() }}
                </span>
            </div>

            {{-- 3. Débitos do Mês --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-rose-400 text-[10px] uppercase font-sans font-semibold block">Débitos</span>
                <span class="font-bold text-rose-400 text-sm tabular-nums mt-0.5 block">
                    {{ $timeBankSummary->formattedMonthDebits() }}
                </span>
            </div>

            {{-- 4. Ajustes Manuais --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-amber-400 text-[10px] uppercase font-sans font-semibold block">Ajustes</span>
                <span class="font-bold text-amber-400 text-sm tabular-nums mt-0.5 block">
                    {{ $timeBankSummary->formattedMonthAdjustments() }}
                </span>
            </div>

            {{-- 5. Compensações / Baixas --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-indigo-400 text-[10px] uppercase font-sans font-semibold block">Compensações</span>
                <span class="font-bold text-indigo-400 text-sm tabular-nums mt-0.5 block">
                    {{ $timeBankSummary->formattedCompensations() }}
                </span>
            </div>

            {{-- 6. Saldo Final da Competência --}}
            <div class="bg-slate-800/60 p-2.5 rounded-xl border border-slate-800">
                <span class="text-slate-300 text-[10px] uppercase font-sans font-semibold block">Saldo Final</span>
                <span class="font-bold text-sm tabular-nums mt-0.5 block {{ $timeBankSummary->closingBalanceMinutes >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $timeBankSummary->formattedClosingBalance() }}
                </span>
            </div>
        </div>

        {{-- Alerta de Zeramento Histórico Registrado (se aplicável) --}}
        @if($timeBankSummary->closingResetMinutes !== 0)
            <div class="p-3 border border-amber-900/60 bg-amber-950/40 rounded-xl text-xs space-y-1">
                <div class="flex items-center justify-between gap-2 flex-wrap font-mono">
                    <span class="font-sans font-bold text-amber-300">Zeramento histórico registrado:</span>
                    <span class="font-bold text-amber-400 tabular-nums">{{ $timeBankSummary->formattedClosingReset() }}</span>
                </div>
                <p class="text-[11px] text-amber-200/70 font-sans">
                    Competência encerrada sob regra legada de zeramento automático no fechamento. Este registro não comprova quitação fiscal fática sem evidência financeira anexa.
                </p>
            </div>
        @endif

        {{-- Destinações Realizadas Após o Fechamento da Competência --}}
        @if($timeBankSummary->postClosingSettlements && $timeBankSummary->postClosingSettlements->isNotEmpty())
            <div class="p-3 border border-slate-800 bg-slate-950/80 rounded-xl text-xs space-y-2">
                <span class="font-sans font-bold text-slate-300 block">
                    Movimentações posteriores ao fechamento (informativo auditado):
                </span>
                <div class="space-y-1 font-mono text-[11px]">
                    @foreach($timeBankSummary->postClosingSettlements as $pcs)
                        <div class="flex items-center justify-between text-slate-400">
                            <span>{{ $pcs->operation_date?->format('d/m/Y') ?? 'Data N/D' }}: {{ $pcs->settlement_type?->label() ?? 'Destinação' }}</span>
                            <span class="font-bold text-slate-200 tabular-nums">{{ \App\Models\TimeBankAccount::formatMinutes($pcs->minutes) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-slate-500 font-sans">
                    Lançamentos ocorridos após o fechamento não alteram o saldo histórico congelado no snapshot.
                </p>
            </div>
        @endif

        {{-- Conciliação Contábil & Detalhamento Auditado (Acordeão) --}}
        @if(isset($reconciliationResult))
            <div class="pt-2 border-t border-slate-800/80">
                <button type="button"
                    @click="showReconciliation = !showReconciliation"
                    class="flex items-center justify-between w-full text-left py-1 text-xs text-slate-400 hover:text-slate-200 transition-colors">
                    <span class="flex items-center gap-1.5 font-medium">
                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        <span>Auditoria e Conciliação do Ledger</span>
                        <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold {{ $reconciliationResult->badgeClass }}">
                            {{ $reconciliationResult->statusLabel }}
                        </span>
                    </span>
                    <span class="text-[11px] text-indigo-400 font-medium" x-text="showReconciliation ? 'Ocultar detalhes ▲' : 'Ver conciliação ▼'"></span>
                </button>

                <div x-show="showReconciliation" x-cloak class="mt-2.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800 space-y-2 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] font-mono">
                        <div class="text-slate-400">
                            Saldo Inicial + Movimentos: <strong class="text-slate-200">{{ $reconciliationResult->formattedExpectedBalance() }}</strong>
                        </div>
                        <div class="text-slate-400">
                            Saldo Final Apurado: <strong class="text-slate-200">{{ $reconciliationResult->formattedRecordedBalance() }}</strong>
                        </div>
                    </div>

                    @if(!empty($reconciliationResult->issues))
                        <div class="space-y-1 pt-1.5 border-t border-slate-800/60">
                            <span class="font-bold text-amber-400 text-[11px] block">Ocorrências Auditadas:</span>
                            @foreach($reconciliationResult->issues as $issue)
                                <p class="text-[11px] text-slate-300 flex items-start gap-1.5">
                                    <span class="text-amber-400 shrink-0 select-none">•</span>
                                    <span>{{ $issue }}</span>
                                </p>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($reconciliationResult->divergencesExplanation))
                        <div class="space-y-1 pt-1.5 border-t border-slate-800/60">
                            <span class="font-bold text-indigo-300 text-[11px] block">Diferença entre Apuração PTRP e Banco:</span>
                            @foreach($reconciliationResult->divergencesExplanation as $divExp)
                                <p class="text-[11px] text-slate-300 flex items-start gap-1.5">
                                    <span class="text-indigo-400 shrink-0 select-none">•</span>
                                    <span>{{ $divExp }}</span>
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </section>
    @elseif(isset($timeBankSummary) && $timeBankSummary->isHistoricalLimitation)
        <div class="p-3.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600">
            <strong>Limitação histórica:</strong> {{ $timeBankSummary->historicalLimitationMessage }}
        </div>
    @endif

    {{-- RESUMO DAS DIFERENÇAS DE JORNADA (PTRP) --}}
    @if(isset($differencesSummary) && ($totalPunches > 0 || $totalWorkedMinutes > 0 || $differencesSummary->concludedDaysCount > 0))
    <section class="bg-white rounded-2xl border border-gray-200/90 p-4 sm:p-5 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xs sm:text-sm font-bold text-gray-900 tracking-tight flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        <span>Resumo das diferenças de jornada</span>
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $differencesSummary->isDefinitive() ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                        {{ $differencesSummary->statusLabel }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    Comparativo analítico entre horas previstas e apuradas na competência (independente de banco de horas).
                </p>
            </div>

            @if($differencesSummary->destinationDescription)
                <div class="text-left sm:text-right">
                    <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider block">Destinação</span>
                    <span class="text-xs font-semibold text-gray-700">
                        {{ $differencesSummary->settlementModality?->label() ?? 'Regime Padrão' }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Grid de Diferenças: Positivas, Negativas, Líquida Matemática --}}
        <div class="grid grid-cols-1 min-[360px]:grid-cols-3 gap-3">
            {{-- Positivas --}}
            <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3">
                <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Diferenças Positivas</span>
                <p class="text-xl sm:text-2xl font-bold font-mono tabular-nums text-emerald-700 mt-1">
                    {{ $differencesSummary->formattedPositive() }}
                </p>
                <p class="text-[11px] text-emerald-800/80 mt-0.5">
                    Horas apuradas acima do previsto
                </p>
            </div>

            {{-- Negativas --}}
            <div class="bg-rose-50/50 border border-rose-100 rounded-xl p-3">
                <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wider block">Diferenças Negativas</span>
                <p class="text-xl sm:text-2xl font-bold font-mono tabular-nums text-rose-700 mt-1">
                    {{ $differencesSummary->formattedNegative() }}
                </p>
                <p class="text-[11px] text-rose-800/80 mt-0.5">
                    Atrasos e ausências não justificadas
                </p>
            </div>

            {{-- Líquida Matemática --}}
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[10px] font-bold text-slate-700 uppercase tracking-wider block">Diferença Líquida</span>
                    <span class="text-[10px] text-slate-500 font-medium">Cálculo matemático</span>
                </div>
                <p class="text-xl sm:text-2xl font-bold font-mono tabular-nums {{ $differencesSummary->netMinutes > 0 ? 'text-emerald-700' : ($differencesSummary->netMinutes < 0 ? 'text-rose-700' : 'text-slate-700') }} mt-1">
                    {{ $differencesSummary->formattedNet() }}
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Saldo matemático da competência
                </p>
            </div>
        </div>

        {{-- Quatro Indicadores da Relação Laboral --}}
        <div class="pt-3 border-t border-gray-100">
            <span class="text-[11px] font-bold text-gray-700 uppercase tracking-wider block mb-2">
                Conciliação das Etapas de Apuração
            </span>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 text-xs font-mono">
                {{-- 1. Diferença de jornada --}}
                <div class="bg-gray-50/70 p-2.5 rounded-xl border border-gray-200">
                    <span class="text-gray-500 text-[10px] uppercase font-sans font-semibold block">1. Apuração PTRP</span>
                    <span class="font-bold text-gray-900 text-sm tabular-nums mt-0.5 block">
                        {{ $differencesSummary->formattedNet() }}
                    </span>
                    <span class="text-[10px] text-gray-400 font-sans block mt-0.5">Tempo apurado bruto</span>
                </div>

                {{-- 2. Horas elegíveis --}}
                <div class="bg-gray-50/70 p-2.5 rounded-xl border border-gray-200">
                    <span class="text-gray-500 text-[10px] uppercase font-sans font-semibold block">2. Horas Elegíveis</span>
                    <span class="font-bold text-gray-900 text-sm tabular-nums mt-0.5 block">
                        {{ \App\Domain\PTRP\DTOs\CalculatedJourney::formatMinutes($differencesSummary->destinedToCompensationMinutes) }}
                    </span>
                    <span class="text-[10px] text-gray-400 font-sans block mt-0.5">Aptas à compensação</span>
                </div>

                {{-- 3. Banco contabilizado --}}
                <div class="bg-gray-50/70 p-2.5 rounded-xl border border-gray-200">
                    <span class="text-gray-500 text-[10px] uppercase font-sans font-semibold block">3. Banco Registrado</span>
                    <span class="font-bold text-gray-900 text-sm tabular-nums mt-0.5 block">
                        @if($timeBankSummary)
                            {{ $timeBankSummary->formattedClosingBalance() }}
                        @else
                            00:00
                        @endif
                    </span>
                    <span class="text-[10px] text-gray-400 font-sans block mt-0.5">Lançamentos no ledger</span>
                </div>

                {{-- 4. Destinação pendente --}}
                <div class="bg-gray-50/70 p-2.5 rounded-xl border border-gray-200">
                    <span class="text-gray-500 text-[10px] uppercase font-sans font-semibold block">4. Saldo Pendente</span>
                    <span class="font-bold text-amber-700 text-sm tabular-nums mt-0.5 block">
                        {{ \App\Domain\PTRP\DTOs\CalculatedJourney::formatMinutes($differencesSummary->pendingSettlementMinutes) }}
                    </span>
                    <span class="text-[10px] text-gray-400 font-sans block mt-0.5">Aguardando quitação</span>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 mt-2 font-sans">
                Estes valores podem divergir entre si conforme a absorção de tolerâncias legais (Art. 58 da CLT), destinações automáticas para pagamento em folha ou quitações periódicas acordadas.
            </p>
        </div>

        {{-- Situação de Destinação / Modalidade --}}
        @if($differencesSummary->destinationDescription)
            <div class="bg-indigo-50/40 border border-indigo-100/80 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                <div class="space-y-0.5">
                    <span class="font-bold text-indigo-950 block">Situação da Destinação Legal:</span>
                    <p class="text-indigo-900/80">{{ $differencesSummary->destinationDescription }}</p>
                </div>
                @if($differencesSummary->settlementModality === \App\Domain\Settlement\Enums\SettlementModality::MonthlyCompensation)
                    <div class="flex items-center gap-3 font-mono text-[11px] shrink-0">
                        @if($differencesSummary->destinedToCompensationMinutes > 0)
                            <span class="text-indigo-900">Destinadas: <strong>{{ \App\Domain\PTRP\DTOs\CalculatedJourney::formatMinutes($differencesSummary->destinedToCompensationMinutes) }}</strong></span>
                        @endif
                        @if($differencesSummary->settledMinutes > 0)
                            <span class="text-emerald-800">Quitadas: <strong>{{ \App\Domain\PTRP\DTOs\CalculatedJourney::formatMinutes($differencesSummary->settledMinutes) }}</strong></span>
                        @endif
                        @if($differencesSummary->pendingSettlementMinutes > 0)
                            <span class="text-amber-800">Pendentes: <strong>{{ \App\Domain\PTRP\DTOs\CalculatedJourney::formatMinutes($differencesSummary->pendingSettlementMinutes) }}</strong></span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Notas Normativas e Ressalvas do Resumo --}}
        @if(!empty($differencesSummary->notes))
            <div class="text-[11px] text-gray-500 space-y-1 pt-1 border-t border-gray-100">
                @foreach($differencesSummary->notes as $note)
                    <p class="flex items-start gap-1.5">
                        <span class="text-gray-400 shrink-0 select-none">•</span>
                        <span>{{ $note }}</span>
                    </p>
                @endforeach
            </div>
        @endif
    </section>
    @endif

    {{-- 4. CARDS DE INDICADORES OBJETIVOS (SEM ÍCONES GIGANTES SUPÉRFLUOS) --}}
    @if($totalPunches > 0 || $totalWorkedMinutes > 0)
    <section class="grid grid-cols-1 min-[360px]:grid-cols-2 lg:grid-cols-3 gap-3">
        
        {{-- Total Trabalhado --}}
        <div class="bg-white border border-gray-200/90 rounded-2xl p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Total Trabalhado</span>
                @if($isPartial)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200" title="Apuração parcial: existem dias com pendência no mês">
                        Parcial
                    </span>
                @elseif($isClosedPeriod)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        Fechado
                    </span>
                @endif
            </div>
            <p class="text-2xl font-bold text-gray-900 font-mono tabular-nums mt-1.5">{{ $totalMonthFormatted }}</p>
            <p class="text-[11px] text-gray-500 mt-1">
                @if($isClosedPeriod)
                    Apuração formal congelada em snapshot
                @else
                    Apuração minuto a minuto PTRP
                @endif
            </p>
        </div>

        {{-- Dias Trabalhados --}}
        <div class="bg-white border border-gray-200/90 rounded-2xl p-4 shadow-2xs">
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Dias Trabalhados</span>
            <p class="text-2xl font-bold text-emerald-800 font-mono tabular-nums mt-1.5">{{ $workedDaysCount }} {{ $workedDaysCount === 1 ? 'dia' : 'dias' }}</p>
            <p class="text-[11px] text-gray-500 mt-1">
                {{ $completedDaysCount }} concluídas @if($incompleteDaysCount > 0) · <span class="text-rose-600 font-semibold">{{ $incompleteDaysCount }} incompleta(s)</span>@endif
            </p>
        </div>

        {{-- Média Diária (Sem Roxo Aleatório) --}}
        <div class="bg-white border border-gray-200/90 rounded-2xl p-4 shadow-2xs min-[360px]:col-span-2 lg:col-span-1">
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Média Diária</span>
            <p class="text-2xl font-bold text-indigo-900 font-mono tabular-nums mt-1.5">{{ $avgFormatted }}</p>
            <p class="text-[11px] text-gray-500 mt-1 truncate" title="{{ $avgCriteria }}">{{ $avgCriteria }}</p>
        </div>
    </section>
    @endif

    {{-- 5. HISTÓRICO CRONOLÓGICO POR DATA & TIMELINE MOBILE-FIRST --}}
    <main class="space-y-3 sm:space-y-4" data-loading-class="opacity-50" wire:transition>
        @forelse($groupedEntries as $date => $dayEntries)
            <article class="bg-white border border-gray-200/90 rounded-2xl overflow-hidden shadow-2xs" wire:key="day-{{ $date }}" x-data="{ showDetails: false }">
                
                {{-- Cabeçalho do Dia (Mobile First) --}}
                <div class="bg-gray-50/80 px-3.5 sm:px-4 py-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xs sm:text-sm font-bold text-gray-900 capitalize flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            <span>{{ Carbon::parse($date)->isoFormat('dddd, LL') }}</span>
                        </h2>

                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-200/70 text-gray-700">
                            {{ count($dayEntries) }} {{ count($dayEntries) === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        @if(isset($daysCalculated[$date]))
                            @php $dayCalc = $daysCalculated[$date]; @endphp

                            @if(!empty($dayCalc['is_pending_configuration']))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200" title="Apuração pendente de escala configurada">
                                    Sem escala
                                </span>
                            @elseif(!empty($dayCalc['shift_code']))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Plantão
                                </span>
                            @elseif(!empty($dayCalc['is_day_off']))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    Folga
                                </span>
                            @endif

                            @if(($dayCalc['night_minutes'] ?? 0) > 0)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-purple-50 text-purple-800 border border-purple-200" title="Horas Noturnas: {{ intdiv($dayCalc['night_minutes'], 60) }}h {{ $dayCalc['night_minutes'] % 60 }}m">
                                    🌙 {{ sprintf('%02dh %02dm', intdiv($dayCalc['night_minutes'], 60), $dayCalc['night_minutes'] % 60) }}
                                </span>
                            @endif

                            {{-- Saldo / Diferença Diária Assinada --}}
                            @if(isset($dayCalc['formatted_difference']))
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold font-mono border {{ $dayCalc['difference_badge_class'] ?? 'bg-slate-100 text-slate-700 border-slate-300' }}"
                                      title="Diferença da jornada: {{ $dayCalc['formatted_difference'] }}">
                                    {{ $dayCalc['formatted_difference'] }}
                                </span>
                            @endif

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $dayCalc['status_badge_class'] }}">
                                @if($dayCalc['is_open'])
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                @endif
                                @if($dayCalc['status'] === 'concluded')
                                    {{ $dayCalc['formatted'] }} trabalhadas
                                @else
                                    {{ $dayCalc['formatted'] }} ({{ $dayCalc['status_label'] }})
                                @endif
                            </span>

                            {{-- Botão de Detalhes Expansíveis --}}
                            <button type="button" 
                                    @click="showDetails = !showDetails" 
                                    class="min-h-[32px] inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition cursor-pointer"
                                    aria-label="Ver detalhes de previsão e tolerância da jornada">
                                <span x-text="showDetails ? 'Ocultar' : 'Detalhes'"></span>
                                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="showDetails ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        @endif

                        @if(! $isClosedPeriod)
                            <button type="button" 
                                    wire:click="openTreatmentModal('{{ $date }}')" 
                                    class="min-h-[32px] inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 transition cursor-pointer"
                                    title="Solicitar correção ou justificativa para este dia">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                <span>Ajustar</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Painel Expansível de Detalhes da Jornada (Previsto, Apurado, Diferença, Tolerância, Tratamentos) --}}
                @if(isset($daysCalculated[$date]))
                <div x-show="showDetails" x-collapse x-cloak class="bg-gray-50/90 border-b border-gray-200 px-3.5 sm:px-4 py-3 text-xs">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 font-mono text-[11px]">
                        <div class="bg-white p-2.5 rounded-xl border border-gray-200/80 shadow-2xs">
                            <span class="text-gray-500 font-sans block text-[10px] uppercase font-semibold">Previsto</span>
                            <span class="font-bold text-gray-900 text-sm">{{ $dayCalc['scheduled_formatted'] ?? '00:00' }}</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-gray-200/80 shadow-2xs">
                            <span class="text-gray-500 font-sans block text-[10px] uppercase font-semibold">Apurado</span>
                            <span class="font-bold text-gray-900 text-sm">{{ $dayCalc['worked_formatted'] ?? '00:00' }}</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-gray-200/80 shadow-2xs">
                            <span class="text-gray-500 font-sans block text-[10px] uppercase font-semibold">Diferença</span>
                            <span class="font-bold text-sm {{ ($dayCalc['difference_minutes'] ?? 0) > 0 ? 'text-emerald-700' : (($dayCalc['difference_minutes'] ?? 0) < 0 ? 'text-rose-700' : 'text-slate-700') }}">
                                {{ $dayCalc['formatted_difference'] ?? '00:00' }}
                            </span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-gray-200/80 shadow-2xs">
                            <span class="text-gray-500 font-sans block text-[10px] uppercase font-semibold">Tolerância Legal</span>
                            <span class="font-bold text-gray-800 text-sm">
                                @if(!empty($dayCalc['tolerated_minutes']) && $dayCalc['tolerated_minutes'] > 0)
                                    {{ $dayCalc['tolerated_minutes'] }} min (aplicada)
                                @else
                                    CLT Art. 58
                                @endif
                            </span>
                        </div>
                    </div>
                    @if(!empty($dayCalc['notes']) && count($dayCalc['notes']) > 0)
                        <div class="mt-2.5 pt-2 border-t border-gray-200/70 text-gray-600 space-y-1">
                            <span class="font-sans font-semibold text-[10px] uppercase text-gray-500 block">Tratamentos / Justificativas / Ocorrências:</span>
                            @foreach($dayCalc['notes'] as $note)
                                <p class="flex items-center gap-1.5 font-sans">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>{{ $note }}</span>
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Faixa de Solicitações de Tratamento do Dia --}}
                @if(isset($daysCalculated[$date]['treatments']) && $daysCalculated[$date]['treatments']->isNotEmpty())
                    <div class="bg-slate-50 px-3.5 sm:px-4 py-2 border-b border-gray-100 flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Ajustes:</span>
                        @foreach($daysCalculated[$date]['treatments'] as $treatment)
                            @if($treatment->status->value === 'pending')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-900 border border-amber-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>Pendente: {{ $treatment->type->label() }}</span>
                                </span>
                            @elseif($treatment->status->value === 'approved')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-900 border border-emerald-300">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span>Aprovada: {{ $treatment->type->label() }}</span>
                                </span>
                            @elseif($treatment->status->value === 'rejected')
                                <button type="button" 
                                        wire:click="viewTreatmentDetails('{{ $treatment->id }}')" 
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-900 border border-rose-300 hover:bg-rose-100 transition cursor-pointer"
                                        title="Clique para ver o motivo da recusa">
                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Recusada: {{ $treatment->type->label() }}</span>
                                    <span class="underline text-[11px] text-rose-700 font-bold ml-1">Ver motivo</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Notas Normativas PTRP do Dia --}}
                @if(isset($daysCalculated[$date]['notes']) && count($daysCalculated[$date]['notes']) > 0)
                    <div class="bg-indigo-50/30 px-3.5 sm:px-4 py-2 border-b border-indigo-100/60 text-xs text-indigo-900 space-y-0.5">
                        @foreach($daysCalculated[$date]['notes'] as $note)
                            <p class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                <span>{{ $note }}</span>
                            </p>
                        @endforeach
                    </div>
                @endif

                {{-- Timeline de Marcações do Dia --}}
                <ul class="divide-y divide-gray-100" role="list">
                    @foreach($dayEntries as $entry)
                        <li class="px-3.5 sm:px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-gray-50/80 transition {{ !empty($entry->is_disregarded) ? 'bg-gray-50/60 opacity-70' : '' }}" wire:key="entry-{{ $entry->id }}">
                            
                            {{-- Lado Esquerdo: Tipo, Horário e Badges --}}
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold shrink-0 {{ $entry->type === 'in' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                    {{ $entry->type === 'in' ? 'Entrada' : 'Saída' }}
                                </span>

                                <span class="text-sm sm:text-base font-bold font-mono tabular-nums text-gray-900 {{ !empty($entry->is_disregarded) ? 'line-through text-gray-400' : '' }}">
                                    {{ Carbon::parse($entry->timestamp)->format('H:i:s') }}
                                </span>

                                @if($entry->is_manual)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        Ajuste Manual
                                    </span>
                                @endif

                                @if($entry->nsr)
                                    <span class="text-xs font-mono tabular-nums text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                        NSR #{{ str_pad((string)$entry->nsr, 9, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endif

                                {{-- Status da Marcação no PTRP --}}
                                @if(!empty($entry->is_disregarded))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-gray-200 text-gray-700">
                                        Desconsiderada pelo RH
                                    </span>
                                @elseif(!empty($entry->has_pending_disregard))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-amber-50 text-amber-900 border border-amber-300">
                                        Desconsideração Pendente
                                    </span>
                                @elseif(!empty($entry->has_rejected_disregard) && $entry->treatment)
                                    <button type="button" 
                                            wire:click="viewTreatmentDetails('{{ $entry->treatment->id }}')" 
                                            class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-300 hover:bg-rose-100 cursor-pointer"
                                            title="Clique para ver a justificativa da recusa">
                                        Desconsideração Rejeitada (Ver)
                                    </button>
                                @endif
                            </div>

                            {{-- Lado Direito: Ações Contextuais, Localização e Comprovante --}}
                            <div class="flex items-center gap-3 flex-wrap justify-between sm:justify-end pt-1 sm:pt-0 border-t sm:border-t-0 border-gray-100">
                                
                                {{-- Ação Contextual de Desconsiderar Batida --}}
                                @if(! $isClosedPeriod && empty($entry->is_disregarded) && empty($entry->has_pending_disregard))
                                    <button type="button" 
                                            wire:click="openTreatmentModalForPunch('{{ $date }}', '{{ $entry->id }}')" 
                                            class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-rose-600 font-medium transition cursor-pointer"
                                            title="Solicitar desconsideração desta marcação indevida ou duplicada">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        <span>Desconsiderar</span>
                                    </button>
                                @endif

                                {{-- Detalhe Geográfico Contextual (Apenas quando relevante) --}}
                                @if($entry->has_valid_location)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-mono text-gray-500 bg-gray-50 px-2 py-0.5 rounded border border-gray-200" title="Coordenadas geográficas registradas no ponto">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                        <span>Lat: {{ number_format($entry->latitude, 4) }}, Lng: {{ number_format($entry->longitude, 4) }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-sans text-gray-400" title="Sem coordenadas de GPS registradas no ato da batida">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                        <span class="italic">Localização não disponível</span>
                                    </span>
                                @endif

                                {{-- Link Direto ao Comprovante Digital --}}
                                @if(isset($entry->receipt) && $entry->receipt)
                                    <a href="{{ route('receipts.pdf', ['code' => $entry->receipt->verification_code]) }}"
                                       target="_blank"
                                       title="Visualizar comprovante de ponto homologado (PDF)"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition">
                                        <span>Comprovante</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            {{-- 6. ESTADO VAZIO ESPECÍFICO --}}
            <div class="text-center py-12 px-4 bg-white border border-gray-200/90 rounded-2xl shadow-2xs space-y-3" wire:key="empty-state">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Não há marcações neste período.</h3>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto mt-1">
                        Quando houver registros de ponto ou justificativas de jornada nesta competência, eles aparecerão aqui cronologicamente.
                    </p>
                </div>
                @if(! $isClosedPeriod)
                    <div class="pt-2">
                        <button type="button" 
                                wire:click="openTreatmentModal" 
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold rounded-xl text-xs transition border border-indigo-200 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Solicitar inclusão manual para este mês</span>
                        </button>
                    </div>
                @endif
            </div>
        @endforelse
    </main>

    {{-- 7. MODAL DE SOLICITAÇÃO DE AJUSTE / JUSTIFICATIVA CONTEXTUAL --}}
    @if($showTreatmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="treatment-modal-title"
             @keydown.escape.window="$wire.closeTreatmentModal()">
            <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-4 sm:p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-150 border border-gray-200">
                
                {{-- Topo do Modal --}}
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </div>
                        <div>
                            <h2 id="treatment-modal-title" class="text-base font-bold text-gray-900 tracking-tight">Solicitar Ajuste ou Justificativa</h2>
                            <p class="text-[11px] text-gray-500">Tratamento formal auditado sob o PTRP</p>
                        </div>
                    </div>
                    <button type="button" 
                            wire:click="closeTreatmentModal" 
                            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition cursor-pointer"
                            aria-label="Fechar modal">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="submitTreatmentRequest" class="space-y-4 text-xs">
                    
                    {{-- Seleção Contextual do Tipo de Solicitação (Touch Friendly) --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Tipo de Solicitação</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 p-1 bg-gray-100 rounded-xl" role="tablist">
                            <button type="button" 
                                    wire:click="$set('reqType', 'manual_punch_added')"
                                    class="min-h-[44px] py-2 px-2.5 rounded-lg text-center font-bold text-xs transition cursor-pointer {{ $reqType === 'manual_punch_added' ? 'bg-white text-indigo-700 shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                                Inclusão de Batida
                            </button>
                            <button type="button" 
                                    wire:click="$set('reqType', 'absence_justified')"
                                    class="min-h-[44px] py-2 px-2.5 rounded-lg text-center font-bold text-xs transition cursor-pointer {{ $reqType === 'absence_justified' ? 'bg-white text-indigo-700 shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                                Justificativa Ausência
                            </button>
                            <button type="button" 
                                    wire:click="$set('reqType', 'punch_disregarded')"
                                    class="min-h-[44px] py-2 px-2.5 rounded-lg text-center font-bold text-xs transition cursor-pointer {{ $reqType === 'punch_disregarded' ? 'bg-white text-indigo-700 shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                                Desconsiderar Batida
                            </button>
                        </div>
                        @error('reqType') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 1. CAMPOS DE INCLUSÃO DE BATIDA ESQUECIDA --}}
                    @if($reqType === 'manual_punch_added')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Data da Ocorrência *</label>
                                <input type="date" wire:model.live="reqDate" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono text-xs focus:ring-2 focus:ring-indigo-500" required>
                                @error('reqDate') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Horário Previsto *</label>
                                <input type="time" wire:model="reqTime" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono text-xs focus:ring-2 focus:ring-indigo-500" required>
                                @error('reqTime') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Justificativa do Esquecimento *</label>
                            <textarea wire:model="reqReason" rows="3" class="w-full px-3 py-2 border rounded-xl text-xs focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Esquecimento de registro na saída para almoço em virtude de atendimento emergencial..." required></textarea>
                            @error('reqReason') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Anexo / Comprovante <span class="text-gray-400 font-normal">(Opcional)</span></label>
                            <input type="file" wire:model="reqAttachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                            <p class="text-[10px] text-gray-500 mt-1">Formatos aceitos: PDF, PNG, JPG (máx. 5MB). Opcional para inclusões manuais.</p>
                            @error('reqAttachment') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                    {{-- 2. CAMPOS DE JUSTIFICATIVA DE AUSÊNCIA (DIA INTEIRO / ABONO) --}}
                    @elseif($reqType === 'absence_justified')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Data da Ausência *</label>
                                <input type="date" wire:model.live="reqDate" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono text-xs focus:ring-2 focus:ring-indigo-500" required>
                                <p class="text-[10px] text-gray-500 mt-0.5">A justificativa cobre o expediente completo da data.</p>
                                @error('reqDate') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Motivo Legal / Categoria *</label>
                                <select wire:model.live="reqReasonCategory" class="w-full min-h-[44px] px-3 py-2 border rounded-xl bg-white font-medium text-xs focus:ring-2 focus:ring-indigo-500">
                                    <option value="medical_certificate">Atestado Médico / Odontológico</option>
                                    <option value="medical_appointment">Declaração de Consulta / Exames</option>
                                    <option value="bereavement">Falecimento em Família (Licença Nojo)</option>
                                    <option value="wedding">Casamento (Licença Gala)</option>
                                    <option value="blood_donation">Doação Voluntária de Sangue</option>
                                    <option value="court_summons">Convocação Judicial / Eleitoral</option>
                                    <option value="other">Outro Motivo / Força Maior</option>
                                </select>
                                @error('reqReasonCategory') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Justificativa e Detalhamento *</label>
                            <textarea wire:model="reqReason" rows="3" class="w-full px-3 py-2 border rounded-xl text-xs focus:ring-2 focus:ring-indigo-500" placeholder="Descreva o motivo da ausência para análise do RH/Gestor..." required></textarea>
                            @error('reqReason') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            @php
                                $isDocRequired = ($reqReasonCategory === 'medical_certificate');
                            @endphp
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                Anexo / Comprovante Documental 
                                @if($isDocRequired)
                                    <span class="text-rose-600 font-bold">* (Obrigatório)</span>
                                @else
                                    <span class="text-gray-400 font-normal">(Opcional)</span>
                                @endif
                            </label>
                            <input type="file" wire:model="reqAttachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                            <p class="text-[10px] {{ $isDocRequired ? 'text-rose-600 font-medium' : 'text-gray-500' }} mt-1">
                                @if($isDocRequired)
                                    Atestados médicos exigem comprovação documental arquivada de forma privada (PDF, PNG, JPG de até 5MB).
                                @else
                                    Formatos aceitos: PDF, PNG, JPG (máx. 5MB). Opcional para este motivo.
                                @endif
                            </p>
                            @error('reqAttachment') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                    {{-- 3. CAMPOS DE DESCONSIDERAÇÃO DE MARCAÇÃO --}}
                    @elseif($reqType === 'punch_disregarded')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Data da Marcação *</label>
                                <input type="date" wire:model.live="reqDate" class="w-full min-h-[44px] px-3 py-2 border rounded-xl font-mono text-xs focus:ring-2 focus:ring-indigo-500" required>
                                @error('reqDate') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Marcação Existente a Desconsiderar *</label>
                                @if(!empty($availablePunchesForDate))
                                    <select wire:model="reqReferencePunchId" class="w-full min-h-[44px] px-3 py-2 border rounded-xl bg-white font-mono text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Selecione a marcação...</option>
                                        @foreach($availablePunchesForDate as $punchOpt)
                                            <option value="{{ $punchOpt['id'] }}">{{ $punchOpt['label'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="p-2.5 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-[11px]">
                                        Nenhuma marcação bruta encontrada para {{ Carbon::parse($reqDate)->format('d/m/Y') }}.
                                    </div>
                                @endif
                                @error('reqReferencePunchId') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 leading-relaxed text-[11px]">
                            <strong>Garantia de Integridade Fiscal:</strong> O registro bruto original permanece registrado de forma imutável no REP-P. A desconsideração é auditada no PTRP para descarte da apuração após aprovação do RH.
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Motivo da Desconsideração *</label>
                            <textarea wire:model="reqReason" rows="3" class="w-full px-3 py-2 border rounded-xl text-xs focus:ring-2 focus:ring-indigo-500" placeholder="Descreva porque esta marcação deve ser desconsiderada (ex: batida duplicada acidental, teste operacional)..." required></textarea>
                            @error('reqReason') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Anexo <span class="text-gray-400 font-normal">(Opcional)</span></label>
                            <input type="file" wire:model="reqAttachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                            @error('reqAttachment') <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div wire:loading wire:target="reqAttachment" class="text-[11px] text-indigo-600 font-semibold">
                        Enviando anexo para armazenamento privado... aguarde.
                    </div>

                    <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl text-indigo-900 leading-relaxed text-[11px]">
                        A solicitação será registrada com status <strong>Pendente</strong> no PTRP, sendo submetida para aprovação do gestor do setor e RH antes de alterar o espelho de ponto.
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" 
                                wire:click="closeTreatmentModal" 
                                class="min-h-[44px] px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                wire:target="submitTreatmentRequest"
                                :disabled="$wire.isSubmitting"
                                class="min-h-[44px] px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="submitTreatmentRequest">Enviar Solicitação</span>
                            <span wire:loading wire:target="submitTreatmentRequest" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Enviando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 8. MODAL DE DETALHES DA DECISÃO DO TRATAMENTO --}}
    @if($showTreatmentDetailsModal && $viewingTreatment)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="treatment-details-title"
             @keydown.escape.window="$wire.closeTreatmentDetailsModal()">
            <div class="bg-white rounded-2xl max-w-md w-full p-4 sm:p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-150 border border-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 id="treatment-details-title" class="text-base font-bold text-gray-900 tracking-tight">Detalhes da Solicitação</h2>
                        <p class="text-xs text-gray-500">{{ $viewingTreatment['type_label'] }} · {{ $viewingTreatment['effective_date'] }}</p>
                    </div>
                    <button type="button" 
                            wire:click="closeTreatmentDetailsModal" 
                            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition cursor-pointer"
                            aria-label="Fechar detalhes">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Status Atual</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-bold text-xs mt-1
                            {{ $viewingTreatment['status'] === 'approved' ? 'bg-emerald-50 text-emerald-900 border border-emerald-300' : '' }}
                            {{ $viewingTreatment['status'] === 'rejected' ? 'bg-rose-50 text-rose-900 border border-rose-300' : '' }}
                            {{ $viewingTreatment['status'] === 'pending' ? 'bg-amber-50 text-amber-900 border border-amber-300' : '' }}">
                            {{ $viewingTreatment['status_label'] }}
                        </span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Justificativa do Solicitante</span>
                        <p class="text-gray-800 bg-gray-50 p-2.5 rounded-xl border border-gray-200 mt-1 leading-relaxed">
                            {{ $viewingTreatment['reason_text'] }}
                        </p>
                    </div>

                    @if($viewingTreatment['status'] === 'rejected')
                        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-rose-700 block tracking-wider">Motivo da Recusa</span>
                            <p class="font-semibold text-xs leading-relaxed">
                                {{ $viewingTreatment['rejection_reason'] ?: 'Motivo não informado pelo avaliador.' }}
                            </p>
                            @if($viewingTreatment['decided_by'])
                                <p class="text-[11px] text-rose-700 pt-1">
                                    Decidido por: <strong>{{ $viewingTreatment['decided_by'] }}</strong> em {{ $viewingTreatment['decided_at'] }}
                                </p>
                            @endif
                        </div>
                    @elseif($viewingTreatment['status'] === 'approved')
                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-emerald-700 block tracking-wider">Decisão Favorável</span>
                            <p class="text-[11px] text-emerald-700">
                                Aprovada formalmente por <strong>{{ $viewingTreatment['decided_by'] ?? 'RH' }}</strong> em {{ $viewingTreatment['decided_at'] }}.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3 border-t border-gray-100">
                    <button type="button" 
                            wire:click="closeTreatmentDetailsModal" 
                            class="min-h-[44px] px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer transition">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>