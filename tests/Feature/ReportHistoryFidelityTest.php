<?php

namespace Tests\Feature;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Enums\AttendanceReviewStatus;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ReviewForClose;
use Tests\TestCase;

class ReportHistoryFidelityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $admin;

    protected User $managerA;

    protected User $managerB;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Fidelidade Co', 'trade_name' => 'Fidelidade', 'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil', 'rep_p_software_version' => '2.0.0',
        ]);
        $this->establishment = Establishment::create([
            'company_id' => $this->company->id, 'name' => 'Sede', 'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@fidelidade.local', 'password' => 'x', 'role' => UserRole::Admin,
        ]);
        $this->managerA = User::create([
            'name' => 'Gestor A', 'email' => 'ga@fidelidade.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->managerB = User::create([
            'name' => 'Gestor B', 'email' => 'gb@fidelidade.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Setor A', 'manager_id' => $this->managerA->id,
        ]);
        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Setor B', 'manager_id' => $this->managerB->id,
        ]);
    }

    protected function hire(string $name, Sector $sector, ?string $cpf = null): Employee
    {
        $user = User::create([
            'name' => $name, 'email' => Str::slug($name).rand(100, 9999).'@fidelidade.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);
        $schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM '.$name, 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
        $employee = Employee::create([
            'user_id' => $user->id, 'sector_id' => $sector->id,
            'registration_number' => 'MAT-'.rand(10000, 99999),
            'cpf' => $cpf ?? sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Cargo Original', 'legal_regime' => LegalRegime::CLT,
        ]);
        app(AssignWorkScheduleAction::class)->execute(
            employee: $employee, schedule: $schedule,
            effectiveFrom: '2026-09-01', reason: 'Base', assignedBy: $this->admin,
        );

        return $employee;
    }

    protected function punch(Employee $employee, string $dateTime, string $direction): void
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

    protected function closeOctober(): void
    {
        ReviewForClose::reviewAll(Employee::all(), 2026, 10, $this->admin);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
    }

    protected function report(User $actor, array $filters = []): array
    {
        return app(MonthlyAttendanceReportService::class)->getReportData(2026, 10, $actor, $filters);
    }

    public function test_transferred_employee_stays_with_frozen_data(): void
    {
        $emp = $this->hire('Transferido', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        $emp->update(['sector_id' => $this->sectorB->id]);

        $row = collect($this->report($this->admin)['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertNotNull($row);
        $this->assertEquals('Setor A', $row['department']);
        $this->assertEquals(540, $row['worked_minutes']);
    }

    public function test_deactivated_employee_stays_in_historic_report(): void
    {
        $emp = $this->hire('Desligado', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        $emp->inactivate('Desligamento para teste');

        $row = collect($this->report($this->admin)['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertNotNull($row);
        $this->assertEquals('Desligado', $row['name']);
        $this->assertEquals(540, $row['worked_minutes']);
    }

    public function test_later_name_and_title_changes_do_not_leak_into_history(): void
    {
        $emp = $this->hire('Nome Antigo', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        $emp->user->update(['name' => 'Nome Novo']);
        $emp->update(['job_title' => 'Cargo Novo']);

        $row = collect($this->report($this->admin)['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertEquals('Nome Antigo', $row['name']);
        $this->assertEquals('Cargo Original', $row['job_title']);
    }

    public function test_manager_sees_only_frozen_managed_sectors(): void
    {
        $empA = $this->hire('Do Setor A', $this->sectorA);
        $empB = $this->hire('Do Setor B', $this->sectorB);
        foreach ([$empA, $empB] as $emp) {
            $this->punch($emp, '2026-10-06 08:00:00', 'in');
            $this->punch($emp, '2026-10-06 17:00:00', 'out');
        }
        $this->closeOctober();

        // Transferência posterior não desloca o histórico.
        $empA->update(['sector_id' => $this->sectorB->id]);

        $rowsA = $this->report($this->managerA)['rows'];
        $this->assertNotNull(collect($rowsA)->firstWhere('employee_id', $empA->id));
        $this->assertNull(collect($rowsA)->firstWhere('employee_id', $empB->id));

        $rowsB = $this->report($this->managerB)['rows'];
        $this->assertNull(collect($rowsB)->firstWhere('employee_id', $empA->id));
        $this->assertNotNull(collect($rowsB)->firstWhere('employee_id', $empB->id));
    }

    public function test_manager_cannot_guess_other_sector_employee_id(): void
    {
        $empB = $this->hire('Alvo', $this->sectorB);
        $this->closeOctober();

        try {
            $this->report($this->managerA, ['employee_id' => $empB->id]);
            $this->fail('Acesso por ID conhecido deveria ser bloqueado.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
        }

        try {
            $this->report($this->managerA, ['sector_id' => $this->sectorB->id]);
            $this->fail('Filtro de setor proibido deveria ser bloqueado.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
        }
    }

    public function test_missing_snapshot_row_is_unavailable_without_hours_or_approval(): void
    {
        $emp = $this->hire('Sem Snapshot', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');

        ClosedPeriodEmployeeSnapshot::query()->delete();
        ClosedPeriod::create([
            'year' => 2026, 'month' => 10, 'status' => 'closed', 'snapshot_version' => 1,
            'closed_by' => $this->admin->id, 'closed_at' => now(),
        ]);

        $report = $this->report($this->admin);
        $this->assertTrue($report['history_unavailable']);
        $row = collect($report['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertNotNull($row);
        $this->assertEquals('Histórico indisponível', $row['review_status_label']);
        $this->assertEquals(0, $row['worked_minutes']);
        $this->assertEquals(0, $row['scheduled_minutes']);
    }

    public function test_report_without_individual_review_attributes_nothing(): void
    {
        $emp = $this->hire('Sem Conferencia', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        MonthlyAttendanceReview::where('employee_id', $emp->id)->delete();

        $row = collect($this->report($this->admin)['rows'])->firstWhere('employee_id', $emp->id);
        $this->assertEquals(AttendanceReviewStatus::Pending, $row['review_status']);
        $this->assertEquals('Aguardando Conferência', $row['review_status_label']);
        $this->assertNull($row['reviewed_by_name']);
        $this->assertNull($row['reviewed_at']);
    }

    public function test_pdf_csv_and_screen_share_identical_totals(): void
    {
        $emp = $this->hire('Totais', $this->sectorA, '01234567890');
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 12:00:00', 'out');
        $this->punch($emp, '2026-10-06 13:00:00', 'in');
        $this->punch($emp, '2026-10-06 19:00:00', 'out');
        $this->closeOctober();

        $report = $this->report($this->admin);
        $totals = $report['totals'];

        $csv = $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=10');
        $csv->assertOk();
        $lines = array_values(array_filter(explode("\r\n", $csv->streamedContent())));
        $this->assertGreaterThan(1, count($lines));

        $sum = ['scheduled' => 0, 'worked' => 0, 'balance' => 0];
        foreach (array_slice($lines, 1) as $line) {
            $fields = str_getcsv($line, ';');
            $sum['scheduled'] += $this->csvHoursToMinutes($fields[9]);
            $sum['worked'] += $this->csvHoursToMinutes($fields[10]);
            $sum['balance'] += $this->csvHoursToMinutes($fields[11]);
        }
        $this->assertEquals($totals['scheduled_minutes'], $sum['scheduled']);
        $this->assertEquals($totals['worked_minutes'], $sum['worked']);
        $this->assertEquals($totals['balance_minutes'], $sum['balance']);

        $pdf = $this->actingAs($this->admin)->get('/admin/reports/consolidated-pdf?year=2026&month=10');
        $pdf->assertOk();
        $pdf->assertSee($report['closed_hash']);
    }

    protected function csvHoursToMinutes(string $value): int
    {
        $value = trim($value, " \t\n\r\0\x0B'\"");
        $sign = str_starts_with($value, '-') ? -1 : 1;
        $value = ltrim($value, '+-');
        [$h, $m] = array_map('intval', explode(':', $value) + [0, 0]);

        return $sign * ($h * 60 + $m);
    }

    public function test_csv_neutralizes_formula_and_preserves_leading_zeros(): void
    {
        $user = User::create([
            'name' => '=CMD|\'/c calc\'!A0', 'email' => 'formula@fidelidade.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);
        $schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
        $emp = Employee::create([
            'user_id' => $user->id, 'sector_id' => $this->sectorA->id,
            'registration_number' => '@MAT+1', 'cpf' => '01234567890',
            'job_title' => '-Cargo', 'legal_regime' => LegalRegime::CLT,
        ]);
        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp, schedule: $schedule,
            effectiveFrom: '2026-09-01', reason: 'Base', assignedBy: $this->admin,
        );
        $this->closeOctober();

        $csv = $this->actingAs($this->admin)->get('/admin/reports/consolidated-csv?year=2026&month=10');
        $csv->assertOk();
        $content = $csv->streamedContent();

        $this->assertStringContainsString('"\'=CMD', $content);
        $this->assertStringContainsString('"\'@MAT+1"', $content);
        $this->assertStringContainsString('"\'-Cargo"', $content);
        $this->assertStringContainsString('"\'01234567890"', $content);
        $this->assertStringNotContainsString('";=CMD', $content);
    }

    public function test_reopen_preserves_versions_and_new_snapshot_has_sector(): void
    {
        $emp = $this->hire('Versoes', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        app(ReopenMonthlyPeriodAction::class)->execute(
            2026, 10, $this->admin, 'Correção autorizada para teste de versões.'
        );
        ReviewForClose::reviewAll(Employee::all(), 2026, 10, $this->admin);
        $reclosed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);

        $this->assertEquals(2, $reclosed->snapshot_version);
        $snap = $reclosed->currentSnapshots()->where('employee_id', $emp->id)->first();
        $this->assertEquals($this->sectorA->id, $snap->employee_snapshot['sector_id']);
        $this->assertEquals(1, ClosedPeriodEmployeeSnapshot::where('closed_period_id', $reclosed->id)->where('version', 1)->count());
    }

    public function test_closed_report_issues_no_live_ptrp_queries(): void
    {
        $emp = $this->hire('Fechado', $this->sectorA);
        $this->punch($emp, '2026-10-06 08:00:00', 'in');
        $this->punch($emp, '2026-10-06 17:00:00', 'out');
        $this->closeOctober();

        $liveTables = [
            'punch_events', 'shift_assignments', 'treatment_events',
            'work_schedule_assignments', 'time_entries', 'calendar_events',
            'labor_rule_profiles', 'work_time_settlement_policies',
        ];
        $hits = [];
        DB::listen(function ($query) use (&$hits, $liveTables) {
            foreach ($liveTables as $table) {
                if (str_contains($query->sql, $table)) {
                    $hits[$table] = ($hits[$table] ?? 0) + 1;
                }
            }
        });

        $this->report($this->admin);

        $this->assertSame([], $hits);
    }
}
