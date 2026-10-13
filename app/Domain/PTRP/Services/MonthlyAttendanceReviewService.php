<?php

namespace App\Domain\PTRP\Services;

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Enums\AttendanceReviewStatus;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Employee;
use App\Models\MonthlyAttendanceReview;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyAttendanceReviewService
{
    public function __construct(
        protected CalculateDailyJourneyAction $calculateDailyJourneyAction,
    ) {}

    /**
     * Retorna a lista de colaboradores elegíveis para visualização/conferência
     * considerando a hierarquia de permissões (gestores apenas seus setores, admin todos).
     *
     * @return Builder<Employee>
     */
    public function getScopedEmployeesQuery(User $actor, ?int $sectorId = null): Builder
    {
        $scopeService = app(SectorScopeService::class);
        $scopeService->authorizeSchedules($actor);

        if ($sectorId !== null) {
            $scopeService->authorizeSector($actor, $sectorId);
        }

        $query = Employee::with(['user', 'sector.establishment', 'workSchedule']);

        if ($actor->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $actor->id)->pluck('id');
            $query->whereIn('sector_id', $managedSectorIds);
        }

        if ($sectorId) {
            $query->where('sector_id', $sectorId);
        }

        $query->orderBy('id', 'desc');

        return $query;
    }

    /**
     * Resolve o resumo analítico de conferência para um colaborador na competência especificada.
     * Se a competência estiver fechada, consulta ESTRITAMENTE os snapshots imutáveis (sem recálculo vivo).
     */
    public function resolveEmployeeReviewData(Employee $employee, int $year, int $month, ?MonthlyBatchContext $batch = null): array
    {
        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        if ($isClosed) {
            return $this->resolveFromClosedSnapshot($employee, $closedPeriod, $year, $month);
        }

        $batch ??= app(MonthlyJourneyBatchService::class)->forCompetence(collect([$employee]), $year, $month);
        $review = $this->findReview($employee, $year, $month);

        return $this->buildLiveItem(
            employee: $employee,
            year: $year,
            month: $month,
            batch: $batch,
            pendingTreatmentsCount: $this->pendingTreatmentsCountFor($employee, $year, $month),
            hasShiftsInPeriod: $this->hasShiftsInPeriod($employee, $year, $month),
            review: $review,
            stale: $review !== null && $this->isReviewStale($employee, $review, $year, $month),
        );
    }

    /**
     * Resolve dados do colaborador a partir do snapshot congelado da competência fechada.
     */
    protected function resolveFromClosedSnapshot(Employee $employee, ClosedPeriod $closedPeriod, int $year, int $month): array
    {
        $snapshot = $closedPeriod->currentSnapshots()
            ->where('employee_id', $employee->id)
            ->first();

        // Buscar registro de conferência associado
        $review = MonthlyAttendanceReview::where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return $this->buildClosedItem($employee, $closedPeriod, $snapshot, $review);
    }

    /**
     * Agrega os dados congelados de um colaborador (snapshots imutáveis, sem recálculo).
     */
    protected function buildClosedItem(
        Employee $employee,
        ClosedPeriod $closedPeriod,
        ?ClosedPeriodEmployeeSnapshot $snapshot,
        ?MonthlyAttendanceReview $review,
    ): array {
        // Sem snapshot congelado: estado de indisponibilidade histórica,
        // nunca apuração ao vivo apresentada como definitiva.
        if ($snapshot === null) {
            return [
                'employee_id' => $employee->id,
                'name' => $employee->user?->name ?? 'Colaborador #'.$employee->id,
                'cpf' => $employee->cpf,
                'registration_number' => $employee->registration_number,
                'job_title' => $employee->job_title,
                'department' => $employee->sector?->name ?? 'Sem setor',
                'schedule_name' => 'Histórico indisponível',
                'schedule_code' => null,
                'scheduled_minutes' => 0,
                'worked_minutes' => 0,
                'balance_minutes' => 0,
                'occurrences' => ['Histórico da competência indisponível para este servidor.'],
                'occurrences_count' => 1,
                'has_unresolved_occurrences' => true,
                'status' => AttendanceReviewStatus::Pending,
                'reviewed_by_name' => $review?->reviewer?->name,
                'reviewed_at' => $review?->reviewed_at,
                'reservation_notes' => $review?->reservation_notes,
                'is_closed' => true,
                'from_snapshot' => false,
                'history_unavailable' => true,
            ];
        }

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

        // Ausência de conferência registrada não significa conferência aprovada.
        $status = $review?->status ?? AttendanceReviewStatus::Pending;
        if ($review === null) {
            $occurrences[] = 'Aguardando conferência.';
        }
        $hasUnresolved = $review === null;

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
            'has_unresolved_occurrences' => $hasUnresolved,
            'status' => $status,
            'reviewed_by_name' => $review?->reviewer?->name ?? $closedPeriod->closer?->name ?? 'Sistema / RH',
            'reviewed_at' => $review?->reviewed_at ?? $closedPeriod->closed_at,
            'reservation_notes' => $review?->reservation_notes,
            'is_closed' => true,
            'from_snapshot' => true,
            'history_unavailable' => false,
        ];
    }

    /**
     * Resolve dados do colaborador ao vivo na competência aberta utilizando o motor existente.
     */
    protected function resolveFromLiveCalculation(Employee $employee, int $year, int $month, ?MonthlyBatchContext $batch = null): array
    {
        $batch ??= app(MonthlyJourneyBatchService::class)->forCompetence(collect([$employee]), $year, $month);
        $review = $this->findReview($employee, $year, $month);

        return $this->buildLiveItem(
            employee: $employee,
            year: $year,
            month: $month,
            batch: $batch,
            pendingTreatmentsCount: $this->pendingTreatmentsCountFor($employee, $year, $month),
            hasShiftsInPeriod: $this->hasShiftsInPeriod($employee, $year, $month),
            review: $review,
            stale: $review !== null && $this->isReviewStale($employee, $review, $year, $month),
        );
    }

    /**
     * Agrega a apuração viva de um colaborador sobre o lote mensal compartilhado.
     */
    protected function buildLiveItem(
        Employee $employee,
        int $year,
        int $month,
        MonthlyBatchContext $batch,
        int $pendingTreatmentsCount,
        bool $hasShiftsInPeriod,
        ?MonthlyAttendanceReview $review,
        bool $stale = false,
    ): array {
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $daysInMonth = $endDate->day;

        $scheduledMinutes = 0;
        $workedMinutes = 0;
        $occurrences = [];
        $incompleteDaysCount = 0;
        $unjustifiedAbsencesCount = 0;
        $missingScheduleDaysCount = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $curDate = Carbon::createFromDate($year, $month, $d);
            $journey = $batch->journeyFor($employee, $curDate);

            $scheduledMinutes += $journey->scheduledMinutes;
            $workedMinutes += $journey->workedMinutes;

            if ($journey->isIncomplete) {
                $incompleteDaysCount++;
            }
        }

        // 1. Ausência total de escala ou plantões no período
        $hasNoScheduleAssigned = ($batch->scheduleFor($employee, $endDate) === null)
            && ! $hasShiftsInPeriod;

        if ($hasNoScheduleAssigned) {
            $occurrences[] = 'Servidor sem escala de trabalho ou plantões atribuídos na competência';
        }

        // 2. Batidas incompletas ou ímpares
        if ($incompleteDaysCount > 0) {
            $occurrences[] = "{$incompleteDaysCount} dia(s) com marcações incompletas ou ímpares";
        }

        // 3. Tratamentos pendentes do colaborador no mês
        if ($pendingTreatmentsCount > 0) {
            $occurrences[] = "{$pendingTreatmentsCount} solicitação(ões) de tratamento aguardando aprovação pelo RH";
        }

        // 4. Dados alterados após a conferência registrada
        if ($stale) {
            $occurrences[] = 'Dados alterados após a conferência — necessária nova validação.';
        }

        $balanceMinutes = $workedMinutes - $scheduledMinutes;
        $hasUnresolvedOccurrences = count($occurrences) > 0;

        // Determinar status inteligente
        $status = AttendanceReviewStatus::Pending;
        if ($review) {
            $status = $review->status;
        } elseif ($hasUnresolvedOccurrences) {
            $status = AttendanceReviewStatus::WithOccurrences;
        }

        $schedule = $batch->scheduleFor($employee, $endDate) ?? $employee->workSchedule;

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
            'history_unavailable' => false,
            'stale' => $stale,
        ];
    }

    /**
     * Indica se dados relevantes mudaram após a conferência registrada,
     * exigindo nova validação antes do fechamento (sem alterar o passado).
     *
     * Sinais: tratamentos decididos/alterados, atribuições de escala
     * criadas ou encerradas, e plantões criados/alterados na competência.
     * Marcações de ponto (ledger imutável com relógio de banco distinto)
     * não participam do sinal.
     */
    public function isReviewStale(Employee $employee, MonthlyAttendanceReview $review, int $year, int $month): bool
    {
        if ($review->reviewed_at === null) {
            return false;
        }

        // Comparação em resolução de segundos (precisão do banco),
        // evitando falso-positivo por microssegundos da memória.
        $reviewedAt = $review->reviewed_at->copy()->startOfSecond();
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $treatmentChanged = TreatmentEvent::where(function ($query) use ($employee) {
            $query->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('effective_at', '>=', $start)
            ->where('effective_at', '<=', $end)
            ->where('updated_at', '>', $reviewedAt)
            ->exists();

        if ($treatmentChanged) {
            return true;
        }

        $assignmentChanged = WorkScheduleAssignment::where('employee_id', $employee->id)
            ->where('effective_from', '<=', $end->format('Y-m-d'))
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $start->format('Y-m-d'));
            })
            ->where(function ($query) use ($reviewedAt) {
                $query->where('updated_at', '>', $reviewedAt)
                    ->orWhere('created_at', '>', $reviewedAt);
            })
            ->exists();

        if ($assignmentChanged) {
            return true;
        }

        $shiftChanged = ShiftAssignment::where('employee_id', $employee->id)
            ->forPeriod($start, $end)
            ->where('updated_at', '>', $reviewedAt)
            ->exists();

        return $shiftChanged;
    }

    /**
     * Retorna os resumos agregados e contadores de elegibilidade para a competência.
     */
    public function getCompetenceOverview(int $year, int $month, User $actor, ?int $sectorId = null): array
    {
        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        if ($isClosed) {
            $closedPeriod->loadMissing('closer');
        }

        $employees = $this->getScopedEmployeesQuery($actor, $sectorId)->get();
        $totalEmployees = $employees->count();
        $employeeIds = $employees->map(fn (Employee $employee) => $employee->id)->all();

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Conferências da competência em uma única consulta.
        $reviewsByEmployee = $employeeIds
            ? MonthlyAttendanceReview::with('reviewer')
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy(fn (MonthlyAttendanceReview $review) => $review->employee_id)
            : collect();

        $batch = null;
        $pendingCounts = [];
        $shiftsExistence = [];
        $snapshotsByEmployee = collect();

        // Conferências desatualizadas por mudanças após o registro (pré-carga global).
        $staleEmployeeIds = $this->staleEmployeeIds($employeeIds, $reviewsByEmployee, $year, $month);

        $reviewedCount = 0;
        $reviewedWithReservationCount = 0;
        $pendingCount = 0;
        $withOccurrencesCount = 0;
        $staleCount = 0;

        $items = [];

        // Processamento em blocos para limitar o pico de memória,
        // preservando totais e itens idênticos ao processamento integral.
        foreach ($employees->chunk(100) as $chunk) {
            $chunkIds = $chunk->map(fn (Employee $employee) => $employee->id)->all();

            if ($isClosed) {
                // Snapshots congelados do bloco (sem recálculo).
                $snapshotsByEmployee = $closedPeriod->currentSnapshots()
                    ->whereIn('employee_id', $chunkIds)
                    ->get()
                    ->keyBy(fn (ClosedPeriodEmployeeSnapshot $snapshot) => $snapshot->employee_id);
            } else {
                // Lote mensal compartilhado do bloco.
                $batch = app(MonthlyJourneyBatchService::class)->forCompetence($chunk, $year, $month);

                // Tratamentos pendentes por colaborador do bloco.
                // Um tratamento com vínculo e emprego distintos conta para ambos,
                // espelhando a consulta original (employee_id = X OR employment_id = X).
                $pendingCounts = [];
                $pendingEvents = TreatmentEvent::where('status', TreatmentEventStatus::Pending)
                    ->whereBetween('effective_at', [$startOfMonth, $endOfMonth])
                    ->where(function ($query) use ($chunkIds) {
                        $query->whereIn('employee_id', $chunkIds)
                            ->orWhereIn('employment_id', $chunkIds);
                    })
                    ->get(['employee_id', 'employment_id']);

                foreach ($pendingEvents as $event) {
                    foreach (array_unique(array_filter([$event->employee_id, $event->employment_id])) as $ownerId) {
                        $pendingCounts[$ownerId] = ($pendingCounts[$ownerId] ?? 0) + 1;
                    }
                }

                // Existência de plantões no período para o bloco.
                $shiftsExistence = [];
                $shiftOwnerIds = ShiftAssignment::whereIn('employee_id', $chunkIds)
                    ->forPeriod($startOfMonth, $endOfMonth)
                    ->distinct()
                    ->pluck('employee_id')
                    ->all();

                foreach ($shiftOwnerIds as $ownerId) {
                    $shiftsExistence[$ownerId] = true;
                }
            }

            /** @var Employee $emp */
            foreach ($chunk as $emp) {
                $review = $reviewsByEmployee->get($emp->id);
                $stale = $review !== null && isset($staleEmployeeIds[$emp->id]);
                if ($stale) {
                    $staleCount++;
                }

                $data = $isClosed
                    ? $this->buildClosedItem($emp, $closedPeriod, $snapshotsByEmployee->get($emp->id), $review)
                    : $this->buildLiveItem(
                        employee: $emp,
                        year: $year,
                        month: $month,
                        batch: $batch,
                        pendingTreatmentsCount: $pendingCounts[$emp->id] ?? 0,
                        hasShiftsInPeriod: $shiftsExistence[$emp->id] ?? false,
                        review: $review,
                        stale: $stale,
                    );
                $items[] = $data;

                match ($data['status']) {
                    AttendanceReviewStatus::Reviewed => $reviewedCount++,
                    AttendanceReviewStatus::ReviewedWithReservation => $reviewedWithReservationCount++,
                    AttendanceReviewStatus::WithOccurrences => $withOccurrencesCount++,
                    default => $pendingCount++,
                };
            }

            unset($batch, $snapshotsByEmployee, $pendingCounts, $shiftsExistence);
        }

        $totalChecked = $reviewedCount + $reviewedWithReservationCount;
        $blockingReasons = [];

        if (! $isClosed) {
            // Verificar condições de fechamento ordinário
            if ($totalEmployees === 0) {
                $blockingReasons[] = 'Nenhum servidor abrangido pela competência.';
            }

            if ($totalChecked < $totalEmployees) {
                $missing = $totalEmployees - $totalChecked;
                $blockingReasons[] = "Existem {$missing} colaborador(es) sem conferência concluída.";
            }

            if ($withOccurrencesCount > 0) {
                $blockingReasons[] = "Existem {$withOccurrencesCount} colaborador(es) com ocorrências não resolvidas que exigem ressalva fundamentada.";
            }

            if ($staleCount > 0) {
                $blockingReasons[] = "Existem {$staleCount} conferência(s) com dados alterados após o registro, exigindo nova validação individual.";
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

    /**
     * Identificadores com mudanças relevantes após a conferência registrada.
     *
     * @param  array<int>  $employeeIds
     * @param  Collection<int, MonthlyAttendanceReview>  $reviewsByEmployee
     * @return array<int, true>
     */
    protected function staleEmployeeIds(array $employeeIds, Collection $reviewsByEmployee, int $year, int $month): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $reviewedAtByEmployee = [];
        foreach ($reviewsByEmployee as $employeeId => $review) {
            if ($review->reviewed_at !== null) {
                // Resolução de segundos (precisão do banco).
                $reviewedAtByEmployee[$employeeId] = $review->reviewed_at->copy()->startOfSecond();
            }
        }

        if (empty($reviewedAtByEmployee)) {
            return [];
        }

        $stale = [];
        $markNewerThanReview = function (int $ownerId, ?Carbon $changedAt) use (&$stale, $reviewedAtByEmployee): void {
            if ($changedAt === null) {
                return;
            }
            $reviewedAt = $reviewedAtByEmployee[$ownerId] ?? null;
            if ($reviewedAt !== null && $changedAt->gt($reviewedAt)) {
                $stale[$ownerId] = true;
            }
        };

        $treatmentChanges = TreatmentEvent::where(function ($query) use ($employeeIds) {
            $query->whereIn('employee_id', $employeeIds)
                ->orWhereIn('employment_id', $employeeIds);
        })
            ->where('effective_at', '>=', $start)
            ->where('effective_at', '<=', $end)
            ->get(['employee_id', 'employment_id', 'updated_at']);
        foreach ($treatmentChanges as $event) {
            foreach (array_unique(array_filter([$event->employee_id, $event->employment_id])) as $ownerId) {
                $markNewerThanReview($ownerId, $event->updated_at);
            }
        }

        $assignmentChanges = WorkScheduleAssignment::whereIn('employee_id', $employeeIds)
            ->where('effective_from', '<=', $end->format('Y-m-d'))
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $start->format('Y-m-d'));
            })
            ->get(['employee_id', 'created_at', 'updated_at']);
        foreach ($assignmentChanges as $assignment) {
            $changedAt = $assignment->updated_at && $assignment->created_at
                ? ($assignment->updated_at->gt($assignment->created_at) ? $assignment->updated_at : $assignment->created_at)
                : ($assignment->updated_at ?? $assignment->created_at);
            $markNewerThanReview($assignment->employee_id, $changedAt);
        }

        $shiftChanges = ShiftAssignment::whereIn('employee_id', $employeeIds)
            ->forPeriod($start, $end)
            ->get(['employee_id', 'updated_at']);
        foreach ($shiftChanges as $shift) {
            $markNewerThanReview($shift->employee_id, $shift->updated_at);
        }

        return $stale;
    }

    /**
     * Quantidade de tratamentos pendentes do colaborador na competência.
     */
    protected function pendingTreatmentsCountFor(Employee $employee, int $year, int $month): int
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return TreatmentEvent::where(function ($query) use ($employee) {
            $query->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('status', TreatmentEventStatus::Pending)
            ->whereBetween('effective_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Indica se o colaborador possui plantões no período da competência.
     */
    protected function hasShiftsInPeriod(Employee $employee, int $year, int $month): bool
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return ShiftAssignment::where('employee_id', $employee->id)
            ->forPeriod($startDate, $endDate)
            ->exists();
    }

    /**
     * Registro de conferência salvo para o colaborador na competência.
     */
    protected function findReview(Employee $employee, int $year, int $month): ?MonthlyAttendanceReview
    {
        return MonthlyAttendanceReview::where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }
}
