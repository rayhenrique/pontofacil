<?php

namespace App\Domain\PTRP\Traits;

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Actions\ApplyPointAdjustmentAction;
use App\Domain\PTRP\Services\PointAdjustmentPreviewService;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait HandlesPointAdjustment
{
    public bool $showAdjustmentModal = false;

    public ?int $adjEmployeeId = null;

    public string $adjEmployeeName = '';

    public string $adjDate = '';

    public string $adjAction = 'add_entry'; // add_entry, add_exit, disregard_punch, justify_absence

    public string $adjTime = '';

    public ?string $adjRefPunchId = null;

    public string $adjReasonCategory = 'medical_certificate';

    public string $adjReason = '';

    public $adjAttachment = null;

    public bool $adjDirectApproval = false;

    public bool $adjCanDirectApprove = false;

    public string $adjDirectApprovalNotice = '';

    public bool $adjIsSubmitting = false;

    public array $adjSequencePreview = [];

    public array $adjAvailablePunches = [];

    /**
     * Abre o modal unificado de ajuste/correção de ponto com dados pré-preenchidos.
     */
    public function openPointAdjustmentModal(
        int|string $employeeId,
        ?string $date = null,
        ?string $action = null,
        ?string $punchId = null
    ): void {
        $actor = Auth::user();
        if (! $actor) {
            abort(401);
        }

        $employee = Employee::with(['user', 'sector.establishment'])->findOrFail((int) $employeeId);

        // Validação de Permissão no Servidor
        if ($actor->isManager()) {
            app(SectorScopeService::class)->authorizeEmployee($actor, $employee);
        } elseif ($actor->isEmployee() && (int) $employee->user_id !== (int) $actor->id) {
            abort(403, 'Acesso restrito ao próprio espelho de ponto.');
        }

        $parsedDate = $date ? Carbon::parse($date) : now();

        if (ClosedPeriod::isClosed($parsedDate->year, $parsedDate->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Competência Fechada',
                'message' => sprintf(
                    'A competência %02d/%04d encontra-se formalmente fechada e congelada fiscalmente. Ajustes retroativos não são permitidos.',
                    $parsedDate->month,
                    $parsedDate->year
                ),
                'buttonText' => 'Entendido',
            ]);

            return;
        }

        $this->adjEmployeeId = $employee->id;
        $this->adjEmployeeName = $employee->user?->name ?? "Colaborador #{$employee->id}";
        $this->adjDate = $parsedDate->toDateString();
        $this->adjAction = $action ?? ($punchId ? 'disregard_punch' : 'add_entry');
        $this->adjTime = ''; // Não inventa horário
        $this->adjReasonCategory = 'medical_certificate';
        $this->adjReason = '';
        $this->adjAttachment = null;
        $this->adjIsSubmitting = false;
        $this->adjRefPunchId = $punchId ? (string) $punchId : null;

        // Configuração de Aprovação Direta e Segregação de Funções
        $isOwnProfile = ($employee->user_id === $actor->id);
        if ($actor->isAdmin()) {
            $this->adjCanDirectApprove = true;
            $this->adjDirectApproval = true;
            $this->adjDirectApprovalNotice = 'Como Administrador/RH, você pode registrar e aprovar formalmente este ajuste de forma imediata.';
        } elseif ($actor->isManager()) {
            if ($isOwnProfile) {
                // Segregação de funções estrita
                $this->adjCanDirectApprove = false;
                $this->adjDirectApproval = false;
                $this->adjDirectApprovalNotice = 'Segregação de Funções: Ajustes solicitados para o seu próprio espelho exigem aprovação da Coordenação de RH.';
            } else {
                $this->adjCanDirectApprove = true;
                $this->adjDirectApproval = true;
                $this->adjDirectApprovalNotice = 'Como Gestor responsável pelo setor, você pode registrar e aprovar diretamente com auditoria.';
            }
        } else {
            $this->adjCanDirectApprove = false;
            $this->adjDirectApproval = false;
            $this->adjDirectApprovalNotice = 'Sua solicitação de ajuste será enviada para análise e aprovação formal do RH/Gestor.';
        }

        $this->resetErrorBag();
        $this->refreshAdjustmentPreview();

        if ($punchId && ! empty($this->adjAvailablePunches)) {
            $this->adjRefPunchId = (string) $punchId;
        } elseif ($this->adjAction === 'disregard_punch' && ! empty($this->adjAvailablePunches)) {
            $this->adjRefPunchId = (string) $this->adjAvailablePunches[0]['id'];
        }

        $this->showAdjustmentModal = true;
    }

    public function closePointAdjustmentModal(): void
    {
        $this->showAdjustmentModal = false;
        $this->adjIsSubmitting = false;
        $this->adjAttachment = null;
        $this->resetErrorBag();
    }

    public function updatedAdjDate($value): void
    {
        $this->refreshAdjustmentPreview();
        if ($this->adjAction === 'disregard_punch' && ! empty($this->adjAvailablePunches)) {
            $this->adjRefPunchId = (string) $this->adjAvailablePunches[0]['id'];
        } else {
            $this->adjRefPunchId = null;
        }
    }

    public function updatedAdjAction($value): void
    {
        if ($value === 'disregard_punch') {
            if (! empty($this->adjAvailablePunches) && ! $this->adjRefPunchId) {
                $this->adjRefPunchId = (string) $this->adjAvailablePunches[0]['id'];
            }
        }
        $this->refreshAdjustmentPreview();
    }

    public function updatedAdjTime($value): void
    {
        $this->refreshAdjustmentPreview();
    }

    public function updatedAdjRefPunchId($value): void
    {
        $this->refreshAdjustmentPreview();
    }

    /**
     * Atualiza a prévia da sequência cronológica do dia em tempo real.
     */
    public function refreshAdjustmentPreview(): void
    {
        if (! $this->adjEmployeeId || ! $this->adjDate) {
            $this->adjSequencePreview = [];
            $this->adjAvailablePunches = [];

            return;
        }

        $employee = Employee::with(['sector.establishment'])->find($this->adjEmployeeId);
        if (! $employee) {
            return;
        }

        $previewService = app(PointAdjustmentPreviewService::class);
        $result = $previewService->generatePreview(
            employee: $employee,
            date: $this->adjDate,
            proposedAction: $this->adjAction,
            proposedTime: $this->adjTime,
            proposedRefPunchId: $this->adjRefPunchId
        );

        $this->adjSequencePreview = $result;
        $this->adjAvailablePunches = $result['available_punches'] ?? [];
    }

    /**
     * Submete o ajuste manual aplicando validações e regras do PTRP.
     */
    public function submitPointAdjustment(): void
    {
        $actor = Auth::user();
        if (! $actor || ! $this->adjEmployeeId) {
            return;
        }

        if ($this->adjIsSubmitting) {
            return;
        }
        $this->adjIsSubmitting = true;

        $employee = Employee::with(['user', 'sector.establishment'])->findOrFail($this->adjEmployeeId);

        // Validação de Escopo
        if ($actor->isManager()) {
            app(SectorScopeService::class)->authorizeEmployee($actor, $employee);
        } elseif ($actor->isEmployee() && (int) $employee->user_id !== (int) $actor->id) {
            abort(403, 'Acesso não autorizado.');
        }

        // Validação de Formulário
        $rules = [
            'adjDate' => 'required|date',
            'adjAction' => 'required|in:add_entry,add_exit,disregard_punch,justify_absence',
            'adjReason' => 'required|string|min:5|max:500',
        ];

        if ($this->adjAction === 'add_entry' || $this->adjAction === 'add_exit') {
            $rules['adjTime'] = 'required|date_format:H:i';
            $rules['adjAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        } elseif ($this->adjAction === 'disregard_punch') {
            $rules['adjRefPunchId'] = 'required|string';
            $rules['adjAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        } elseif ($this->adjAction === 'justify_absence') {
            $rules['adjReasonCategory'] = 'required|in:medical_certificate,medical_appointment,bereavement,wedding,blood_donation,court_summons,other';
            if ($this->adjReasonCategory === 'medical_certificate') {
                $rules['adjAttachment'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
            } else {
                $rules['adjAttachment'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
            }
        }

        $messages = [
            'adjDate.required' => 'A data do ajuste é obrigatória.',
            'adjTime.required' => 'Informe o horário para inclusão da marcação.',
            'adjTime.date_format' => 'Horário em formato inválido (HH:MM).',
            'adjRefPunchId.required' => 'Selecione a marcação que deseja desconsiderar.',
            'adjReason.required' => 'A justificativa do ajuste é obrigatória.',
            'adjReason.min' => 'A justificativa deve conter pelo menos 5 caracteres explicativos.',
            'adjReason.max' => 'A justificativa não pode ultrapassar 500 caracteres.',
            'adjAttachment.required' => 'O anexo comprobatório (atestado médico) é obrigatório para este motivo legal.',
            'adjAttachment.mimes' => 'O anexo deve ser um documento nos formatos PDF, JPG, JPEG ou PNG.',
            'adjAttachment.max' => 'O anexo não pode ultrapassar 5MB.',
        ];

        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $e) {
            $this->adjIsSubmitting = false;
            throw $e;
        }

        // Upload de Anexo Seguro (armazenamento privado local para LGPD)
        $attachmentPath = null;
        if ($this->adjAttachment) {
            $attachmentPath = $this->adjAttachment->store('treatment_attachments', 'local');
        }

        $autoApprove = $this->adjCanDirectApprove && $this->adjDirectApproval;

        try {
            $action = app(ApplyPointAdjustmentAction::class);
            $event = $action->execute(
                employee: $employee,
                actionType: $this->adjAction,
                date: $this->adjDate,
                reason: $this->adjReason,
                actor: $actor,
                time: $this->adjTime ?: null,
                referencePunchId: $this->adjRefPunchId,
                reasonCategory: $this->adjReasonCategory,
                attachmentPath: $attachmentPath,
                autoApprove: $autoApprove,
            );

            $this->showAdjustmentModal = false;
            $this->adjIsSubmitting = false;
            $this->adjAttachment = null;

            $statusText = $event->status->label();
            $title = $autoApprove ? 'Ajuste de Ponto Aprovado!' : 'Solicitação de Ajuste Registrada!';
            $message = $autoApprove
                ? "O ajuste de ponto foi formalmente aprovado e incorporado à apuração PTRP na data {$this->adjDate}."
                : "A solicitação de ajuste foi enviada com sucesso para análise do RH na data {$this->adjDate}.";

            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => $title,
                'message' => $message,
                'buttonText' => 'Entendido',
            ]);

            // Hook para a tela recarregar seus dados
            if (method_exists($this, 'onPointAdjustmentSaved')) {
                $this->onPointAdjustmentSaved($event);
            }
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->adjIsSubmitting = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Ajuste Não Permitido',
                'message' => $e->getMessage(),
                'buttonText' => 'Revisar',
            ]);
        } catch (\Throwable $e) {
            $this->adjIsSubmitting = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Processar Ajuste',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }
}
