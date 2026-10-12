<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Enums\AttendanceReviewStatus;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\MonthlyAttendanceReview;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\Carbon;

class MonthlyAttendanceReviewService
{
    public function __construct(
        protected CalculateDailyJourneyAction $calculateDailyJourneyAction,
    ) {}

    /**
     * Retorna a lista de colaboradores elegíveis para visualização/conferência
     * considerando a hierarquia de permissões (gestores apenas seus setores, admin todos).
     */
    public function getScopedEmployeesQuery(User $actor, ?int $sectorId = null)
    {
        $query = Employee::with(['user', 'sector.establishment', 'workSchedule']);

        if ($actor->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $actor->id)->pluck('id');
            $query->whereIn('sector_id', $managedSectorIds);
        }

        if ($sectorId) {
            $query->where('sector_id', $sectorId);
        }

        return $query->orderBy('id', 'desc');
    }

    /**
     * Resolve o resumo analítico de conferência para um colaborador na competência especificada.
     * Se a competência estiver fechada, consulta ESTRITAMENTE os snapshots imutáveis (sem recálculo vivo).
     */
    public function resolveEmployeeReviewData(Employee $employee, int $year, int $month): array
    {
        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        if ($isClosed) {
            return $this->resolveFromClosedSnapshot($employee, $closedPeriod, $year, $month);
        }

        return $this->resolveFromLiveCalculation($employee, $year, $month);
    }

    /**
     * Resolve dados do colaborador a partir do snapshot congelado da competência fechada.
     */
    protected function resolveFromClosedSnapshot(Employee $employee, ClosedPeriod $closedPeriod, int $year, int $month): array
    {
        $snapshot = $closedPeriod->currentSnapshots()
            ->where('employee_id', $employee->id)
            ->first();

        $empSnapshot = $snapshot?->employee_snapshot ?? [];
        $schedSnapshot = $snapshot?->schedule_snapshot ?? [];
        $journeySnapshot = $snapshot?->journey_snapshot ?? [];
        $treatmentsSnapshot = $snapshot?->treatment_snapshot ?? [];

        $scheduledMinutes = 0;
        $workedMinutes = 0;
        $occurrences = [];
        $hasIncomplete = false;

        foreach ($journeySnapshot as $dayJourney) {
            $scheduledMinutes += (int) ($dayJourney['scheduledMinutes'] ?? $dayJourney['scheduled_minutes'] ?? 0);
            $workedMinutes += (int) ($dayJourney['workedMinutes'] ?? $dayJourney['worked_minutes'] ?? 0);

            if (! empty($dayJourney['isIncomplete'])) {
                $hasIncomplete = true;
            }
        }

        if ($hasIncomplete) {
            $occurrences[] = 'Registros de batida incompletos/ímpares identificados na competência';
        }

        $balanceMinutes = $workedMinutes - $scheduledMinutes;

        // Buscar registro de conferência associado
        $review = MonthlyAttendanceReview::where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $status = $review?->status ?? AttendanceReviewStatus::Reviewed;

        return [
            'employee_id' => $employee->id,
            'name' => $empSnapshot['name'] ?? $employee->user?->name ?? 'Colaborador #'.$employee->id,
            'cpf' => $empSnapshot['cpf'] ?? $employee->cpf,
            'registration_number' => $empSnapshot['registration_number'] ?? $employee->registration_number,
            'job_title' => $empSnapshot['job_title'] ?? $employee->job_title,
            'department' => $empSnapshot['department'] ?? $employee->sector?->name ?? 'Sem setor',
            'schedule_name' => $schedSnapshot['name'] ?? 'Sem escala',
            'schedule_code' => $schedSnapshot['code'] ?? null,
            'scheduled_minutes' => $scheduledMinutes,
            'worked_minutes' => $workedMinutes,
            'balance_minutes' => $balanceMinutes,
            'occurrences' => $occurrences,
            'occurrences_count' => count($occurrences),
            'has_unresolved_occurrences' => false, // No fechado, ocorrências foram chanceladas ou ressalvadas
            'status' => $status,
            'reviewed_by_name' => $review?->reviewer?->name ?? $closedPeriod->closer?->name ?? 'Sistema / RH',
            'reviewed_at' => $review?->reviewed_at ?? $closedPeriod->closed_at,
            'reservation_notes' => $review?->reservation_notes,
            'is_closed' => true,
            'from_snapshot' => true,
        ];
    }

    /**
     * Resolve dados do colaborador ao vivo na competência aberta utilizando o motor existente.
     */
    protected function resolveFromLiveCalculation(Employee $employee, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $endDate->day;

        $scheduledMinutes = 0;
        $workedMinutes = 0;
        $occurrences = [];
        $incompleteDaysCount = 0;
        $unjustifiedAbsencesCount = 0;
        $missingScheduleDaysCount = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $curDate = Carbon::createFromDate($year, $month, $d);
            $journey = $this->calculateDailyJourneyAction->execute($employee, $curDate);

            $scheduledMinutes += $journey->scheduledMinutes;
            $workedMinutes += $journey->workedMinutes;

            if ($journey->isIncomplete) {
                $incompleteDaysCount++;
            }
        }

        // 1. Ausência total de escala ou plantões no período
        $hasNoScheduleAssigned = ($employee->getWorkScheduleForDate($endDate) === null)
            && ! ShiftAssignment::where('employee_id', $employee->id)->forPeriod($startDate, $endDate)->exists();

        if ($hasNoScheduleAssigned) {
            $occurrences[] = 'Servidor sem escala de trabalho ou plantões atribuídos na competência';
        }

        // 2. Batidas incompletas ou ímpares
        if ($incompleteDaysCount > 0) {
            $occurrences[] = "{$incompleteDaysCount} dia(s) com marcações incompletas ou ímpares";
        }

        // 3. Tratamentos pendentes do colaborador no mês
        $pendingTreatmentsCount = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('status', TreatmentEventStatus::Pending)
            ->whereBetween('effective_at', [$startDate, $endDate])
            ->count();

        if ($pendingTreatmentsCount > 0) {
            $occurrences[] = "{$pendingTreatmentsCount} solicitação(ões) de tratamento aguardando aprovação pelo RH";
        }

        $balanceMinutes = $workedMinutes - $scheduledMinutes;
        $hasUnresolvedOccurrences = count($occurrences) > 0;

        // Buscar registro salvo no banco
        $review = MonthlyAttendanceReview::where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        // Determinar status inteligente
        $status = AttendanceReviewStatus::Pending;
        if ($review) {
            $status = $review->status;
        } elseif ($hasUnresolvedOccurrences) {
            $status = AttendanceReviewStatus::WithOccurrences;
        }

        $schedule = $employee->getWorkScheduleForDate($endDate) ?? $employee->workSchedule;

        return [
            'employee_id' => $employee->id,
            'name' => $employee->user?->name ?? 'Colaborador #'.$employee->id,
            'cpf' => $employee->cpf,
            'registration_number' => $employee->registration_number,
            'job_title' => $employee->job_title,
            'department' => $employee->sector?->name ?? 'Sem setor',
            'schedule_name' => $schedule?->name ?? 'Sem escala atribuída',
            'schedule_code' => $schedule?->code,
            'scheduled_minutes' => $scheduledMinutes,
            'worked_minutes' => $workedMinutes,
            'balance_minutes' => $balanceMinutes,
            'occurrences' => $occurrences,
            'occurrences_count' => count($occurrences),
            'has_unresolved_occurrences' => $hasUnresolvedOccurrences,
            'status' => $status,
            'reviewed_by_name' => $review?->reviewer?->name,
            'reviewed_at' => $review?->reviewed_at,
            'reservation_notes' => $review?->reservation_notes,
            'is_closed' => false,
            'from_snapshot' => false,
        ];
    }

    /**
     * Retorna os resumos agregados e contadores de elegibilidade para a competência.
     */
    public function getCompetenceOverview(int $year, int $month, User $actor, ?int $sectorId = null): array
    {
        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        $employees = $this->getScopedEmployeesQuery($actor, $sectorId)->get();
        $totalEmployees = $employees->count();

        $reviewedCount = 0;
        $reviewedWithReservationCount = 0;
        $pendingCount = 0;
        $withOccurrencesCount = 0;

        $items = [];
        foreach ($employees as $emp) {
            $data = $this->resolveEmployeeReviewData($emp, $year, $month);
            $items[] = $data;

            match ($data['status']) {
                AttendanceReviewStatus::Reviewed => $reviewedCount++,
                AttendanceReviewStatus::ReviewedWithReservation => $reviewedWithReservationCount++,
                AttendanceReviewStatus::WithOccurrences => $withOccurrencesCount++,
                default => $pendingCount++,
            };
        }

        $totalChecked = $reviewedCount + $reviewedWithReservationCount;
        $blockingReasons = [];

        if (! $isClosed) {
            // Verificar condições de fechamento ordinário
            if ($totalChecked < $totalEmployees) {
                $missing = $totalEmployees - $totalChecked;
                $blockingReasons[] = "Existem {$missing} colaborador(es) sem conferência concluída.";
            }

            if ($withOccurrencesCount > 0) {
                $blockingReasons[] = "Existem {$withOccurrencesCount} colaborador(es) com ocorrências não resolvidas que exigem ressalva fundamentada.";
            }

            // Tratamentos pendentes gerais
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endOfMonth = $startOfMonth->copy()->endOfMonth();
            $pendingTreatments = TreatmentEvent::where('status', TreatmentEventStatus::Pending)
                ->whereBetween('effective_at', [$startOfMonth, $endOfMonth])
                ->count();

            if ($pendingTreatments > 0) {
                $blockingReasons[] = "Existem {$pendingTreatments} solicitações de tratamento de ponto pendentes de aprovação pelo RH.";
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'is_closed' => $isClosed,
            'closed_period' => $closedPeriod,
            'total_employees' => $totalEmployees,
            'reviewed_count' => $reviewedCount,
            'reviewed_with_reservation_count' => $reviewedWithReservationCount,
            'pending_count' => $pendingCount,
            'with_occurrences_count' => $withOccurrencesCount,
            'can_close' => empty($blockingReasons) && ! $isClosed && $totalEmployees > 0,
            'blocking_reasons' => $blockingReasons,
            'items' => $items,
        ];
    }
}
