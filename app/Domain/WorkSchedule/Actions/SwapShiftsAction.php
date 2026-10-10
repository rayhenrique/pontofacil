<?php

namespace App\Domain\WorkSchedule\Actions;

use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SwapShiftsAction
{
    /**
     * Executa a troca ou permuta autorizada de plantão entre colaboradores,
     * preservando a rastreabilidade e sem alterar os fatos brutos de registro de ponto.
     *
     * @throws ValidationException
     */
    public function execute(
        ShiftAssignment $originalShift,
        Employee $replacementEmployee,
        string $reason,
        User $assignedBy,
    ): ShiftAssignment {
        $year = (int) $originalShift->start_at_local->format('Y');
        $month = (int) $originalShift->start_at_local->format('n');

        if (ClosedPeriod::isClosed($year, $month)) {
            $monthLabel = sprintf('%02d/%04d', $month, $year);
            throw ValidationException::withMessages([
                'shift' => ["Não é permitido permutar ou alterar plantão em competência já fechada ({$monthLabel})."],
            ]);
        }

        if ($originalShift->employee_id === $replacementEmployee->id) {
            throw ValidationException::withMessages([
                'replacement_employee_id' => ['O colaborador substituto não pode ser o mesmo titular do plantão.'],
            ]);
        }

        return DB::transaction(function () use ($originalShift, $replacementEmployee, $reason, $assignedBy) {
            $originalEmployeeName = $originalShift->employee->user?->name ?? 'Colaborador Titular';
            $replacementName = $replacementEmployee->user?->name ?? 'Substituto';

            // 1. Atualizar o plantão original para permutado
            $originalShift->update([
                'status' => ShiftStatus::Swapped,
                'swapped_with_employee_id' => $replacementEmployee->id,
                'notes' => trim(($originalShift->notes ?? '')." | Permutado para {$replacementName}. Motivo: {$reason}"),
            ]);

            // 2. Criar o novo plantão atribuído ao substituto
            return ShiftAssignment::create([
                'shift_code' => (string) Str::uuid(),
                'employee_id' => $replacementEmployee->id,
                'work_schedule_assignment_id' => null,
                'work_schedule_id' => $originalShift->work_schedule_id,
                'start_at_utc' => $originalShift->start_at_utc,
                'end_at_utc' => $originalShift->end_at_utc,
                'start_at_local' => $originalShift->start_at_local,
                'end_at_local' => $originalShift->end_at_local,
                'timezone' => $originalShift->timezone,
                'break_minutes' => $originalShift->break_minutes,
                'expected_work_minutes' => $originalShift->expected_work_minutes,
                'shift_type' => ShiftType::Swapped,
                'origin' => ShiftOrigin::Swap,
                'status' => ShiftStatus::Scheduled,
                'is_day_off' => false,
                'is_night_shift' => $originalShift->is_night_shift,
                'crosses_midnight' => $originalShift->crosses_midnight,
                'reason' => "Permuta autorizada do titular {$originalEmployeeName}: {$reason}",
                'assigned_by' => $assignedBy->id,
                'swapped_with_employee_id' => $originalShift->employee_id,
                'notes' => "Assunção de plantão originalmente de {$originalEmployeeName}",
            ]);
        });
    }
}
