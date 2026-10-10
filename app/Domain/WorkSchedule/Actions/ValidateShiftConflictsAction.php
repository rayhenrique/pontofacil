<?php

namespace App\Domain\WorkSchedule\Actions;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use Illuminate\Support\Collection;

class ValidateShiftConflictsAction
{
    /**
     * Valida conflitos, sobreposições e descansos interjornada para os plantões de um colaborador.
     *
     * @return array{has_critical_conflicts: bool, conflicts: array, warnings: array}
     */
    public function execute(Employee $employee, ?Collection $shifts = null): array
    {
        $shiftsList = $shifts ?? ShiftAssignment::where('employee_id', $employee->id)
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
            ->where('is_day_off', false)
            ->orderBy('start_at_utc', 'asc')
            ->get();

        $conflicts = [];
        $warnings = [];

        $total = $shiftsList->count();

        for ($i = 0; $i < $total; $i++) {
            /** @var ShiftAssignment $current */
            $current = $shiftsList[$i];

            // 1. Checagem de competência fechada
            $year = (int) $current->start_at_local->format('Y');
            $month = (int) $current->start_at_local->format('n');
            if (ClosedPeriod::isClosed($year, $month)) {
                $monthLabel = sprintf('%02d/%04d', $month, $year);
                $conflicts[] = [
                    'type' => 'closed_period',
                    'shift_id' => $current->id,
                    'shift_code' => $current->shift_code,
                    'message' => "O plantão em {$current->start_at_local->format('d/m/Y')} incide sobre a competência já fechada ({$monthLabel}).",
                ];
            }

            // 2. Checagem de vigência da escala atribuída
            $hasActiveAssignment = $employee->workScheduleAssignments()
                ->where('effective_from', '<=', $current->start_at_local->format('Y-m-d'))
                ->where(function ($q) use ($current) {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $current->start_at_local->format('Y-m-d'));
                })
                ->exists();

            if (! $hasActiveAssignment && $employee->work_schedule_id === null) {
                $warnings[] = [
                    'type' => 'out_of_assignment_period',
                    'shift_id' => $current->id,
                    'shift_code' => $current->shift_code,
                    'message' => "O plantão em {$current->start_at_local->format('d/m/Y')} foi programado sem escala atribuída formalmente para esta data.",
                ];
            }

            // Checagens com o próximo plantão
            if ($i + 1 < $total) {
                /** @var ShiftAssignment $next */
                $next = $shiftsList[$i + 1];

                // 3. Sobreposição física de horários
                if ($current->overlapsWith($next->start_at_utc, $next->end_at_utc)) {
                    $conflicts[] = [
                        'type' => 'overlap',
                        'shift_a_id' => $current->id,
                        'shift_b_id' => $next->id,
                        'message' => "Sobreposição crítica entre o plantão de {$current->start_at_local->format('d/m H:i')} a {$current->end_at_local->format('d/m H:i')} e o plantão de {$next->start_at_local->format('d/m H:i')} a {$next->end_at_local->format('d/m H:i')}.",
                    ];
                }

                // 4. Descanso Mínimo Interjornada
                $restHours = $current->end_at_utc->diffInHours($next->start_at_utc, false);

                $requiredRest = match ($current->shift_type) {
                    ShiftType::Shift24x72 => 72,
                    ShiftType::Shift12x36 => 36,
                    default => 11, // CLT Art. 66
                };

                if ($restHours < $requiredRest && $restHours >= 0) {
                    $warnings[] = [
                        'type' => 'insufficient_rest',
                        'shift_a_id' => $current->id,
                        'shift_b_id' => $next->id,
                        'rest_hours' => $restHours,
                        'required_hours' => $requiredRest,
                        'message' => "Descanso interjornada insuficiente ({$restHours}h apuradas vs. {$requiredRest}h exigidas) entre o plantão de {$current->start_at_local->format('d/m')} e o de {$next->start_at_local->format('d/m')}.",
                    ];
                }
            }
        }

        return [
            'has_critical_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
            'warnings' => $warnings,
        ];
    }
}
