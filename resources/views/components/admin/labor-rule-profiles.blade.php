<?php

use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\LaborRuleProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Perfis Normativos - RH')] class extends Component
{
    use WithPagination;

    // Filtros de busca
    public string $search = '';
    public string $regimeFilter = '';
    public string $statusFilter = '';
    public string $validityFilter = '';

    // Modais de Controle
    public bool $showFormModal = false;
    public bool $showViewModal = false;
    public bool $showApproveModal = false;
    public bool $showRevokeModal = false;
    public bool $showVersionHistoryModal = false;

    // Identificadores de Contexto
    public ?int $selectedProfileId = null;
    public ?int $editingProfileId = null;
    public ?int $parentProfileId = null;
    public bool $isCreatingVersion = false;

    // Campos do Formulário
    public string $name = '';
    public string $code = '';
    public string $legal_regime = 'municipal_statutory';
    public string $jurisdiction = 'municipal';
    public string $entity_name = '';
    public string $legal_reference = '';
    public string $description = '';
    public string $effective_from = '';
    public ?string $effective_until = null;
    public string $change_reason = '';

    // Modalidades autorizadas
    public array $allowed_modalities = ['standard', '12x36'];

    // Parâmetros de apuração (configuration)
    public string $night_start_time = '22:00';
    public string $night_end_time = '05:00';
    public float $additional_percentage = 20.0;
    public int $reduced_hour_seconds = 3150;
    public bool $apply_reduced_hour = true;
    public bool $apply_extension = true;
    public bool $scale_12x36_compensates_extension = false;
    public bool $scale_12x36_compensates_holidays = false;
    public string $holiday_treatment = 'normal';
    public bool $deduct_break_from_night = true;

    // Revogação
    public string $revocation_reason = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || $user->role !== UserRole::Admin) {
            abort(403, 'Acesso restrito ao RH / Administrador do sistema. Gestores de setor não podem gerenciar perfis normativos.');
        }

        $this->effective_from = Carbon::today()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRegimeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedValidityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLegalRegime($value): void
    {
        $regime = LegalRegime::tryFrom($value);
        if ($regime) {
            $this->jurisdiction = $regime->defaultJurisdiction() ?? 'municipal';
            if ($regime === LegalRegime::CLT) {
                $this->additional_percentage = max(20.0, $this->additional_percentage);
                $this->scale_12x36_compensates_extension = true;
                $this->scale_12x36_compensates_holidays = true;
                $this->holiday_treatment = 'scale_offset';
            } elseif ($regime === LegalRegime::FederalStatutory) {
                $this->additional_percentage = max(25.0, $this->additional_percentage);
                $this->scale_12x36_compensates_extension = false;
                $this->scale_12x36_compensates_holidays = false;
                $this->holiday_treatment = 'normal';
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isCreatingVersion = false;
        $this->legal_regime = 'municipal_statutory';
        $this->jurisdiction = 'municipal';
        $this->effective_from = Carbon::today()->format('Y-m-d');
        $this->allowed_modalities = ['standard', '12x36'];
        $this->additional_percentage = 20.0;
        $this->showFormModal = true;
    }

    public function openEditDraftModal(int $id): void
    {
        $profile = LaborRuleProfile::findOrFail($id);

        if (! $profile->isDraft()) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Perfil Já Aprovado',
                'message' => 'Perfis já aprovados e vigentes não podem ser editados retroativamente. Crie uma nova versão para manter a integridade histórica.',
            ]);

            return;
        }

        $this->resetForm();
        $this->editingProfileId = $profile->id;
        $this->isCreatingVersion = false;
        $this->populateFromProfile($profile);
        $this->showFormModal = true;
    }

    public function openCreateNextVersionModal(int $id): void
    {
        $profile = LaborRuleProfile::findOrFail($id);
        $this->resetForm();
        $this->parentProfileId = $profile->id;
        $this->isCreatingVersion = true;
        $this->populateFromProfile($profile);
        $this->name = $profile->name . ' (Nova Versão)';
        $this->effective_from = Carbon::today()->startOfMonth()->addMonth()->format('Y-m-d');
        $this->effective_until = null;
        $this->change_reason = "Revisão normativa baseada na versão {$profile->version}.";
        $this->showFormModal = true;
    }

    public function openViewModal(int $id): void
    {
        $this->selectedProfileId = $id;
        $this->showViewModal = true;
    }

    public function openApproveModal(int $id): void
    {
        $profile = LaborRuleProfile::findOrFail($id);
        if (! $profile->isDraft()) {
            return;
        }
        $this->selectedProfileId = $id;
        $this->showApproveModal = true;
    }

    public function confirmApproval(): void
    {
        if (! $this->selectedProfileId) {
            return;
        }

        $profile = LaborRuleProfile::findOrFail($this->selectedProfileId);
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::Admin) {
            abort(403, 'Apenas o Administrador / RH pode aprovar perfis normativos.');
        }

        try {
            DB::transaction(function () use ($profile, $user) {
                $profile->approve($user);

                // Se for nova versão de um perfil pai, encerra a vigência do pai na véspera do início da nova versão
                if ($profile->parent_profile_id) {
                    $parent = LaborRuleProfile::find($profile->parent_profile_id);
                    if ($parent && $parent->isApproved() && ! $parent->effective_until) {
                        $parentClosing = $profile->effective_from->copy()->subDay();
                        if ($parentClosing->gte($parent->effective_from)) {
                            $parent->update(['effective_until' => $parentClosing->format('Y-m-d')]);
                        }
                    }
                }
            });

            $this->showApproveModal = false;
            $this->selectedProfileId = null;

            session()->flash('message', "Perfil normativo '{$profile->name}' aprovado com sucesso! Agora ele pode ser atribuído a colaboradores e autorizar escalas especiais.");
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Perfil Aprovado!',
                'message' => "O perfil '{$profile->name}' foi homologado com sucesso e passa a ter validade jurídica para autorização de escalas especiais.",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Aprovar',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function openRevokeModal(int $id): void
    {
        $this->selectedProfileId = $id;
        $this->revocation_reason = '';
        $this->showRevokeModal = true;
    }

    public function confirmRevocation(): void
    {
        $this->validate([
            'revocation_reason' => 'required|string|min:6|max:1000',
        ], [
            'revocation_reason.required' => 'Informe a fundamentação formal ou motivo da revogação do perfil.',
            'revocation_reason.min' => 'O motivo deve conter ao menos 6 caracteres para fins de auditoria.',
        ]);

        if (! $this->selectedProfileId) {
            return;
        }

        $profile = LaborRuleProfile::findOrFail($this->selectedProfileId);
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::Admin) {
            abort(403, 'Apenas o Administrador / RH pode revogar perfis normativos.');
        }

        $profile->revoke($user, $this->revocation_reason);

        $this->showRevokeModal = false;
        $this->selectedProfileId = null;
        $this->revocation_reason = '';

        session()->flash('message', "Perfil normativo '{$profile->name}' revogado com sucesso. Novas escalas sob este perfil não poderão mais ser atribuídas.");
        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Perfil Revogado',
            'message' => "O perfil '{$profile->name}' foi revogado e arquivado para consultas históricas.",
        ]);
    }

    public function save(): void
    {
        $user = Auth::user();
        if (! $user || $user->role !== UserRole::Admin) {
            abort(403, 'Apenas o Administrador / RH pode salvar perfis normativos.');
        }

        $this->validate([
            'name' => 'required|string|min:4|max:150',
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('labor_rule_profiles', 'code')->ignore($this->editingProfileId),
            ],
            'legal_regime' => 'required|string',
            'jurisdiction' => 'required|string',
            'entity_name' => 'nullable|string|max:150',
            'legal_reference' => 'required|string|min:4|max:255',
            'description' => 'nullable|string|max:1000',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'allowed_modalities' => 'required|array|min:1',
            'night_start_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'night_end_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'additional_percentage' => 'required|numeric|min:0|max:100',
            'reduced_hour_seconds' => 'required|integer|min:1800|max:3600',
        ], [
            'legal_reference.required' => 'A fundamentação legal (Lei nº, Decreto, Portaria ou Acordo Coletivo) é obrigatória para conformidade REP-P / PTRP.',
            'allowed_modalities.required' => 'Selecione ao menos uma modalidade autorizada pela norma.',
            'effective_until.after_or_equal' => 'A data final de vigência não pode ser anterior à data de início.',
        ]);

        $config = [
            'night_start_time' => $this->night_start_time,
            'night_end_time' => $this->night_end_time,
            'additional_percentage' => (float) $this->additional_percentage,
            'reduced_hour_seconds' => (int) $this->reduced_hour_seconds,
            'apply_reduced_hour' => (bool) $this->apply_reduced_hour,
            'apply_extension' => (bool) $this->apply_extension,
            'scale_12x36_compensates_extension' => (bool) $this->scale_12x36_compensates_extension,
            'scale_12x36_compensates_holidays' => (bool) $this->scale_12x36_compensates_holidays,
            'holiday_treatment' => $this->holiday_treatment,
            'deduct_break_from_night' => (bool) $this->deduct_break_from_night,
            'allowed_modalities' => array_values($this->allowed_modalities),
        ];

        try {
            DB::transaction(function () use ($config, $user) {
                if ($this->isCreatingVersion && $this->parentProfileId) {
                    $parent = LaborRuleProfile::findOrFail($this->parentProfileId);
                    $newVersion = $parent->createNextVersion([
                        'name' => $this->name,
                        'legal_regime' => $this->legal_regime,
                        'jurisdiction' => $this->jurisdiction,
                        'entity_name' => $this->entity_name ?: null,
                        'legal_reference' => $this->legal_reference,
                        'description' => $this->description ?: null,
                        'effective_from' => $this->effective_from,
                        'effective_until' => $this->effective_until ?: null,
                        'change_reason' => $this->change_reason ?: "Nova versão baseada na versão {$parent->version}",
                        'configuration' => $config,
                    ], $user);

                    session()->flash('message', "Nova versão {$newVersion->version} criada como rascunho com sucesso! Revise os parâmetros e aprove para ativá-la.");
                } elseif ($this->editingProfileId) {
                    $profile = LaborRuleProfile::findOrFail($this->editingProfileId);
                    $profile->update([
                        'name' => $this->name,
                        'code' => $this->code ?: $profile->code,
                        'legal_regime' => $this->legal_regime,
                        'jurisdiction' => $this->jurisdiction,
                        'entity_name' => $this->entity_name ?: null,
                        'legal_reference' => $this->legal_reference,
                        'description' => $this->description ?: null,
                        'effective_from' => $this->effective_from,
                        'effective_until' => $this->effective_until ?: null,
                        'configuration' => $config,
                    ]);

                    session()->flash('message', 'Perfil em rascunho atualizado com sucesso.');
                } else {
                    $finalCode = ! empty(trim($this->code))
                        ? trim($this->code)
                        : strtolower(preg_replace('/[^a-z0-9_-]/', '', str_replace(' ', '-', $this->name))).'-'.substr(md5(uniqid()), 0, 5);

                    LaborRuleProfile::create([
                        'name' => $this->name,
                        'code' => $finalCode,
                        'legal_regime' => $this->legal_regime,
                        'jurisdiction' => $this->jurisdiction,
                        'entity_name' => $this->entity_name ?: null,
                        'legal_reference' => $this->legal_reference,
                        'description' => $this->description ?: null,
                        'effective_from' => $this->effective_from,
                        'effective_until' => $this->effective_until ?: null,
                        'approval_status' => LaborRuleApprovalStatus::Draft,
                        'version' => 1,
                        'configuration' => $config,
                    ]);

                    session()->flash('message', 'Perfil normativo cadastrado em Rascunho com sucesso. Revise os parâmetros e clique em Aprovar quando estiver homologado.');
                }
            });

            $this->showFormModal = false;
            $this->resetForm();
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Perfil Normativo Salvo',
                'message' => 'Os parâmetros do perfil de jornada foram gravados com sucesso no banco de dados.',
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (\Exception $e) {
            $this->addError('legal_reference', $e->getMessage());
        }
    }

    private function populateFromProfile(LaborRuleProfile $profile): void
    {
        $this->name = $profile->name;
        $this->code = $profile->code;
        $this->legal_regime = $profile->legal_regime?->value ?? (string) $profile->legal_regime;
        $this->jurisdiction = $profile->jurisdiction;
        $this->entity_name = $profile->entity_name ?? '';
        $this->legal_reference = $profile->legal_reference;
        $this->description = $profile->description ?? '';
        $this->effective_from = $profile->effective_from->format('Y-m-d');
        $this->effective_until = $profile->effective_until?->format('Y-m-d');

        $cfg = $profile->configuration ?? [];
        $this->allowed_modalities = $cfg['allowed_modalities'] ?? ['standard', '12x36'];
        $this->night_start_time = $cfg['night_start_time'] ?? '22:00';
        $this->night_end_time = $cfg['night_end_time'] ?? '05:00';
        $this->additional_percentage = (float) ($cfg['additional_percentage'] ?? 20.0);
        $this->reduced_hour_seconds = (int) ($cfg['reduced_hour_seconds'] ?? 3150);
        $this->apply_reduced_hour = ! empty($cfg['apply_reduced_hour']);
        $this->apply_extension = ! empty($cfg['apply_extension']);
        $this->scale_12x36_compensates_extension = ! empty($cfg['scale_12x36_compensates_extension']);
        $this->scale_12x36_compensates_holidays = ! empty($cfg['scale_12x36_compensates_holidays']);
        $this->holiday_treatment = $cfg['holiday_treatment'] ?? 'normal';
        $this->deduct_break_from_night = ! empty($cfg['deduct_break_from_night']);
    }

    private function resetForm(): void
    {
        $this->editingProfileId = null;
        $this->parentProfileId = null;
        $this->isCreatingVersion = false;
        $this->name = '';
        $this->code = '';
        $this->legal_regime = 'municipal_statutory';
        $this->jurisdiction = 'municipal';
        $this->entity_name = '';
        $this->legal_reference = '';
        $this->description = '';
        $this->effective_from = Carbon::today()->format('Y-m-d');
        $this->effective_until = null;
        $this->change_reason = '';
        $this->allowed_modalities = ['standard', '12x36'];
        $this->night_start_time = '22:00';
        $this->night_end_time = '05:00';
        $this->additional_percentage = 20.0;
        $this->reduced_hour_seconds = 3150;
        $this->apply_reduced_hour = true;
        $this->apply_extension = true;
        $this->scale_12x36_compensates_extension = false;
        $this->scale_12x36_compensates_holidays = false;
        $this->holiday_treatment = 'normal';
        $this->deduct_break_from_night = true;
        $this->resetErrorBag();
    }

    public function with(): array
    {
        $today = Carbon::today()->format('Y-m-d');

        $query = LaborRuleProfile::query()
            ->with(['approvedByUser', 'revokedByUser', 'parentProfile'])
            ->withCount('employees');

        if (! empty(trim($this->search))) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('legal_reference', 'like', $term)
                    ->orWhere('entity_name', 'like', $term);
            });
        }

        if (! empty($this->regimeFilter)) {
            $query->where('legal_regime', $this->regimeFilter);
        }

        if (! empty($this->statusFilter)) {
            $query->where('approval_status', $this->statusFilter);
        }

        if ($this->validityFilter === 'active') {
            $query->where('effective_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $today);
                });
        } elseif ($this->validityFilter === 'expired') {
            $query->whereNotNull('effective_until')
                ->where('effective_until', '<', $today);
        }

        $profiles = $query->orderBy('legal_regime')
            ->orderBy('name')
            ->orderBy('version', 'desc')
            ->paginate(15);

        $counts = [
            'total' => LaborRuleProfile::count(),
            'approved' => LaborRuleProfile::approved()->count(),
            'draft' => LaborRuleProfile::draft()->count(),
            'revoked' => LaborRuleProfile::revoked()->count(),
        ];

        $viewingProfile = $this->selectedProfileId
            ? LaborRuleProfile::with(['approvedByUser', 'revokedByUser', 'parentProfile', 'childVersions', 'employees.user', 'assignments.employee.user'])
                ->find($this->selectedProfileId)
            : null;

        return [
            'profiles' => $profiles,
            'counts' => $counts,
            'viewingProfile' => $viewingProfile,
            'regimes' => LegalRegime::cases(),
            'approvalStatuses' => LaborRuleApprovalStatus::cases(),
        ];
    }
};
?>

<div class="py-6 sm:py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- CABEÇALHO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1">
                <span>Configurações</span>
                <span class="text-slate-400">/</span>
                <span>Regulamentação Laboral</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                <svg class="h-8 w-8 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                Perfis Normativos e Autorizações
            </h1>
            <p class="text-sm text-slate-600 mt-1 max-w-3xl">
                Cadastre e homologue as legislações que fundamentam jornadas administrativas, plantões 12×36 e 24×72, apuração de adicional noturno e compensações. Garanta segurança jurídica antes da atribuição de escalas aos servidores.
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button wire:click="openCreateModal" type="button" class="min-h-[44px] px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-xs flex items-center gap-2 transition cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Novo Perfil Normativo</span>
            </button>
        </div>
    </div>

    <!-- MENSAGEM FLASH -->
    @if (session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl flex items-center gap-3 text-sm shadow-xs">
        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span class="font-medium">{{ session('message') }}</span>
    </div>
    @endif

    <!-- CARDS DE RESUMO ESTRATÉGICO -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total de Perfis</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-900">{{ $counts['total'] }}</span>
                <span class="text-xs text-slate-400">cadastrados</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Aprovados & Vigentes</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-emerald-700">{{ $counts['approved'] }}</span>
                <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold">Liberam escalas</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Rascunhos / Em Análise</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-amber-700">{{ $counts['draft'] }}</span>
                <span class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full font-semibold">Aguardam RH</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Revogados / Históricos</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-600">{{ $counts['revoked'] }}</span>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full font-semibold">Preservados</span>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Buscar Perfil ou Norma</label>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nome, código, lei ou ente..." class="block w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white min-h-[42px]">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Regime Jurídico</label>
                <select wire:model.live="regimeFilter" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 min-h-[42px]">
                    <option value="">Todos os Regimes</option>
                    @foreach($regimes as $reg)
                        <option value="{{ $reg->value }}">{{ $reg->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Situação de Homologação</label>
                <select wire:model.live="statusFilter" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 min-h-[42px]">
                    <option value="">Todas as Situações</option>
                    @foreach($approvalStatuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vigência Temporal</label>
                <select wire:model.live="validityFilter" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 min-h-[42px]">
                    <option value="">Todas as Vigências</option>
                    <option value="active">Ativo Hoje</option>
                    <option value="expired">Expirado / Encerrado</option>
                </select>
            </div>
        </div>
    </div>

    <!-- LISTAGEM DE PERFIS NORMATIVOS -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left">
                <thead class="bg-slate-50 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Perfil Normativo / Fundamento</th>
                        <th class="px-4 py-3.5">Regime & Ente</th>
                        <th class="px-4 py-3.5">Vigência</th>
                        <th class="px-4 py-3.5">Modalidades</th>
                        <th class="px-4 py-3.5">Situação</th>
                        <th class="px-4 py-3.5 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($profiles as $prof)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 flex items-center gap-2">
                                <span>{{ $prof->name }}</span>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">v{{ $prof->version }}</span>
                            </div>
                            <div class="text-xs text-slate-500 flex items-center gap-2 mt-0.5">
                                <span class="font-mono text-slate-400">{{ $prof->code }}</span>
                                <span>•</span>
                                <span class="truncate max-w-xs" title="{{ $prof->legal_reference }}">{{ $prof->legal_reference }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $prof->legal_regime === LegalRegime::CLT ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($prof->legal_regime === LegalRegime::FederalStatutory ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200') }}">
                                {{ $prof->legal_regime?->label() ?? $prof->legal_regime }}
                            </span>
                            @if($prof->entity_name)
                                <div class="text-xs text-slate-600 font-medium mt-1 truncate max-w-xs">{{ $prof->entity_name }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-700">
                            <div>Início: <strong class="font-mono">{{ $prof->effective_from->format('d/m/Y') }}</strong></div>
                            <div>Fim: <span class="font-mono">{{ $prof->effective_until ? $prof->effective_until->format('d/m/Y') : 'Em aberto' }}</span></div>
                        </td>

                        <td class="px-4 py-3.5">
                            @php
                                $modalities = $prof->configuration['allowed_modalities'] ?? [];
                            @endphp
                            <div class="flex flex-wrap gap-1 max-w-xs">
                                @foreach($modalities as $mKey)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ match($mKey) {
                                            'standard' => 'Administrativa',
                                            'continuous' => 'Corrida',
                                            '6x1' => 'Escala 6x1',
                                            '12x36' => 'Plantão 12x36',
                                            '24x72' => 'Plantão 24x72',
                                            'shift_turn' => 'Turnos',
                                            default => $mKey
                                        } }}
                                    </span>
                                @endforeach
                            </div>
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap">
                            @if($prof->approval_status === LaborRuleApprovalStatus::Approved)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aprovado
                                </span>
                            @elseif($prof->approval_status === LaborRuleApprovalStatus::Draft)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Rascunho
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    Revogado
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <button wire:click="openViewModal({{ $prof->id }})" type="button" class="p-1.5 text-slate-500 hover:text-indigo-600 rounded-lg hover:bg-slate-100 transition min-h-[36px] min-w-[36px] flex items-center justify-center cursor-pointer" title="Visualizar parâmetros e histórico">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>

                                @if($prof->isDraft())
                                    <button wire:click="openEditDraftModal({{ $prof->id }})" type="button" class="p-1.5 text-indigo-600 hover:text-indigo-800 rounded-lg hover:bg-indigo-50 transition min-h-[36px] min-w-[36px] flex items-center justify-center cursor-pointer" title="Editar rascunho">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button wire:click="openApproveModal({{ $prof->id }})" type="button" class="px-2.5 py-1 text-xs font-semibold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition min-h-[36px] cursor-pointer shadow-xs">
                                        Aprovar
                                    </button>
                                @endif

                                @if($prof->isApproved())
                                    <button wire:click="openCreateNextVersionModal({{ $prof->id }})" type="button" class="px-2.5 py-1 text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition min-h-[36px] cursor-pointer" title="Criar nova versão legislativa">
                                        Nova Versão
                                    </button>
                                    <button wire:click="openRevokeModal({{ $prof->id }})" type="button" class="p-1.5 text-rose-600 hover:text-rose-800 rounded-lg hover:bg-rose-50 transition min-h-[36px] min-w-[36px] flex items-center justify-center cursor-pointer" title="Revogar perfil">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                            <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <div class="font-medium">Nenhum perfil normativo encontrado.</div>
                            <div class="text-xs text-slate-400 mt-1">Clique em "Novo Perfil Normativo" para cadastrar regras para o regime desejado.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($profiles->hasPages())
        <div class="p-4 border-t border-slate-200">
            {{ $profiles->links() }}
        </div>
        @endif
    </div>

    <!-- MODAL DE CRIAÇÃO / EDIÇÃO / NOVA VERSÃO -->
    @if($showFormModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showFormModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-3 sm:p-4">
            <div class="relative z-10 w-full max-w-3xl bg-white rounded-3xl p-5 sm:p-7 shadow-2xl text-left space-y-6 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">
                            {{ $isCreatingVersion ? 'Criar Nova Versão de Perfil Normativo' : ($editingProfileId ? 'Editar Perfil Normativo em Elaboração' : 'Cadastrar Novo Perfil Normativo') }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $isCreatingVersion ? 'Esta nova versão herdará os dados anteriores e iniciará em Rascunho para revisão antes da aprovação.' : 'Configure as diretrizes legais oficiais que autorizam modalidades de trabalho e parâmetros de apuração.' }}
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showFormModal', false)" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-6">
                    <!-- SEÇÃO 1: IDENTIFICAÇÃO E ENQUADRAMENTO -->
                    <div class="space-y-4">
                        <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            1. Identificação e Enquadramento Jurídico
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nome do Perfil Normativo *</label>
                                <input type="text" wire:model="name" placeholder="Ex: Estatuto dos Servidores de Teotônio Vilela (Plantão 12x36 e Saúde)" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500" required>
                                @error('name') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Código Único (Opcional)</label>
                                <input type="text" wire:model="code" placeholder="Ex: mun-teotonio-saude-12x36" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                @error('code') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Regime Jurídico *</label>
                                <select wire:model.live="legal_regime" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500" required>
                                    @foreach($regimes as $reg)
                                        <option value="{{ $reg->value }}">{{ $reg->label() }}</option>
                                    @endforeach
                                </select>
                                @error('legal_regime') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Esfera Administrativa *</label>
                                <select wire:model="jurisdiction" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="municipal">Municipal</option>
                                    <option value="state">Estadual</option>
                                    <option value="federal">Federal</option>
                                    <option value="other">Outra / Específica</option>
                                </select>
                                @error('jurisdiction') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ente / Órgão Governamental</label>
                                <input type="text" wire:model="entity_name" placeholder="Ex: Prefeitura Municipal de Teotônio Vilela" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                @error('entity_name') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Referência Legal ou Normativa (Obrigatória) *</label>
                                <input type="text" wire:model="legal_reference" placeholder="Ex: Lei Municipal nº 1.450/2018, art. 32; Decreto nº 12/2020; ACT 2026" class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500" required>
                                @error('legal_reference') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Descrição e Observações</label>
                                <textarea wire:model="description" rows="2" placeholder="Descreva os objetivos, categorias profissionais abrangidas e particularidades da norma..." class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500"></textarea>
                                @error('description') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Início da Vigência *</label>
                                <input type="date" wire:model="effective_from" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500" required>
                                @error('effective_from') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Fim da Vigência (Opcional)</label>
                                <input type="date" wire:model="effective_until" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                                @error('effective_until') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            @if($isCreatingVersion)
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-indigo-700 uppercase mb-1">Motivo da Alteração Legislativa *</label>
                                <input type="text" wire:model="change_reason" placeholder="Ex: Adequação à nova Lei Municipal nº 2.100/2026 com alteração de adicional" class="block w-full px-3 py-2 text-sm border border-indigo-300 rounded-xl bg-indigo-50/40 focus:ring-2 focus:ring-indigo-500" required>
                                @error('change_reason') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- SEÇÃO 2: MODALIDADES AUTORIZADAS -->
                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                2. Modalidades de Escala Autorizadas por Esta Norma
                            </span>
                            <span class="text-[10px] text-slate-500 font-normal">Escalas especiais só são permitidas com respaldo</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('standard', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="standard" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Jornada Administrativa</div>
                                    <div class="text-[10px] text-slate-500">Horários semanais fixos (ex: 40h)</div>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('continuous', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="continuous" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Jornada Contínua</div>
                                    <div class="text-[10px] text-slate-500">Turno ininterrupto de 6h</div>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('6x1', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="6x1" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Escala 6x1</div>
                                    <div class="text-[10px] text-slate-500">6 dias de trabalho x 1 folga</div>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('12x36', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="12x36" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Plantão 12x36</div>
                                    <div class="text-[10px] text-slate-500">12h trabalho x 36h descanso</div>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('24x72', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="24x72" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Plantão 24x72</div>
                                    <div class="text-[10px] text-slate-500">24h trabalho x 72h descanso</div>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ in_array('shift_turn', $allowed_modalities) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="checkbox" wire:model="allowed_modalities" value="shift_turn" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Turnos de Revezamento</div>
                                    <div class="text-[10px] text-slate-500">Escalas rotativas especiais</div>
                                </div>
                            </label>
                        </div>
                        @error('allowed_modalities') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- SEÇÃO 3: PARÂMETROS DE APURAÇÃO (NOTURNO E COMPENSAÇÃO) -->
                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                3. Parâmetros de Apuração de Horas e Compensações
                            </span>
                            <span class="text-[10px] text-slate-500 font-normal">Pisos legais CLT e estatutários respeitados</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Início da Janela Noturna</label>
                                <input type="time" wire:model="night_start_time" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl" required>
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Padrão nacional: 22:00</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Fim da Janela Noturna</label>
                                <input type="time" wire:model="night_end_time" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl" required>
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Padrão nacional: 05:00</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Adicional Noturno (%) *</label>
                                <input type="number" step="0.1" wire:model="additional_percentage" class="block w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-xl" required>
                                <span class="text-[10px] text-slate-400 mt-0.5 block">CLT mín 20%; Federais mín 25%</span>
                                @error('additional_percentage') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="space-y-2.5 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="apply_reduced_hour" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Aplicar Hora Noturna Reduzida (52min30s)</span>
                                    <p class="text-[10px] text-slate-500">Fator de cômputo ficto 60/52.5 (1,142857) conforme CLT Art. 73 §1º e legislações vigentes.</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="apply_extension" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Aplicar Prorrogação Noturna (Súmula 60 TST / Art. 73 §5º)</span>
                                    <p class="text-[10px] text-slate-500">Jornadas cumpridas integralmente no período noturno que se estendam além das 05h mantêm o adicional para as horas prorrogadas.</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="scale_12x36_compensates_extension" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Plantão 12x36 Compensa Prorrogação Noturna</span>
                                    <p class="text-[10px] text-slate-500">Conforme Art. 59-A, parágrafo único da CLT, a remuneração pactuada em 12x36 já abrange as prorrogações do trabalho noturno.</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="scale_12x36_compensates_holidays" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Plantão 12x36 Compensa Feriados e Repouso Semanal Remunerado</span>
                                    <p class="text-[10px] text-slate-500">Feriados trabalhados na escala 12x36 são compensados pelo descanso de 36 horas subsequente (Art. 59-A CLT).</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="deduct_break_from_night" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Deduzir Intervalo Intrajornada do Cômputo Noturno</span>
                                    <p class="text-[10px] text-slate-500">Subtrai o tempo de intervalo intrajornada (ex: 1h) do total de minutos noturnos apurados.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showFormModal', false)" class="min-h-[44px] px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="min-h-[44px] px-5 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 shadow-sm transition cursor-pointer">
                            {{ $isCreatingVersion ? 'Criar Versão em Rascunho' : ($editingProfileId ? 'Salvar Alterações' : 'Salvar Perfil em Rascunho') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE VISUALIZAÇÃO COMPLETA -->
    @if($showViewModal && $viewingProfile)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showViewModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-3 sm:p-4">
            <div class="relative z-10 w-full max-w-3xl bg-white rounded-3xl p-5 sm:p-7 shadow-2xl text-left space-y-6 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xl font-bold text-slate-900">{{ $viewingProfile->name }}</h3>
                            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 font-bold text-slate-700">Versão {{ $viewingProfile->version }}</span>
                        </div>
                        <p class="text-xs font-mono text-slate-500 mt-0.5">{{ $viewingProfile->code }}</p>
                    </div>
                    <button type="button" wire:click="$set('showViewModal', false)" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-4 text-sm">
                    <!-- ENQUADRAMENTO E STATUS -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Regime</span>
                            <div class="font-bold text-slate-900 mt-0.5">{{ $viewingProfile->legal_regime?->label() }}</div>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Esfera</span>
                            <div class="font-bold text-slate-900 mt-0.5 uppercase">{{ $viewingProfile->jurisdiction }}</div>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Situação</span>
                            <div class="font-bold mt-0.5 {{ $viewingProfile->isApproved() ? 'text-emerald-700' : ($viewingProfile->isDraft() ? 'text-amber-700' : 'text-rose-700') }}">
                                {{ $viewingProfile->approval_status?->label() }}
                            </div>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Vigência</span>
                            <div class="font-bold text-slate-900 mt-0.5 font-mono text-xs">
                                {{ $viewingProfile->effective_from->format('d/m/Y') }} a {{ $viewingProfile->effective_until ? $viewingProfile->effective_until->format('d/m/Y') : 'Em aberto' }}
                            </div>
                        </div>
                    </div>

                    <!-- FUNDAMENTAÇÃO LEGAL -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                        <span class="text-xs font-bold text-slate-500 uppercase">Fundamentação Legal / Referência</span>
                        <div class="text-sm font-semibold text-slate-900">{{ $viewingProfile->legal_reference }}</div>
                        @if($viewingProfile->entity_name)
                            <div class="text-xs text-indigo-700 font-medium">Ente federativo: {{ $viewingProfile->entity_name }}</div>
                        @endif
                        @if($viewingProfile->description)
                            <p class="text-xs text-slate-600 pt-1 border-t border-slate-200/60 mt-1">{{ $viewingProfile->description }}</p>
                        @endif
                    </div>

                    <!-- MODALIDADES E PARÂMETROS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                            <span class="text-xs font-bold text-slate-500 uppercase">Modalidades Autorizadas</span>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach($viewingProfile->configuration['allowed_modalities'] ?? [] as $m)
                                    <span class="px-2 py-1 rounded-lg text-xs font-semibold bg-white text-indigo-700 border border-slate-200 shadow-2xs">
                                        {{ match($m) {
                                            'standard' => 'Jornada Administrativa',
                                            'continuous' => 'Horário Corrido',
                                            '6x1' => 'Escala 6x1',
                                            '12x36' => 'Plantão 12x36',
                                            '24x72' => 'Plantão 24x72',
                                            'shift_turn' => 'Turnos Especiais',
                                            default => $m
                                        } }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1 text-xs">
                            <span class="font-bold text-slate-500 uppercase block mb-1.5">Parâmetros de Apuração</span>
                            <div>Adicional Noturno: <strong>{{ $viewingProfile->getAdditionalPercentage() ?? 20 }}%</strong> ({{ $viewingProfile->getNightStartTime() }} às {{ $viewingProfile->getNightEndTime() }})</div>
                            <div>Hora Reduzida (52m30s): <strong>{{ $viewingProfile->shouldApplyReducedHour() ? 'Sim' : 'Não' }}</strong></div>
                            <div>Prorrogação Noturna: <strong>{{ $viewingProfile->shouldApplyExtension() ? 'Sim' : 'Não' }}</strong></div>
                            <div>12x36 Compensa Prorrogação: <strong>{{ $viewingProfile->shouldScale12x36CompensateExtension() ? 'Sim' : 'Não' }}</strong></div>
                            <div>12x36 Compensa Feriados: <strong>{{ $viewingProfile->shouldScale12x36CompensateHolidays() ? 'Sim' : 'Não' }}</strong></div>
                        </div>
                    </div>

                    <!-- CICLO DE AUDITORIA E VERSÕES -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                        <span class="font-bold text-slate-500 uppercase block">Rastreabilidade & Auditoria</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                            <div>Homologado por: <strong>{{ $viewingProfile->approvedByUser->name ?? 'Pendente de aprovação' }}</strong></div>
                            <div>Data de Homologação: <strong>{{ $viewingProfile->approved_at ? $viewingProfile->approved_at->format('d/m/Y H:i:s') : '—' }}</strong></div>
                            @if($viewingProfile->isRevoked())
                                <div>Revogado por: <strong>{{ $viewingProfile->revokedByUser->name ?? 'Não identificado' }}</strong></div>
                                <div>Data de Revogação: <strong>{{ $viewingProfile->revoked_at ? $viewingProfile->revoked_at->format('d/m/Y H:i:s') : '—' }}</strong></div>
                                <div class="sm:col-span-2 text-rose-700 bg-rose-50 p-2 rounded-xl border border-rose-200">
                                    <strong>Motivo da Revogação:</strong> {{ $viewingProfile->revocation_reason }}
                                </div>
                            @endif
                            @if($viewingProfile->parent_profile_id)
                                <div class="sm:col-span-2 text-indigo-700">
                                    Derivado da Versão Anterior: <strong>{{ $viewingProfile->parentProfile?->name ?? ('#'.$viewingProfile->parent_profile_id) }}</strong>
                                    @if($viewingProfile->change_reason)
                                        <div class="text-[11px] text-slate-500 mt-0.5">Motivo: {{ $viewingProfile->change_reason }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- COLABORADORES VINCULADOS -->
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase">
                            <span>Colaboradores com Este Perfil Atribuído</span>
                            <span class="text-slate-400 font-mono">{{ $viewingProfile->employees->count() }} vinculado(s)</span>
                        </div>
                        @if($viewingProfile->employees->isNotEmpty())
                            <div class="max-h-36 overflow-y-auto border border-slate-200 rounded-xl bg-white p-2 divide-y divide-slate-100 text-xs">
                                @foreach($viewingProfile->employees as $emp)
                                    <div class="py-1 px-2 flex items-center justify-between">
                                        <span class="font-medium text-slate-900">{{ $emp->user->name ?? 'Colaborador #'.$emp->id }}</span>
                                        <span class="text-slate-400 font-mono">{{ $emp->masked_cpf }} • Matrícula {{ $emp->registration_number }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-xs text-slate-400 italic">Nenhum colaborador atribuído diretamente a este perfil normativo.</div>
                        @endif
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showViewModal', false)" class="min-h-[44px] px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                        Fechar
                    </button>
                    @if($viewingProfile->isDraft())
                        <button type="button" wire:click="openApproveModal({{ $viewingProfile->id }})" class="min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 shadow-sm transition cursor-pointer">
                            Homologar / Aprovar Perfil
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE CONFIRMAÇÃO DE APROVAÇÃO -->
    @if($showApproveModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showApproveModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl text-left space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Homologar Perfil Normativo</h3>
                        <p class="text-xs text-slate-500">Autorização oficial para uso pelo RH</p>
                    </div>
                </div>

                <p class="text-sm text-slate-600">
                    Você confirma que as regras, adicionais e modalidades deste perfil estão de acordo com a legislação oficial vigente? Após homologado, os parâmetros serão congelados para garantir a imutabilidade das apurações.
                </p>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" wire:click="$set('showApproveModal', false)" class="min-h-[44px] px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmApproval" class="min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 shadow-sm transition cursor-pointer">
                        Confirmar e Homologar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DE REVOGAÇÃO -->
    @if($showRevokeModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" wire:click="$set('showRevokeModal', false)"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl text-left space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Revogar Perfil Normativo</h3>
                        <p class="text-xs text-slate-500">Encerramento formal de autorizações</p>
                    </div>
                </div>

                <p class="text-sm text-slate-600">
                    Ao revogar este perfil, novas atribuições de escalas especiais serão bloqueadas. As apurações já fechadas permanecerão preservadas conforme o histórico legal.
                </p>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Motivo / Fundamentação da Revogação *</label>
                    <textarea wire:model="revocation_reason" rows="3" placeholder="Ex: Substituído pela Lei Municipal nº 2.500/2026 a partir do próximo exercício..." class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-rose-500" required></textarea>
                    @error('revocation_reason') <span class="text-xs text-rose-600 block mt-1 font-semibold">{{ $message }}</span> @enderror
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" wire:click="$set('showRevokeModal', false)" class="min-h-[44px] px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmRevocation" class="min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-xl text-sm font-semibold hover:bg-rose-700 shadow-sm transition cursor-pointer">
                        Confirmar Revogação
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
