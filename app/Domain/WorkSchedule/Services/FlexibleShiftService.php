<?php

namespace App\Domain\WorkSchedule\Services;

use App\Enums\WorkScheduleModality;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FlexibleShiftService
{
    public const TURN_DAY = 'day';

    public const TURN_NIGHT = 'night';

    public const TURN_PENDING = 'pending_classification';

    public const TURN_UNDEFINED = 'undefined';

    /**
     * Verifica se a escala é da modalidade Plantonista Flexível 12x36.
     */
    public function isFlexibleSchedule(?WorkSchedule $schedule): bool
    {
        if ($schedule === null) {
            return false;
        }

        return $schedule->modality === WorkScheduleModality::FlexibleTwelveByThirtySix
            || (! empty($schedule->cycle_data['is_flexible']));
    }

    /**
     * Classifica o turno a partir das marcações efetivas do dia:
     * - 'day' (Diurno): entrada na janela de referência diurna (~07:00).
     * - 'night' (Noturno): entrada na janela de referência noturna (~19:00).
     * - 'pending_classification': entrada fora das janelas ou sequência ambígua para conferência.
     * - 'undefined': sem marcações (turno a definir, sem inventar falta).
     *
     * @param  array<int, array<string, mixed>>  $effectivePunches
     */
    public function classifyTurn(
        array $effectivePunches,
        ?WorkSchedule $schedule = null,
        ?string $timezone = 'America/Sao_Paulo'
    ): string {
        // Filtrar batidas que pertencem ao plantão anterior (saídas matutinas da véspera) e duplicadas
        $dayPunches = array_values(array_filter($effectivePunches, function ($p) {
            return empty($p['is_previous_day_exit']) && empty($p['is_duplicate']) && empty($p['is_disregarded']);
        }));

        if (empty($dayPunches)) {
            return self::TURN_UNDEFINED;
        }

        // Localizar a primeira batida de entrada do dia
        $firstEntry = null;
        foreach ($dayPunches as $p) {
            $dir = $p['effective_direction'] ?? $p['direction'] ?? $p['type'] ?? 'in';
            if ($dir === 'clock_in' || $dir === 'in') {
                $firstEntry = $p;
                break;
            }
        }

        // Se houver apenas saída registrada (ex: entrada esquecida), inspecionar a saída
        if ($firstEntry === null) {
            $firstPunch = $dayPunches[0];
            $pTime = $this->parsePunchLocalTime($firstPunch, $timezone);
            $hour = (int) $pTime->format('H');
            $minute = (int) $pTime->format('i');
            $timeMin = $hour * 60 + $minute;

            // Se for saída no fim da tarde/noite (ex: 18:00 - 20:30), sugere turno diurno com entrada faltante
            if ($timeMin >= (17 * 60) && $timeMin <= (21 * 60)) {
                return self::TURN_DAY;
            }

            return self::TURN_PENDING;
        }

        $entryTime = $this->parsePunchLocalTime($firstEntry, $timezone);
        $entryHour = (int) $entryTime->format('H');
        $entryMin = (int) $entryTime->format('i');
        $entryTotalMinutes = $entryHour * 60 + $entryMin;

        // Horários de referência configurados ou padrão
        $dayStartStr = $schedule?->cycle_data['day_start_time'] ?? '07:00';
        $nightStartStr = $schedule?->cycle_data['night_start_time'] ?? '19:00';

        [$dSH, $dSM] = explode(':', $dayStartStr);
        [$nSH, $nSM] = explode(':', $nightStartStr);

        $dayStartRefMin = (int) $dSH * 60 + (int) $dSM;     // ex: 420 (07:00)
        $nightStartRefMin = (int) $nSH * 60 + (int) $nSM; // ex: 1140 (19:00)

        // Janela Diurna: de 2 horas antes da referência até 6 horas depois (05:00 às 13:00 por padrão)
        $dayWindowStart = max(0, $dayStartRefMin - 120);
        $dayWindowEnd = min(1439, $dayStartRefMin + 360);

        // Janela Noturna: de 2 horas antes da referência até o fim do dia civil (17:00 às 23:59 por padrão)
        $nightWindowStart = max(0, $nightStartRefMin - 120);
        $nightWindowEnd = 1439;

        if ($entryTotalMinutes >= $dayWindowStart && $entryTotalMinutes <= $dayWindowEnd) {
            return self::TURN_DAY;
        }

        if ($entryTotalMinutes >= $nightWindowStart && $entryTotalMinutes <= $nightWindowEnd) {
            return self::TURN_NIGHT;
        }

        // Se a entrada for durante a madrugada (ex: 00:00 às 04:59)
        // Pode ser um atraso extremo do plantão noturno iniciado na véspera
        if ($entryTotalMinutes < $dayWindowStart) {
            return self::TURN_PENDING;
        }

        // Intervalo intermediário (ex: 13:01 às 16:59): fora das janelas habituais de plantão 12x36
        return self::TURN_PENDING;
    }

    /**
     * Valida o intervalo de descanso entre dois plantões consecutivos.
     * Retorna detalhes sobre suficiência (mínimo 36h) e eventuais sobreposições.
     *
     * @return array{rest_hours: float, required_hours: int, is_sufficient: bool, is_overlap: bool, warning: ?string}
     */
    public function calculateRestBetween(
        CarbonInterface $previousEnd,
        CarbonInterface $currentStart,
        int $requiredHours = 36
    ): array {
        $minutes = $previousEnd->diffInMinutes($currentStart, false);
        $hours = round($minutes / 60, 2);

        $isOverlap = $minutes < 0;
        $isSufficient = $minutes >= ($requiredHours * 60);

        $warning = null;
        if ($isOverlap) {
            $warning = sprintf(
                'Sobreposição crítica entre plantões: o início às %s ocorre antes do término do anterior (%s).',
                $currentStart->format('d/m H:i'),
                $previousEnd->format('d/m H:i')
            );
        } elseif (! $isSufficient) {
            $warning = sprintf(
                'Descanso interjornada insuficiente (%sh apuradas vs. %sh exigidas) entre o término às %s e a entrada às %s.',
                $hours,
                $requiredHours,
                $previousEnd->format('d/m H:i'),
                $currentStart->format('d/m H:i')
            );
        }

        return [
            'rest_hours' => $hours,
            'required_hours' => $requiredHours,
            'is_sufficient' => $isSufficient,
            'is_overlap' => $isOverlap,
            'warning' => $warning,
        ];
    }

    /**
     * Atualiza os atributos de um ShiftAssignment com base no turno reconhecido,
     * sem alterar marcações originais.
     */
    public function syncTurnToShift(
        ShiftAssignment $shift,
        string $turn,
        CarbonInterface $date,
        ?WorkSchedule $schedule = null
    ): void {
        $ws = $schedule ?? $shift->workSchedule;
        $tz = $shift->timezone ?? $ws?->timezone ?? 'America/Sao_Paulo';

        $dayStartStr = $ws?->cycle_data['day_start_time'] ?? '07:00';
        $dayEndStr = $ws?->cycle_data['day_end_time'] ?? '19:00';
        $nightStartStr = $ws?->cycle_data['night_start_time'] ?? '19:00';
        $nightEndStr = $ws?->cycle_data['night_end_time'] ?? '07:00';
        $breakMin = (int) ($ws?->cycle_data['break_minutes'] ?? 60);

        $localDate = Carbon::parse($date->format('Y-m-d'), $tz);

        if ($turn === self::TURN_DAY) {
            [$sH, $sM] = explode(':', $dayStartStr);
            [$eH, $eM] = explode(':', $dayEndStr);

            $startLocal = $localDate->copy()->setTime((int) $sH, (int) $sM, 0);
            $endLocal = $localDate->copy()->setTime((int) $eH, (int) $eM, 0);

            $shift->update([
                'recognized_turn' => self::TURN_DAY,
                'is_night_shift' => false,
                'crosses_midnight' => false,
                'start_at_local' => $startLocal,
                'end_at_local' => $endLocal,
                'start_at_utc' => $startLocal->copy()->setTimezone('UTC'),
                'end_at_utc' => $endLocal->copy()->setTimezone('UTC'),
                'break_minutes' => $breakMin,
                'expected_work_minutes' => 720 - $breakMin,
                'notes' => 'Plantão Flexível 12×36 — Turno Diurno reconhecido automaticamente pelas marcações.',
            ]);
        } elseif ($turn === self::TURN_NIGHT) {
            [$sH, $sM] = explode(':', $nightStartStr);
            [$eH, $eM] = explode(':', $nightEndStr);

            $startLocal = $localDate->copy()->setTime((int) $sH, (int) $sM, 0);
            $endLocal = $localDate->copy()->addDay()->setTime((int) $eH, (int) $eM, 0);

            $shift->update([
                'recognized_turn' => self::TURN_NIGHT,
                'is_night_shift' => true,
                'crosses_midnight' => true,
                'start_at_local' => $startLocal,
                'end_at_local' => $endLocal,
                'start_at_utc' => $startLocal->copy()->setTimezone('UTC'),
                'end_at_utc' => $endLocal->copy()->setTimezone('UTC'),
                'break_minutes' => $breakMin,
                'expected_work_minutes' => 720 - $breakMin,
                'notes' => 'Plantão Flexível 12×36 — Turno Noturno reconhecido automaticamente pelas marcações.',
            ]);
        } elseif ($turn === self::TURN_PENDING) {
            $shift->update([
                'recognized_turn' => self::TURN_PENDING,
                'notes' => 'Plantão Flexível 12×36 — Turno pendente de classificação para conferência administrativa.',
            ]);
        }
    }

    protected function parsePunchLocalTime(array $punch, string $timezone): Carbon
    {
        if (isset($punch['timestamp']) && $punch['timestamp'] instanceof CarbonInterface) {
            return $punch['timestamp']->copy()->setTimezone($timezone);
        }

        if (isset($punch['timestamp']) && is_string($punch['timestamp'])) {
            return Carbon::parse($punch['timestamp'], $timezone);
        }

        if (isset($punch['time']) && is_string($punch['time'])) {
            return Carbon::createFromFormat('H:i', $punch['time'], $timezone);
        }

        return Carbon::now($timezone);
    }
}
