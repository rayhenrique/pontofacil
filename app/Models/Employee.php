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

    public function workScheduleAssignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class)->orderBy('effective_from', 'desc');
    }

    public function timeBankAccount(): HasOne
    {
        return $this->hasOne(TimeBankAccount::class);
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
