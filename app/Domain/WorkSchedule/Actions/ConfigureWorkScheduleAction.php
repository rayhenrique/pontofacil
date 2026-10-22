<?php

namespace App\Domain\WorkSchedule\Actions;

use App\Domain\Company\Services\SectorScopeService;
use App\Enums\LegalRegime;
use App\Enums\WorkScheduleModality;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfigureWorkScheduleAction
{
    /**
     * Modelo A) ADMINISTRATIVO
     * - Dias selecionáveis (padrão Seg-Sex).
     * - Entrada, início/fim de intervalo e saída (ex: 08:00–12:00 / 13:00–17:00).
     */
    public function createAdministrative(array $data, ?User $actor = null): WorkSchedule
    {
        $name = trim($data['name'] ?? 'Jornada Administrativa');
        $days = $data['days'] ?? [1, 2, 3, 4, 5]; // 1 = seg ... 5 = sex
        $morningStart = $data['morning_start'] ?? '08:00';
        $morningEnd = $data['morning_end'] ?? '12:00';
        $afternoonStart = $data['afternoon_start'] ?? '13:00';
        $afternoonEnd = $data['afternoon_end'] ?? '17:00';
        $tolerance = (int) ($data['tolerance_minutes'] ?? 5);
        $dailyTolerance = (int) ($data['daily_tolerance_minutes'] ?? 10);

        [$mStartH, $mStartM] = explode(':', $morningStart);
        [$mEndH, $mEndM] = explode(':', $morningEnd);
        [$aStartH, $aStartM] = explode(':', $afternoonStart);
        [$aEndH, $aEndM] = explode(':', $afternoonEnd);

        $morningMin = ((int) $mEndH * 60 + (int) $mEndM) - ((int) $mStartH * 60 + (int) $mStartM);
        $afternoonMin = ((int) $aEndH * 60 + (int) $aEndM) - ((int) $aStartH * 60 + (int) $aStartM);
        $breakMin = ((int) $aStartH * 60 + (int) $aStartM) - ((int) $mEndH * 60 + (int) $mEndM);

        if ($morningMin <= 0 || $afternoonMin <= 0) {
            throw ValidationException::withMessages([
                'periods' => ['Os horários de saída devem ser posteriores aos horários de entrada.'],
            ]);
        }

        if ($breakMin < 0) {
            throw ValidationException::withMessages([
                'interval' => ['O início do intervalo deve ser anterior ao término do intervalo.'],
            ]);
        }

        $dailyWorkMin = $morningMin + $afternoonMin;
        $weeklyMin = count($days) * $dailyWorkMin;

        $scheduleData = [];
        $dayNames = [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        for ($i = 0; $i <= 6; $i++) {
            $isWork = in_array($i, $days);
            $scheduleData[$i] = [
                'day_name' => $dayNames[$i],
                'is_work_day' => $isWork,
                'is_day_off' => ! $isWork,
                'periods' => $isWork ? [
                    ['start' => $morningStart, 'end' => $morningEnd],
                    ['start' => $afternoonStart, 'end' => $afternoonEnd],
                ] : [],
                'break_minutes' => $isWork ? $breakMin : 0,
                'expected_minutes' => $isWork ? $dailyWorkMin : 0,
            ];
        }

        return WorkSchedule::create([
            'code' => $data['code'] ?? 'ADM-'.Str::upper(Str::random(6)),
            'name' => $name,
            'description' => $data['description'] ?? sprintf('Modelo Administrativo: %s às %s e %s às %s', $morningStart, $morningEnd, $afternoonStart, $afternoonEnd),
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => $dailyWorkMin,
            'expected_weekly_minutes' => $weeklyMin,
            'tolerance_minutes' => $tolerance,
            'daily_tolerance_minutes' => $dailyTolerance,
            'requires_legal_authorization' => false,
            'schedule_data' => $scheduleData,
            'active' => true,
        ]);
    }

    /**
     * Modelo B) HORÁRIO CORRIDO
     * - Dias selecionáveis.
     * - Entrada e saída únicas, com tratamento de intervalo conforme regime (ex: 07:00–13:00).
     */
    public function createContinuous(array $data, ?User $actor = null): WorkSchedule
    {
        $name = trim($data['name'] ?? 'Jornada Contínua');
        $days = $data['days'] ?? [1, 2, 3, 4, 5];
        $startTime = $data['start_time'] ?? '07:00';
        $endTime = $data['end_time'] ?? '13:00';
        $breakMinutes = (int) ($data['break_minutes'] ?? 0);
        $deductBreak = (bool) ($data['deduct_break'] ?? false);
        $tolerance = (int) ($data['tolerance_minutes'] ?? 5);
        $dailyTolerance = (int) ($data['daily_tolerance_minutes'] ?? 10);

        [$sH, $sM] = explode(':', $startTime);
        [$eH, $eM] = explode(':', $endTime);

        $durationMin = ((int) $eH * 60 + (int) $eM) - ((int) $sH * 60 + (int) $sM);
        if ($durationMin <= 0) {
            throw ValidationException::withMessages([
                'end_time' => ['O horário de saída deve ser posterior ao horário de entrada.'],
            ]);
        }

        $dailyWorkMin = $deductBreak ? max(0, $durationMin - $breakMinutes) : $durationMin;
        $weeklyMin = count($days) * $dailyWorkMin;

        $scheduleData = [];
        $dayNames = [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        for ($i = 0; $i <= 6; $i++) {
            $isWork = in_array($i, $days);
            $scheduleData[$i] = [
                'day_name' => $dayNames[$i],
                'is_work_day' => $isWork,
                'is_day_off' => ! $isWork,
                'periods' => $isWork ? [
                    ['start' => $startTime, 'end' => $endTime],
                ] : [],
                'break_minutes' => $isWork ? $breakMinutes : 0,
                'expected_minutes' => $isWork ? $dailyWorkMin : 0,
            ];
        }

        return WorkSchedule::create([
            'code' => $data['code'] ?? 'CORR-'.Str::upper(Str::random(6)),
            'name' => $name,
            'description' => $data['description'] ?? sprintf('Horário Corrido: %s às %s (%d min/dia)', $startTime, $endTime, $dailyWorkMin),
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => $dailyWorkMin,
            'expected_weekly_minutes' => $weeklyMin,
            'tolerance_minutes' => $tolerance,
            'daily_tolerance_minutes' => $dailyTolerance,
            'requires_legal_authorization' => false,
            'schedule_data' => $scheduleData,
            'active' => true,
        ]);
    }

    /**
     * Modelo C) PLANTÃO 12 HORAS (12x36)
     * - Início, duração, ciclo de descanso (36h), intervalo, data-âncora e vigência.
     * - Suporta diurno (07:00–19:00) e noturno (19:00–07:00 com virada de dia).
     */
    public function createShift12h(array $data, ?User $actor = null): WorkSchedule
    {
        $name = trim($data['name'] ?? 'Plantão 12x36');
        $startTime = $data['start_time'] ?? '07:00';
        $endTime = $data['end_time'] ?? '19:00';
        $breakMinutes = (int) ($data['break_minutes'] ?? 60);
        $anchorDate = $data['anchor_date'] ?? Carbon::today()->format('Y-m-d');
        $isNight = ! empty($data['is_night_shift']) || $startTime === '19:00';

        [$sH, $sM] = explode(':', $startTime);
        [$eH, $eM] = explode(':', $endTime);

        $crossesMidnight = $isNight || (int) $eH < (int) $sH;
        $dailyWorkMin = 720 - $breakMinutes;
        $weeklyAverageMin = (int) round($dailyWorkMin * 3.5); // 3,5 plantões na semana em média

        return WorkSchedule::create([
            'code' => $data['code'] ?? 'PL12-'.Str::upper(Str::random(6)),
            'name' => $name,
            'description' => $data['description'] ?? sprintf('Plantão 12x36 (%s às %s, descanso 36h)', $startTime, $endTime),
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_days' => 2,
            'expected_daily_minutes' => $dailyWorkMin,
            'expected_weekly_minutes' => $weeklyAverageMin,
            'tolerance_minutes' => (int) ($data['tolerance_minutes'] ?? 5),
            'daily_tolerance_minutes' => (int) ($data['daily_tolerance_minutes'] ?? 10),
            'requires_legal_authorization' => false, // 12x36 permitida pela CLT Art. 59-A e estatutos
            'cycle_data' => [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_hours' => 12,
                'rest_hours' => 36,
                'break_minutes' => $breakMinutes,
                'is_night_shift' => $isNight,
                'crosses_midnight' => $crossesMidnight,
                'anchor_date' => $anchorDate,
            ],
            'schedule_data' => [],
            'active' => true,
        ]);
    }

    /**
     * Modelo D) PLANTÃO 24 HORAS (24x72)
     * - Início, duração, descanso (72h), intervalo e vigência.
     * - Suportar 24x72 somente com autorização normativa aplicável (ex: policiais, saúde, bombeiros).
     */
    public function createShift24h(array $data, ?User $actor = null): WorkSchedule
    {
        $name = trim($data['name'] ?? 'Plantão 24x72');
        $startTime = $data['start_time'] ?? '07:00';
        $endTime = $data['end_time'] ?? '07:00';
        $breakMinutes = (int) ($data['break_minutes'] ?? 120);
        $anchorDate = $data['anchor_date'] ?? Carbon::today()->format('Y-m-d');
        $allowedRegimes = $data['allowed_legal_regimes'] ?? [
            LegalRegime::FederalStatutory->value,
            LegalRegime::StateStatutory->value,
            LegalRegime::MunicipalStatutory->value,
        ];

        $dailyWorkMin = 1440 - $breakMinutes;
        $weeklyAverageMin = (int) round($dailyWorkMin * 1.75); // 1 a cada 4 dias

        return WorkSchedule::create([
            'code' => $data['code'] ?? 'PL24-'.Str::upper(Str::random(6)),
            'name' => $name,
            'description' => $data['description'] ?? sprintf('Plantão 24x72 (%s às %s, descanso 72h)', $startTime, $endTime),
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'cycle_days' => 4,
            'expected_daily_minutes' => $dailyWorkMin,
            'expected_weekly_minutes' => $weeklyAverageMin,
            'tolerance_minutes' => (int) ($data['tolerance_minutes'] ?? 5),
            'daily_tolerance_minutes' => (int) ($data['daily_tolerance_minutes'] ?? 10),
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => $allowedRegimes,
            'cycle_data' => [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_hours' => 24,
                'rest_hours' => 72,
                'break_minutes' => $breakMinutes,
                'is_night_shift' => true,
                'crosses_midnight' => true,
                'anchor_date' => $anchorDate,
            ],
            'schedule_data' => [],
            'active' => true,
        ]);
    }

    /**
     * Modelo E) PLANTONISTA FLEXÍVEL 12x36
     * - Plantões de 12 horas com alternância entre turnos diurnos e noturnos no mesmo mês.
     * - Reconhecimento automático pelas marcações de ponto, controle de descanso de 36h e apuração.
     */
    public function createFlexible12h(array $data, ?User $actor = null): WorkSchedule
    {
        $name = trim($data['name'] ?? 'Plantonista Flexível 12x36');
        $dayStart = $data['day_start_time'] ?? '07:00';
        $dayEnd = $data['day_end_time'] ?? '19:00';
        $nightStart = $data['night_start_time'] ?? '19:00';
        $nightEnd = $data['night_end_time'] ?? '07:00';
        $breakMinutes = (int) ($data['break_minutes'] ?? 60);
        $anchorDate = $data['anchor_date'] ?? Carbon::today()->format('Y-m-d');
        $autoDetect = (bool) ($data['auto_detect_turn'] ?? true);

        $dailyWorkMin = 720 - $breakMinutes;
        $weeklyAverageMin = (int) round($dailyWorkMin * 3.5); // 3,5 plantões na semana em média

        return WorkSchedule::create([
            'code' => $data['code'] ?? 'FLEX12-'.Str::upper(Str::random(6)),
            'name' => $name,
            'description' => $data['description'] ?? sprintf(
                'Plantonista Flexível 12x36 (Diurno: %s–%s / Noturno: %s–%s, descanso 36h)',
                $dayStart,
                $dayEnd,
                $nightStart,
                $nightEnd
            ),
            'modality' => WorkScheduleModality::FlexibleTwelveByThirtySix,
            'cycle_days' => 2,
            'expected_daily_minutes' => $dailyWorkMin,
            'expected_weekly_minutes' => $weeklyAverageMin,
            'tolerance_minutes' => (int) ($data['tolerance_minutes'] ?? 5),
            'daily_tolerance_minutes' => (int) ($data['daily_tolerance_minutes'] ?? 10),
            'requires_legal_authorization' => false,
            'cycle_data' => [
                'is_flexible' => true,
                'day_start_time' => $dayStart,
                'day_end_time' => $dayEnd,
                'night_start_time' => $nightStart,
                'night_end_time' => $nightEnd,
                'start_time' => $dayStart,
                'end_time' => $dayEnd,
                'duration_hours' => 12,
                'rest_hours' => 36,
                'break_minutes' => $breakMinutes,
                'auto_detect_turn' => $autoDetect,
                'anchor_date' => $anchorDate,
            ],
            'schedule_data' => [],
            'active' => true,
        ]);
    }

    /**
     * Calcula prévia em tempo real das cargas diária, semanal e mensal prevista no calendário real.
     * Não presume 200h.
     */
    public function preview(string $template, array $data, ?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now();
        $targetYear = $year ?? (int) $now->format('Y');
        $targetMonth = $month ?? (int) $now->format('n');

        $dailyMinutes = 0;
        $weeklyMinutes = 0;
        $monthlyExpectedMinutes = 0;
        $workDaysInMonth = 0;
        $crossesMidnight = false;
        $isNight = false;
        $requiresAuth = false;

        $startDate = Carbon::createFromDate($targetYear, $targetMonth, 1)->startOfDay();
        $daysInMonth = $startDate->daysInMonth;

        match ($template) {
            'administrative' => (function () use (
                $data, $daysInMonth, $targetYear, $targetMonth,
                &$dailyMinutes, &$weeklyMinutes, &$monthlyExpectedMinutes, &$workDaysInMonth
            ) {
                $days = $data['days'] ?? [1, 2, 3, 4, 5];
                $mStart = $data['morning_start'] ?? '08:00';
                $mEnd = $data['morning_end'] ?? '12:00';
                $aStart = $data['afternoon_start'] ?? '13:00';
                $aEnd = $data['afternoon_end'] ?? '17:00';

                [$mSH, $mSM] = explode(':', $mStart);
                [$mEH, $mEM] = explode(':', $mEnd);
                [$aSH, $aSM] = explode(':', $aStart);
                [$aEH, $aEM] = explode(':', $aEnd);

                $mMin = max(0, ((int) $mEH * 60 + (int) $mEM) - ((int) $mSH * 60 + (int) $mSM));
                $aMin = max(0, ((int) $aEH * 60 + (int) $aEM) - ((int) $aSH * 60 + (int) $aSM));
                $dailyMinutes = $mMin + $aMin;
                $weeklyMinutes = count($days) * $dailyMinutes;

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $wDay = Carbon::createFromDate($targetYear, $targetMonth, $d)->dayOfWeek;
                    if (in_array($wDay, $days)) {
                        $workDaysInMonth++;
                        $monthlyExpectedMinutes += $dailyMinutes;
                    }
                }
            })(),

            'continuous' => (function () use (
                $data, $daysInMonth, $targetYear, $targetMonth,
                &$dailyMinutes, &$weeklyMinutes, &$monthlyExpectedMinutes, &$workDaysInMonth
            ) {
                $days = $data['days'] ?? [1, 2, 3, 4, 5];
                $sTime = $data['start_time'] ?? '07:00';
                $eTime = $data['end_time'] ?? '13:00';
                $bMin = (int) ($data['break_minutes'] ?? 0);
                $deduct = (bool) ($data['deduct_break'] ?? false);

                [$sH, $sM] = explode(':', $sTime);
                [$eH, $eM] = explode(':', $eTime);

                $dur = max(0, ((int) $eH * 60 + (int) $eM) - ((int) $sH * 60 + (int) $sM));
                $dailyMinutes = $deduct ? max(0, $dur - $bMin) : $dur;
                $weeklyMinutes = count($days) * $dailyMinutes;

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $wDay = Carbon::createFromDate($targetYear, $targetMonth, $d)->dayOfWeek;
                    if (in_array($wDay, $days)) {
                        $workDaysInMonth++;
                        $monthlyExpectedMinutes += $dailyMinutes;
                    }
                }
            })(),

            'shift_12h' => (function () use (
                $data, $daysInMonth, $targetYear, $targetMonth, $startDate,
                &$dailyMinutes, &$weeklyMinutes, &$monthlyExpectedMinutes, &$workDaysInMonth,
                &$crossesMidnight, &$isNight
            ) {
                $sTime = $data['start_time'] ?? '07:00';
                $eTime = $data['end_time'] ?? '19:00';
                $bMin = (int) ($data['break_minutes'] ?? 60);
                $isNight = ! empty($data['is_night_shift']) || $sTime === '19:00';

                [$sH] = explode(':', $sTime);
                [$eH] = explode(':', $eTime);
                $crossesMidnight = $isNight || (int) $eH < (int) $sH;

                $dailyMinutes = 720 - $bMin;
                $weeklyMinutes = (int) round($dailyMinutes * 3.5);

                $anchorStr = $data['anchor_date'] ?? null;
                $anchor = $anchorStr ? Carbon::parse($anchorStr)->startOfDay() : $startDate->copy();

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $cur = Carbon::createFromDate($targetYear, $targetMonth, $d)->startOfDay();
                    $diff = (int) $anchor->diffInDays($cur, false);
                    if ((($diff % 2 + 2) % 2) === 0) {
                        $workDaysInMonth++;
                        $monthlyExpectedMinutes += $dailyMinutes;
                    }
                }
            })(),

            'shift_24h' => (function () use (
                $data, $daysInMonth, $targetYear, $targetMonth, $startDate,
                &$dailyMinutes, &$weeklyMinutes, &$monthlyExpectedMinutes, &$workDaysInMonth,
                &$crossesMidnight, &$isNight, &$requiresAuth
            ) {
                $bMin = (int) ($data['break_minutes'] ?? 120);
                $dailyMinutes = 1440 - $bMin;
                $weeklyMinutes = (int) round($dailyMinutes * 1.75);
                $crossesMidnight = true;
                $isNight = true;
                $requiresAuth = true;

                $anchorStr = $data['anchor_date'] ?? null;
                $anchor = $anchorStr ? Carbon::parse($anchorStr)->startOfDay() : $startDate->copy();

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $cur = Carbon::createFromDate($targetYear, $targetMonth, $d)->startOfDay();
                    $diff = (int) $anchor->diffInDays($cur, false);
                    if ((($diff % 4 + 4) % 4) === 0) {
                        $workDaysInMonth++;
                        $monthlyExpectedMinutes += $dailyMinutes;
                    }
                }
            })(),

            'flexible_12h' => (function () use (
                $data, $daysInMonth, $targetYear, $targetMonth, $startDate,
                &$dailyMinutes, &$weeklyMinutes, &$monthlyExpectedMinutes, &$workDaysInMonth,
                &$crossesMidnight, &$isNight
            ) {
                $bMin = (int) ($data['break_minutes'] ?? 60);
                $dailyMinutes = 720 - $bMin;
                $weeklyMinutes = (int) round($dailyMinutes * 3.5);
                $crossesMidnight = true; // Turnos noturnos atravessam meia-noite
                $isNight = false; // Alternância diurno/noturno automática

                $anchorStr = $data['anchor_date'] ?? null;
                $anchor = $anchorStr ? Carbon::parse($anchorStr)->startOfDay() : $startDate->copy();

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $cur = Carbon::createFromDate($targetYear, $targetMonth, $d)->startOfDay();
                    $diff = (int) $anchor->diffInDays($cur, false);
                    if ((($diff % 2 + 2) % 2) === 0) {
                        $workDaysInMonth++;
                        $monthlyExpectedMinutes += $dailyMinutes;
                    }
                }
            })(),

            default => null,
        };

        return [
            'template' => $template,
            'year' => $targetYear,
            'month' => $targetMonth,
            'daily_minutes' => $dailyMinutes,
            'daily_formatted' => sprintf('%dh%02d', intdiv($dailyMinutes, 60), $dailyMinutes % 60),
            'weekly_minutes' => $weeklyMinutes,
            'weekly_formatted' => sprintf('%dh%02d', intdiv($weeklyMinutes, 60), $weeklyMinutes % 60),
            'monthly_expected_minutes' => $monthlyExpectedMinutes,
            'monthly_expected_formatted' => sprintf('%dh%02d', intdiv($monthlyExpectedMinutes, 60), $monthlyExpectedMinutes % 60),
            'work_days_count' => $workDaysInMonth,
            'crosses_midnight' => $crossesMidnight,
            'is_night_shift' => $isNight,
            'requires_authorization' => $requiresAuth,
        ];
    }

    /**
     * Aplica uma escala a vários colaboradores autorizados com controle de vigência.
     * Preserva histórico e não sobrescreve retroativamente.
     */
    public function applyToEmployees(
        WorkSchedule $schedule,
        array $employeeIds,
        CarbonInterface|string $effectiveFrom,
        CarbonInterface|string|null $effectiveUntil = null,
        string $reason = 'Atribuição de escala',
        ?User $actor = null
    ): array {
        $assignAction = app(AssignWorkScheduleAction::class);
        $appliedCount = 0;
        $errors = [];

        foreach ($employeeIds as $empId) {
            $employee = Employee::find($empId);
            if (! $employee) {
                continue;
            }

            if ($actor !== null && ! app(SectorScopeService::class)->canManageEmployee($actor, $employee)) {
                $errors[$employee->id] = 'Sem autorização para atribuir escala a este colaborador.';

                continue;
            }

            try {
                $assignAction->execute(
                    employee: $employee,
                    schedule: $schedule,
                    effectiveFrom: $effectiveFrom,
                    effectiveUntil: $effectiveUntil,
                    reason: $reason,
                    assignedBy: $actor,
                );
                $appliedCount++;
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? 'Erro de validação';
                $errors[$employee->id] = "{$employee->user?->name}: {$msg}";
            }
        }

        return [
            'applied_count' => $appliedCount,
            'errors' => $errors,
        ];
    }
}
