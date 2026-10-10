<?php

namespace App\Domain\WorkSchedule\Actions;

use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignWorkScheduleAction
{
    /**
     * Atribui uma escala de trabalho a um colaborador de forma versionada no tempo,
     * garantindo integridade de histórico, validação de fechamento e ausência de sobreposições conflitantes.
     *
     * @throws ValidationException
     */
    public function execute(
        Employee $employee,
        WorkSchedule $schedule,
        CarbonInterface|string $effectiveFrom,
        CarbonInterface|string|null $effectiveUntil = null,
        string $reason = 'Atribuição inicial de escala',
        ?User $assignedBy = null,
        ?string $notes = null,
    ): WorkScheduleAssignment {
        $from = $effectiveFrom instanceof CarbonInterface ? $effectiveFrom->copy()->startOfDay() : Carbon::parse($effectiveFrom)->startOfDay();
        $until = $effectiveUntil ? ($effectiveUntil instanceof CarbonInterface ? $effectiveUntil->copy()->startOfDay() : Carbon::parse($effectiveUntil)->startOfDay()) : null;

        if ($until && $until->lt($from)) {
            throw ValidationException::withMessages([
                'effective_until' => ['A data final de vigência não pode ser anterior à data de início.'],
            ]);
        }

        // 1. Regra de competência fechada: não permite atribuir escala em período já fechado
        $targetYear = (int) $from->format('Y');
        $targetMonth = (int) $from->format('n');

        if (ClosedPeriod::isClosed($targetYear, $targetMonth)) {
            $formattedMonth = sprintf('%02d/%04d', $targetMonth, $targetYear);
            throw ValidationException::withMessages([
                'effective_from' => ["Não é permitido atribuir ou alterar escala com vigência em competência já fechada ({$formattedMonth})."],
            ]);
        }

        // 2. Regra de autorização normativa para a modalidade da escala (ex: 12x36, 24x72)
        if ($schedule->requires_legal_authorization) {
            if (! $schedule->isAuthorizedForRegime($employee->legal_regime)) {
                $modalityName = $schedule->modality?->label() ?? 'Especial';
                throw ValidationException::withMessages([
                    'work_schedule_id' => [
                        "A escala '{$schedule->name}' ({$modalityName}) requer autorização normativa específica não habilitada para o regime do colaborador.",
                    ],
                ]);
            }
        }

        return DB::transaction(function () use ($employee, $schedule, $from, $until, $reason, $assignedBy, $notes) {
            $fromStr = $from->format('Y-m-d');
            $untilStr = $until?->format('Y-m-d');

            // Obter atribuições existentes para checar sobreposições
            $existingAssignments = WorkScheduleAssignment::where('employee_id', $employee->id)
                ->orderBy('effective_from', 'asc')
                ->get();

            // 3. Resolução de histórico ou conflito
            foreach ($existingAssignments as $existing) {
                $existingFromStr = $existing->effective_from->format('Y-m-d');
                $existingUntilStr = $existing->effective_until?->format('Y-m-d');

                // Caso especial: a atribuição existente está aberta (sem data final)
                if ($existingUntilStr === null) {
                    if ($fromStr > $existingFromStr) {
                        // A nova escala entra após o início da anterior: encerra a anterior no dia anterior à nova
                        $closingDate = $from->copy()->subDay()->format('Y-m-d');
                        $existing->update(['effective_until' => $closingDate]);

                        continue;
                    }

                    if ($fromStr === $existingFromStr) {
                        throw ValidationException::withMessages([
                            'effective_from' => ['Já existe uma atribuição de escala iniciando exatamente nesta mesma data.'],
                        ]);
                    }

                    // Tentando iniciar antes de uma escala aberta existente
                    if ($untilStr && $untilStr < $existingFromStr) {
                        // É um período histórico anterior sem conflito
                        continue;
                    }

                    throw ValidationException::withMessages([
                        'effective_from' => ['Conflito de vigência: a data informada colide com uma escala vigente aberta.'],
                    ]);
                }

                // A atribuição existente tem data de início e fim definidas
                if ($existing->conflictsWith($fromStr, $untilStr)) {
                    throw ValidationException::withMessages([
                        'effective_from' => [
                            "Conflito de vigência: o colaborador já possui a escala '{$existing->workSchedule?->name}' atribuída entre {$existing->effective_from->format('d/m/Y')} e {$existing->effective_until->format('d/m/Y')}.",
                        ],
                    ]);
                }
            }

            // 4. Criação da nova atribuição
            $assignment = WorkScheduleAssignment::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'effective_from' => $fromStr,
                'effective_until' => $untilStr,
                'reason' => $reason,
                'assigned_by' => $assignedBy?->id,
                'notes' => $notes,
            ]);

            // 5. Se a nova atribuição é a atualmente vigente, atualiza o campo legado em employees
            $todayStr = Carbon::today()->format('Y-m-d');
            if ($fromStr <= $todayStr && ($untilStr === null || $untilStr >= $todayStr)) {
                $employee->update(['work_schedule_id' => $schedule->id]);
            }

            return $assignment;
        });
    }
}
