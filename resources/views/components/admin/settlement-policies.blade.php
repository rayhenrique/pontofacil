<?php

use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Enums\LegalRegime;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Política de Jornada e Compensação')] class extends Component
{
    use WithPagination;

    // Filtros
    public string $filterScope = 'all';
    public string $filterRegime = 'all';
    public string $filterModality = 'all';
    public string $search = '';

    // Modal e Estados de Fluxo
    public bool $showModal = false;
    public bool $isEditing = false;
    public bool $isReviewing = false;
    public ?int $policyId = null;
    public ?string $presetMessage = null;

    // Avisos de Segurança e Contexto
    public ?string $closedPeriodWarning = null;
    public ?string $conflictWarning = null;

    // 1. Para quem vale esta regra? (Aplicação e Regime)
    public string $name = '';
    public string $scope_type = 'company'; // 'company', 'establishment', 'employee'
    public ?int $establishment_id = null;
    public ?int $employee_id = null;
    public ?string $legal_regime = 'clt';

    // 2. O que será feito com as diferenças de jornada? (Tratamento)
    public string $modality = 'monthly_compensation';

    // 3. Quando essa regra começa a valer? (Vigência e Referência)
    public string $effective_from = '';
    public ?string $effective_until = null;
    public string $legal_framework = '';
    public string $status = 'active';

    // Configurações Avançadas (Prazos, Limites e Tolerâncias)
    public int $max_compensation_months = 1;
    public int $carry_over_limit_hours = 0; // 0 = sem teto
    public int $daily_bank_limit_minutes = 60; // Para modelo misto
    public int $punch_tolerance_minutes = 5;
    public int $daily_tolerance_minutes = 10;
    public bool $compensate_deficit_with_credit = true;
    public bool $allow_unjustified_deficit_deduction = true;
    public ?string $notes = null;

    public function mount(): void
    {
        $this->effective_from = now()->startOfMonth()->toDateString();
    }

    public function applyPreset(string $presetKey): void
    {
        $this->resetValidation();
        $this->conflictWarning = null;
        $this->closedPeriodWarning = null;

        switch ($presetKey) {
            case 'clt_no_bank':
                $this->name = 'Empresa CLT - Sem Banco de Horas';
                $this->scope_type = 'company';
                $this->establishment_id = null;
                $this->employee_id = null;
                $this->legal_regime = 'clt';
                $this->modality = SettlementModality::NoBank->value;
                $this->legal_framework = 'Regulamento interno da organização - sem regime de banco de horas';
                $this->max_compensation_months = 1;
                $this->carry_over_limit_hours = 0;
                $this->daily_bank_limit_minutes = 60;
                $this->punch_tolerance_minutes = 5;
                $this->daily_tolerance_minutes = 10;
                $this->presetMessage = 'Sugestão aplicada: Empresa CLT sem banco de horas. Revise os campos e confirme a configuração.';
                break;

            case 'clt_monthly':
                $this->name = 'Empresa CLT - Compensação Mensal';
                $this->scope_type = 'company';
                $this->establishment_id = null;
                $this->employee_id = null;
                $this->legal_regime = 'clt';
                $this->modality = SettlementModality::MonthlyCompensation->value;
                $this->legal_framework = 'CLT, art. 59, § 6º - Acordo individual tácito ou escrito para compensação no mesmo mês';
                $this->max_compensation_months = 1;
                $this->carry_over_limit_hours = 0;
                $this->daily_bank_limit_minutes = 60;
                $this->punch_tolerance_minutes = 5;
                $this->daily_tolerance_minutes = 10;
                $this->presetMessage = 'Sugestão aplicada: Empresa CLT com compensação dentro do mês.';
                break;

            case 'clt_bank':
                $this->name = 'Empresa CLT - Banco de Horas';
                $this->scope_type = 'company';
                $this->establishment_id = null;
                $this->employee_id = null;
                $this->legal_regime = 'clt';
                $this->modality = SettlementModality::CumulativeBank->value;
                $this->legal_framework = 'CLT, art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)';
                $this->max_compensation_months = 6;
                $this->carry_over_limit_hours = 0;
                $this->daily_bank_limit_minutes = 60;
                $this->punch_tolerance_minutes = 5;
                $this->daily_tolerance_minutes = 10;
                $this->presetMessage = 'Sugestão aplicada: Empresa CLT com banco de horas acumulativo (até 6 meses).';
                break;

            case 'statutory':
                $this->name = 'Órgão Público - Regime Estatutário';
                $this->scope_type = 'company';
                $this->establishment_id = null;
                $this->employee_id = null;
                $this->legal_regime = LegalRegime::MunicipalStatutory->value;
                $this->modality = SettlementModality::NoBank->value;
                $this->legal_framework = 'Estatuto dos Servidores Públicos / Lei Municipal (informar lei e artigo correspondentes)';
                $this->max_compensation_months = 1;
                $this->carry_over_limit_hours = 0;
                $this->daily_bank_limit_minutes = 60;
                $this->punch_tolerance_minutes = 5;
                $this->daily_tolerance_minutes = 10;
                $this->presetMessage = 'Sugestão aplicada para órgão público. Complete a lei ou regulamento próprio aplicável.';
                break;
        }

        $this->checkContextualWarnings();
    }

    public function updatedLegalRegime($value): void
    {
        if ($value === 'clt' && empty($this->legal_framework)) {
            $this->legal_framework = 'CLT, art. 59, § 6º - Acordo para compensação de horas';
        } elseif ($value === LegalRegime::FederalStatutory->value && empty($this->legal_framework)) {
            $this->legal_framework = 'Lei nº 8.112/1990, art. 74 c/c Decreto nº 1.590/1995';
        } elseif (in_array($value, [LegalRegime::MunicipalStatutory->value, LegalRegime::StateStatutory->value], true)) {
            if (empty($this->legal_framework) || str_contains(strtolower($this->legal_framework), 'clt')) {
                $this->legal_framework = 'Estatuto dos Servidores Públicos (informar lei correspondente)';
            }
        }
        $this->checkContextualWarnings();
    }

    public function updatedModality($value): void
    {
        if ($value === SettlementModality::MonthlyCompensation->value) {
            $this->max_compensation_months = 1;
            if ($this->legal_regime === 'clt' && (empty($this->legal_framework) || str_contains($this->legal_framework, '§ 5º'))) {
                $this->legal_framework = 'CLT, art. 59, § 6º - Acordo individual para compensação no mesmo mês';
            }
        } elseif ($value === SettlementModality::CumulativeBank->value) {
            if ($this->max_compensation_months <= 1) {
                $this->max_compensation_months = 6;
            }
            if ($this->legal_regime === 'clt' && (empty($this->legal_framework) || str_contains($this->legal_framework, '§ 6º'))) {
                $this->legal_framework = 'CLT, art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)';
            }
        } elseif ($value === SettlementModality::NoBank->value) {
            if (empty($this->legal_framework)) {
                $this->legal_framework = 'Regulamento interno da organização - sem banco de horas';
            }
        } elseif ($value === SettlementModality::DirectPayroll->value) {
            if (empty($this->legal_framework)) {
                $this->legal_framework = 'CLT, art. 59 caput - Horas extras autorizadas encaminhadas para a folha de pagamento';
            }
        } elseif ($value === SettlementModality::TimeOff->value) {
            if (empty($this->legal_framework)) {
                $this->legal_framework = 'Acordo de compensação por descanso / folga programada';
            }
        }
        $this->checkContextualWarnings();
    }

    public function updatedEffectiveFrom(): void
    {
        $this->checkContextualWarnings();
    }

    public function updatedScopeType(): void
    {
        if ($this->scope_type === 'company') {
            $this->establishment_id = null;
            $this->employee_id = null;
        } elseif ($this->scope_type === 'establishment') {
            $this->employee_id = null;
        } elseif ($this->scope_type === 'employee') {
            $this->establishment_id = null;
        }
        $this->checkContextualWarnings();
    }

    public function updatedEstablishmentId(): void
    {
        $this->checkContextualWarnings();
    }

    public function updatedEmployeeId(): void
    {
        $this->checkContextualWarnings();
    }

    protected function checkContextualWarnings(): void
    {
        $this->closedPeriodWarning = null;
        $this->conflictWarning = null;

        if (empty($this->effective_from)) {
            return;
        }

        try {
            $date = Carbon::parse($this->effective_from);
            if (ClosedPeriod::isClosed($date->year, $date->month)) {
                $this->closedPeriodWarning = sprintf(
                    'A competência %02d/%d já está formalmente fechada. Esta política valerá apenas para apurações futuras em aberto e não altera os cálculos já congelados do período histórico.',
                    $date->month,
                    $date->year
                );
            }
        } catch (\Throwable $e) {
            // Ignora falhas de parse enquanto digita
        }

        try {
            $dateStr = Carbon::parse($this->effective_from)->format('Y-m-d');
            $query = WorkTimeSettlementPolicy::where('status', 'active')
                ->where('approval_status', SettlementPolicyApprovalStatus::Approved->value)
                ->where('employee_id', $this->scope_type === 'employee' ? $this->employee_id : null)
                ->where('establishment_id', $this->scope_type === 'establishment' ? $this->establishment_id : null);

            if ($this->isEditing && $this->policyId) {
                $query->where('id', '!=', $this->policyId);
            }

            if ($this->legal_regime) {
                $query->where('legal_regime', $this->legal_regime);
            }

            $overlap = $query->where(function ($q) use ($dateStr) {
                $q->where('effective_from', $dateStr)
                    ->orWhere(function ($sub) use ($dateStr) {
                        $sub->where('effective_from', '<=', $dateStr)
                            ->where(function ($tail) use ($dateStr) {
                                $tail->whereNull('effective_until')
                                    ->orWhere('effective_until', '>=', $dateStr);
                            });
                    });
            })->first();

            if ($overlap) {
                $this->conflictWarning = sprintf(
                    'Já existe a política ativa "%s" com vigência coincidente para este mesmo escopo. Salvar esta nova regra substituirá a anterior a partir de %s.',
                    $overlap->name,
                    Carbon::parse($this->effective_from)->format('d/m/Y')
                );
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->isEditing = false;
        $this->isReviewing = false;
        $this->policyId = null;
        $this->presetMessage = null;
        $this->closedPeriodWarning = null;
        $this->conflictWarning = null;

        $this->name = 'Nova Política de Compensação';
        $this->scope_type = 'company';
        $this->establishment_id = null;
        $this->employee_id = null;
        $this->legal_regime = 'clt';
        $this->modality = SettlementModality::MonthlyCompensation->value;
        $this->effective_from = now()->startOfMonth()->toDateString();
        $this->effective_until = null;
        $this->legal_framework = 'CLT, art. 59, § 6º - Acordo individual para compensação no mesmo mês';
        $this->status = 'active';

        $this->max_compensation_months = 1;
        $this->carry_over_limit_hours = 0;
        $this->daily_bank_limit_minutes = 60;
        $this->punch_tolerance_minutes = 5;
        $this->daily_tolerance_minutes = 10;
        $this->compensate_deficit_with_credit = true;
        $this->allow_unjustified_deficit_deduction = true;
        $this->notes = null;

        $this->checkContextualWarnings();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $this->isEditing = true;
        $this->isReviewing = false;
        $this->presetMessage = null;
        $this->closedPeriodWarning = null;
        $this->conflictWarning = null;

        $policy = WorkTimeSettlementPolicy::findOrFail($id);

        $this->policyId = $policy->id;
        $this->name = $policy->name;
        $this->scope_type = $policy->getScopeType();
        $this->establishment_id = $policy->establishment_id;
        $this->employee_id = $policy->employee_id;
        $this->legal_regime = $policy->legal_regime?->value ?? 'clt';
        $this->modality = $policy->modality->value;
        $this->effective_from = $policy->effective_from->toDateString();
        $this->effective_until = $policy->effective_until?->toDateString();
        $this->legal_framework = $policy->legal_framework;
        $this->status = $policy->status;
        $this->notes = $policy->notes;

        $this->max_compensation_months = (int) ($policy->compensation_terms['max_months'] ?? 1);
        $this->punch_tolerance_minutes = (int) ($policy->compensation_terms['punch_tolerance_minutes'] ?? 5);
        $this->daily_tolerance_minutes = (int) ($policy->compensation_terms['daily_tolerance_minutes'] ?? 10);
        $this->compensate_deficit_with_credit = (bool) ($policy->compensation_terms['compensate_deficit_with_credit'] ?? true);
        $this->allow_unjustified_deficit_deduction = (bool) ($policy->compensation_terms['allow_unjustified_deficit_deduction'] ?? true);
        $this->carry_over_limit_hours = (int) ($policy->carry_over_terms['max_carry_over_hours'] ?? 0);
        $this->daily_bank_limit_minutes = (int) ($policy->hybrid_rules['daily_bank_limit_minutes'] ?? 60);

        $this->checkContextualWarnings();
        $this->showModal = true;
    }

    public function goToReview(): void
    {
        $rules = [
            'name' => 'required|string|max:120',
            'scope_type' => 'required|in:company,establishment,employee',
            'establishment_id' => 'nullable|required_if:scope_type,establishment|exists:establishments,id',
            'employee_id' => 'nullable|required_if:scope_type,employee|exists:employees,id',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'legal_framework' => 'required|string|min:3|max:300',
            'modality' => 'required|string',
            'punch_tolerance_minutes' => 'nullable|integer|min:0|max:30',
            'daily_tolerance_minutes' => 'nullable|integer|min:0|max:60',
        ];

        if (in_array($this->modality, [SettlementModality::CumulativeBank->value, SettlementModality::Hybrid->value], true)) {
            $rules['max_compensation_months'] = 'required|integer|min:1|max:24';
            $rules['carry_over_limit_hours'] = 'nullable|integer|min:0';
        }

        if ($this->modality === SettlementModality::Hybrid->value) {
            $rules['daily_bank_limit_minutes'] = 'required|integer|min:1|max:480';
        }

        $this->validate($rules);

        // Validação jurídica contextual de regime estatutário
        $regime = LegalRegime::tryFrom((string) $this->legal_regime);
        if ($regime && $regime->isStatutory()) {
            $lower = strtolower($this->legal_framework);
            if (str_contains($lower, 'clt') && ! str_contains($lower, 'lei') && ! str_contains($lower, 'estatuto') && ! str_contains($lower, 'decreto')) {
                $this->addError('legal_framework', 'Servidores estatutários não são regidos pela CLT. Indique o estatuto ou lei aplicável do respectivo ente.');
                return;
            }
        }

        $this->checkContextualWarnings();
        $this->isReviewing = true;
    }

    public function backToEditing(): void
    {
        $this->isReviewing = false;
    }

    public function saveDraft(): void
    {
        $this->executeSave(SettlementPolicyApprovalStatus::Draft->value);
    }

    public function saveAndActivate(): void
    {
        if (! Auth::user()?->isAdmin()) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Permissão Insuficiente',
                'message' => 'Apenas administradores de RH têm autorização para aprovar e ativar políticas de jornada.',
                'buttonText' => 'Entendido',
            ]);
            return;
        }

        $this->executeSave(SettlementPolicyApprovalStatus::Approved->value);
    }

    protected function executeSave(string $approvalStatus): void
    {
        try {
            $data = [
                'name' => $this->name,
                'establishment_id' => $this->scope_type === 'establishment' ? $this->establishment_id : null,
                'employee_id' => $this->scope_type === 'employee' ? $this->employee_id : null,
                'legal_regime' => $this->legal_regime ?: null,
                'modality' => $this->modality,
                'effective_from' => $this->effective_from,
                'effective_until' => $this->effective_until ?: null,
                'legal_framework' => $this->legal_framework,
                'compensation_terms' => [
                    'max_months' => in_array($this->modality, [SettlementModality::CumulativeBank->value, SettlementModality::Hybrid->value], true) ? (int) $this->max_compensation_months : 1,
                    'punch_tolerance_minutes' => (int) $this->punch_tolerance_minutes,
                    'daily_tolerance_minutes' => (int) $this->daily_tolerance_minutes,
                    'compensate_deficit_with_credit' => (bool) $this->compensate_deficit_with_credit,
                    'allow_unjustified_deficit_deduction' => (bool) $this->allow_unjustified_deficit_deduction,
                ],
                'carry_over_terms' => [
                    'max_carry_over_hours' => (int) $this->carry_over_limit_hours,
                ],
                'hybrid_rules' => [
                    'daily_bank_limit_minutes' => (int) $this->daily_bank_limit_minutes,
                ],
                'status' => $this->status,
                'approval_status' => $approvalStatus,
                'notes' => $this->notes,
            ];

            if ($approvalStatus === SettlementPolicyApprovalStatus::Approved->value) {
                $data['approved_by'] = Auth::id();
                $data['approved_at'] = now();
            } else {
                $data['approved_by'] = null;
                $data['approved_at'] = null;
            }

            if ($this->isEditing && $this->policyId) {
                $policy = WorkTimeSettlementPolicy::findOrFail($this->policyId);

                // Bloqueio contra edição destrutiva de política em período fechado
                $policyStart = $policy->effective_from;
                if (ClosedPeriod::isClosed($policyStart->year, $policyStart->month) && $policy->approval_status === SettlementPolicyApprovalStatus::Approved) {
                    if ($policy->modality->value !== $this->modality || $policy->getScopeType() !== $this->scope_type) {
                        $this->dispatch('app-modal-alert', [
                            'type' => 'error',
                            'title' => 'Alteração Bloqueada',
                            'message' => 'Esta política já foi utilizada em competências formalmente fechadas e não pode sofrer alterações destrutivas em seu histórico. Para novas regras, encerre a vigência ou cadastre uma nova política a partir do período aberto.',
                            'buttonText' => 'Entendido',
                        ]);
                        return;
                    }
                }

                $policy->update($data);
            } else {
                $data['created_by'] = Auth::id();
                WorkTimeSettlementPolicy::create($data);
            }

            $this->showModal = false;
            $this->isReviewing = false;

            if ($approvalStatus === SettlementPolicyApprovalStatus::Approved->value) {
                $this->dispatch('app-modal-alert', [
                    'type' => 'success',
                    'title' => 'Política aprovada e ativada.',
                    'message' => sprintf('A política "%s" foi aprovada e está ativa a partir de %s.', $this->name, Carbon::parse($this->effective_from)->format('d/m/Y')),
                    'buttonText' => 'OK',
                ]);
            } else {
                $this->dispatch('app-modal-alert', [
                    'type' => 'info',
                    'title' => 'Política salva.',
                    'message' => sprintf('A política "%s" foi salva como rascunho com sucesso.', $this->name),
                    'buttonText' => 'OK',
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erro ao salvar política de compensação de jornada', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'policy_id' => $this->policyId,
                'user_id' => Auth::id(),
            ]);

            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Não foi possível salvar',
                'message' => 'Ocorreu uma falha ao registrar as informações. Os dados foram preservados e o detalhamento técnico foi registrado no log de segurança.',
                'buttonText' => 'Entendido',
            ]);
        }
    }

    public function toggleStatus(int $id): void
    {
        $policy = WorkTimeSettlementPolicy::findOrFail($id);
        $policy->status = $policy->status === 'active' ? 'inactive' : 'active';
        $policy->save();

        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Situação Atualizada',
            'message' => sprintf('A política "%s" agora está %s.', $policy->name, $policy->status === 'active' ? 'ativa' : 'inativa'),
            'buttonText' => 'OK',
        ]);
    }

    public function with(): array
    {
        $query = WorkTimeSettlementPolicy::with(['establishment', 'employee.user', 'approvedByUser'])
            ->orderBy('id', 'desc');

        if ($this->filterScope === 'company') {
            $query->whereNull('employee_id')->whereNull('establishment_id');
        } elseif ($this->filterScope === 'establishment') {
            $query->whereNotNull('establishment_id')->whereNull('employee_id');
        } elseif ($this->filterScope === 'employee') {
            $query->whereNotNull('employee_id');
        }

        if ($this->filterRegime !== 'all') {
            $query->where('legal_regime', $this->filterRegime);
        }

        if ($this->filterModality !== 'all') {
            $query->where('modality', $this->filterModality);
        }

        if (! empty($this->search)) {
            $s = '%' . $this->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('legal_framework', 'like', $s);
            });
        }

        $policies = $query->paginate(15);
        $establishments = Establishment::orderBy('name')->get();
        $employees = Employee::with('user')->orderBy('id')->get();

        return [
            'policies' => $policies,
            'establishments' => $establishments,
            'employees' => $employees,
            'regimes' => LegalRegime::cases(),
            'modalities' => SettlementModality::cases(),
        ];
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-2 sm:px-6 lg:px-8 space-y-6">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-700 shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Política de Jornada e Compensação</h1>
                    <p class="text-xs sm:text-sm text-gray-600 mt-0.5 leading-relaxed">
                        Defina de forma simples como as diferenças de horas são tratadas para sua empresa, estabelecimentos ou colaboradores.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.time-bank') }}" class="inline-flex items-center px-3.5 py-2.5 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-xl text-xs font-semibold transition gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                Extrato do Banco
            </a>

            @if(Auth::user()?->isAdmin())
                <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs gap-1.5 min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Nova Política
                </button>
            @endif
        </div>
    </div>

    <!-- Guia Rápido de Configuração (3 Passos Simples) -->
    <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-4 sm:p-5">
        <div class="flex items-center gap-2 text-indigo-900 font-bold text-xs uppercase tracking-wider mb-2">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            Como configurar em 3 respostas simples
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-700">
            <div class="bg-white/80 rounded-xl p-3 border border-indigo-100/80">
                <span class="font-bold text-indigo-700 block mb-0.5">1. Para quem vale a regra?</span>
                <span class="text-gray-600 text-[11px]">Escolha se a regra se aplica a toda a empresa, a um estabelecimento ou a um colaborador.</span>
            </div>
            <div class="bg-white/80 rounded-xl p-3 border border-indigo-100/80">
                <span class="font-bold text-indigo-700 block mb-0.5">2. O que fazer com as diferenças?</span>
                <span class="text-gray-600 text-[11px]">Selecione a modalidade: sem banco, compensação no mês, banco de horas, folha ou folga.</span>
            </div>
            <div class="bg-white/80 rounded-xl p-3 border border-indigo-100/80">
                <span class="font-bold text-indigo-700 block mb-0.5">3. Quando começa a valer?</span>
                <span class="text-gray-600 text-[11px]">Informe a data de início e a referência normativa do instrumento acordado.</span>
            </div>
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-200/80 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="filterScope" class="block text-xs font-bold text-gray-700 mb-1">Aplicação</label>
                <select id="filterScope" wire:model.live="filterScope" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todas as Aplicações</option>
                    <option value="company">Toda a Empresa</option>
                    <option value="establishment">Estabelecimentos</option>
                    <option value="employee">Colaboradores Específicos</option>
                </select>
            </div>

            <div>
                <label for="filterRegime" class="block text-xs font-bold text-gray-700 mb-1">Regime Jurídico</label>
                <select id="filterRegime" wire:model.live="filterRegime" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todos os Regimes</option>
                    @foreach($regimes as $reg)
                        <option value="{{ $reg->value }}">{{ $reg->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filterModality" class="block text-xs font-bold text-gray-700 mb-1">Tratamento das Horas</label>
                <select id="filterModality" wire:model.live="filterModality" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todos os Tratamentos</option>
                    @foreach($modalities as $mod)
                        <option value="{{ $mod->value }}">{{ $mod->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filterSearch" class="block text-xs font-bold text-gray-700 mb-1">Buscar por termo</label>
                <input id="filterSearch" type="text" wire:model.live.debounce.300ms="search" placeholder="Nome ou fundamentação..." class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
            </div>
        </div>
    </div>

    <!-- Listagem Administrativa (Mobile-First: Tabela Compacta no Desktop e Cartões Adaptados no Mobile) -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-200/80 overflow-hidden">
        
        <!-- Desktop: Tabela Compacta -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-gray-200">
                <thead class="bg-gray-50/80 text-gray-700 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-4 py-3.5">Nome</th>
                        <th class="px-4 py-3.5">Aplicação</th>
                        <th class="px-4 py-3.5">Regime</th>
                        <th class="px-4 py-3.5">Modalidade</th>
                        <th class="px-4 py-3.5">Vigência</th>
                        <th class="px-4 py-3.5">Situação</th>
                        <th class="px-4 py-3.5 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($policies as $p)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900 text-sm">{{ $p->name }}</div>
                                <div class="text-gray-500 text-[11px] mt-0.5 line-clamp-1 max-w-sm" title="{{ $p->legal_framework }}">
                                    {{ $p->legal_framework }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->employee)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                        {{ $p->employee->user?->name ?? 'Colaborador ID '.$p->employee->id }}
                                    </span>
                                @elseif($p->establishment)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200">
                                        {{ $p->establishment->name }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 border border-slate-300">
                                        Toda a Empresa
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->legal_regime)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-gray-100 text-gray-800">
                                        {{ $p->legal_regime->label() }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-[11px]">Geral</span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @php
                                    $modBadge = match($p->modality) {
                                        \App\Domain\Settlement\Enums\SettlementModality::CumulativeBank => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::MonthlyCompensation => 'bg-sky-50 text-sky-800 border-sky-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::DirectPayroll => 'bg-amber-50 text-amber-800 border-amber-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::NoBank => 'bg-slate-100 text-slate-800 border-slate-300',
                                        \App\Domain\Settlement\Enums\SettlementModality::TimeOff => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::Hybrid => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold border {{ $modBadge }}">
                                    {{ $p->modality->label() }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5 text-gray-600 font-mono text-[11px]">
                                {{ $p->effective_from->format('d/m/Y') }}
                                @if($p->effective_until)
                                    até {{ $p->effective_until->format('d/m/Y') }}
                                @else
                                    <span class="text-emerald-700 font-medium">(indeterminada)</span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->status === 'active' && $p->isApproved())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ativa
                                    </span>
                                @elseif($p->approval_status === \App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus::Draft)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        Rascunho
                                    </span>
                                @elseif($p->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-sky-50 text-sky-700 border border-sky-200">
                                        Pendente
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">
                                        Inativa
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 text-right space-x-1 whitespace-nowrap">
                                @if(Auth::user()?->isAdmin())
                                    <button wire:click="openEditModal({{ $p->id }})" class="px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition min-h-[36px]">
                                        Editar
                                    </button>
                                    <button wire:click="toggleStatus({{ $p->id }})" class="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-lg transition min-h-[36px]">
                                        {{ $p->status === 'active' ? 'Desativar' : 'Ativar' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500 text-xs">
                                Nenhuma política cadastrada correspondente aos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile: Lista Adaptada (Cartões sem Overflow Horizontal) -->
        <div class="md:hidden p-3 space-y-3">
            @forelse($policies as $p)
                <div class="bg-white rounded-xl border border-gray-200/90 p-3.5 shadow-2xs space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">{{ $p->name }}</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-2">{{ $p->legal_framework }}</p>
                        </div>
                        <div>
                            @if($p->status === 'active' && $p->isApproved())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ativa
                                </span>
                            @elseif($p->approval_status === \App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus::Draft)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                    Rascunho
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                    Inativa
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-gray-100">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Aplicação</span>
                            <span class="font-semibold text-gray-800 text-[11px]">
                                @if($p->employee)
                                    Colaborador: {{ $p->employee->user?->name ?? 'ID '.$p->employee->id }}
                                @elseif($p->establishment)
                                    Unidade: {{ $p->establishment->name }}
                                @else
                                    Toda a Empresa
                                @endif
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Regime</span>
                            <span class="font-semibold text-gray-800 text-[11px]">
                                {{ $p->legal_regime?->label() ?? 'Geral' }}
                            </span>
                        </div>

                        <div class="col-span-2">
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Tratamento</span>
                            <span class="font-semibold text-indigo-700 text-[11px]">
                                {{ $p->modality->label() }}
                            </span>
                        </div>

                        <div class="col-span-2">
                            <span class="text-[10px] uppercase font-bold text-gray-500 block">Vigência</span>
                            <span class="font-mono text-gray-700 text-[11px]">
                                A partir de {{ $p->effective_from->format('d/m/Y') }}
                                @if($p->effective_until)
                                    até {{ $p->effective_until->format('d/m/Y') }}
                                @else
                                    (indeterminada)
                                @endif
                            </span>
                        </div>
                    </div>

                    @if(Auth::user()?->isAdmin())
                        <div class="pt-2 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button wire:click="openEditModal({{ $p->id }})" class="flex-1 text-center py-2 px-3 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-xl min-h-[44px] flex items-center justify-center">
                                Editar
                            </button>
                            <button wire:click="toggleStatus({{ $p->id }})" class="flex-1 text-center py-2 px-3 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl min-h-[44px] flex items-center justify-center">
                                {{ $p->status === 'active' ? 'Desativar' : 'Ativar' }}
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 text-xs">
                    Nenhuma política cadastrada correspondente aos filtros.
                </div>
            @endforelse
        </div>

        <div class="p-4 border-t border-gray-100">
            {{ $policies->links() }}
        </div>
    </div>

    <!-- Modal Mobile-First: Criação / Edição de Política de Jornada e Compensação -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full max-h-[92vh] flex flex-col border border-gray-200/80 overflow-hidden">
                
                <!-- Cabeçalho do Modal -->
                <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-bold text-gray-900">
                            @if($isReviewing)
                                Revisão da Política
                            @elseif($isEditing)
                                Editar Política de Compensação
                            @else
                                Nova Política de Jornada e Compensação
                            @endif
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if($isReviewing)
                                Confira o resumo antes de salvar ou ativar a regra.
                            @else
                                Três passos simples: para quem vale, o tratamento das horas e o início da vigência.
                            @endif
                        </p>
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Fechar">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Conteúdo do Modal (Scrollável) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 text-xs">

                    @if($isReviewing)
                        <!-- ETAPA DE REVISÃO OBJETIVA ANTES DE SALVAR -->
                        <div class="space-y-4">
                            <div class="p-4 rounded-xl bg-indigo-50/80 border border-indigo-200 text-indigo-950 space-y-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 block">Resumo da Configuração</span>
                                
                                <p class="text-sm font-semibold text-gray-900 leading-snug">
                                    @if($scope_type === 'employee')
                                        @php
                                            $selectedEmp = $employees->firstWhere('id', $this->employee_id);
                                        @endphp
                                        Esta política será aplicada individualmente ao colaborador {{ $selectedEmp?->user?->name ?? 'selecionado' }}, a partir de {{ \Carbon\Carbon::parse($effective_from)->format('d/m/Y') }}.
                                    @elseif($scope_type === 'establishment')
                                        @php
                                            $selectedEst = $establishments->firstWhere('id', $this->establishment_id);
                                        @endphp
                                        Esta política será aplicada aos colaboradores do estabelecimento {{ $selectedEst?->name ?? 'selecionado' }}, a partir de {{ \Carbon\Carbon::parse($effective_from)->format('d/m/Y') }}.
                                    @else
                                        Esta política será aplicada a toda a empresa, a partir de {{ \Carbon\Carbon::parse($effective_from)->format('d/m/Y') }}.
                                    @endif
                                </p>

                                @php
                                    $treatmentLabels = [
                                        'no_bank' => 'Sem banco de horas',
                                        'monthly_compensation' => 'Compensação mensal',
                                        'cumulative_bank' => 'Banco acumulativo',
                                        'direct_payroll' => 'Encaminhar horas autorizadas para folha',
                                        'time_off' => 'Compensar por folga',
                                        'hybrid' => 'Modelo misto',
                                    ];
                                    $currentTreatmentLabel = $treatmentLabels[$modality] ?? 'Compensação mensal';

                                    $regimeLabels = [
                                        'clt' => 'CLT',
                                        'federal_statutory' => 'Estatutário federal',
                                        'state_statutory' => 'Estatutário estadual',
                                        'municipal_statutory' => 'Estatutário municipal',
                                        'other' => 'Outro regime',
                                    ];
                                    $currentRegimeLabel = $regimeLabels[$legal_regime] ?? 'CLT';
                                @endphp

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 text-xs text-gray-700 border-t border-indigo-200/80">
                                    <div>
                                        <strong class="text-gray-900">Tratamento:</strong>
                                        <span>{{ $currentTreatmentLabel }}</span>
                                    </div>
                                    <div>
                                        <strong class="text-gray-900">Regime:</strong>
                                        <span>{{ $currentRegimeLabel }}</span>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <strong class="text-gray-900">Referência normativa:</strong>
                                        <span>{{ $legal_framework }}</span>
                                    </div>
                                    <div>
                                        <strong class="text-gray-900">Situação:</strong>
                                        <span>Aguardando aprovação</span>
                                    </div>
                                    @if($effective_until)
                                        <div>
                                            <strong class="text-gray-900">Término da Vigência:</strong>
                                            <span>{{ \Carbon\Carbon::parse($effective_until)->format('d/m/Y') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Avisos Contextuais de Segurança -->
                            @if($conflictWarning)
                                <div class="p-3.5 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 text-xs flex items-start gap-2.5">
                                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <div>
                                        <strong>Sobreposição identificada:</strong> {{ $conflictWarning }}
                                    </div>
                                </div>
                            @endif

                            @if($closedPeriodWarning)
                                <div class="p-3.5 bg-sky-50 border border-sky-300 rounded-xl text-sky-900 text-xs flex items-start gap-2.5">
                                    <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <div>
                                        <strong>Período Histórico:</strong> {{ $closedPeriodWarning }}
                                    </div>
                                </div>
                            @endif

                            <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-600 text-xs">
                                <strong>Situação prevista:</strong> Você pode salvar como rascunho para revisar posteriormente ou aprovar e ativar imediatamente para a equipe.
                            </div>
                        </div>

                    @else
                        <!-- FORMULÁRIO BÁSICO E AVANÇADO -->
                        <form id="policy-form" wire:submit="goToReview" class="space-y-5">
                            
                            <!-- Sugestões de Configuração Rápida -->
                            <div class="bg-gray-50/80 rounded-xl p-3 border border-gray-200/90">
                                <span class="block text-xs font-bold text-gray-700 mb-1.5">
                                    Sugestões de preenchimento rápido (modelos sugeridos, não decisões jurídicas):
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" wire:click="applyPreset('clt_no_bank')" class="px-2.5 py-1.5 bg-white hover:bg-indigo-50 border border-gray-300 hover:border-indigo-300 text-gray-700 hover:text-indigo-700 rounded-lg text-xs font-semibold transition min-h-[36px]">
                                        Empresa CLT sem banco
                                    </button>
                                    <button type="button" wire:click="applyPreset('clt_monthly')" class="px-2.5 py-1.5 bg-white hover:bg-indigo-50 border border-gray-300 hover:border-indigo-300 text-gray-700 hover:text-indigo-700 rounded-lg text-xs font-semibold transition min-h-[36px]">
                                        Empresa CLT com compensação mensal
                                    </button>
                                    <button type="button" wire:click="applyPreset('clt_bank')" class="px-2.5 py-1.5 bg-white hover:bg-indigo-50 border border-gray-300 hover:border-indigo-300 text-gray-700 hover:text-indigo-700 rounded-lg text-xs font-semibold transition min-h-[36px]">
                                        Empresa CLT com banco
                                    </button>
                                    <button type="button" wire:click="applyPreset('statutory')" class="px-2.5 py-1.5 bg-white hover:bg-indigo-50 border border-gray-300 hover:border-indigo-300 text-gray-700 hover:text-indigo-700 rounded-lg text-xs font-semibold transition min-h-[36px]">
                                        Órgão público com regime estatutário
                                    </button>
                                </div>
                                @if($presetMessage)
                                    <p class="text-[11px] text-emerald-700 font-semibold mt-2">{{ $presetMessage }}</p>
                                @endif
                            </div>

                            <!-- PERGUNTA 1: Para quem vale esta regra? -->
                            <div class="bg-white rounded-xl p-4 border border-gray-200/90 space-y-3">
                                <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-100 pb-2">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[11px] font-bold">1</span>
                                    <span>Para quem vale esta regra? (Aplicação e Regime)</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label for="policyName" class="block font-bold text-gray-700 mb-1">Nome identificador da política *</label>
                                        <input id="policyName" type="text" wire:model="name" placeholder="Ex.: Compensação Mensal - Sede Administrativa" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                        @error('name') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="scopeType" class="block font-bold text-gray-700 mb-1">Aplicação *</label>
                                        <select id="scopeType" wire:model.live="scope_type" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            <option value="company">Toda a Empresa</option>
                                            <option value="establishment">Um Estabelecimento</option>
                                            <option value="employee">Um Colaborador Específico</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="legalRegime" class="block font-bold text-gray-700 mb-1">Regime Jurídico</label>
                                        <select id="legalRegime" wire:model.live="legal_regime" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            <option value="clt">CLT (Consolidação das Leis do Trabalho)</option>
                                            <option value="federal_statutory">Estatutário Federal</option>
                                            <option value="state_statutory">Estatutário Estadual</option>
                                            <option value="municipal_statutory">Estatutário Municipal</option>
                                        </select>
                                    </div>

                                    @if($scope_type === 'establishment')
                                        <div class="sm:col-span-2">
                                            <label for="establishmentId" class="block font-bold text-gray-700 mb-1">Selecione o Estabelecimento *</label>
                                            <select id="establishmentId" wire:model.live="establishment_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                                <option value="">Selecione a unidade...</option>
                                                @foreach($establishments as $est)
                                                    <option value="{{ $est->id }}">{{ $est->name }} ({{ $est->code }})</option>
                                                @endforeach
                                            </select>
                                            @error('establishment_id') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    @elseif($scope_type === 'employee')
                                        <div class="sm:col-span-2">
                                            <label for="employeeId" class="block font-bold text-gray-700 mb-1">Selecione o Colaborador *</label>
                                            <select id="employeeId" wire:model.live="employee_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                                <option value="">Selecione o colaborador...</option>
                                                @foreach($employees as $emp)
                                                    <option value="{{ $emp->id }}">{{ $emp->user?->name ?? 'Colab '.$emp->id }} (CPF: {{ $emp->cpf }})</option>
                                                @endforeach
                                            </select>
                                            @error('employee_id') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                </div>

                                @if($legal_regime && in_array($legal_regime, ['federal_statutory', 'state_statutory', 'municipal_statutory'], true))
                                    <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 text-xs">
                                        <strong>Atenção para servidores estatutários:</strong> O regime não é regido pela CLT privada. Indique a lei, decreto ou estatuto oficial do seu ente federativo.
                                    </div>
                                @endif
                            </div>

                            <!-- PERGUNTA 2: O que será feito com as diferenças de jornada? -->
                            <div class="bg-white rounded-xl p-4 border border-gray-200/90 space-y-3">
                                <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-100 pb-2">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[11px] font-bold">2</span>
                                    <span>O que será feito com as diferenças de jornada? (Tratamento)</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <!-- Opção 1: Sem banco de horas -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'no_bank' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="no_bank" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Sem banco de horas</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            O sistema acompanha as diferenças de jornada, sem acumular automaticamente créditos em banco. O tratamento será definido conforme as regras aplicáveis.
                                        </p>
                                    </label>

                                    <!-- Opção 2: Compensar dentro do mês -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'monthly_compensation' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="monthly_compensation" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Compensar dentro do mês</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            As diferenças podem ser compensadas dentro da competência, conforme a política autorizada.
                                        </p>
                                    </label>

                                    <!-- Opção 3: Acumular em banco -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'cumulative_bank' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="cumulative_bank" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Acumular em banco</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            Créditos e débitos elegíveis são controlados entre competências, respeitando as regras de compensação.
                                        </p>
                                    </label>

                                    <!-- Opção 4: Encaminhar para folha -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'direct_payroll' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="direct_payroll" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Encaminhar horas autorizadas para folha</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            Horas autorizadas são encaminhadas para processamento. O sistema não realiza pagamentos automaticamente.
                                        </p>
                                    </label>

                                    <!-- Opção 5: Compensar por folga -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'time_off' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="time_off" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Compensar por folga</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            Diferenças autorizadas geram créditos para usufruto de folga, conforme a programação alinhada com a gestão.
                                        </p>
                                    </label>

                                    <!-- Opção 6: Modelo misto -->
                                    <label class="relative flex flex-col p-3 rounded-xl border cursor-pointer transition {{ $modality === 'hybrid' ? 'border-indigo-600 bg-indigo-50/50 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="modality" value="hybrid" class="text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-bold text-gray-900 text-xs">Modelo misto</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1 leading-relaxed">
                                            Parte das horas é acumulada em banco até o limite definido e o restante é encaminhado para folha ou outro tratamento autorizado.
                                        </p>
                                    </label>
                                </div>
                                @error('modality') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- PERGUNTA 3: Quando essa regra começa a valer? -->
                            <div class="bg-white rounded-xl p-4 border border-gray-200/90 space-y-3">
                                <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-100 pb-2">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[11px] font-bold">3</span>
                                    <span>Quando essa regra começa a valer? (Vigência e Referência)</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="effectiveFrom" class="block font-bold text-gray-700 mb-1">Data de início da vigência *</label>
                                        <input id="effectiveFrom" type="date" wire:model.live="effective_from" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                        @error('effective_from') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="legalFramework" class="block font-bold text-gray-700 mb-1">Referência normativa aplicável *</label>
                                        <input id="legalFramework" type="text" wire:model="legal_framework" placeholder="Ex.: CLT art. 59 § 5º, Acordo Coletivo 2026 ou Lei Municipal nº..." class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                        <span class="text-[11px] text-gray-500 block mt-0.5">O sistema registra a fundamentação para auditoria. A validade jurídica depende do instrumento formal correspondente.</span>
                                        @error('legal_framework') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                @if($conflictWarning)
                                    <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 text-xs">
                                        {{ $conflictWarning }}
                                    </div>
                                @endif

                                @if($closedPeriodWarning)
                                    <div class="p-3 bg-sky-50 border border-sky-300 rounded-xl text-sky-900 text-xs">
                                        {{ $closedPeriodWarning }}
                                    </div>
                                @endif
                            </div>

                            <!-- CONFIGURAÇÃO AVANÇADA (DIVULGAÇÃO PROGRESSIVA) -->
                            <div x-data="{ showAdvanced: false }" class="border border-gray-200/90 rounded-xl overflow-hidden bg-white">
                                <button type="button" @click="showAdvanced = !showAdvanced" :aria-expanded="showAdvanced" class="w-full p-3.5 bg-gray-50/80 hover:bg-gray-100/80 text-left font-bold text-gray-700 flex items-center justify-between text-xs transition min-h-[44px]">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                        </svg>
                                        Configurações Avançadas (prazos, limites, tolerâncias e exceções)
                                    </span>
                                    <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="showAdvanced ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="showAdvanced" x-collapse class="p-4 space-y-4 border-t border-gray-200/80 bg-white">
                                    <p class="text-[11px] text-gray-500">
                                        Campos opcionais e específicos para detalhamento operacional.
                                    </p>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @if(in_array($modality, ['cumulative_bank', 'hybrid'], true))
                                            <div>
                                                <label for="maxMonths" class="block font-bold text-gray-700 mb-1">Prazo Máximo de Compensação (Meses)</label>
                                                <select id="maxMonths" wire:model="max_compensation_months" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                                    <option value="1">1 mês</option>
                                                    <option value="6">6 meses (Acordo individual escrito - CLT art. 59 § 5º)</option>
                                                    <option value="12">12 meses / 1 ano (Exige CCT/ACT - CLT art. 59 § 2º)</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label for="carryOver" class="block font-bold text-gray-700 mb-1">Teto de transporte de horas (0 = sem teto)</label>
                                                <input id="carryOver" type="number" wire:model="carry_over_limit_hours" min="0" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            </div>
                                        @endif

                                        @if($modality === 'hybrid')
                                            <div>
                                                <label for="dailyBankLimit" class="block font-bold text-gray-700 mb-1">Teto Diário para Banco de Horas (Minutos) *</label>
                                                <input id="dailyBankLimit" type="number" wire:model="daily_bank_limit_minutes" min="1" max="480" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                                <span class="text-[11px] text-gray-500">Ex.: 60 min. O restante é destinado para a folha.</span>
                                                @error('daily_bank_limit_minutes') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        @endif

                                        <div>
                                            <label for="effectiveUntil" class="block font-bold text-gray-700 mb-1">Fim da Vigência (opcional)</label>
                                            <input id="effectiveUntil" type="date" wire:model="effective_until" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            <span class="text-[11px] text-gray-500">Deixe em branco para vigência por prazo indeterminado.</span>
                                            @error('effective_until') <span class="text-rose-600 font-semibold text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label for="punchTolerance" class="block font-bold text-gray-700 mb-1">Tolerância por Batida (Minutos)</label>
                                            <input id="punchTolerance" type="number" wire:model="punch_tolerance_minutes" min="0" max="30" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            <span class="text-[11px] text-gray-500">Padrão CLT: 5 minutos (art. 58, § 1º).</span>
                                        </div>

                                        <div>
                                            <label for="dailyTolerance" class="block font-bold text-gray-700 mb-1">Tolerância Diária Máxima (Minutos)</label>
                                            <input id="dailyTolerance" type="number" wire:model="daily_tolerance_minutes" min="0" max="60" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                            <span class="text-[11px] text-gray-500">Padrão CLT: 10 minutos diários (Súmula 366 TST).</span>
                                        </div>
                                    </div>

                                    <div class="space-y-2 pt-2 border-t border-gray-100">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model="compensate_deficit_with_credit" class="rounded text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-gray-700 font-medium">Permitir compensação de déficits com créditos do período</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model="allow_unjustified_deficit_deduction" class="rounded text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-gray-700 font-medium">Prever desconto de atrasos ou faltas injustificadas conforme regulamento</span>
                                        </label>
                                    </div>

                                    <div class="pt-2">
                                        <label for="policyNotes" class="block font-bold text-gray-700 mb-1">Notas Administrativas / Histórico Interno</label>
                                        <textarea id="policyNotes" wire:model="notes" rows="2" placeholder="Observações internas sobre o instrumento ou histórico de aprovação..." class="w-full border border-gray-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                </div>

                <!-- Rodapé / Ações do Modal -->
                <div class="p-4 bg-gray-50 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-2">
                    @if($isReviewing)
                        <button type="button" wire:click="backToEditing" wire:loading.attr="disabled" class="w-full sm:w-auto px-4 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 font-semibold rounded-xl text-xs min-h-[44px]">
                            Voltar e editar
                        </button>
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" class="w-full sm:w-auto px-4 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs min-h-[44px]">
                            Salvar rascunho
                        </button>
                        @if(Auth::user()?->isAdmin())
                            <button type="button" wire:click="saveAndActivate" wire:loading.attr="disabled" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-bold rounded-xl text-xs shadow-xs min-h-[44px]">
                                Aprovar e ativar
                            </button>
                        @endif
                    @else
                        <button type="button" wire:click="$set('showModal', false)" wire:loading.attr="disabled" class="w-full sm:w-auto px-4 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 font-semibold rounded-xl text-xs min-h-[44px]">
                            Cancelar
                        </button>
                        <button type="submit" form="policy-form" wire:loading.attr="disabled" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-bold rounded-xl text-xs shadow-xs min-h-[44px]">
                            Revisar configuração
                        </button>
                    @endif
                </div>

            </div>
        </div>
    @endif
</div>
