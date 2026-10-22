<?php

namespace App\Domain\WorkSchedule\Services;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\WorkScheduleModality;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShiftMidnightService
{
    /**
     * Valida estritamente se um plantão é real, ativo, vigente, autorizado
     * e habilitado para continuidade através da meia-noite.
     *
     * Regras obrigatórias da Portaria 671 e diretrizes do PTRP:
     * 1. Deve existir registro formal de ShiftAssignment atribuído ao colaborador.
     * 2. Status ativo: Scheduled, Confirmed ou Completed (Cancelado ou Rascunho = NÃO).
     * 3. Não pode ser folga (is_day_off = false, shift_type != OffDay).
     * 4. Não pode ser sobreaviso / prontidão (shift_type != OnCall).
     * 5. Deve ser modalidade identificada de plantão (12x36, 24x72, extraordinário ou trocado,
     *    ou vinculado a escala com modalidade de plantão).
     * 6. Deve ter início antes e término depois da meia-noite (start_at_local < midnight < end_at_local).
     * 7. Deve estar juridicamente autorizado pelo regime do colaborador e perfil normativo.
     *
     * NÃO autoriza continuidade apenas por:
     * - horário após 18h;
     * - última batida ser entrada;
     * - intervalo inferior a 16h/24h;
     * - atributo crosses_midnight isolado;
     * - vínculo estatutário ou CLT;
     * - escala genérica sem programação de plantão.
     */
    public function isAuthorizedCrossMidnightShift(?ShiftAssignment $shift, Employee $employee): bool
    {
        if ($shift === null) {
            return false;
        }

        if ((int) $shift->employee_id !== (int) $employee->id) {
            return false;
        }

        // 1. Status ativo
        $statusValue = $shift->status instanceof ShiftStatus ? $shift->status : ShiftStatus::tryFrom((string) $shift->status);
        if (! in_array($statusValue, [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed], true)) {
            return false;
        }

        // 2. Não pode ser folga da escala
        $typeValue = $shift->shift_type instanceof ShiftType ? $shift->shift_type : ShiftType::tryFrom((string) $shift->shift_type);
        if ($shift->is_day_off || $typeValue === ShiftType::OffDay) {
            return false;
        }

        // 3. Não pode ser sobreaviso / prontidão (sobreaviso não habilita continuidade automática)
        if ($typeValue === ShiftType::OnCall) {
            return false;
        }

        // 4. Modalidade de plantão
        $isShiftTypePlantao = in_array($typeValue, [
            ShiftType::Shift12x36,
            ShiftType::Shift24x72,
            ShiftType::Extraordinary,
            ShiftType::Swapped,
        ], true);

        $schedule = $shift->relationLoaded('workSchedule') ? $shift->workSchedule : $shift->workSchedule()->first();
        $isSchedulePlantao = false;
        if ($schedule !== null) {
            $modality = $schedule->modality instanceof WorkScheduleModality
                ? $schedule->modality
                : WorkScheduleModality::tryFrom((string) $schedule->modality);

            $isSchedulePlantao = in_array($modality, [
                WorkScheduleModality::TwelveByThirtySix,
                WorkScheduleModality::FlexibleTwelveByThirtySix,
                WorkScheduleModality::TwentyFourBySeventyTwo,
                WorkScheduleModality::VariableShifts,
                WorkScheduleModality::CustomCycle,
            ], true);
        }

        if (! $isShiftTypePlantao && ! $isSchedulePlantao) {
            return false;
        }

        // 5. Início anterior e término posterior à meia-noite no timezone local
        $start = $shift->start_at_local ? Carbon::parse($shift->start_at_local) : null;
        $end = $shift->end_at_local ? Carbon::parse($shift->end_at_local) : null;

        if ($start === null || $end === null) {
            return false;
        }

        $startDayEnd = $start->copy()->addDay()->startOfDay();

        // Início deve ser antes da meia-noite do dia de início, e término deve ser estritamente posterior
        if (! ($start->lt($startDayEnd) && $end->gt($startDayEnd))) {
            return false;
        }

        // 6. Autorização normativa/jurídica
        $regime = $employee->legal_regime ?? LegalRegime::CLT;
        if ($schedule !== null) {
            if (! $schedule->isAuthorizedForRegime($regime)) {
                return false;
            }
            if ($schedule->modalityAuthorizationErrorFor($employee, $start) !== null) {
                return false;
            }
        } elseif (in_array($typeValue, [ShiftType::Shift12x36, ShiftType::Shift24x72], true)) {
            $profile = $employee->getLaborRuleProfileForDate($start) ?? LaborRuleProfile::createStandardCltProfile();
            $modKey = $typeValue === ShiftType::Shift12x36 ? '12x36' : '24x72';
            if ($profile === null || ! $profile->isModalityAllowed($modKey)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Localiza se existe um plantão autorizado em andamento cobrindo o instante fornecido.
     * Suporta a travessia após a meia-noite com tolerância operacional de encerramento.
     */
    public function findCoveringAuthorizedShift(
        Employee $employee,
        CarbonInterface $targetTime,
        ?Establishment $establishment = null
    ): ?ShiftAssignment {
        $est = $establishment ?? $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment();
        $tz = $est?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');

        $localTime = Carbon::parse($targetTime)->setTimezone($tz);
        $dayStart = $localTime->copy()->startOfDay();
        $prevDayStart = $localTime->copy()->subDay()->startOfDay();
        $dayEnd = $localTime->copy()->addDay()->startOfDay();

        // 1. Plantão que iniciou na véspera e atravessou a meia-noite
        $prevShifts = ShiftAssignment::where('employee_id', $employee->id)
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
            ->where('is_day_off', false)
            ->where('start_at_local', '>=', $prevDayStart)
            ->where('start_at_local', '<', $dayStart)
            ->orderBy('start_at_local', 'desc')
            ->get();

        // Se houver plantão flexível na véspera que ainda não foi sincronizado para noturno,
        // checar se houve batida de entrada noturna (>= 17:00) na véspera
        foreach ($prevShifts as $s) {
            if ($s->isFlexible() && $s->recognized_turn !== 'night' && ! $s->crosses_midnight) {
                $hasNightPunchYesterday = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                    if ($employee->user_id) {
                        $q->orWhere('user_id', $employee->user_id);
                    }
                })
                    ->where('occurred_at_local', '>=', $prevDayStart->copy()->setTime(17, 0, 0))
                    ->where('occurred_at_local', '<', $dayStart)
                    ->where('direction', 'in')
                    ->exists();

                if ($hasNightPunchYesterday) {
                    app(FlexibleShiftService::class)->syncTurnToShift($s, FlexibleShiftService::TURN_NIGHT, $prevDayStart);
                    $s->refresh();
                }
            }
        }

        $authorizedPrevShifts = $prevShifts->filter(
            fn ($s) => $this->isAuthorizedCrossMidnightShift($s, $employee)
        );

        // Se não houver plantão formal pré-gerado na véspera, verificar se o colaborador possui
        // atribuição vigente de escala flexível e entrada noturna registrada na véspera
        if ($authorizedPrevShifts->isEmpty()) {
            $wsYesterday = $employee->getWorkScheduleForDate($prevDayStart);
            if ($wsYesterday && app(FlexibleShiftService::class)->isFlexibleSchedule($wsYesterday)) {
                $yesterdayNightPunch = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                    if ($employee->user_id) {
                        $q->orWhere('user_id', $employee->user_id);
                    }
                })
                    ->where('occurred_at_local', '>=', $prevDayStart->copy()->setTime(17, 0, 0))
                    ->where('occurred_at_local', '<', $dayStart)
                    ->where('direction', 'in')
                    ->first();

                if ($yesterdayNightPunch !== null) {
                    $nightStartStr = $wsYesterday->cycle_data['night_start_time'] ?? '19:00';
                    $nightEndStr = $wsYesterday->cycle_data['night_end_time'] ?? '07:00';
                    [$nSH, $nSM] = explode(':', $nightStartStr);
                    [$nEH, $nEM] = explode(':', $nightEndStr);

                    $startLocal = $prevDayStart->copy()->setTime((int) $nSH, (int) $nSM, 0);
                    $endLocal = $dayStart->copy()->setTime((int) $nEH, (int) $nEM, 0);
                    $breakMin = (int) ($wsYesterday->cycle_data['break_minutes'] ?? 60);

                    $activeAssignment = $employee->workScheduleAssignments()
                        ->where('effective_from', '<=', $prevDayStart->format('Y-m-d'))
                        ->where(function ($q) use ($prevDayStart) {
                            $q->whereNull('effective_until')
                                ->orWhere('effective_until', '>=', $prevDayStart->format('Y-m-d'));
                        })
                        ->first();

                    $dynamicShift = ShiftAssignment::create([
                        'shift_code' => (string) Str::uuid(),
                        'employee_id' => $employee->id,
                        'work_schedule_assignment_id' => $activeAssignment?->id,
                        'work_schedule_id' => $wsYesterday->id,
                        'start_at_utc' => $startLocal->copy()->setTimezone('UTC'),
                        'end_at_utc' => $endLocal->copy()->setTimezone('UTC'),
                        'start_at_local' => $startLocal,
                        'end_at_local' => $endLocal,
                        'timezone' => $tz,
                        'break_minutes' => $breakMin,
                        'expected_work_minutes' => 720 - $breakMin,
                        'shift_type' => ShiftType::Shift12x36,
                        'origin' => ShiftOrigin::GeneratedCycle,
                        'status' => ShiftStatus::Scheduled,
                        'is_day_off' => false,
                        'is_night_shift' => true,
                        'crosses_midnight' => true,
                        'recognized_turn' => FlexibleShiftService::TURN_NIGHT,
                        'reason' => 'Reconhecimento automático de plantão flexível noturno',
                        'notes' => 'Plantão Flexível 12×36 — Turno Noturno reconhecido automaticamente pelas marcações.',
                    ]);

                    $authorizedPrevShifts = collect([$dynamicShift]);
                }
            }
        }

        if ($authorizedPrevShifts->count() > 1) {
            Log::warning('shift_midnight.ambiguous_overlapping_prev_shifts', [
                'employee_id' => $employee->id,
                'target_time' => $localTime->toDateTimeString(),
                'shifts_count' => $authorizedPrevShifts->count(),
            ]);

            return null; // Situação ambígua: não escolher silenciosamente
        }

        $prevShift = $authorizedPrevShifts->first();

        if ($prevShift) {
            // Se o instante for antes ou no encerramento previsto: cobre o plantão
            if ($localTime->lte($prevShift->end_at_local)) {
                return $prevShift;
            }

            // Tolerância operacional de até 3 horas além do término previsto para troca de plantão
            $shiftEndWithGrace = $prevShift->end_at_local->copy()->addHours(3);
            if ($localTime->lte($shiftEndWithGrace)) {
                // A tolerância é estritamente contexto operacional: exige que o plantão tenha
                // sido efetivamente iniciado e esteja com sequência em aberto (sem saída registrada).
                $lastPunchInShift = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('user_id', $employee->user_id);
                })
                    ->where('occurred_at_local', '>=', $prevShift->start_at_local)
                    ->where('occurred_at_local', '<=', $localTime)
                    ->orderBy('occurred_at_local', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                // Se não houve nenhuma batida ou se a última batida já foi saída, o plantão não tem sequência aberta
                if ($lastPunchInShift !== null && $lastPunchInShift->direction === 'in') {
                    // Verificar se há novo plantão ativo já iniciando hoje cobrindo este horário
                    $hasNewShiftStartingNow = ShiftAssignment::where('employee_id', $employee->id)
                        ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
                        ->where('is_day_off', false)
                        ->where('start_at_local', '>=', $dayStart)
                        ->where('start_at_local', '<=', $localTime)
                        ->where('end_at_local', '>', $localTime)
                        ->exists();

                    if (! $hasNewShiftStartingNow) {
                        return $prevShift;
                    }
                }
            }
        }

        // 2. Plantão que iniciou hoje
        $todayShifts = ShiftAssignment::where('employee_id', $employee->id)
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
            ->where('is_day_off', false)
            ->where('start_at_local', '>=', $dayStart)
            ->where('start_at_local', '<', $dayEnd)
            ->orderBy('start_at_local', 'asc')
            ->get();

        $authorizedTodayShifts = $todayShifts->filter(
            fn ($s) => $this->isAuthorizedCrossMidnightShift($s, $employee)
        );

        if ($authorizedTodayShifts->count() > 1) {
            Log::warning('shift_midnight.ambiguous_overlapping_today_shifts', [
                'employee_id' => $employee->id,
                'target_time' => $localTime->toDateTimeString(),
                'shifts_count' => $authorizedTodayShifts->count(),
            ]);

            return null; // Situação ambígua: não escolher silenciosamente
        }

        $todayShift = $authorizedTodayShifts->first();

        if ($todayShift && $localTime->gte($todayShift->start_at_local)) {
            return $todayShift;
        }

        return null;
    }
}
