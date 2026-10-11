<?php

namespace App\Models;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Enums\LegalRegime;
use App\Enums\WorkloadModality;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Resolve o perfil de regras jurídicas aplicável para a data especificada.
     * Estatutários estaduais e municipais NÃO herdam automaticamente regras federais ou CLT.
     */
    public function getLaborRuleProfileForDate(?CarbonInterface $date = null): ?LaborRuleProfile
    {
        $targetDate = $date ?? Carbon::today();

        // 1. Se houver perfil atribuído diretamente ao colaborador:
        if ($this->labor_rule_profile_id) {
            $assigned = $this->laborRuleProfile;
            if ($assigned && $assigned->isApproved() && $assigned->isEffectiveAt($targetDate)) {
                return $assigned;
            }
        }

        // 2. Regime CLT: fallback para o perfil padrão da CLT urbana
        if ($this->legal_regime === LegalRegime::CLT) {
            return LaborRuleProfile::where('legal_regime', LegalRegime::CLT->value)
                ->approved()
                ->activeAt($targetDate)
                ->first() ?? LaborRuleProfile::createStandardCltProfile();
        }

        // 3. Servidor Federal: fallback para o perfil padrão da Lei 8.112/1990
        if ($this->legal_regime === LegalRegime::FederalStatutory) {
            return LaborRuleProfile::where('legal_regime', LegalRegime::FederalStatutory->value)
                ->approved()
                ->activeAt($targetDate)
                ->first() ?? LaborRuleProfile::createFederalStatutoryProfile();
        }

        // 4. Estatutários estaduais ou municipais: NÃO herdam automaticamente
        // Somente se houver perfil explicitamente cadastrado e aprovado para o regime
        if ($this->legal_regime?->isStatutory()) {
            return LaborRuleProfile::where('legal_regime', $this->legal_regime->value)
                ->approved()
                ->activeAt($targetDate)
                ->first();
        }

        return null;
    }

    /**
     * Obtém a escala de trabalho aplicável para a data especificada considerando o versionamento histórico.
     * Caso não haja atribuições registradas (dados legados), utiliza a relação direta $this->workSchedule.
     */
    public function getWorkScheduleForDate(?CarbonInterface $date = null): ?WorkSchedule
    {
        $targetDate = $date ?? Carbon::today();
        $dateStr = $targetDate->format('Y-m-d');

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
