<?php

namespace App\Domain\WorkSchedule\Services;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\WorkScheduleModality;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;

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
        $prevShift = ShiftAssignment::where('employee_id', $employee->id)
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
            ->where('is_day_off', false)
            ->where('start_at_local', '>=', $prevDayStart)
            ->where('start_at_local', '<', $dayStart)
            ->orderBy('start_at_local', 'desc')
            ->first();

        if ($prevShift && $this->isAuthorizedCrossMidnightShift($prevShift, $employee)) {
            // Tolerância operacional de até 3 horas além do término previsto para troca de plantão
            $shiftEndWithGrace = $prevShift->end_at_local->copy()->addHours(3);
            if ($localTime->lte($shiftEndWithGrace)) {
                return $prevShift;
            }
        }

        // 2. Plantão que iniciou hoje
        $todayShift = ShiftAssignment::where('employee_id', $employee->id)
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
            ->where('is_day_off', false)
            ->where('start_at_local', '>=', $dayStart)
            ->where('start_at_local', '<', $dayEnd)
            ->orderBy('start_at_local', 'asc')
            ->first();

        if ($todayShift && $this->isAuthorizedCrossMidnightShift($todayShift, $employee)) {
            if ($localTime->gte($todayShift->start_at_local)) {
                return $todayShift;
            }
        }

        return null;
    }
}
