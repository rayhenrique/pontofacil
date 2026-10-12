<?php

namespace Tests\Feature;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Domain\WorkSchedule\Actions\SwapShiftsAction;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishmentA;

    protected Establishment $establishmentB;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $adminUser;

    protected User $managerA;

    protected User $managerB;

    protected User $employeeUser;

    protected User $auditorUser;

    protected Employee $empA;

    protected Employee $empB;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste Escopo',
            'trade_name' => 'Escopo Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishmentA = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Unidade A',
            'code' => 'EST-A',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);

        $this->establishmentB = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Unidade B',
            'code' => 'EST-B',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000276',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin RH',
            'email' => 'admin@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerA = User::create([
            'name' => 'Gestor A',
            'email' => 'gestor.a@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->managerB = User::create([
            'name' => 'Gestor B',
            'email' => 'gestor.b@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Servidor Comum',
            'email' => 'servidor@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->auditorUser = User::create([
            'name' => 'Auditor Fiscal',
            'email' => 'auditor@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Auditor,
        ]);

        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishmentA->id,
            'name' => 'Setor A',
            'manager_id' => $this->managerA->id,
        ]);

        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishmentB->id,
            'name' => 'Setor B',
            'manager_id' => $this->managerB->id,
        ]);

        $this->schedule = WorkSchedule::create([
            'name' => 'Administrativo 40h',
            'code' => 'ADM-40',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'schedule_data' => [],
        ]);

        $this->empA = $this->createEmployee('Servidor Setor A', $this->sectorA);
        $this->empB = $this->createEmployee('Servidor Setor B', $this->sectorB);
    }

    protected function createEmployee(string $name, Sector $sector): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).rand(100, 999).'@escopo.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-'.rand(10000, 99999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Servidor',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    protected function createShift(Employee $employee, string $date = '2026-10-06 08:00:00'): ShiftAssignment
    {
        $startLocal = Carbon::parse($date);
        $endLocal = $startLocal->copy()->addHours(8);

        return ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $this->schedule->id,
            'start_at_utc' => $startLocal->copy()->utc(),
            'end_at_utc' => $endLocal->copy()->utc(),
            'start_at_local' => $startLocal,
            'end_at_local' => $endLocal,
            'timezone' => 'America/Sao_Paulo',
            'shift_type' => ShiftType::Regular,
            'origin' => ShiftOrigin::GeneratedCycle,
            'expected_work_minutes' => 480,
            'break_minutes' => 60,
        ]);
    }

    public function test_admin_can_assign_schedule_to_any_employee(): void
    {
        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $this->empB,
            schedule: $this->schedule,
            effectiveFrom: '2026-10-01',
            reason: 'Atribuição admin',
            assignedBy: $this->adminUser,
        );

        $this->assertEquals($this->empB->id, $assignment->employee_id);
    }

    public function test_manager_can_assign_schedule_to_own_sector(): void
    {
        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $this->empA,
            schedule: $this->schedule,
            effectiveFrom: '2026-10-01',
            reason: 'Atribuição gestor',
            assignedBy: $this->managerA,
        );

        $this->assertEquals($this->empA->id, $assignment->employee_id);
    }

    public function test_manager_cannot_assign_schedule_to_other_sector(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $this->empB,
            schedule: $this->schedule,
            effectiveFrom: '2026-10-01',
            reason: 'Tentativa indevida',
            assignedBy: $this->managerA,
        );
    }

    public function test_employee_and_auditor_cannot_assign_schedules(): void
    {
        foreach ([$this->employeeUser, $this->auditorUser] as $actor) {
            try {
                app(AssignWorkScheduleAction::class)->execute(
                    employee: $this->empA,
                    schedule: $this->schedule,
                    effectiveFrom: '2026-10-01',
                    assignedBy: $actor,
                );
                $this->fail('Deveria negar atribuição para perfil sem permissão.');
            } catch (AuthorizationException $e) {
                $this->assertSame('Acesso não autorizado.', $e->getMessage());
            }
        }
    }

    public function test_manager_cannot_generate_shifts_for_other_sector(): void
    {
        $this->expectException(AuthorizationException::class);

        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->empB,
            schedule: $this->schedule,
            anchorDate: '2026-10-01',
            startDate: '2026-10-06',
            endDate: '2026-10-07',
            assignedBy: $this->managerA,
        );
    }

    public function test_swap_requires_both_employees_in_manager_scope(): void
    {
        $shiftA = $this->createShift($this->empA);

        // Gestor A tenta permutar para substituto de outro setor -> negado
        try {
            app(SwapShiftsAction::class)->execute(
                originalShift: $shiftA,
                replacementEmployee: $this->empB,
                reason: 'Permuta entre setores',
                assignedBy: $this->managerA,
            );
            $this->fail('Permuta entre setores deveria ser negada.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
        }

        // Gestor B tenta operar plantão do setor A -> negado
        $shiftA2 = $this->createShift($this->empA, '2026-10-07 08:00:00');
        $empB2 = $this->createEmployee('Substituto B', $this->sectorB);

        try {
            app(SwapShiftsAction::class)->execute(
                originalShift: $shiftA2,
                replacementEmployee: $empB2,
                reason: 'Tentativa fora do setor',
                assignedBy: $this->managerB,
            );
            $this->fail('Gestor de outro setor não deveria permutar.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
        }

        // Admin pode permutar entre setores
        $replacement = $this->createEmployee('Substituto A', $this->sectorA);
        $swapped = app(SwapShiftsAction::class)->execute(
            originalShift: $shiftA,
            replacementEmployee: $replacement,
            reason: 'Permuta autorizada pelo RH',
            assignedBy: $this->adminUser,
        );

        $this->assertEquals($replacement->id, $swapped->employee_id);
    }

    public function test_open_review_modal_only_for_authorized_employee(): void
    {
        // Gestor A abre modal do próprio setor com sucesso
        Livewire::actingAs($this->managerA)
            ->test('admin.monthly-review')
            ->call('openReviewModal', $this->empA->id)
            ->assertHasNoErrors();

        // Gestor A tenta abrir modal de outro setor -> 403 genérico
        Livewire::actingAs($this->managerA)
            ->test('admin.monthly-review')
            ->call('openReviewModal', $this->empB->id)
            ->assertForbidden();
    }

    public function test_review_action_denies_unauthorized_without_leaking_name(): void
    {
        try {
            app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                employee: $this->empB,
                year: 2026,
                month: 10,
                reviewer: $this->managerA,
            );
            $this->fail('Conferência fora do setor deveria ser negada.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
            $this->assertStringNotContainsString('Servidor Setor B', $e->getMessage());
        }
    }

    public function test_employee_and_auditor_cannot_review(): void
    {
        foreach ([$this->employeeUser, $this->auditorUser] as $actor) {
            try {
                app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                    employee: $this->empA,
                    year: 2026,
                    month: 10,
                    reviewer: $actor,
                );
                $this->fail('Perfil sem permissão não deveria conferir.');
            } catch (AuthorizationException $e) {
                $this->assertSame('Acesso não autorizado.', $e->getMessage());
            }
        }
    }

    public function test_report_filters_reject_unauthorized_scope(): void
    {
        $service = app(MonthlyAttendanceReportService::class);

        // Gestor A com setor próprio funciona
        $data = $service->getReportData(2026, 10, $this->managerA, ['sector_id' => $this->sectorA->id]);
        $this->assertNotEmpty($data);

        // Setor de outro gestor -> 403
        foreach ([
            ['sector_id' => $this->sectorB->id],
            ['employee_id' => $this->empB->id],
            ['establishment_id' => $this->establishmentB->id],
        ] as $filters) {
            try {
                $service->getReportData(2026, 10, $this->managerA, $filters);
                $this->fail('Filtro fora do escopo deveria ser negado: '.json_encode($filters));
            } catch (AuthorizationException $e) {
                $this->assertSame('Acesso não autorizado.', $e->getMessage());
            }
        }

        // Servidor e auditor negados
        foreach ([$this->employeeUser, $this->auditorUser] as $actor) {
            try {
                $service->getReportData(2026, 10, $actor, []);
                $this->fail('Perfil sem permissão não deveria gerar relatório.');
            } catch (AuthorizationException $e) {
                $this->assertSame('Acesso não autorizado.', $e->getMessage());
            }
        }
    }

    public function test_manager_cannot_dispatch_report_log_from_other_scope(): void
    {
        $service = app(MonthlyAttendanceReportService::class);

        $foreignLog = MonthlyAttendanceReportLog::create([
            'year' => 2026,
            'month' => 10,
            'sector_id' => $this->sectorB->id,
            'generated_by' => $this->managerB->id,
            'format' => 'csv',
            'is_provisional' => true,
            'filters' => ['sector_id' => $this->sectorB->id],
            'totals_snapshot' => [],
        ]);

        try {
            $service->recordDispatchToPayroll($foreignLog, $this->managerA, 'Tentativa indevida');
            $this->fail('Despacho de outro escopo deveria ser negado.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Acesso não autorizado.', $e->getMessage());
        }

        $this->assertDatabaseMissing('monthly_attendance_report_logs', [
            'id' => $foreignLog->id,
            'dispatched_by' => $this->managerA->id,
        ]);

        // Admin pode despachar
        $service->recordDispatchToPayroll($foreignLog, $this->adminUser, 'Despacho RH');
        $this->assertNotNull($foreignLog->fresh()->dispatched_to_payroll_at);
    }

    public function test_closed_competence_blocks_shift_mutations(): void
    {
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 10,
            'status' => 'closed',
            'snapshot_version' => 1,
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $shift = $this->createShift($this->empA, '2026-10-06 08:00:00');

        // Edição direta via componente bloqueia competência fechada (sem recálculo)
        Livewire::actingAs($this->adminUser)
            ->test('admin.shifts-management')
            ->set('edit_shift_id', $shift->id)
            ->set('edit_start_datetime', '2026-10-06T09:00')
            ->set('edit_end_datetime', '2026-10-06T17:00')
            ->set('edit_reason', 'Ajuste teste')
            ->call('updateShift')
            ->assertHasErrors(['edit_reason']);

        $this->assertEquals(
            '2026-10-06 08:00:00',
            $shift->fresh()->start_at_local->format('Y-m-d H:i:s')
        );

        // Geração em competência fechada bloqueada
        try {
            app(GenerateCyclicShiftsAction::class)->execute(
                employee: $this->empA,
                schedule: $this->schedule,
                anchorDate: '2026-10-01',
                startDate: '2026-10-06',
                endDate: '2026-10-07',
                assignedBy: $this->adminUser,
            );
            $this->fail('Geração em competência fechada deveria ser bloqueada.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // Conferência em competência fechada bloqueada
        try {
            app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                employee: $this->empA,
                year: 2026,
                month: 10,
                reviewer: $this->adminUser,
            );
            $this->fail('Conferência em competência fechada deveria ser bloqueada.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('fechada', $e->getMessage());
        }
    }

    public function test_shifts_livewire_filter_manipulation_is_blocked(): void
    {
        Livewire::actingAs($this->managerA)
            ->test('admin.shifts-management')
            ->set('selected_sector_id', (string) $this->sectorB->id)
            ->assertForbidden();

        Livewire::actingAs($this->managerA)
            ->test('admin.shifts-management')
            ->set('selected_employee_id', (string) $this->empB->id)
            ->assertForbidden();
    }

    public function test_report_controller_rejects_unauthorized_sector_filter(): void
    {
        $this->actingAs($this->managerA)
            ->get('/admin/reports/consolidated-csv?year=2026&month=10&sector_id='.$this->sectorB->id)
            ->assertForbidden();

        $this->actingAs($this->auditorUser)
            ->get('/admin/reports/consolidated-csv?year=2026&month=10')
            ->assertForbidden();

        $this->actingAs($this->employeeUser)
            ->get('/admin/reports/consolidated-csv?year=2026&month=10')
            ->assertForbidden();
    }
}
