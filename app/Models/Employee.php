<?php

namespace App\Models;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Enums\LegalRegime;
use App\Enums\WorkloadModality;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property Carbon|null $normative_effective_from
 * @property Carbon|null $normative_effective_until
 */
class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'sector_id',
        'work_schedule_id',
        'registration_number',
        'cpf',
        'phone',
        'job_title',
        'contract_type',
        'legal_regime',
        'labor_rule_profile_id',
        'workload',
        'daily_workload_minutes',
        'weekly_workload_minutes',
        'monthly_workload_minutes',
        'workload_modality',
        'is_variable_workload',
        'normative_jurisdiction',
        'normative_entity',
        'normative_reference',
        'normative_act_number',
        'normative_effective_from',
        'normative_effective_until',
        'normative_validation_status',
        'normative_notes',
        'zone',
    ];

    protected $casts = [
        'legal_regime' => LegalRegime::class,
        'workload_modality' => WorkloadModality::class,
        'daily_workload_minutes' => 'integer',
        'weekly_workload_minutes' => 'integer',
        'monthly_workload_minutes' => 'integer',
        'is_variable_workload' => 'boolean',
        'normative_effective_from' => 'date',
        'normative_effective_until' => 'date',
    ];

    /**
     * Retorna o CPF com ofuscação parcial para minimização de dados conforme LGPD (Art. 6º, III).
     */
    public function getMaskedCpfAttribute(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cpf);
        if (strlen($digits) === 11) {
            return '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**';
        }

        return '***.***.***-**';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function laborRuleProfile(): BelongsTo
    {
        return $this->belongsTo(LaborRuleProfile::class);
    }

    public function laborRuleProfileAssignments(): HasMany
    {
        return $this->hasMany(EmployeeLaborRuleProfileAssignment::class)->orderBy('effective_from', 'desc');
    }

    public function workScheduleAssignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class)->orderBy('effective_from', 'desc');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class)->orderBy('start_at_local', 'asc');
    }

    public function timeBankAccount(): HasOne
    {
        return $this->hasOne(TimeBankAccount::class);
    }

    public function settlementPolicies(): HasMany
    {
        return $this->hasMany(WorkTimeSettlementPolicy::class)->orderBy('effective_from', 'desc');
    }

    public function settlementDischarges(): HasMany
    {
        return $this->hasMany(WorkTimeSettlementDischarge::class)->orderBy('execution_date', 'desc');
    }

    public function workTimeSettlements(): HasMany
    {
        return $this->hasMany(WorkTimeSettlement::class)->orderBy('operation_date', 'desc');
    }

    public function monthlyAttendanceReviews(): HasMany
    {
        return $this->hasMany(MonthlyAttendanceReview::class);
    }

    /**
     * Resolve a política de compensação e destinação aplicável para a data especificada.
     */
    public function getSettlementPolicyForDate(?CarbonInterface $date = null): ?WorkTimeSettlementPolicy
    {
        $targetDate = $date ?? Carbon::today();

        return app(WorkTimeSettlementService::class)->resolvePolicy($this, $targetDate);
    }

    /**
     * Atribui um perfil normativo ao colaborador de forma versionada no tempo,
     * garantindo integridade de histórico, validade do regime e ausência de sobreposições conflitantes.
     *
     * @throws ValidationException
     */
    public function assignLaborRuleProfile(
        LaborRuleProfile $profile,
        CarbonInterface|string $effectiveFrom,
        CarbonInterface|string|null $effectiveUntil = null,
        string $reason = 'Atribuição de perfil normativo',
        ?User $assignedBy = null,
        ?string $notes = null,
    ): EmployeeLaborRuleProfileAssignment {
        if (! $profile->isApproved()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'labor_rule_profile_id' => ['Apenas perfis normativos aprovados podem ser atribuídos a colaboradores.'],
            ]);
        }

        $from = $effectiveFrom instanceof CarbonInterface ? $effectiveFrom->copy()->startOfDay() : Carbon::parse($effectiveFrom)->startOfDay();
        $until = $effectiveUntil ? ($effectiveUntil instanceof CarbonInterface ? $effectiveUntil->copy()->startOfDay() : Carbon::parse($effectiveUntil)->startOfDay()) : null;

        if ($until && $until->lt($from)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'effective_until' => ['A data final de vigência não pode ser anterior à data de início.'],
            ]);
        }

        $regimeVal = $this->legal_regime instanceof LegalRegime ? $this->legal_regime->value : (string) $this->legal_regime;
        $profileRegimeVal = $profile->legal_regime instanceof LegalRegime ? $profile->legal_regime->value : (string) $profile->legal_regime;

        if ($regimeVal !== $profileRegimeVal) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'labor_rule_profile_id' => [
                    "Incompatibilidade jurídica: o perfil '{$profile->name}' pertence ao regime {$profile->legal_regime?->label()}, incompatível com o regime {$this->legal_regime?->label()} do colaborador.",
                ],
            ]);
        }

        return DB::transaction(function () use ($profile, $from, $until, $reason, $assignedBy, $notes) {
            $fromStr = $from->format('Y-m-d');
            $untilStr = $until?->format('Y-m-d');

            $existingAssignments = EmployeeLaborRuleProfileAssignment::where('employee_id', $this->id)
                ->orderBy('effective_from', 'asc')
                ->get();

            foreach ($existingAssignments as $existing) {
                $existingFromStr = $existing->effective_from->format('Y-m-d');
                $existingUntilStr = $existing->effective_until?->format('Y-m-d');

                if ($existingUntilStr === null) {
                    if ($fromStr > $existingFromStr) {
                        $closingDate = $from->copy()->subDay()->format('Y-m-d');
                        $existing->update(['effective_until' => $closingDate]);

                        continue;
                    }

                    if ($fromStr === $existingFromStr) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'effective_from' => ['Já existe uma atribuição de perfil normativo iniciando exatamente nesta mesma data.'],
                        ]);
                    }

                    if ($untilStr && $untilStr < $existingFromStr) {
                        continue;
                    }

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'effective_from' => ['Conflito de vigência: a data informada colide com uma atribuição de perfil aberta.'],
                    ]);
                }

                if ($existing->conflictsWith($fromStr, $untilStr)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'effective_from' => [
                            "Conflito de vigência: o colaborador já possui o perfil '{$existing->laborRuleProfile?->name}' atribuído entre {$existing->effective_from->format('d/m/Y')} e {$existing->effective_until->format('d/m/Y')}.",
                        ],
                    ]);
                }
            }

            $assignment = EmployeeLaborRuleProfileAssignment::create([
                'employee_id' => $this->id,
                'labor_rule_profile_id' => $profile->id,
                'effective_from' => $fromStr,
                'effective_until' => $untilStr,
                'reason' => $reason,
                'assigned_by' => $assignedBy?->id,
                'notes' => $notes,
            ]);

            $todayStr = Carbon::today()->format('Y-m-d');
            if ($fromStr <= $todayStr && ($untilStr === null || $untilStr >= $todayStr)) {
                $this->update(['labor_rule_profile_id' => $profile->id]);
            }

            return $assignment;
        });
    }

    /**
     * Resolve o perfil de regras jurídicas aplicável para a data especificada.
     * Estatutários estaduais e municipais NÃO herdam automaticamente regras federais ou CLT.
     */
    public function getLaborRuleProfileForDate(?CarbonInterface $date = null): ?LaborRuleProfile
    {
        $targetDate = $date ?? Carbon::today();
        $dateStr = $targetDate->format('Y-m-d');

        // 1. Prioridade máxima: atribuição versionada no tempo (employee_labor_rule_profile_assignments)
        if ($this->relationLoaded('laborRuleProfileAssignments')) {
            $assignment = $this->laborRuleProfileAssignments
                ->first(function ($assignment) use ($dateStr) {
                    $effectiveFrom = $assignment->effective_from instanceof CarbonInterface
                        ? $assignment->effective_from->format('Y-m-d')
                        : (string) $assignment->effective_from;

                    $effectiveUntil = $assignment->effective_until instanceof CarbonInterface
                        ? $assignment->effective_until->format('Y-m-d')
                        : ($assignment->effective_until ? (string) $assignment->effective_until : null);

                    return $effectiveFrom <= $dateStr && ($effectiveUntil === null || $effectiveUntil >= $dateStr);
                });

            if ($assignment && $assignment->laborRuleProfile) {
                $profile = $assignment->laborRuleProfile;
                if ($this->isProfileCompatible($profile, $targetDate)) {
                    return $profile;
                }
            }
        } else {
            $assignment = $this->laborRuleProfileAssignments()
                ->where('effective_from', '<=', $dateStr)
                ->where(function ($query) use ($dateStr) {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $dateStr);
                })
                ->first();

            if ($assignment && $assignment->laborRuleProfile) {
                $profile = $assignment->laborRuleProfile;
                if ($this->isProfileCompatible($profile, $targetDate)) {
                    return $profile;
                }
            }
        }

        // 2. Se houver perfil atribuído diretamente ao colaborador (campo legado/sincronizado):
        if ($this->labor_rule_profile_id) {
            $assigned = $this->laborRuleProfile;
            if ($assigned && $this->isProfileCompatible($assigned, $targetDate)) {
                return $assigned;
            }
        }

        // 3. Regime CLT: fallback para o perfil padrão oficial da CLT urbana
        if ($this->legal_regime === LegalRegime::CLT) {
            return LaborRuleProfile::where('legal_regime', LegalRegime::CLT->value)
                ->approved()
                ->activeAt($targetDate)
                ->first() ?? LaborRuleProfile::createStandardCltProfile();
        }

        // 4. Servidor Federal: fallback para o perfil padrão da Lei 8.112/1990
        if ($this->legal_regime === LegalRegime::FederalStatutory) {
            return LaborRuleProfile::where('legal_regime', LegalRegime::FederalStatutory->value)
                ->approved()
                ->activeAt($targetDate)
                ->first() ?? LaborRuleProfile::createFederalStatutoryProfile();
        }

        // 5. Estatutários estaduais, municipais ou outros regimes:
        // NÃO herdam automaticamente e NÃO selecionam arbitrariamente se houver múltiplos perfis
        if ($this->legal_regime?->isStatutory() || $this->legal_regime === LegalRegime::Other) {
            $candidatesQuery = LaborRuleProfile::where('legal_regime', $this->legal_regime->value)
                ->approved()
                ->activeAt($targetDate);

            if (! empty($this->normative_entity)) {
                $candidatesQuery->where(function ($q) {
                    $q->whereNull('entity_name')
                        ->orWhere('entity_name', $this->normative_entity);
                });
            }

            $candidates = $candidatesQuery->get();

            // Se houver mais de uma norma aplicável, exige atribuição explícita para evitar uso indevido de norma alheia
            if ($candidates->count() === 1) {
                $candidate = $candidates->first();
                if ($this->isProfileCompatible($candidate, $targetDate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Valida critérios de compatibilidade entre o colaborador e o perfil normativo.
     */
    public function isProfileCompatible(LaborRuleProfile $profile, CarbonInterface|string $targetDate): bool
    {
        if (! $profile->isApproved() || ! $profile->isEffectiveAt($targetDate)) {
            return false;
        }

        $empRegime = $this->legal_regime instanceof LegalRegime ? $this->legal_regime->value : (string) $this->legal_regime;
        $profileRegime = $profile->legal_regime instanceof LegalRegime ? $profile->legal_regime->value : (string) $profile->legal_regime;

        if ($empRegime !== $profileRegime) {
            return false;
        }

        // Se o colaborador tem ente definido e o perfil tem ente definido, devem coincidir
        if (! empty($this->normative_entity) && ! empty($profile->entity_name)) {
            if (mb_strtolower(trim($this->normative_entity)) !== mb_strtolower(trim($profile->entity_name))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtém a escala de trabalho aplicável para a data especificada considerando o versionamento histórico.
     * Caso não haja atribuições registradas (dados legados), utiliza a relação direta $this->workSchedule.
     */
    public function getWorkScheduleForDate(?CarbonInterface $date = null): ?WorkSchedule
    {
        $targetDate = $date ?? Carbon::today();
        $dateStr = $targetDate->format('Y-m-d');

        if ($this->relationLoaded('workScheduleAssignments')) {
            $assignment = $this->workScheduleAssignments
                ->first(function ($assignment) use ($dateStr) {
                    $effectiveFrom = $assignment->effective_from instanceof CarbonInterface
                        ? $assignment->effective_from->format('Y-m-d')
                        : (string) $assignment->effective_from;

                    $effectiveUntil = $assignment->effective_until instanceof CarbonInterface
                        ? $assignment->effective_until->format('Y-m-d')
                        : ($assignment->effective_until ? (string) $assignment->effective_until : null);

                    return $effectiveFrom <= $dateStr && ($effectiveUntil === null || $effectiveUntil >= $dateStr);
                });

            if ($assignment) {
                return $assignment->workSchedule;
            }
        } else {
            $assignment = $this->workScheduleAssignments()
                ->where('effective_from', '<=', $dateStr)
                ->where(function ($query) use ($dateStr) {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $dateStr);
                })
                ->first();

            if ($assignment) {
                return $assignment->workSchedule;
            }
        }

        // Fallback transparente para o vínculo legado de escala
        return $this->workSchedule;
    }

    /**
     * Descrição textual estruturada da carga horária para telas e relatórios.
     */
    public function getWorkloadDescription(): string
    {
        if ($this->is_variable_workload || $this->workload_modality === WorkloadModality::VariableBySchedule) {
            return 'Variável por escala / plantões';
        }

        if ($this->weekly_workload_minutes) {
            $weeklyHours = sprintf('%02dh', intdiv($this->weekly_workload_minutes, 60));
            if ($this->daily_workload_minutes) {
                $dailyHours = sprintf('%02dh', intdiv($this->daily_workload_minutes, 60));

                return "{$weeklyHours} semanais ({$dailyHours} diárias)";
            }

            return "{$weeklyHours} semanais";
        }

        if (! empty($this->workload)) {
            return (string) $this->workload;
        }

        return 'Configuração pendente';
    }

    public function treatmentEvents()
    {
        return $this->hasMany(TreatmentEvent::class);
    }

    /**
     * Inativa / desliga fiscalmente o trabalhador, gerando o evento de mutação tipo 'E' na ARP.
     */
    public function inactivate(?string $reason = null): void
    {
        $establishment = $this->sector?->establishment ?? CurrentCompany::defaultEstablishment() ?? Establishment::first();
        if ($establishment) {
            app(RecordArpEventAction::class)->recordWorkerMutation(
                establishment: $establishment,
                employee: $this,
                mutationType: 'E',
                details: [
                    'cpf' => $this->cpf,
                    'name' => $this->user?->name ?? 'Colaborador',
                    'registration_number' => $this->registration_number,
                    'reason' => $reason ?? 'Inativação/desligamento do trabalhador',
                ],
                actor: Auth::user()
            );
        }
    }
}
