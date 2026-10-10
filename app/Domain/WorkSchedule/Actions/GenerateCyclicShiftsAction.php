<?php

namespace App\Domain\WorkSchedule\Actions;

use App\Domain\Calendar\Services\WorkCalendarService;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateCyclicShiftsAction
{
    /**
     * Produz jornadas e plantões previstos a partir do modelo de escala,
     * suportando escalas fixas, 6x1, 12x36 (diurna/noturna), 24x72 e ciclos personalizados.
     *
     * @throws ValidationException
     */
    public function execute(
        Employee $employee,
        WorkSchedule $schedule,
        CarbonInterface|string $anchorDate,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        ?string $timezone = null,
        ?User $assignedBy = null,
        bool $includeOffDays = false,
    ): Collection {
        $tz = $timezone ?? $schedule->timezone ?? 'America/Sao_Paulo';

        $anchor = $anchorDate instanceof CarbonInterface
            ? $anchorDate->copy()->setTimezone($tz)->startOfDay()
            : Carbon::parse($anchorDate, $tz)->startOfDay();

        $start = $startDate instanceof CarbonInterface
            ? $startDate->copy()->setTimezone($tz)->startOfDay()
            : Carbon::parse($startDate, $tz)->startOfDay();

        $end = $endDate instanceof CarbonInterface
            ? $endDate->copy()->setTimezone($tz)->endOfDay()
            : Carbon::parse($endDate, $tz)->endOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['A data final não pode ser anterior à data inicial.'],
            ]);
        }

        // 1. Verificação de competências fechadas no período
        $periodCursor = $start->copy()->startOfMonth();
        $periodLimit = $end->copy()->startOfMonth();

        while ($periodCursor->lte($periodLimit)) {
            $year = (int) $periodCursor->format('Y');
            $month = (int) $periodCursor->format('n');

            if (ClosedPeriod::isClosed($year, $month)) {
                $monthLabel = sprintf('%02d/%04d', $month, $year);
                throw ValidationException::withMessages([
                    'period' => ["Não é permitido gerar ou alterar plantões para a competência já fechada ({$monthLabel})."],
                ]);
            }

            $periodCursor->addMonth();
        }

        // 2. Validação de autorização normativa para modalidades especiais (ex: 24x72)
        if ($schedule->requires_legal_authorization) {
            if (! $schedule->isAuthorizedForRegime($employee->legal_regime)) {
                $modalityLabel = $schedule->modality?->label() ?? 'Especial';
                throw ValidationException::withMessages([
                    'work_schedule_id' => [
                        "A escala '{$schedule->name}' ({$modalityLabel}) requer amparo legal ou regime específico não compatível com o vínculo do colaborador.",
                    ],
                ]);
            }
        }

        $establishment = $employee->sector?->establishment ?? Establishment::first();
        $calendarService = app(WorkCalendarService::class);

        return DB::transaction(function () use (
            $employee,
            $schedule,
            $anchor,
            $start,
            $end,
            $tz,
            $assignedBy,
            $includeOffDays,
            $establishment,
            $calendarService
        ) {
            $generatedShifts = collect();
            $currentDay = $start->copy();

            // Obter assignment vigente atual do funcionário para vincular chave estrangeira
            $activeAssignment = $employee->workScheduleAssignments()
                ->where('effective_from', '<=', $start->format('Y-m-d'))
                ->where(function ($q) use ($start) {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $start->format('Y-m-d'));
                })
                ->first();

            while ($currentDay->lte($end)) {
                $dayShifts = $this->resolveShiftsForDay(
                    employee: $employee,
                    schedule: $schedule,
                    anchor: $anchor,
                    targetDate: $currentDay,
                    tz: $tz,
                    includeOffDays: $includeOffDays,
                    calendarService: $calendarService,
                    establishment: $establishment,
                );

                foreach ($dayShifts as $shiftData) {
                    // IDEMPOTÊNCIA E PRESERVAÇÃO:
                    // Se já existir plantão manual ou troca para este mesmo dia/horário, NÃO sobrescreve!
                    $startUtc = $shiftData['start_at_local']->copy()->setTimezone('UTC');
                    $endUtc = $shiftData['end_at_local']->copy()->setTimezone('UTC');

                    $existing = ShiftAssignment::where('employee_id', $employee->id)
                        ->where('start_at_utc', $startUtc)
                        ->first();

                    if ($existing) {
                        // Preserva plantões manuais, extraordinários ou trocas
                        if (in_array($existing->origin, [ShiftOrigin::ManualAssignment, ShiftOrigin::Swap, ShiftOrigin::Extraordinary])) {
                            $generatedShifts->push($existing);

                            continue;
                        }

                        // Se for gerado de ciclo prévio, atualiza dados sem duplicar
                        $existing->update([
                            'work_schedule_id' => $schedule->id,
                            'work_schedule_assignment_id' => $activeAssignment?->id,
                            'end_at_utc' => $endUtc,
                            'start_at_local' => $shiftData['start_at_local'],
                            'end_at_local' => $shiftData['end_at_local'],
                            'break_minutes' => $shiftData['break_minutes'],
                            'expected_work_minutes' => $shiftData['expected_work_minutes'],
                            'shift_type' => $shiftData['shift_type'],
                            'is_day_off' => $shiftData['is_day_off'],
                            'is_night_shift' => $shiftData['is_night_shift'],
                            'crosses_midnight' => $shiftData['crosses_midnight'],
                            'notes' => $shiftData['notes'] ?? null,
                        ]);

                        $generatedShifts->push($existing);

                        continue;
                    }

                    // Criação do novo plantão
                    $created = ShiftAssignment::create([
                        'shift_code' => (string) Str::uuid(),
                        'employee_id' => $employee->id,
                        'work_schedule_assignment_id' => $activeAssignment?->id,
                        'work_schedule_id' => $schedule->id,
                        'start_at_utc' => $startUtc,
                        'end_at_utc' => $endUtc,
                        'start_at_local' => $shiftData['start_at_local'],
                        'end_at_local' => $shiftData['end_at_local'],
                        'timezone' => $tz,
                        'break_minutes' => $shiftData['break_minutes'],
                        'expected_work_minutes' => $shiftData['expected_work_minutes'],
                        'shift_type' => $shiftData['shift_type'],
                        'origin' => ShiftOrigin::GeneratedCycle,
                        'status' => ShiftStatus::Scheduled,
                        'is_day_off' => $shiftData['is_day_off'],
                        'is_night_shift' => $shiftData['is_night_shift'],
                        'crosses_midnight' => $shiftData['crosses_midnight'],
                        'reason' => 'Geração automatizada de escala cíclica',
                        'assigned_by' => $assignedBy?->id,
                        'notes' => $shiftData['notes'] ?? null,
                    ]);

                    $generatedShifts->push($created);
                }

                $currentDay->addDay();
            }

            return $generatedShifts;
        });
    }

    /**
     * Resolve os parâmetros de plantão para um dia específico dentro do ciclo.
     */
    protected function resolveShiftsForDay(
        Employee $employee,
        WorkSchedule $schedule,
        Carbon $anchor,
        Carbon $targetDate,
        string $tz,
        bool $includeOffDays,
        WorkCalendarService $calendarService,
        ?Establishment $establishment,
    ): array {
        $modality = $schedule->modality ?? WorkScheduleModality::FixedWeekly;
        $diffDays = (int) $anchor->diffInDays($targetDate, false);

        // 1. Modalidade 12x36
        if ($modality === WorkScheduleModality::TwelveByThirtySix) {
            $cycleIndex = ($diffDays % 2 + 2) % 2;

            if ($cycleIndex === 0) {
                // Dia de plantão 12h
                $isNight = ! empty($schedule->cycle_data['is_night_shift']) ||
                    ($schedule->cycle_data['start_time'] ?? '') === '19:00';

                $startTime = $schedule->cycle_data['start_time'] ?? ($isNight ? '19:00' : '07:00');
                $endTime = $schedule->cycle_data['end_time'] ?? ($isNight ? '07:00' : '19:00');
                $breakMinutes = (int) ($schedule->cycle_data['break_minutes'] ?? 60);

                [$startH, $startM] = explode(':', $startTime);
                [$endH, $endM] = explode(':', $endTime);

                $startLocal = $targetDate->copy()->setTime((int) $startH, (int) $startM, 0);

                // Se o término for menor ou igual ao início, cruza meia-noite para o dia seguinte!
                if ($isNight || (int) $endH < (int) $startH || ((int) $endH === (int) $startH && (int) $endM <= (int) $startM)) {
                    $endLocal = $targetDate->copy()->addDay()->setTime((int) $endH, (int) $endM, 0);
                    $crossesMidnight = true;
                } else {
                    $endLocal = $targetDate->copy()->setTime((int) $endH, (int) $endM, 0);
                    $crossesMidnight = false;
                }

                $calendarDay = $calendarService->resolveDay($targetDate, $establishment, 720);
                $calendarNotes = [];
                foreach ($calendarDay->appliedEvents as $ev) {
                    $calendarNotes[] = "Calendário: {$ev->name} ({$ev->type->label()})";
                }

                return [[
                    'start_at_local' => $startLocal,
                    'end_at_local' => $endLocal,
                    'break_minutes' => $breakMinutes,
                    'expected_work_minutes' => 720 - $breakMinutes,
                    'shift_type' => ShiftType::Shift12x36,
                    'is_day_off' => false,
                    'is_night_shift' => $isNight,
                    'crosses_midnight' => $crossesMidnight,
                    'notes' => implode('. ', $calendarNotes),
                ]];
            }

            if ($includeOffDays) {
                return [[
                    'start_at_local' => $targetDate->copy()->setTime(0, 0, 0),
                    'end_at_local' => $targetDate->copy()->setTime(23, 59, 59),
                    'break_minutes' => 0,
                    'expected_work_minutes' => 0,
                    'shift_type' => ShiftType::OffDay,
                    'is_day_off' => true,
                    'is_night_shift' => false,
                    'crosses_midnight' => false,
                    'notes' => 'Folga da escala 12×36 (36h de descanso)',
                ]];
            }

            return [];
        }

        // 2. Modalidade 24x72
        if ($modality === WorkScheduleModality::TwentyFourBySeventyTwo) {
            $cycleIndex = ($diffDays % 4 + 4) % 4;

            if ($cycleIndex === 0) {
                // Dia de plantão 24h: 07:00 de hoje às 07:00 de amanhã
                $startTime = $schedule->cycle_data['start_time'] ?? '07:00';
                $endTime = $schedule->cycle_data['end_time'] ?? '07:00';
                $breakMinutes = (int) ($schedule->cycle_data['break_minutes'] ?? 120);

                [$startH, $startM] = explode(':', $startTime);
                [$endH, $endM] = explode(':', $endTime);

                $startLocal = $targetDate->copy()->setTime((int) $startH, (int) $startM, 0);
                $endLocal = $targetDate->copy()->addDay()->setTime((int) $endH, (int) $endM, 0);

                return [[
                    'start_at_local' => $startLocal,
                    'end_at_local' => $endLocal,
                    'break_minutes' => $breakMinutes,
                    'expected_work_minutes' => 1440 - $breakMinutes,
                    'shift_type' => ShiftType::Shift24x72,
                    'is_day_off' => false,
                    'is_night_shift' => true, // 24h sempre cruza período noturno 22h-05h
                    'crosses_midnight' => true,
                    'notes' => 'Plantão 24h seguido de 72h de descanso',
                ]];
            }

            if ($includeOffDays) {
                return [[
                    'start_at_local' => $targetDate->copy()->setTime(0, 0, 0),
                    'end_at_local' => $targetDate->copy()->setTime(23, 59, 59),
                    'break_minutes' => 0,
                    'expected_work_minutes' => 0,
                    'shift_type' => ShiftType::OffDay,
                    'is_day_off' => true,
                    'is_night_shift' => false,
                    'crosses_midnight' => false,
                    'notes' => "Folga da escala 24×72 (Dia {$cycleIndex} de 3)",
                ]];
            }

            return [];
        }

        // 3. Modalidade 6x1
        if ($modality === WorkScheduleModality::SixByOne) {
            $cycleIndex = ($diffDays % 7 + 7) % 7;
            $offDayIndex = (int) ($schedule->cycle_data['off_day_index'] ?? 6); // 6º índice como folga

            if ($cycleIndex !== $offDayIndex) {
                $startTime = $schedule->cycle_data['start_time'] ?? '08:00';
                $endTime = $schedule->cycle_data['end_time'] ?? '16:00';
                $breakMinutes = (int) ($schedule->cycle_data['break_minutes'] ?? 60);

                [$startH, $startM] = explode(':', $startTime);
                [$endH, $endM] = explode(':', $endTime);

                $startLocal = $targetDate->copy()->setTime((int) $startH, (int) $startM, 0);
                $endLocal = $targetDate->copy()->setTime((int) $endH, (int) $endM, 0);

                return [[
                    'start_at_local' => $startLocal,
                    'end_at_local' => $endLocal,
                    'break_minutes' => $breakMinutes,
                    'expected_work_minutes' => 480 - $breakMinutes,
                    'shift_type' => ShiftType::Regular,
                    'is_day_off' => false,
                    'is_night_shift' => false,
                    'crosses_midnight' => false,
                    'notes' => 'Escala 6×1 - Dia de trabalho',
                ]];
            }

            if ($includeOffDays) {
                return [[
                    'start_at_local' => $targetDate->copy()->setTime(0, 0, 0),
                    'end_at_local' => $targetDate->copy()->setTime(23, 59, 59),
                    'break_minutes' => 0,
                    'expected_work_minutes' => 0,
                    'shift_type' => ShiftType::OffDay,
                    'is_day_off' => true,
                    'is_night_shift' => false,
                    'crosses_midnight' => false,
                    'notes' => 'Folga semanal da escala 6×1',
                ]];
            }

            return [];
        }

        // 4. Modalidade Padrão Semanal Fixa (5x2 ou configurada em schedule_data)
        $dayConfig = $schedule->getScheduleForDay($targetDate->dayOfWeek);
        if (! empty($dayConfig['is_work_day'])) {
            $periods = $dayConfig['periods'] ?? [];
            $startTime = ! empty($periods) ? ($periods[0]['start'] ?? '08:00') : '08:00';
            $endTime = ! empty($periods) ? ($periods[count($periods) - 1]['end'] ?? '18:00') : '18:00';
            $breakMinutes = (int) ($dayConfig['break_minutes'] ?? 120);
            $expectedMinutes = (int) ($dayConfig['expected_minutes'] ?? 480);

            [$startH, $startM] = explode(':', $startTime);
            [$endH, $endM] = explode(':', $endTime);

            $startLocal = $targetDate->copy()->setTime((int) $startH, (int) $startM, 0);
            $endLocal = $targetDate->copy()->setTime((int) $endH, (int) $endM, 0);

            // Verificar se há feriado/recesso no calendário laboral
            $calendarDay = $calendarService->resolveDay($targetDate, $establishment, $expectedMinutes);
            $calendarNotes = [];
            foreach ($calendarDay->appliedEvents as $ev) {
                $calendarNotes[] = "{$ev->name} ({$ev->type->label()})";
            }

            return [[
                'start_at_local' => $startLocal,
                'end_at_local' => $endLocal,
                'break_minutes' => $breakMinutes,
                'expected_work_minutes' => $calendarDay->expectedMinutes,
                'shift_type' => ShiftType::Regular,
                'is_day_off' => false,
                'is_night_shift' => false,
                'crosses_midnight' => false,
                'notes' => ! empty($calendarNotes) ? implode('. ', $calendarNotes) : null,
            ]];
        }

        if ($includeOffDays) {
            return [[
                'start_at_local' => $targetDate->copy()->setTime(0, 0, 0),
                'end_at_local' => $targetDate->copy()->setTime(23, 59, 59),
                'break_minutes' => 0,
                'expected_work_minutes' => 0,
                'shift_type' => ShiftType::OffDay,
                'is_day_off' => true,
                'is_night_shift' => false,
                'crosses_midnight' => false,
                'notes' => 'Descanso Semanal Remunerado (DSR)',
            ]];
        }

        return [];
    }
}
