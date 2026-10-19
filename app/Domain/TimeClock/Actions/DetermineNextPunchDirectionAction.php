<?php

namespace App\Domain\TimeClock\Actions;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\WorkSchedule\Services\ShiftMidnightService;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DetermineNextPunchDirectionAction
{
    public function __construct(
        protected ?ShiftMidnightService $shiftMidnightService = null
    ) {
        $this->shiftMidnightService ??= app(ShiftMidnightService::class);
    }

    /**
     * Determina a próxima direção da batida ('in' ou 'out') contextualizada pela jornada e pelo dia civil local:
     *
     * 1. DIA CIVIL LOCAL:
     *    Usa o timezone do estabelecimento vigente. O dia corresponde ao intervalo [00:00, 00:00 do dia seguinte).
     *
     * 2. JORNADA COMUM (administrativo, corrido, 5x2, 6x1, etc.):
     *    - A primeira batida de um novo dia civil sempre inicia uma nova sequência ('in').
     *    - Não usa a última entrada do dia anterior para gerar saída automática.
     *    - Determina a próxima direção apenas pelas marcações do dia local [dayStart, dayEnd).
     *
     * 3. PLANTÃO AUTORIZADO ATRAVESSANDO MEIA-NOITE (12x36, 24x72, etc.):
     *    - Continua a sequência ininterrupta do mesmo plantão.
     *    - Se a última batida no plantão foi 'in', a próxima é 'out'. Se foi 'out' (intervalo), a próxima é 'in'.
     *
     * 4. RESILIÊNCIA:
     *    - Nunca bloqueia o registro de ponto por escala ausente ou inconsistência. Fallback seguro para 'in'.
     */
    public function execute(
        User $user,
        ?CarbonInterface $targetTime = null,
        ?Establishment $establishment = null
    ): string {
        try {
            $employee = Employee::where('user_id', $user->id)->first();
            $est = $establishment ?? $employee?->sector?->establishment ?? CurrentCompany::defaultEstablishment();
            $tz = $est?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');

            $localTime = $targetTime ? Carbon::parse($targetTime)->setTimezone($tz) : Carbon::now($tz);
            $dayStart = $localTime->copy()->startOfDay();
            $dayEnd = $localTime->copy()->addDay()->startOfDay();

            // Cenário A: Plantão autorizado atravessando a meia-noite
            if ($employee !== null) {
                $coveringShift = $this->shiftMidnightService->findCoveringAuthorizedShift($employee, $localTime, $est);

                if ($coveringShift !== null) {
                    $lastShiftPunch = PunchEvent::where(function ($q) use ($user, $employee) {
                        $q->where('user_id', $user->id)
                            ->orWhere('employee_id', $employee->id);
                    })
                        ->where('occurred_at_local', '>=', $coveringShift->start_at_local)
                        ->where('occurred_at_local', '<=', $localTime)
                        ->orderBy('occurred_at_local', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastShiftPunch !== null) {
                        return ($lastShiftPunch->direction === 'in') ? 'out' : 'in';
                    }

                    return 'in';
                }
            }

            // Cenário B: Jornada Comum (determinar próxima direção apenas pelas marcações do dia local)
            $lastTodayPunch = PunchEvent::where(function ($q) use ($user, $employee) {
                $q->where('user_id', $user->id);
                if ($employee !== null) {
                    $q->orWhere('employee_id', $employee->id);
                }
            })
                ->where('occurred_at_local', '>=', $dayStart)
                ->where('occurred_at_local', '<', $dayEnd)
                ->where('occurred_at_local', '<=', $localTime)
                ->orderBy('occurred_at_local', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastTodayPunch !== null) {
                return ($lastTodayPunch->direction === 'in') ? 'out' : 'in';
            }

            // Fallback para histórico legado em TimeEntry apenas do dia local
            $lastLegacyToday = TimeEntry::where('user_id', $user->id)
                ->where('timestamp', '>=', $dayStart)
                ->where('timestamp', '<', $dayEnd)
                ->where('timestamp', '<=', $localTime)
                ->orderBy('timestamp', 'desc')
                ->first();

            if ($lastLegacyToday !== null) {
                $dir = match ($lastLegacyToday->type) {
                    'clock_in', 'in' => 'in',
                    'clock_out', 'out' => 'out',
                    default => 'in',
                };

                return ($dir === 'in') ? 'out' : 'in';
            }

            // Primeira batida de novo dia inicia nova sequência
            return 'in';
        } catch (\Throwable) {
            // Em qualquer cenário de exceção não prevista, o registro nunca deve ser bloqueado
            return 'in';
        }
    }
}
