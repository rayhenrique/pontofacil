<?php

namespace Tests\Feature;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Enums\AttendanceReviewStatus;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $admin;

    protected User $managerA;

    protected User $managerB;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Integridade Co', 'trade_name' => 'Integridade', 'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil', 'rep_p_software_version' => '2.0.0',
        ]);
        $this->establishment = Establishment::create([
            'company_id' => $this->company->id, 'name' => 'Sede', 'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@integridade.local', 'password' => 'x', 'role' => UserRole::Admin,
        ]);
        $this->managerA = User::create([
            'name' => 'Gestor A', 'email' => 'ga@integridade.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->managerB = User::create([
            'name' => 'Gestor B', 'email' => 'gb@integridade.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Setor A', 'manager_id' => $this->managerA->id,
        ]);
        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Setor B', 'manager_id' => $this->managerB->id,
        ]);
        $this->schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM 40h', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
    }

    protected function createEmployee(string $name, ?Sector $sector = null): Employee
    {
        $user = User::create([
            'name' => $name, 'email' => Str::slug($name).rand(100, 9999).'@integridade.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id, 'sector_id' => ($sector ?? $this->sectorA)->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-'.rand(10000, 99999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Servidor', 'legal_regime' => LegalRegime::CLT,
        ]);
    }

    protected function addPunch(Employee $employee, string $dateTime, string $direction): void
    {
        $local = Carbon::parse($dateTime);
        PunchEvent::create([
            'id' => (string) Str::ulid(), 'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id, 'user_id' => $employee->user_id,
            'nsr' => rand(1000, 999999), 'direction' => $direction,
            'occurred_at_utc' => $local->copy()->utc(), 'occurred_at_local' => $local,
            'fiscal_hash' => hash('sha256', Str::random()), 'audit_chain_hash' => hash('sha256', Str::random()),
            'payload_hash' => hash('sha256', Str::random()), 'hash_type_7' => hash('sha256', Str::random()),
            'integrity_hash' => hash('sha256', Str::random()),
        ]);
    }

    protected function fullDay(Employee $employee, int $day): void
    {
        foreach (['08:00' => 'in', '12:00' => 'out', '13:00' => 'in', '17:00' => 'out'] as $time => $dir) {
            $this->addPunch($employee, "2026-10-{$day} {$time}:00", $dir);
        }
    }

    protected function assign(Employee $employee, ?WorkSchedule $schedule = null, string $from = '2026-09-01'): void
    {
        app(AssignWorkScheduleAction::class)->execute(
            employee: $employee, schedule: $schedule ?? $this->schedule,
            effectiveFrom: $from, reason: 'Integridade', assignedBy: $this->admin,
        );
    }

    public function test_open_competence_without_review_is_pending_not_approved(): void
    {
        $emp = $this->createEmployee('Sem Conferencia');
        $this->assign($emp);
        $this->fullDay($emp, 6);

        $service = app(MonthlyAttendanceReviewService::class);
        $data = $service->resolveEmployeeReviewData($emp, 2026, 10);

        $this->assertEquals(AttendanceReviewStatus::Pending, $data['status']);
        $this->assertFalse($data['is_closed']);
    }

    public function test_individual_and_batch_reviews_are_consistent(): void
    {
        $cleanA = $this->createEmployee('Limpo A');
        $cleanB = $this->createEmployee('Limpo B');
        $dirty = $this->createEmployee('Incompleto');
        foreach ([$cleanA, $cleanB, $dirty] as $emp) {
            $this->assign($emp);
        }
        $this->fullDay($cleanA, 6);
        $this->fullDay($cleanB, 6);
        $this->addPunch($dirty, '2026-10-06 08:00:00', 'in');

        $action = app(ReviewMonthlyAttendanceAction::class);

        $reviewA = $action->reviewEmployee($cleanA, 2026, 10, $this->managerA);
        $this->assertEquals(AttendanceReviewStatus::Reviewed, $reviewA->status);

        try {
            $action->reviewEmployee($dirty, 2026, 10, $this->managerA);
            $this->fail('Conferência com ocorrência deveria exigir ressalva.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ressalva', $e->getMessage());
        }

        $reviewDirty = $action->reviewEmployee($dirty, 2026, 10, $this->managerA, 'Saída autorizada verbalmente pela chefia.');
        $this->assertEquals(AttendanceReviewStatus::ReviewedWithReservation, $reviewDirty->status);

        $result = $action->reviewBatch([$cleanB->id], 2026, 10, $this->managerA);
        $this->assertEquals(1, $result['reviewed_count']);
        $this->assertEquals(AttendanceReviewStatus::Reviewed, $cleanB->monthlyAttendanceReviews()->first()->status);
    }

    public function test_close_blocked_with_pending_and_stale_reviews(): void
    {
        $pending = $this->createEmployee('Pendente');
        $stale = $this->createEmployee('Desatualizado');
        $this->assign($pending);
        $this->assign($stale);
        $this->fullDay($stale, 6);

        $action = app(ReviewMonthlyAttendanceAction::class);
        $action->reviewEmployee($stale, 2026, 10, $this->managerA);

        // Mudança relevante após a conferência: nova atribuição de escala.
        $other = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM Alternativa', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '14:00', 'afternoon_end' => '18:00',
        ], $this->admin);
        MonthlyAttendanceReview::where('employee_id', $stale->id)->update([
            'reviewed_at' => Carbon::now()->subHour(),
        ]);
        $this->assign($stale, $other, '2026-10-20');

        $service = app(MonthlyAttendanceReviewService::class);
        $overview = $service->getCompetenceOverview(2026, 10, $this->admin);

        $staleItem = collect($overview['items'])->firstWhere('employee_id', $stale->id);
        $this->assertTrue($staleItem['stale']);
        $this->assertTrue($staleItem['has_unresolved_occurrences']);
        $this->assertContains(
            'Dados alterados após a conferência — necessária nova validação.',
            $staleItem['occurrences']
        );
        $this->assertFalse($overview['can_close']);

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);
            $this->fail('Fechamento deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('nova validação', $e->getMessage());
        }

        // Nova validação com ressalva libera o caminho do fechamento.
        $action->reviewEmployee($stale, 2026, 10, $this->managerA, 'Nova escala conferida e validada.');
        $action->reviewEmployee($pending, 2026, 10, $this->managerA, 'Sem escala no período, conferência com ressalva.');
        $overviewAfter = $service->getCompetenceOverview(2026, 10, $this->admin);
        $this->assertFalse(collect($overviewAfter['items'])->firstWhere('employee_id', $stale->id)['stale']);
    }

    public function test_treatment_after_review_requires_revalidation(): void
    {
        $emp = $this->createEmployee('Tratamento Posterior');
        $this->assign($emp);
        $this->addPunch($emp, '2026-10-06 08:00:00', 'in');

        $action = app(ReviewMonthlyAttendanceAction::class);
        $action->reviewEmployee($emp, 2026, 10, $this->managerA, 'Saída autorizada verbalmente.');
        MonthlyAttendanceReview::where('employee_id', $emp->id)->update([
            'reviewed_at' => Carbon::now()->subHour(),
        ]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $emp,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-06 17:00:00'),
            reasonText: 'Saída esquecida por atendimento urgente',
            requestedBy: $emp->user,
            newValueJson: ['direction' => 'out'],
        );
        app(ApproveTreatmentEventAction::class)->execute(event: $event, approvedBy: $this->admin);

        $service = app(MonthlyAttendanceReviewService::class);
        $data = $service->resolveEmployeeReviewData($emp, 2026, 10);
        $this->assertTrue($data['stale']);
    }

    public function test_missing_snapshot_yields_unavailable_state_never_live_as_definitive(): void
    {
        $traced = $this->createEmployee('Com Vestigio');
        $this->assign($traced);
        $this->fullDay($traced, 6);

        ClosedPeriod::create([
            'year' => 2026, 'month' => 10, 'status' => 'closed', 'snapshot_version' => 1,
            'closed_by' => $this->admin->id, 'closed_at' => now(),
        ]);

        $service = app(MonthlyAttendanceReviewService::class);
        $data = $service->resolveEmployeeReviewData($traced, 2026, 10);

        $this->assertTrue($data['is_closed']);
        $this->assertTrue($data['history_unavailable']);
        $this->assertEquals(AttendanceReviewStatus::Pending, $data['status']);
        $this->assertEquals(0, $data['scheduled_minutes']);
        $this->assertEquals(0, $data['worked_minutes']);

        $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $this->assertTrue($report['is_closed']);
        $this->assertTrue($report['history_unavailable']);
        $row = collect($report['rows'])->firstWhere('employee_id', $traced->id);
        $this->assertNotNull($row);
        $this->assertEquals('Histórico indisponível', $row['review_status_label']);
        $this->assertEquals(0, $row['worked_minutes']);
    }

    public function test_sector_change_after_close_keeps_frozen_department(): void
    {
        $emp = $this->createEmployee('Mudanca Setor');
        $this->assign($emp);
        $this->fullDay($emp, 6);

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);

        $emp->update(['sector_id' => $this->sectorB->id]);

        $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $row = collect($report['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertEquals('Setor A', $row['department']);

        // O escopo acompanha a lotação atual.
        $managerReport = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->managerA, []);
        $this->assertNull(collect($managerReport['rows'])->firstWhere('employee_id', $emp->id));
        $managerBReport = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->managerB, []);
        $this->assertNotNull(collect($managerBReport['rows'])->firstWhere('employee_id', $emp->id));
    }

    public function test_night_shift_crossing_competence_boundary(): void
    {
        $night = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12N', 'start_time' => '19:00', 'end_time' => '07:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-31', 'is_night_shift' => true,
        ], $this->admin);

        $emp = $this->createEmployee('Noturno Virada');
        $this->assign($emp, $night);
        ShiftAssignment::create([
            'employee_id' => $emp->id, 'work_schedule_id' => $night->id,
            'start_at_utc' => Carbon::parse('2026-10-31 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-11-01 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-31 19:00:00'),
            'end_at_local' => Carbon::parse('2026-11-01 07:00:00'),
            'timezone' => 'America/Sao_Paulo', 'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::GeneratedCycle, 'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60, 'expected_work_minutes' => 660,
            'is_night_shift' => true, 'crosses_midnight' => true,
        ]);
        $this->addPunch($emp, '2026-10-31 19:00:00', 'in');
        $this->addPunch($emp, '2026-11-01 07:00:00', 'out');

        $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $row = collect($report['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertEquals(720, $row['worked_minutes']);
        $this->assertGreaterThan(0, $row['night_minutes']);

        $novReport = app(MonthlyAttendanceReportService::class)->getReportData(2026, 11, $this->admin, []);
        $novRow = collect($novReport['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertEquals(0, $novRow['worked_minutes']);
    }

    public function test_positive_negative_splits_ignore_pending_days(): void
    {
        // Congela o "hoje" no fim do mês para apurar todos os dias como passados.
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00'));

        try {
            $extra = $this->createEmployee('Splits');
            $this->assign($extra);
            // Dia 6: +60 completo (08–12, 13–18).
            foreach (['08:00' => 'in', '12:00' => 'out', '13:00' => 'in', '18:00' => 'out'] as $time => $dir) {
                $this->addPunch($extra, "2026-10-06 {$time}:00", $dir);
            }
            // Dia 7: incompleto (só entrada) — não compõe saldos definitivos.
            $this->addPunch($extra, '2026-10-07 08:00:00', 'in');

            $absenceDays = 0;
            for ($d = 1; $d <= 31; $d++) {
                $date = Carbon::createFromDate(2026, 10, $d);
                if ($date->isWeekday() && ! in_array($d, [6, 7], true)) {
                    $absenceDays++;
                }
            }

            $report = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
            $row = collect($report['rows'])->firstWhere('employee_id', $extra->id);
            $this->assertEquals(60, $row['positive_minutes']);
            $this->assertEquals(-480 * $absenceDays, $row['negative_minutes']);
            $this->assertEquals(540, $row['worked_minutes']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_future_journey_is_never_definitive_deficit(): void
    {
        $emp = $this->createEmployee('Futuro');
        $this->assign($emp);

        $date = Carbon::parse('2030-06-17');
        $journey = app(CalculateDailyJourneyAction::class)->execute($emp, $date);

        $this->assertTrue($journey->isFuture);
        $this->assertGreaterThan(0, $journey->scheduledMinutes);
        $this->assertEquals(0, $journey->absenceMinutes);
        $this->assertNull($journey->dailyDifferenceMinutes());
        $this->assertEquals('pending', $journey->differenceState());
    }

    public function test_in_progress_journey_is_flagged(): void
    {
        $emp = $this->createEmployee('Andamento');
        $this->assign($emp);

        $punchTime = Carbon::now()->subMinutes(30)->format('Y-m-d H:i:00');
        $this->addPunch($emp, $punchTime, 'in');

        $journey = app(CalculateDailyJourneyAction::class)->execute($emp, Carbon::today());

        $this->assertTrue($journey->isInProgress);
    }

    public function test_closed_csv_keeps_frozen_values_after_scale_change(): void
    {
        $emp = $this->createEmployee('Fidelidade');
        $this->assign($emp);
        $this->fullDay($emp, 6);

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);
        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);

        $other = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'Nova Escala Posterior', 'days' => [1, 2, 3],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
        $emp->update(['work_schedule_id' => $other->id]);

        $response = $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=10');
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('ADM 40h', $content);
        $this->assertStringNotContainsString('Nova Escala Posterior', $content);
        $this->assertStringContainsString('V'.$closed->snapshot_version, $content);
    }

    public function test_closed_pdf_contains_hash_and_version(): void
    {
        $emp = $this->createEmployee('Pdf');
        $this->assign($emp);
        $this->fullDay($emp, 6);

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);
        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);

        $response = $this->actingAs($this->admin)->get('/admin/reports/consolidated-pdf?year=2026&month=10');
        $response->assertOk();
        $response->assertSee($closed->snapshot_hash);
        $response->assertSee('V'.$closed->snapshot_version);
    }

    public function test_csv_neutralizes_formula_injection(): void
    {
        $user = User::create([
            'name' => '=CMD|\'/c calc\'!A0', 'email' => 'formula@integridade.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);
        $emp = Employee::create([
            'user_id' => $user->id, 'sector_id' => $this->sectorA->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-F1', 'cpf' => '99988877766',
            'job_title' => 'Servidor', 'legal_regime' => LegalRegime::CLT,
        ]);
        $this->assign($emp);
        $this->fullDay($emp, 6);

        $response = $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=10');
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('"\'=CMD', $content);
        $this->assertStringNotContainsString('";=CMD', $content);
    }

    public function test_export_validates_competence_and_filters(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=13')->assertStatus(422);
        $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=1999&month=10')->assertStatus(422);
        $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=10&sector_id=abc')->assertStatus(422);
        $this->actingAs($this->admin)->get('/admin/reports/consolidated-pdf?year=2026&month=0')->assertStatus(422);
    }

    public function test_dispatch_records_forwarding_without_confirming_payment(): void
    {
        $emp = $this->createEmployee('Despacho');
        $this->assign($emp);
        $this->fullDay($emp, 6);

        $service = app(MonthlyAttendanceReportService::class);
        $data = $service->getReportData(2026, 10, $this->admin, []);
        $log = $service->logReport(2026, 10, $this->admin, 'csv', $data, []);

        $response = $this->actingAs($this->admin)->postJson(
            "/admin/reports/dispatch/{$log->id}",
            ['notes' => 'Encaminhado ao setor responsável.']
        );

        $response->assertOk();
        $response->assertJsonPath('message', 'Encaminhamento ao setor de pagamento registrado com sucesso.');
        $this->assertStringNotContainsStringIgnoringCase(
            'quitado',
            $response->getContent()
        );
        $this->assertNotNull($log->fresh()->dispatched_to_payroll_at);
    }

    public function test_close_preserves_fiscal_integrity_and_reopen_keeps_snapshots(): void
    {
        $emp = $this->createEmployee('Fiscal');
        $this->assign($emp);
        $this->fullDay($emp, 6);
        $punchCount = PunchEvent::where('employee_id', $emp->id)->count();

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);
        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, enforceAttendanceReview: true);
        $hash = $closed->snapshot_hash;
        $snapshotRows = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closed->id)->count();
        $this->assertGreaterThan(0, $snapshotRows);

        // Leitura repetida mantém o mesmo hash.
        $again = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $this->assertEquals($hash, $again['closed_hash']);

        // Reabertura auditada preserva os snapshots históricos.
        app(ReopenMonthlyPeriodAction::class)->execute(2026, 10, $this->admin, 'Correção de cadastro autorizada pela chefia.');
        $this->assertEquals(
            $snapshotRows,
            ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closed->id)->count()
        );

        // Após reabrir, a competência volta a ser provisória (sem hash definitivo).
        $reopened = app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $this->admin, []);
        $this->assertTrue($reopened['is_provisional']);
        $this->assertNull($reopened['closed_hash']);

        // Marcas originais intactas.
        $this->assertEquals($punchCount, PunchEvent::where('employee_id', $emp->id)->count());
    }
}
