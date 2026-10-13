<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Services\MonthlyJourneyBatchService;
use App\Enums\AttendanceReviewStatus;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MonthlyAttendanceReportService
{
    /**
     * Resolve todos os dados do relatório mensal consolidado de frequência.
     * Na competência fechada, lê ESTRITAMENTE dos snapshots congelados imutáveis.
     * Na competência aberta, realiza a apuração preliminar ao vivo, identificada como Provisória.
     *
     * @param  array{establishment_id?: ?int, sector_id?: ?int, employee_id?: ?int}  $filters
     */
    public function getReportData(int $year, int $month, User $actor, array $filters = []): array
    {
        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        $company = CurrentCompany::get();
        $establishmentId = ! empty($filters['establishment_id']) ? (int) $filters['establishment_id'] : null;
        $sectorId = ! empty($filters['sector_id']) ? (int) $filters['sector_id'] : null;
        $employeeId = ! empty($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        // Validação de Escopo / Permissão para Gestores
        $managedSectorIds = null;
        if ($actor->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $actor->id)->pluck('id')->all();
        }

        app(SectorScopeService::class)->validateReportFilters($actor, $establishmentId, $sectorId, $employeeId);

        if ($isClosed) {
            return $this->resolveFromClosedSnapshots(
                closedPeriod: $closedPeriod,
                year: $year,
                month: $month,
                actor: $actor,
                establishmentId: $establishmentId,
                sectorId: $sectorId,
                employeeId: $employeeId,
                managedSectorIds: $managedSectorIds,
            );
        }

        return $this->resolveFromLiveApuracao(
            year: $year,
            month: $month,
            actor: $actor,
            establishmentId: $establishmentId,
            sectorId: $sectorId,
            employeeId: $employeeId,
            managedSectorIds: $managedSectorIds,
        );
    }

    /**
     * Resolve a apuração consolidada de competência fechada usando exclusivamente snapshots.
     */
    protected function resolveFromClosedSnapshots(
        ClosedPeriod $closedPeriod,
        int $year,
        int $month,
        User $actor,
        ?int $establishmentId,
        ?int $sectorId,
        ?int $employeeId,
        ?array $managedSectorIds,
    ): array {
        $company = CurrentCompany::get();

        // Elenco filtrado pelos mesmos critérios da prévia ao vivo
        // (escopo, estabelecimento, setor e servidor).
        $employees = $this->scopedEmployeesQuery($actor, $establishmentId, $sectorId, $employeeId, $managedSectorIds)->get();
        $employeeIds = $employees->map(fn (Employee $employee) => $employee->id)->all();

        $snapshotsByEmployee = $employeeIds
            ? $closedPeriod->currentSnapshots()
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy(fn ($snapshot) => $snapshot->employee_id)
            : collect();

        // Carregar reviews da competência
        $reviews = $employeeIds
            ? MonthlyAttendanceReview::with('reviewer')
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy('employee_id')
            : collect();

        // Vestígios na competência para distinguir "sem snapshot" de "fora da competência".
        $tracedEmployeeIds = $this->tracedEmployeeIds($employeeIds, $year, $month);

        $rows = [];
        $totals = $this->initializeTotals();

        foreach ($employees as $emp) {
            $snap = $snapshotsByEmployee->get($emp->id);
            $review = $reviews->get($emp->id);

            if ($snap === null) {
                // Sem snapshot congelado: nunca apurar ao vivo como definitivo.
                if (! isset($tracedEmployeeIds[$emp->id])) {
                    continue;
                }

                $unavailableRow = $this->unavailableRow($emp, $review);
                $rows[] = $unavailableRow;
                $this->accumulateTotals($totals, $unavailableRow);

                continue;
            }

            $empData = $snap->employee_snapshot ?? [];
            $schedData = $snap->schedule_snapshot ?? [];
            $shiftsData = $snap->shifts_snapshot ?? [];
            $journeys = $snap->journey_snapshot ?? [];
            $treatments = $snap->treatment_snapshot ?? [];

            $scheduledMinutes = 0;
            $workedMinutes = 0;
            $absenceMinutes = 0;
            $nightMinutes = 0;
            $delayMinutes = 0;
            $positiveMinutes = 0;
            $negativeMinutes = 0;

            foreach ($journeys as $j) {
                $sched = (int) ($j['scheduled_minutes'] ?? $j['scheduledMinutes'] ?? 0);
                $worked = (int) ($j['worked_minutes'] ?? $j['workedMinutes'] ?? 0);
                $scheduledMinutes += $sched;
                $workedMinutes += $worked;
                $absenceMinutes += (int) ($j['absence_minutes'] ?? $j['absenceMinutes'] ?? 0);
                $nightMinutes += (int) ($j['physical_night_minutes'] ?? $j['night_shift_minutes'] ?? $j['nightMinutes'] ?? 0);
                $delayMinutes += (int) ($j['late_minutes'] ?? $j['delay_minutes'] ?? $j['delayMinutes'] ?? 0);

                // Diferença classificada pelo PTRP; dias pendentes não compõem
                // os saldos definitivos (espelha o Espelho de Ponto).
                $dayDiff = array_key_exists('daily_difference_minutes', $j) && $j['daily_difference_minutes'] !== null
                    ? (int) $j['daily_difference_minutes']
                    : null;

                if ($dayDiff > 0) {
                    $positiveMinutes += $dayDiff;
                } elseif ($dayDiff < 0) {
                    $negativeMinutes += $dayDiff;
                }
            }

            $diffMinutes = $workedMinutes - $scheduledMinutes;

            $reviewStatus = $review?->status ?? AttendanceReviewStatus::Pending;

            $row = [
                'employee_id' => $snap->employee_id,
                'name' => $empData['name'] ?? 'Servidor #'.$snap->employee_id,
                'registration_number' => $empData['registration_number'] ?? '—',
                'cpf' => $empData['cpf'] ?? '—',
                'legal_regime' => $empData['legal_regime'] ?? 'CLT',
                'job_title' => $empData['job_title'] ?? 'Não informado',
                'department' => $empData['department'] ?? 'Geral',
                'establishment_name' => $empData['establishment_name'] ?? 'Sede',
                'schedule_name' => $schedData['name'] ?? 'Padrão',
                'schedule_modality' => $schedData['modality'] ?? $schedData['type'] ?? 'Semanal',
                'scheduled_minutes' => $scheduledMinutes,
                'worked_minutes' => $workedMinutes,
                'balance_minutes' => $diffMinutes,
                'positive_minutes' => $positiveMinutes,
                'negative_minutes' => $negativeMinutes,
                'absence_minutes' => $absenceMinutes,
                'delay_minutes' => $delayMinutes,
                'night_minutes' => $nightMinutes,
                'shifts_count' => count($shiftsData),
                'treatments_count' => count($treatments),
                'review_status' => $reviewStatus,
                'review_status_label' => $reviewStatus->label(),
                'reservation_notes' => $review?->reservation_notes,
                'reviewed_by_name' => $review?->reviewer?->name ?? $closedPeriod->closer?->name ?? 'RH',
                'reviewed_at' => $review?->reviewed_at ?? $closedPeriod->closed_at,
            ];

            $rows[] = $row;
            $this->accumulateTotals($totals, $row);
        }

        // Ordenar alfabeticamente por nome
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'year' => $year,
            'month' => $month,
            'is_closed' => true,
            'is_provisional' => false,
            'report_type_title' => 'RELATÓRIO MENSAL CONSOLIDADO DE FREQUÊNCIA',
            'report_badge_label' => 'DEFINITIVO / HOMOLOGADO',
            'provisional_notice' => null,
            'history_unavailable' => $snapshotsByEmployee->isEmpty(),
            'closed_period_id' => $closedPeriod->id,
            'closed_version' => $closedPeriod->snapshot_version,
            'closed_hash' => $closedPeriod->snapshot_hash,
            'closed_at' => $closedPeriod->closed_at,
            'closed_by_name' => $closedPeriod->closer?->name ?? 'Administrador',
            'company_name' => $company?->legal_name ?? 'Órgão / Empresa Pública',
            'company_cnpj' => $company?->cnpj,
            'generated_at' => now(),
            'generated_by_name' => $actor->name,
            'totals' => $totals,
            'rows' => $rows,
        ];
    }

    /**
     * Consulta base de servidores com os mesmos filtros de escopo
     * da prévia ao vivo (gestão, estabelecimento, setor e servidor).
     */
    protected function scopedEmployeesQuery(
        User $actor,
        ?int $establishmentId,
        ?int $sectorId,
        ?int $employeeId,
        ?array $managedSectorIds,
    ): Builder {
        $empQuery = Employee::with(['user', 'sector.establishment', 'workSchedule']);

        if ($actor->role === UserRole::Manager && $managedSectorIds !== null) {
            $empQuery->whereIn('sector_id', $managedSectorIds);
        }

        if ($establishmentId) {
            $empQuery->whereHas('sector', fn ($q) => $q->where('establishment_id', $establishmentId));
        }

        if ($sectorId) {
            $empQuery->where('sector_id', $sectorId);
        }

        if ($employeeId) {
            $empQuery->where('id', $employeeId);
        }

        return $empQuery;
    }

    /**
     * Identificadores com vestígios na competência (marcações, plantões,
     * tratamentos ou atribuições), para distinguir ausência de snapshot
     * de vínculo fora da competência.
     *
     * @param  array<int>  $employeeIds
     * @return array<int, true>
     */
    protected function tracedEmployeeIds(array $employeeIds, int $year, int $month): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $traced = [];

        $punchOwners = PunchEvent::whereIn('employee_id', $employeeIds)
            ->where('occurred_at_local', '>=', $start)
            ->where('occurred_at_local', '<=', $end)
            ->distinct()
            ->pluck('employee_id')
            ->all();
        foreach ($punchOwners as $ownerId) {
            $traced[$ownerId] = true;
        }

        $shiftOwners = ShiftAssignment::whereIn('employee_id', $employeeIds)
            ->where('start_at_local', '<=', $end)
            ->where('end_at_local', '>=', $start)
            ->distinct()
            ->pluck('employee_id')
            ->all();
        foreach ($shiftOwners as $ownerId) {
            $traced[$ownerId] = true;
        }

        $treatmentOwners = TreatmentEvent::where(function ($query) use ($employeeIds) {
            $query->whereIn('employee_id', $employeeIds)
                ->orWhereIn('employment_id', $employeeIds);
        })
            ->where('effective_at', '>=', $start)
            ->where('effective_at', '<=', $end)
            ->get(['employee_id', 'employment_id']);
        foreach ($treatmentOwners as $treatment) {
            foreach (array_unique(array_filter([$treatment->employee_id, $treatment->employment_id])) as $ownerId) {
                $traced[$ownerId] = true;
            }
        }

        $assignmentOwners = WorkScheduleAssignment::whereIn('employee_id', $employeeIds)
            ->where('effective_from', '<=', $end->format('Y-m-d'))
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $start->format('Y-m-d'));
            })
            ->distinct()
            ->pluck('employee_id')
            ->all();
        foreach ($assignmentOwners as $ownerId) {
            $traced[$ownerId] = true;
        }

        return $traced;
    }

    /**
     * Linha de indisponibilidade histórica: zeros honestos, sem apuração ao vivo.
     */
    protected function unavailableRow(Employee $employee, ?MonthlyAttendanceReview $review): array
    {
        $reviewStatus = $review?->status ?? AttendanceReviewStatus::Pending;

        return [
            'employee_id' => $employee->id,
            'name' => $employee->user?->name ?? 'Servidor #'.$employee->id,
            'registration_number' => $employee->registration_number ?: '—',
            'cpf' => $employee->cpf ?: '—',
            'legal_regime' => $employee->legal_regime?->value ?? 'CLT',
            'job_title' => $employee->job_title ?: 'Não informado',
            'department' => $employee->sector?->name ?? 'Geral',
            'establishment_name' => $employee->sector?->establishment?->name ?? 'Sede',
            'schedule_name' => 'Histórico indisponível',
            'schedule_modality' => '—',
            'scheduled_minutes' => 0,
            'worked_minutes' => 0,
            'balance_minutes' => 0,
            'positive_minutes' => 0,
            'negative_minutes' => 0,
            'absence_minutes' => 0,
            'delay_minutes' => 0,
            'night_minutes' => 0,
            'shifts_count' => 0,
            'treatments_count' => 0,
            'review_status' => $reviewStatus,
            'review_status_label' => 'Histórico indisponível',
            'reservation_notes' => $review?->reservation_notes,
            'reviewed_by_name' => $review?->reviewer?->name,
            'reviewed_at' => $review?->reviewed_at,
            'history_unavailable' => true,
        ];
    }

    /**
     * Resolve a apuração consolidada ao vivo na competência aberta (Prévia Provisória).
     */
    protected function resolveFromLiveApuracao(
        int $year,
        int $month,
        User $actor,
        ?int $establishmentId,
        ?int $sectorId,
        ?int $employeeId,
        ?array $managedSectorIds,
    ): array {
        $company = CurrentCompany::get();
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $endDate->day;

        $employees = $this->scopedEmployeesQuery($actor, $establishmentId, $sectorId, $employeeId, $managedSectorIds)->get();

        $reviews = MonthlyAttendanceReview::with('reviewer')
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('employee_id');

        // Lote mensal compartilhado com a Conferência, processado em blocos
        // para limitar o pico de memória sem alterar totais ou linhas.
        $rows = [];
        $totals = $this->initializeTotals();

        foreach ($employees->chunk(100) as $chunk) {
            $batch = app(MonthlyJourneyBatchService::class)->forCompetence($chunk, $year, $month);

            foreach ($chunk as $emp) {
                $scheduledMinutes = 0;
                $workedMinutes = 0;
                $absenceMinutes = 0;
                $nightMinutes = 0;
                $delayMinutes = 0;
                $positiveMinutes = 0;
                $negativeMinutes = 0;

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayDate = Carbon::createFromDate($year, $month, $d);
                    $journey = $batch->journeyFor($emp, $dayDate);

                    $scheduledMinutes += $journey->scheduledMinutes;
                    $workedMinutes += $journey->workedMinutes;
                    $absenceMinutes += $journey->absenceMinutes;
                    $nightMinutes += $journey->physicalNightMinutes;
                    $delayMinutes += $journey->lateMinutes;

                    // Diferença classificada pelo PTRP; dias pendentes ou futuros
                    // não compõem os saldos definitivos (espelha o Espelho de Ponto).
                    $dayDiff = $journey->dailyDifferenceMinutes();

                    if ($dayDiff > 0) {
                        $positiveMinutes += $dayDiff;
                    } elseif ($dayDiff < 0) {
                        $negativeMinutes += $dayDiff;
                    }
                }

                $diffMinutes = $workedMinutes - $scheduledMinutes;

                // Plantões e tratamentos (contados sobre o lote pré-carregado)
                $shiftsCount = $batch->shiftsCountInPeriod($emp, $startDate, $endDate);
                $treatmentsCount = $batch->approvedTreatmentsCountFor($emp);

                $review = $reviews->get($emp->id);
                $reviewStatus = $review?->status ?? AttendanceReviewStatus::Pending;

                $schedule = $batch->scheduleFor($emp, $endDate) ?? $emp->workSchedule;

                $row = [
                    'employee_id' => $emp->id,
                    'name' => $emp->user?->name ?? 'Servidor #'.$emp->id,
                    'registration_number' => $emp->registration_number ?: '—',
                    'cpf' => $emp->cpf ?: '—',
                    'legal_regime' => $emp->legal_regime?->value ?? 'CLT',
                    'job_title' => $emp->job_title ?: 'Não informado',
                    'department' => $emp->sector?->name ?? 'Geral',
                    'establishment_name' => $emp->sector?->establishment?->name ?? 'Sede',
                    'schedule_name' => $schedule?->name ?? 'Sem escala',
                    'schedule_modality' => $schedule?->modality?->value ?? 'Semanal',
                    'scheduled_minutes' => $scheduledMinutes,
                    'worked_minutes' => $workedMinutes,
                    'balance_minutes' => $diffMinutes,
                    'positive_minutes' => $positiveMinutes,
                    'negative_minutes' => $negativeMinutes,
                    'absence_minutes' => $absenceMinutes,
                    'delay_minutes' => $delayMinutes,
                    'night_minutes' => $nightMinutes,
                    'shifts_count' => $shiftsCount,
                    'treatments_count' => $treatmentsCount,
                    'review_status' => $reviewStatus,
                    'review_status_label' => $reviewStatus->label(),
                    'reservation_notes' => $review?->reservation_notes,
                    'reviewed_by_name' => $review?->reviewer?->name,
                    'reviewed_at' => $review?->reviewed_at,
                ];

                $rows[] = $row;
                $this->accumulateTotals($totals, $row);
            }

            unset($batch);
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'year' => $year,
            'month' => $month,
            'is_closed' => false,
            'is_provisional' => true,
            'report_type_title' => 'PRÉVIA PROVISÓRIA DE FREQUÊNCIA MENSAL',
            'report_badge_label' => 'PROVISÓRIO — COMPETÊNCIA EM ABERTO',
            'provisional_notice' => 'ATENÇÃO: Documento preliminar de conferência. Não possui validade como relatório final para fechamento da folha até a homologação e fechamento formal da competência pelo RH.',
            'history_unavailable' => false,
            'closed_period_id' => null,
            'closed_version' => null,
            'closed_hash' => null,
            'closed_at' => null,
            'closed_by_name' => null,
            'company_name' => $company?->legal_name ?? 'Órgão / Empresa Pública',
            'company_cnpj' => $company?->cnpj,
            'generated_at' => now(),
            'generated_by_name' => $actor->name,
            'totals' => $totals,
            'rows' => $rows,
        ];
    }

    /**
     * Gera CSV em UTF-8 com BOM (\xEF\xBB\xBF) e separador ponto-e-vírgula (;).
     */
    public function generateCsv(array $reportData): string
    {
        $bom = "\xEF\xBB\xBF";
        $csv = $bom;

        // Cabeçalhos administrativos
        $headers = [
            'Nome do Servidor',
            'Matrícula',
            'CPF',
            'Regime / Vínculo',
            'Cargo / Função',
            'Lotação / Setor',
            'Estabelecimento',
            'Escala de Trabalho',
            'Modalidade de Escala',
            'Carga Prevista (h)',
            'Horas Físicas Trabalhadas (h)',
            'Diferença Líquida (h)',
            'Horas Positivas (h)',
            'Horas Negativas (h)',
            'Faltas (h)',
            'Atrasos (h)',
            'Horas Noturnas (h)',
            'Plantões Realizados (qtd)',
            'Tratamentos Aprovados (qtd)',
            'Situação da Conferência',
            'Ressalvas / Observações',
            'Responsável Conferência',
            'Data da Conferência',
            'Tipo do Relatório',
            'Competência',
            'Versão Fechamento',
        ];

        $csv .= implode(';', array_map([$this, 'escapeCsvField'], $headers))."\r\n";

        $competenceStr = sprintf('%02d/%04d', $reportData['month'], $reportData['year']);
        $versionStr = $reportData['is_closed'] ? ('V'.$reportData['closed_version']) : 'PROVISÓRIO';
        $reportType = $reportData['is_provisional'] ? 'PRÉVIA PROVISÓRIA' : 'OFICIAL DEFINITIVO';

        foreach ($reportData['rows'] as $r) {
            $row = [
                $r['name'],
                $r['registration_number'],
                $r['cpf'],
                $r['legal_regime'],
                $r['job_title'],
                $r['department'],
                $r['establishment_name'],
                $r['schedule_name'],
                $r['schedule_modality'],
                $this->formatMinutesToHoursString($r['scheduled_minutes']),
                $this->formatMinutesToHoursString($r['worked_minutes']),
                $this->formatMinutesToHoursString($r['balance_minutes'], true),
                $this->formatMinutesToHoursString($r['positive_minutes']),
                $this->formatMinutesToHoursString($r['negative_minutes'], true),
                $this->formatMinutesToHoursString($r['absence_minutes']),
                $this->formatMinutesToHoursString($r['delay_minutes']),
                $this->formatMinutesToHoursString($r['night_minutes']),
                (string) $r['shifts_count'],
                (string) $r['treatments_count'],
                $r['review_status_label'],
                $r['reservation_notes'] ?? '',
                $r['reviewed_by_name'] ?? '—',
                $r['reviewed_at'] ? Carbon::parse($r['reviewed_at'])->format('d/m/Y H:i') : '—',
                $reportType,
                $competenceStr,
                $versionStr,
            ];

            $csv .= implode(';', array_map([$this, 'escapeCsvField'], $row))."\r\n";
        }

        return $csv;
    }

    /**
     * Registra o log de emissão do relatório consolidado.
     */
    public function logReport(
        int $year,
        int $month,
        User $actor,
        string $format,
        array $reportData,
        array $filters = [],
    ): MonthlyAttendanceReportLog {
        return MonthlyAttendanceReportLog::create([
            'year' => $year,
            'month' => $month,
            'establishment_id' => ! empty($filters['establishment_id']) ? (int) $filters['establishment_id'] : null,
            'sector_id' => ! empty($filters['sector_id']) ? (int) $filters['sector_id'] : null,
            'generated_by' => $actor->id,
            'format' => $format,
            'is_provisional' => $reportData['is_provisional'],
            'filters' => $filters,
            'totals_snapshot' => $reportData['totals'],
        ]);
    }

    /**
     * Registra formalmente o encaminhamento ao setor de folha/pagamento (ação explícita do RH).
     */
    public function recordDispatchToPayroll(
        MonthlyAttendanceReportLog $log,
        User $actor,
        ?string $notes = null,
    ): MonthlyAttendanceReportLog {
        app(SectorScopeService::class)->authorizeReportLog($actor, $log);

        $log->update([
            'dispatched_to_payroll_at' => now(),
            'dispatched_by' => $actor->id,
            'dispatch_notes' => $notes ? trim($notes) : null,
        ]);

        return $log;
    }

    protected function initializeTotals(): array
    {
        return [
            'total_employees' => 0,
            'scheduled_minutes' => 0,
            'worked_minutes' => 0,
            'balance_minutes' => 0,
            'positive_minutes' => 0,
            'negative_minutes' => 0,
            'absence_minutes' => 0,
            'night_minutes' => 0,
            'delay_minutes' => 0,
            'shifts_count' => 0,
            'treatments_count' => 0,
            'reviewed_count' => 0,
            'with_reservation_count' => 0,
        ];
    }

    protected function accumulateTotals(array &$totals, array $row): void
    {
        $totals['total_employees']++;
        $totals['scheduled_minutes'] += $row['scheduled_minutes'];
        $totals['worked_minutes'] += $row['worked_minutes'];
        $totals['balance_minutes'] += $row['balance_minutes'];
        $totals['positive_minutes'] += $row['positive_minutes'];
        $totals['negative_minutes'] += $row['negative_minutes'];
        $totals['absence_minutes'] += $row['absence_minutes'];
        $totals['night_minutes'] += $row['night_minutes'];
        $totals['delay_minutes'] += $row['delay_minutes'];
        $totals['shifts_count'] += $row['shifts_count'];
        $totals['treatments_count'] += $row['treatments_count'];

        if ($row['review_status'] === AttendanceReviewStatus::Reviewed) {
            $totals['reviewed_count']++;
        } elseif ($row['review_status'] === AttendanceReviewStatus::ReviewedWithReservation) {
            $totals['with_reservation_count']++;
        }
    }

    protected function escapeCsvField(?string $value): string
    {
        if ($value === null) {
            return '""';
        }

        // Campos sistêmicos estritos (horas 00:00 e contagens) não oferecem vetor de fórmula.
        if (preg_match('/^[+-]?\d{2}:\d{2}$/', $value) === 1 || preg_match('/^\d+$/', $value) === 1) {
            return '"'.$value.'"';
        }

        // Neutraliza interpretação como fórmula em planilhas (OWASP CSV Injection).
        if (preg_match('/^[\s]*[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        $val = str_replace('"', '""', $value);

        return '"'.$val.'"';
    }

    public function formatMinutesToHoursString(int $minutes, bool $withSign = false): string
    {
        $sign = $minutes < 0 ? '-' : ($withSign && $minutes > 0 ? '+' : '');
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $h, $m);
    }
}
