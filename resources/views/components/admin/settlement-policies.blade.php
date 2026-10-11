<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\WorkTimeSettlementPolicy;
use App\Models\Establishment;
use App\Models\Employee;
use App\Models\User;
use App\Enums\LegalRegime;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Política de Jornada e Compensação')] class extends Component
{
    use WithPagination;

    // Filtros
    public string $filterScope = 'all';
    public string $filterRegime = 'all';
    public string $filterModality = 'all';
    public string $search = '';

    // Modal de Criação / Edição
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $policyId = null;

    // Campos agrupados em 6 blocos
    // 1. Identificação, Vigência & Escopo
    public string $name = '';
    public string $scope_type = 'company'; // 'company', 'establishment', 'employee'
    public ?int $establishment_id = null;
    public ?int $employee_id = null;
    public string $effective_from = '';
    public ?string $effective_until = null;
    public string $status = 'active';
    public ?string $notes = null;

    // 2. Legislação Aplicável
    public ?string $legal_regime = 'CLT';
    public string $legal_framework = 'CLT, Art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)';

    // 3. Destinação
    public string $modality = 'cumulative_bank';
    public int $daily_bank_limit_minutes = 60; // Para modelo misto

    // 4. Banco de Horas
    public int $max_compensation_months = 6;
    public int $carry_over_limit_hours = 0; // 0 = sem teto

    // 5. Apuração
    public int $punch_tolerance_minutes = 5;
    public int $daily_tolerance_minutes = 10;

    // 6. Tratamento das Diferenças
    public bool $compensate_deficit_with_credit = true;
    public bool $allow_unjustified_deficit_deduction = true;

    public function mount(): void
    {
        $this->effective_from = now()->startOfMonth()->toDateString();
    }

    public function updatedLegalRegime($value): void
    {
        if ($value === 'CLT') {
            $this->legal_framework = 'CLT, Art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)';
            $this->max_compensation_months = 6;
        } elseif ($value === LegalRegime::FederalStatutory->value) {
            $this->legal_framework = 'Lei nº 8.112/1990, art. 74 c/c Decreto nº 1.590/1995 e IN SGP/SEDGG/ME nº 2/2018';
            $this->max_compensation_months = 1;
        } elseif ($value === LegalRegime::MunicipalStatutory->value) {
            $this->legal_framework = 'Lei Municipal / Estatuto dos Servidores Públicos Municipais do Ente';
            $this->max_compensation_months = 1;
        } elseif ($value === LegalRegime::StateStatutory->value) {
            $this->legal_framework = 'Lei Estadual / Estatuto dos Servidores Públicos Estaduais';
            $this->max_compensation_months = 1;
        } else {
            $this->legal_framework = 'Regulamento interno da organização';
        }
    }

    public function updatedModality($value): void
    {
        if ($value === SettlementModality::MonthlyCompensation->value) {
            $this->max_compensation_months = 1;
            if ($this->legal_regime === 'CLT') {
                $this->legal_framework = 'CLT, Art. 59, § 6º - Acordo individual tácito ou escrito para compensação no mesmo mês';
            }
        } elseif ($value === SettlementModality::CumulativeBank->value) {
            if ($this->legal_regime === 'CLT' && $this->max_compensation_months > 6) {
                $this->legal_framework = 'CLT, Art. 59, § 2º - Acordo ou Convenção Coletiva de Trabalho (CCT/ACT anual)';
            }
        } elseif ($value === SettlementModality::DirectPayroll->value) {
            $this->legal_framework = 'CLT, Art. 59 caput - Horas extras remuneradas com adicional em folha';
        } elseif ($value === SettlementModality::NoBank->value) {
            $this->legal_framework = 'Regulamento da organização - sem regime de banco de horas';
        }
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->isEditing = false;
        $this->policyId = null;

        $this->name = 'Nova Política de Compensação';
        $this->scope_type = 'company';
        $this->establishment_id = null;
        $this->employee_id = null;
        $this->effective_from = now()->startOfMonth()->toDateString();
        $this->effective_until = null;
        $this->status = 'active';
        $this->notes = null;

        $this->legal_regime = 'CLT';
        $this->legal_framework = 'CLT, Art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)';
        $this->modality = SettlementModality::CumulativeBank->value;
        $this->max_compensation_months = 6;
        $this->carry_over_limit_hours = 0;
        $this->daily_bank_limit_minutes = 60;
        $this->punch_tolerance_minutes = 5;
        $this->daily_tolerance_minutes = 10;
        $this->compensate_deficit_with_credit = true;
        $this->allow_unjustified_deficit_deduction = true;

        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $policy = WorkTimeSettlementPolicy::findOrFail($id);

        $this->isEditing = true;
        $this->policyId = $policy->id;
        $this->name = $policy->name;
        $this->scope_type = $policy->getScopeType();
        $this->establishment_id = $policy->establishment_id;
        $this->employee_id = $policy->employee_id;
        $this->effective_from = $policy->effective_from->toDateString();
        $this->effective_until = $policy->effective_until?->toDateString();
        $this->status = $policy->status;
        $this->notes = $policy->notes;

        $this->legal_regime = $policy->legal_regime?->value;
        $this->legal_framework = $policy->legal_framework;
        $this->modality = $policy->modality->value;

        $this->max_compensation_months = (int) ($policy->compensation_terms['max_months'] ?? 6);
        $this->punch_tolerance_minutes = (int) ($policy->compensation_terms['punch_tolerance_minutes'] ?? 5);
        $this->daily_tolerance_minutes = (int) ($policy->compensation_terms['daily_tolerance_minutes'] ?? 10);
        $this->carry_over_limit_hours = (int) ($policy->carry_over_terms['max_carry_over_hours'] ?? 0);
        $this->daily_bank_limit_minutes = (int) ($policy->hybrid_rules['daily_bank_limit_minutes'] ?? 60);

        $this->showModal = true;
    }

    public function savePolicy(): void
    {
        $this->validate([
            'name' => 'required|string|max:120',
            'scope_type' => 'required|in:company,establishment,employee',
            'establishment_id' => 'nullable|required_if:scope_type,establishment|exists:establishments,id',
            'employee_id' => 'nullable|required_if:scope_type,employee|exists:employees,id',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'legal_framework' => 'required|string|min:5|max:300',
            'modality' => 'required|string',
            'max_compensation_months' => 'required|integer|min:1|max:24',
            'punch_tolerance_minutes' => 'required|integer|min:0|max:30',
            'daily_tolerance_minutes' => 'required|integer|min:0|max:60',
            'daily_bank_limit_minutes' => 'required|integer|min:1|max:480',
        ]);

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
                'max_months' => (int) $this->max_compensation_months,
                'punch_tolerance_minutes' => (int) $this->punch_tolerance_minutes,
                'daily_tolerance_minutes' => (int) $this->daily_tolerance_minutes,
            ],
            'carry_over_terms' => [
                'max_carry_over_hours' => (int) $this->carry_over_limit_hours,
            ],
            'hybrid_rules' => [
                'daily_bank_limit_minutes' => (int) $this->daily_bank_limit_minutes,
            ],
            'status' => $this->status,
            'approval_status' => SettlementPolicyApprovalStatus::Approved->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes' => $this->notes,
        ];

        try {
            if ($this->isEditing && $this->policyId) {
                $policy = WorkTimeSettlementPolicy::findOrFail($this->policyId);
                $policy->update($data);
                $msg = 'Política de compensação atualizada com sucesso!';
            } else {
                $data['created_by'] = Auth::id();
                WorkTimeSettlementPolicy::create($data);
                $msg = 'Nova política de compensação cadastrada com sucesso!';
            }

            $this->showModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Política Homologada!',
                'message' => $msg,
                'buttonText' => 'OK',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Salvar Política',
                'message' => $e->getMessage(),
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
            'title' => 'Status da Política Atualizado',
            'message' => sprintf('A política "%s" está agora %s.', $policy->name, $policy->status === 'active' ? 'ativa' : 'inativa'),
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
            $s = '%'.$this->search.'%';
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

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-indigo-50 text-indigo-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Política de Jornada e Compensação</h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                        Parametrização normativa de apuração, classificação, destinação e quitação de horas por empresa, estabelecimento ou vínculo.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.time-bank') }}" class="inline-flex items-center px-3.5 py-2.5 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-xl text-xs font-semibold transition gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                Extrato do Banco
            </a>

            @if(Auth::user()->isAdmin())
                <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs gap-1.5 min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Nova Política
                </button>
            @endif
        </div>
    </div>

    <!-- Guia Pedagógico de Domínio e Precedência Determinística -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-800 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-900/60 pb-3">
            <span class="text-xs uppercase tracking-wider font-bold text-indigo-300">Estrutura Normativa do PontoFácil</span>
            <span class="text-xs text-indigo-200 font-mono">Precedência: Vínculo &gt; Estabelecimento &gt; Empresa &gt; Legado</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 sm:gap-4 text-xs">
            <div class="bg-slate-800/80 rounded-xl p-3 border border-slate-700/60">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-5 h-5 rounded-full bg-indigo-500/30 text-indigo-300 font-bold flex items-center justify-center text-[11px]">1</span>
                    <strong class="text-white text-sm">Apuração</strong>
                </div>
                <p class="text-slate-300 leading-relaxed text-[11px]">
                    Fato analítico incontroverso. Identifica horas previstas, trabalhadas e saldo bruto, sem inventar cálculo remuneratório.
                </p>
            </div>

            <div class="bg-slate-800/80 rounded-xl p-3 border border-slate-700/60">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-5 h-5 rounded-full bg-indigo-500/30 text-indigo-300 font-bold flex items-center justify-center text-[11px]">2</span>
                    <strong class="text-white text-sm">Classificação</strong>
                </div>
                <p class="text-slate-300 leading-relaxed text-[11px]">
                    Aplica tolerâncias (Art. 58 CLT), atestados abonados ou pendências jurídicas (como vedações estatutárias).
                </p>
            </div>

            <div class="bg-slate-800/80 rounded-xl p-3 border border-slate-700/60">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-5 h-5 rounded-full bg-indigo-500/30 text-indigo-300 font-bold flex items-center justify-center text-[11px]">3</span>
                    <strong class="text-white text-sm">Destinação</strong>
                </div>
                <p class="text-slate-300 leading-relaxed text-[11px]">
                    Direcionamento legalmente autorizado: banco acumulativo, compensação mensal, horas extras na folha ou folga.
                </p>
            </div>

            <div class="bg-slate-800/80 rounded-xl p-3 border border-slate-700/60">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-5 h-5 rounded-full bg-indigo-500/30 text-indigo-300 font-bold flex items-center justify-center text-[11px]">4</span>
                    <strong class="text-white text-sm">Quitação</strong>
                </div>
                <p class="text-slate-300 leading-relaxed text-[11px]">
                    Comprovação documental da execução: pagamento no holerite, portaria de folga ou zeramento formal do período.
                </p>
            </div>
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Escopo</label>
                <select wire:model.live="filterScope" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todos os Escopos</option>
                    <option value="company">Empresa (Geral da Instalação)</option>
                    <option value="establishment">Estabelecimentos</option>
                    <option value="employee">Vínculos Individuais</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Regime Jurídico</label>
                <select wire:model.live="filterRegime" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todos os Regimes</option>
                    @foreach($regimes as $reg)
                        <option value="{{ $reg->value }}">{{ $reg->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Modalidade</label>
                <select wire:model.live="filterModality" class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                    <option value="all">Todas as Modalidades</option>
                    @foreach($modalities as $mod)
                        <option value="{{ $mod->value }}">{{ $mod->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="md:w-64">
            <label class="block text-xs font-bold text-gray-700 mb-1">Buscar por termo</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nome ou fundamentação..." class="w-full text-xs font-medium border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
        </div>
    </div>

    <!-- Lista de Políticas -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-gray-200">
                <thead class="bg-gray-50/80 text-gray-700 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-4 py-3.5">Política &amp; Fundamentação</th>
                        <th class="px-4 py-3.5">Escopo de Aplicação</th>
                        <th class="px-4 py-3.5">Regime</th>
                        <th class="px-4 py-3.5">Modalidade</th>
                        <th class="px-4 py-3.5">Vigência</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($policies as $p)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900 text-sm">{{ $p->name }}</div>
                                <div class="text-gray-500 text-[11px] mt-0.5 line-clamp-2 max-w-sm" title="{{ $p->legal_framework }}">
                                    {{ $p->legal_framework }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->employee)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                        Colaborador: {{ $p->employee->user?->name ?? 'ID '.$p->employee->id }}
                                    </span>
                                @elseif($p->establishment)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200">
                                        Unidade: {{ $p->establishment->name }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 border border-slate-300">
                                        Empresa (Geral)
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->legal_regime)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-gray-100 text-gray-800">
                                        {{ $p->legal_regime->label() }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-[11px]">Geral (Todos)</span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @php
                                    $modBadge = match($p->modality) {
                                        \App\Domain\Settlement\Enums\SettlementModality::CumulativeBank => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::MonthlyCompensation => 'bg-sky-50 text-sky-800 border-sky-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::DirectPayroll => 'bg-amber-50 text-amber-800 border-amber-200',
                                        \App\Domain\Settlement\Enums\SettlementModality::NoBank => 'bg-slate-100 text-slate-800 border-slate-300',
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
                                    <span class="text-emerald-600 font-semibold">(indeterminada)</span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                @if($p->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ativa
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">
                                        Inativa
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 text-right space-x-1 whitespace-nowrap">
                                @if(Auth::user()->isAdmin())
                                    <button wire:click="openEditModal({{ $p->id }})" class="px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition min-h-[36px]">
                                        Editar
                                    </button>
                                    <button wire:click="toggleStatus({{ $p->id }})" class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-lg transition min-h-[36px]">
                                        {{ $p->status === 'active' ? 'Desativar' : 'Ativar' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500 text-xs">
                                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Nenhuma política cadastrada correspondente aos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100">
            {{ $policies->links() }}
        </div>
    </div>

    <!-- Modal Mobile-First: Criação / Edição de Política de Jornada e Compensação -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4" role="dialog" aria-modal="true">
            <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full max-h-[92vh] flex flex-col border border-gray-200/80 overflow-hidden">
                <!-- Cabeçalho Modal -->
                <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900">
                            {{ $isEditing ? 'Editar Política de Compensação' : 'Nova Política de Jornada e Compensação' }}
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Definição estruturada nos seis eixos normativos</p>
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Conteúdo Modal (Scrollável) -->
                <form wire:submit="savePolicy" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 text-xs">
                    
                    <!-- BLOCO 1: Identificação, Escopo & Vigência -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">1</span>
                            Identificação, Escopo e Vigência
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Nome da Política *</label>
                                <input type="text" wire:model="name" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                @error('name') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Nível de Escopo *</label>
                                <select wire:model.live="scope_type" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                    <option value="company">Empresa (Geral da Instalação)</option>
                                    <option value="establishment">Estabelecimento / Unidade</option>
                                    <option value="employee">Vínculo Individual do Colaborador</option>
                                </select>
                            </div>

                            @if($scope_type === 'establishment')
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Estabelecimento *</label>
                                    <select wire:model="establishment_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                        <option value="">Selecione o Estabelecimento</option>
                                        @foreach($establishments as $est)
                                            <option value="{{ $est->id }}">{{ $est->name }} ({{ $est->code }})</option>
                                        @endforeach
                                    </select>
                                    @error('establishment_id') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            @elseif($scope_type === 'employee')
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Colaborador / Vínculo *</label>
                                    <select wire:model="employee_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                        <option value="">Selecione o Colaborador</option>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->user?->name ?? 'Colab '.$emp->id }} (CPF: {{ $emp->cpf }})</option>
                                        @endforeach
                                    </select>
                                    @error('employee_id') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Início da Vigência *</label>
                                <input type="date" wire:model="effective_from" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                @error('effective_from') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Fim da Vigência (opcional)</label>
                                <input type="date" wire:model="effective_until" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                <span class="text-[11px] text-gray-500">Deixe em branco para vigência contínua indeterminada.</span>
                                @error('effective_until') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- BLOCO 2: Legislação Aplicável & Regime Jurídico -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">2</span>
                            Legislação Aplicável e Regime Jurídico
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Regime Jurídico</label>
                                <select wire:model.live="legal_regime" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                    <option value="">Geral / Aplicável a Todos os Regimes</option>
                                    @foreach($regimes as $r)
                                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Fundamentação Normativa Expressa *</label>
                                <input type="text" wire:model="legal_framework" placeholder="Ex: CLT Art. 59 § 5º ou Lei Municipal nº..." class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                @error('legal_framework') <span class="text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Alerta Normativo Estatutário -->
                        @if($legal_regime && in_array($legal_regime, ['federal_statutory', 'state_statutory', 'municipal_statutory']))
                            <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 text-xs flex items-start gap-2">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <div>
                                    <strong>Atenção Jurídica (Servidores Estatutários):</strong> A CLT não se aplica a servidores estatutários (CF/88 Art. 39, § 3º). O regime de compensação de horário exige expressa previsão em lei ou regulamento próprio do respectivo ente federativo.
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- BLOCO 3: Modalidade de Destinação -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">3</span>
                            Modalidade de Destinação das Horas
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Modalidade Autorizada *</label>
                                <select wire:model.live="modality" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                    @foreach($modalities as $m)
                                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($modality === 'hybrid')
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Teto Diário para Banco de Horas (em minutos) *</label>
                                    <input type="number" wire:model="daily_bank_limit_minutes" min="1" max="480" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                    <span class="text-[11px] text-gray-500">Ex: 60 min (1h). O que ultrapassar é destinado para a folha de pagamento.</span>
                                </div>
                            @endif
                        </div>

                        <div class="p-3 bg-indigo-50/80 border border-indigo-200 rounded-xl text-indigo-900 text-xs">
                            <strong>Consequência Operacional:</strong>
                            @php
                                $currentModEnum = \App\Domain\Settlement\Enums\SettlementModality::tryFrom($modality);
                            @endphp
                            {{ $currentModEnum?->description() }}
                        </div>
                    </div>

                    <!-- BLOCO 4: Banco de Horas & Condições de Transporte -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">4</span>
                            Condições do Banco de Horas e Transporte (Carry-Over)
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Prazo Máximo de Compensação (Meses) *</label>
                                <select wire:model="max_compensation_months" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                    <option value="1">1 mês (Compensação Mensal - Art. 59 § 6º CLT)</option>
                                    <option value="6">6 meses (Acordo Individual Escrito - Art. 59 § 5º CLT)</option>
                                    <option value="12">12 meses / 1 ano (Exige CCT ou ACT - Art. 59 § 2º CLT)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Teto Máximo Transportável em Horas (0 = ilimitado)</label>
                                <input type="number" wire:model="carry_over_limit_hours" min="0" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                            </div>
                        </div>
                    </div>

                    <!-- BLOCO 5: Critérios de Apuração & Tolerância -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">5</span>
                            Critérios de Apuração e Tolerâncias
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tolerância por Batida (Minutos) *</label>
                                <input type="number" wire:model="punch_tolerance_minutes" min="0" max="15" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                <span class="text-[11px] text-gray-500">Padrão CLT: 5 minutos (Art. 58, § 1º).</span>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tolerância Máxima Diária (Minutos) *</label>
                                <input type="number" wire:model="daily_tolerance_minutes" min="0" max="30" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
                                <span class="text-[11px] text-gray-500">Padrão CLT: 10 minutos diários (Súmula 366 TST).</span>
                            </div>
                        </div>
                    </div>

                    <!-- BLOCO 6: Tratamento das Diferenças e Observações -->
                    <div class="bg-slate-50/60 rounded-xl p-4 border border-gray-200/80 space-y-3">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold border-b border-gray-200 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[11px]">6</span>
                            Observações e Notas
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Notas Administrativas / Histórico</label>
                            <textarea wire:model="notes" rows="2" placeholder="Observações internas sobre o instrumento ou aprovação..." class="w-full border border-gray-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>

                    <!-- Botões de Ação do Modal -->
                    <div class="pt-4 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="w-full sm:w-auto px-4 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 font-semibold rounded-xl text-xs min-h-[44px]">
                            Cancelar
                        </button>
                        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-bold rounded-xl text-xs shadow-xs min-h-[44px]">
                            {{ $isEditing ? 'Atualizar Política' : 'Homologar Política' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
