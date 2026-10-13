<?php

namespace Tests\Feature;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\PTRP\Services\MonthlyJourneyBatchService;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyApurationParityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $admin;

    protected int $nsr = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Paridade Co', 'trade_name' => 'Paridade', 'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil', 'rep_p_software_version' => '2.0.0',
        ]);
        $this->establishment = Establishment::create([
            'company_id' => $this->company->id, 'name' => 'Sede', 'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195', 'state' => 'AL', 'city' => 'Maceió',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@paridade.local', 'password' => 'x', 'role' => UserRole::Admin,
        ]);
        $this->sector = Sector::create(['establishment_id' => $this->establishment->id, 'name' => 'Geral']);
    }

    protected function createEmployee(string $name, LegalRegime $regime = LegalRegime::CLT): Employee
    {
        $user = User::create([
            'name' => $name, 'email' => Str::slug($name).($this->nsr++).'@paridade.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id, 'sector_id' => $this->sector->id,
            'registration_number' => 'M'.rand(10000, 99999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Servidor', 'legal_regime' => $regime,
        ]);
    }

    protected function addPunch(Employee $employee, string $dateTime, string $direction): PunchEvent
    {
        $local = Carbon::parse($dateTime);

        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $this->nsr++,
            'direction' => $direction,
            'occurred_at_utc' => $local->copy()->utc(),
            'occurred_at_local' => $local,
            'fiscal_hash' => hash('sha256', Str::random()),
            'audit_chain_hash' => hash('sha256', Str::random()),
            'payload_hash' => hash('sha256', Str::random()),
            'hash_type_7' => hash('sha256', Str::random()),
            'integrity_hash' => hash('sha256', Str::random()),
        ]);
    }

    protected function administrativeSchedule(): WorkSchedule
    {
        return app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM 40h', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
    }

    protected function assign(Employee $employee, WorkSchedule $schedule, string $from = '2026-09-01'): void
    {
        app(AssignWorkScheduleAction::class)->execute(
            employee: $employee, schedule: $schedule, effectiveFrom: $from,
            reason: 'Paridade', assignedBy: $this->admin,
        );
    }

    /**
     * Compara a apuração direta (caminho antigo) com a apuração via lote (caminho novo).
     */
    protected function assertParity(Collection $employees, int $year, int $month): void
    {
        $batch = app(MonthlyJourneyBatchService::class)->forCompetence($employees, $year, $month);
        $days = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        foreach ($employees as $employee) {
            for ($d = 1; $d <= $days; $d++) {
                $date = Carbon::createFromDate($year, $month, $d);

                $direct = app(CalculateDailyJourneyAction::class)->execute($employee, $date);
                $batched = $batch->journeyFor($employee, $date);

                $this->assertJourneyEquals($direct, $batched, "{$employee->id} dia {$date->format('Y-m-d')}");
            }
        }
    }

    protected function assertJourneyEquals(CalculatedJourney $expected, CalculatedJourney $actual, string $context): void
    {
        foreach ([
            'scheduledMinutes', 'workedMinutes', 'ordinaryMinutes', 'overtimeMinutes',
            'lateMinutes', 'earlyLeaveMinutes', 'breakMinutes', 'missingBreakMinutes',
            'absenceMinutes', 'bankCreditMinutes', 'bankDebitMinutes', 'holidayMinutes',
            'physicalNightMinutes', 'legalNightEquivalentMinutes', 'nightFictionalBonusMinutes',
            'compensableOvertimeMinutes', 'nonCompensableOvertimeMinutes',
            'destinedToPayrollMinutes', 'destinedToCompensationMinutes',
            'toleratedMinutes', 'justifiedMinutes',
        ] as $field) {
            $this->assertSame($expected->{$field}, $actual->{$field}, "{$context} campo {$field}");
        }

        foreach (['isIncomplete', 'hasSchedule', 'isPendingConfiguration', 'isPendingSettlement', 'requiresCompensation'] as $flag) {
            $this->assertSame($expected->{$flag}, $actual->{$flag}, "{$context} flag {$flag}");
        }

        $this->assertSame($expected->workScheduleId, $actual->workScheduleId, "{$context} workScheduleId");
        $this->assertSame($expected->shiftAssignmentId, $actual->shiftAssignmentId, "{$context} shiftAssignmentId");
        $this->assertSame($expected->dailyDifferenceMinutes(), $actual->dailyDifferenceMinutes(), "{$context} dailyDifference");
        $this->assertSame(
            collect($expected->effectivePunches)->map(fn ($p) => [$p['time'], $p['direction'] ?? $p['type'], $p['source'] ?? null])->all(),
            collect($actual->effectivePunches)->map(fn ($p) => [$p['time'], $p['direction'] ?? $p['type'], $p['source'] ?? null])->all(),
            "{$context} effectivePunches"
        );
        $this->assertSame($expected->treatmentNotes, $actual->treatmentNotes, "{$context} treatmentNotes");
    }

    public function test_parity_administrative_full_month(): void
    {
        $schedule = $this->administrativeSchedule();
        $employees = collect();
        for ($i = 0; $i < 3; $i++) {
            $emp = $this->createEmployee("Adm {$i}");
            $this->assign($emp, $schedule);
            for ($d = 1; $d <= 31; $d++) {
                $date = Carbon::createFromDate(2026, 10, $d);
                if ($date->isWeekday()) {
                    $this->addPunch($emp, "2026-10-{$d} 08:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 12:00:00", 'out');
                    $this->addPunch($emp, "2026-10-{$d} 13:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 17:00:00", 'out');
                }
            }
            $employees->push($emp);
        }

        $this->assertParity($employees, 2026, 10);
    }

    public function test_parity_continuous_schedule(): void
    {
        $schedule = app(ConfigureWorkScheduleAction::class)->createContinuous([
            'name' => 'Corrido 30h', 'days' => [1, 2, 3, 4, 5],
            'start_time' => '07:00', 'end_time' => '13:00', 'break_minutes' => 15, 'deduct_break' => false,
        ], $this->admin);

        $emp = $this->createEmployee('Corrido');
        $this->assign($emp, $schedule);
        for ($d = 1; $d <= 31; $d++) {
            $date = Carbon::createFromDate(2026, 10, $d);
            if ($date->isWeekday()) {
                $this->addPunch($emp, "2026-10-{$d} 07:00:00", 'in');
                $this->addPunch($emp, "2026-10-{$d} 13:00:00", 'out');
            }
        }

        $this->assertParity(collect([$emp]), 2026, 10);
    }

    public function test_parity_12x36_shifts(): void
    {
        $schedule = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12', 'start_time' => '07:00', 'end_time' => '19:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-05', 'is_night_shift' => false,
        ], $this->admin);

        $emp = $this->createEmployee('Plant 12');
        $this->assign($emp, $schedule);
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp, schedule: $schedule, anchorDate: '2026-10-05',
            startDate: '2026-10-05', endDate: '2026-10-15', assignedBy: $this->admin,
        );

        foreach ([5, 7, 9, 11, 13, 15] as $d) {
            $this->addPunch($emp, "2026-10-{$d} 07:00:00", 'in');
            $this->addPunch($emp, "2026-10-{$d} 19:00:00", 'out');
        }

        $this->assertParity(collect([$emp]), 2026, 10);
    }

    public function test_parity_24x72_shifts(): void
    {
        $schedule = app(ConfigureWorkScheduleAction::class)->createShift24h([
            'name' => 'P24', 'start_time' => '07:00', 'end_time' => '07:00',
            'break_minutes' => 120, 'anchor_date' => '2026-10-01',
        ], $this->admin);

        $emp = $this->createEmployee('Plant 24', LegalRegime::MunicipalStatutory);
        $this->assign($emp, $schedule);
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp, schedule: $schedule, anchorDate: '2026-10-01',
            startDate: '2026-10-01', endDate: '2026-10-10', assignedBy: $this->admin,
        );

        foreach ([1, 5, 9] as $d) {
            $this->addPunch($emp, "2026-10-{$d} 07:00:00", 'in');
            $next = Carbon::createFromDate(2026, 10, $d)->addDay()->format('Y-m-d');
            $this->addPunch($emp, "{$next} 07:00:00", 'out');
        }

        $this->assertParity(collect([$emp]), 2026, 10);
    }

    public function test_parity_night_journey_crossing_midnight(): void
    {
        $schedule = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12N', 'start_time' => '19:00', 'end_time' => '07:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-06', 'is_night_shift' => true,
        ], $this->admin);

        $emp = $this->createEmployee('Noturno');
        $this->assign($emp, $schedule);
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp, schedule: $schedule, anchorDate: '2026-10-06',
            startDate: '2026-10-06', endDate: '2026-10-12', assignedBy: $this->admin,
        );

        foreach ([6, 8, 10, 12] as $d) {
            $this->addPunch($emp, "2026-10-{$d} 19:00:00", 'in');
            $next = Carbon::createFromDate(2026, 10, $d)->addDay()->format('Y-m-d');
            $this->addPunch($emp, "{$next} 07:00:00", 'out');
        }

        $this->assertParity(collect([$emp]), 2026, 10);

        $batch = app(MonthlyJourneyBatchService::class)->forCompetence(collect([$emp]), 2026, 10);
        $journey = $batch->journeyFor($emp, Carbon::parse('2026-10-06'));
        $this->assertGreaterThan(0, $journey->physicalNightMinutes);
    }

    public function test_parity_month_turnover_exit_next_month(): void
    {
        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Virada');
        $this->assign($emp, $schedule);

        $this->addPunch($emp, '2026-10-31 19:00:00', 'in');
        $this->addPunch($emp, '2026-11-01 02:00:00', 'out');

        $this->assertParity(collect([$emp]), 2026, 10);
    }

    public function test_parity_holiday(): void
    {
        CalendarEvent::create([
            'name' => 'Feriado Teste', 'event_date' => '2026-10-12',
            'type' => CalendarEventType::Holiday, 'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected, 'all_day' => true, 'active' => true,
        ]);

        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Feriado');
        $this->assign($emp, $schedule);
        $this->addPunch($emp, '2026-10-12 08:00:00', 'in');
        $this->addPunch($emp, '2026-10-12 12:00:00', 'out');

        $this->assertParity(collect([$emp]), 2026, 10);
    }

    public function test_parity_approved_treatment(): void
    {
        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Tratado');
        $this->assign($emp, $schedule);

        $this->addPunch($emp, '2026-10-06 08:00:00', 'in');

        $manual = app(RequestTreatmentEventAction::class)->execute(
            employee: $emp, type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-06 17:00:00'),
            reasonText: 'Saída esquecida por atendimento urgente',
            requestedBy: $emp->user, newValueJson: ['direction' => 'out'],
        );
        app(ApproveTreatmentEventAction::class)->execute(event: $manual, approvedBy: $this->admin);

        $this->assertParity(collect([$emp]), 2026, 10);

        $batch = app(MonthlyJourneyBatchService::class)->forCompetence(collect([$emp]), 2026, 10);
        $journey = $batch->journeyFor($emp, Carbon::parse('2026-10-06'));
        $this->assertFalse($journey->isIncomplete);
        $this->assertGreaterThan(0, $journey->workedMinutes);
    }

    public function test_parity_manager_scoped_overview_matches_admin_totals(): void
    {
        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Escopo');
        $this->assign($emp, $schedule);
        $this->addPunch($emp, '2026-10-06 08:00:00', 'in');
        $this->addPunch($emp, '2026-10-06 12:00:00', 'out');
        $this->addPunch($emp, '2026-10-06 13:00:00', 'in');
        $this->addPunch($emp, '2026-10-06 17:00:00', 'out');

        $manager = User::create([
            'name' => 'Gestor', 'email' => 'gestor@paridade.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->sector->update(['manager_id' => $manager->id]);

        $service = app(MonthlyAttendanceReviewService::class);
        $adminOverview = $service->getCompetenceOverview(2026, 10, $this->admin);
        $managerOverview = $service->getCompetenceOverview(2026, 10, $manager);

        $this->assertEquals($adminOverview['total_employees'], $managerOverview['total_employees']);
        $this->assertEquals($adminOverview['items'][0]['scheduled_minutes'], $managerOverview['items'][0]['scheduled_minutes']);
        $this->assertEquals($adminOverview['items'][0]['worked_minutes'], $managerOverview['items'][0]['worked_minutes']);
    }

    public function test_closed_period_still_reads_snapshots_without_recalculation(): void
    {
        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Fechado');
        $this->assign($emp, $schedule);
        $this->addPunch($emp, '2026-10-06 08:00:00', 'in');
        $this->addPunch($emp, '2026-10-06 12:00:00', 'out');
        $this->addPunch($emp, '2026-10-06 13:00:00', 'in');
        $this->addPunch($emp, '2026-10-06 17:00:00', 'out');

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->admin);
        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);

        $service = app(MonthlyAttendanceReviewService::class);
        $data = $service->resolveEmployeeReviewData($emp, 2026, 10);

        $this->assertTrue($data['is_closed']);
        $this->assertTrue($data['from_snapshot']);

        $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $this->assertTrue($report['is_closed']);
        $this->assertEquals($closed->snapshot_hash, $report['closed_hash']);
    }

    public function test_service_aggregates_match_hand_computed_values(): void
    {
        $schedule = $this->administrativeSchedule();
        $employees = collect();
        for ($i = 0; $i < 2; $i++) {
            $emp = $this->createEmployee("Manual {$i}");
            $this->assign($emp, $schedule);
            for ($d = 1; $d <= 31; $d++) {
                $date = Carbon::createFromDate(2026, 10, $d);
                if ($date->isWeekday()) {
                    $this->addPunch($emp, "2026-10-{$d} 08:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 12:00:00", 'out');
                    $this->addPunch($emp, "2026-10-{$d} 13:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 17:00:00", 'out');
                }
            }
            $employees->push($emp);
        }

        $weekdays = 0;
        for ($d = 1; $d <= 31; $d++) {
            if (Carbon::createFromDate(2026, 10, $d)->isWeekday()) {
                $weekdays++;
            }
        }
        $expectedScheduled = $weekdays * 480;

        $overview = app(MonthlyAttendanceReviewService::class)->getCompetenceOverview(2026, 10, $this->admin);
        $this->assertEquals(2, $overview['total_employees']);
        foreach ($overview['items'] as $item) {
            $this->assertEquals($expectedScheduled, $item['scheduled_minutes']);
            $this->assertEquals($expectedScheduled, $item['worked_minutes']);
            $this->assertEquals(0, $item['balance_minutes']);
        }

        $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $this->assertEquals(2 * $expectedScheduled, $report['totals']['scheduled_minutes']);
        $this->assertEquals(2 * $expectedScheduled, $report['totals']['worked_minutes']);
        $this->assertCount(2, $report['rows']);
    }

    public function test_monthly_reads_stay_within_query_budget(): void
    {
        $schedule = $this->administrativeSchedule();
        for ($i = 0; $i < 10; $i++) {
            $emp = $this->createEmployee("Orçamento {$i}");
            $this->assign($emp, $schedule);
            for ($d = 1; $d <= 31; $d++) {
                $date = Carbon::createFromDate(2026, 10, $d);
                if ($date->isWeekday()) {
                    $this->addPunch($emp, "2026-10-{$d} 08:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 12:00:00", 'out');
                    $this->addPunch($emp, "2026-10-{$d} 13:00:00", 'in');
                    $this->addPunch($emp, "2026-10-{$d} 17:00:00", 'out');
                }
            }
        }

        $countQueries = function (callable $fn): int {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });

            $fn();

            return $count;
        };

        $overviewQueries = $countQueries(fn () => app(MonthlyAttendanceReviewService::class)->getCompetenceOverview(2026, 10, $this->admin));
        $reportQueries = $countQueries(fn () => app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []));

        // Antes da otimização: ~4.500 consultas para 10 colaboradores.
        $this->assertLessThan(100, $overviewQueries, "Consultas da conferência: {$overviewQueries}");
        $this->assertLessThan(100, $reportQueries, "Consultas do relatório: {$reportQueries}");
    }

    public function test_monthly_reads_do_not_write(): void
    {
        $schedule = $this->administrativeSchedule();
        $emp = $this->createEmployee('Leitura');
        $this->assign($emp, $schedule);
        $this->addPunch($emp, '2026-10-06 08:00:00', 'in');
        $this->addPunch($emp, '2026-10-06 17:00:00', 'out');

        $service = app(MonthlyAttendanceReviewService::class);
        $service->getCompetenceOverview(2026, 10, $this->admin);
        app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);

        $counts = $this->tableCounts();

        $service->getCompetenceOverview(2026, 10, $this->admin);
        app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);

        $this->assertSame($counts, $this->tableCounts());
    }

    /**
     * @return array<string, int>
     */
    protected function tableCounts(): array
    {
        $tables = [
            'punch_events', 'treatment_events', 'shift_assignments',
            'work_schedules', 'work_schedule_assignments', 'monthly_attendance_reviews',
            'monthly_attendance_report_logs', 'labor_rule_profiles', 'closed_periods',
            'closed_period_employee_snapshots',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
