<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $adminUser;

    protected User $managerA;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste Escalas',
            'trade_name' => 'Escalas Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede',
            'code' => 'EST-01',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin RH',
            'email' => 'admin@escalas.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerA = User::create([
            'name' => 'Gestor A',
            'email' => 'gestor.a@escalas.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor A',
            'manager_id' => $this->managerA->id,
        ]);

        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor B',
            'manager_id' => null,
        ]);
    }

    protected function createEmployee(string $name, Sector $sector, LegalRegime $regime = LegalRegime::CLT): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).rand(100, 999).'@escalas.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'registration_number' => 'MAT-'.rand(10000, 99999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Servidor',
            'legal_regime' => $regime,
        ]);
    }

    protected function createAdministrativeSchedule(string $name = 'Administrativo Seg-Sex'): WorkSchedule
    {
        return app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => $name,
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00',
            'morning_end' => '12:00',
            'afternoon_start' => '13:00',
            'afternoon_end' => '17:00',
        ], $this->adminUser);
    }

    public function test_administrative_schedule_appears_immediately_in_schedules_tab(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test('admin.shifts-management')
            ->assertSet('activeTab', 'schedules')
            ->call('openCreateScheduleModal', 'administrative')
            ->set('sched_name', 'Administrativo RH 40h')
            ->set('admin_morning_start', '08:00')
            ->set('admin_morning_end', '12:00')
            ->set('admin_afternoon_start', '13:00')
            ->set('admin_afternoon_end', '17:00')
            ->call('saveSchedule')
            ->assertSet('showScheduleModal', false)
            ->assertSet('activeTab', 'schedules')
            ->assertHasNoErrors()
            ->assertSee('Administrativo RH 40h');

        $this->assertDatabaseHas('work_schedules', [
            'name' => 'Administrativo RH 40h',
            'expected_daily_minutes' => 480,
        ]);
    }

    public function test_continuous_shift_12h_and_24h_creation(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);

        $continuous = $action->createContinuous([
            'name' => 'Corrido 30h',
            'days' => [1, 2, 3, 4, 5],
            'start_time' => '07:00',
            'end_time' => '13:00',
            'break_minutes' => 15,
            'deduct_break' => false,
        ], $this->adminUser);
        $this->assertEquals(360, $continuous->expected_daily_minutes);

        $shift12 = $action->createShift12h([
            'name' => 'Plantão 12x36 Diurno',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'break_minutes' => 60,
            'anchor_date' => '2026-10-01',
            'is_night_shift' => false,
        ], $this->adminUser);
        $this->assertEquals(WorkScheduleModality::TwelveByThirtySix, $shift12->modality);

        $shift24 = $action->createShift24h([
            'name' => 'Plantão 24x72',
            'start_time' => '07:00',
            'end_time' => '07:00',
            'break_minutes' => 120,
            'anchor_date' => '2026-10-01',
        ], $this->adminUser);
        $this->assertEquals(WorkScheduleModality::TwentyFourBySeventyTwo, $shift24->modality);
        $this->assertTrue($shift24->requires_legal_authorization);

        $this->actingAs($this->adminUser);
        $component = Livewire::test('admin.shifts-management');
        $component->assertSee('Corrido 30h');
        $component->assertSee('Plantão 12x36 Diurno');
        $component->assertSee('Plantão 24x72');
    }

    public function test_model_without_assignment_shows_zero_linked_and_empty_assignments(): void
    {
        $this->createAdministrativeSchedule('Modelo Sem Vínculo');

        $this->actingAs($this->adminUser);
        Livewire::test('admin.shifts-management')
            ->assertSee('Modelo Sem Vínculo')
            ->call('switchTab', 'assignments')
            ->assertSee('Nenhum servidor com horário atribuído.');

        $this->assertEquals(0, WorkScheduleAssignment::count());
    }

    public function test_model_with_assignments_shows_count_and_lists_rows(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Servidor Vinculado', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            effectiveFrom: '2026-09-01',
            reason: 'Atribuição de teste',
            assignedBy: $this->adminUser,
        );

        $this->actingAs($this->adminUser);
        Livewire::test('admin.shifts-management')
            ->assertSee('Administrativo Seg-Sex')
            ->call('switchTab', 'assignments')
            ->assertSee('Servidor Vinculado')
            ->assertSee('Administrativo Seg-Sex')
            ->assertSee('Atual');
    }

    public function test_assign_individual_and_batch_via_modal(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp1 = $this->createEmployee('Servidor Um', $this->sectorA);
        $emp2 = $this->createEmployee('Servidor Dois', $this->sectorA);

        $this->actingAs($this->adminUser);

        // Individual
        Livewire::test('admin.shifts-management')
            ->call('openAssignModal', $schedule->id, $emp1->id)
            ->assertSet('showAssignModal', true)
            ->set('assign_effective_from', '2026-09-01')
            ->set('assign_reason', 'Atribuição individual')
            ->call('assignSchedule')
            ->assertSet('showAssignModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $emp1->id,
            'work_schedule_id' => $schedule->id,
        ]);

        // Lote
        Livewire::test('admin.shifts-management')
            ->call('openAssignModal', $schedule->id)
            ->set('assign_employee_ids', [$emp1->id, $emp2->id])
            ->set('assign_effective_from', '2026-11-01')
            ->set('assign_reason', 'Atribuição em lote')
            ->call('assignSchedule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $emp2->id,
            'work_schedule_id' => $schedule->id,
        ]);
    }

    public function test_vigency_change_preserves_history(): void
    {
        $scheduleA = $this->createAdministrativeSchedule('Horário Antigo');
        $scheduleB = $this->createAdministrativeSchedule('Horário Novo');
        $emp = $this->createEmployee('Servidor Histórico', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $scheduleA,
            effectiveFrom: '2026-09-01',
            assignedBy: $this->adminUser,
        );

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $scheduleB,
            effectiveFrom: '2026-11-01',
            reason: 'Troca de horário',
            assignedBy: $this->adminUser,
        );

        $rows = WorkScheduleAssignment::where('employee_id', $emp->id)->orderBy('effective_from')->get();
        $this->assertCount(2, $rows);
        // Histórico preservado: atribuição antiga encerrada no dia anterior
        $this->assertEquals('2026-10-31', $rows[0]->effective_until->format('Y-m-d'));
        $this->assertEquals('2026-11-01', $rows[1]->effective_from->format('Y-m-d'));
        $this->assertNull($rows[1]->effective_until);
    }

    public function test_end_assignment_preserves_row_with_closed_status(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Servidor Encerrar', $this->sectorA);

        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            effectiveFrom: '2026-09-01',
            assignedBy: $this->adminUser,
        );

        $this->actingAs($this->adminUser);
        Livewire::test('admin.shifts-management')
            ->call('openEndAssignmentModal', $assignment->id)
            ->assertSet('showEndAssignmentModal', true)
            ->set('end_effective_until', '2026-12-31')
            ->call('endAssignment')
            ->assertHasNoErrors();

        $fresh = $assignment->fresh();
        $this->assertNotNull($fresh);
        $this->assertEquals('2026-12-31', $fresh->effective_until->format('Y-m-d'));
    }

    public function test_overlapping_assignments_are_rejected(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Servidor Conflito', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            effectiveFrom: '2026-09-01',
            effectiveUntil: '2026-09-30',
            assignedBy: $this->adminUser,
        );

        $result = app(ConfigureWorkScheduleAction::class)->applyToEmployees(
            schedule: $schedule,
            employeeIds: [$emp->id],
            effectiveFrom: '2026-09-15',
            effectiveUntil: '2026-10-15',
            actor: $this->adminUser,
        );

        $this->assertEquals(0, $result['applied_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(1, WorkScheduleAssignment::where('employee_id', $emp->id)->count());
    }

    public function test_closed_competence_blocks_new_assignments(): void
    {
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 10,
            'status' => 'closed',
            'snapshot_version' => 1,
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Servidor Fechado', $this->sectorA);

        $result = app(ConfigureWorkScheduleAction::class)->applyToEmployees(
            schedule: $schedule,
            employeeIds: [$emp->id],
            effectiveFrom: '2026-10-15',
            actor: $this->adminUser,
        );

        $this->assertEquals(0, $result['applied_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertDatabaseMissing('work_schedule_assignments', ['employee_id' => $emp->id]);
    }

    public function test_24h_shift_requires_legal_authorization_for_clt(): void
    {
        $shift24 = app(ConfigureWorkScheduleAction::class)->createShift24h([
            'name' => 'Plantão 24x72 Restrito',
            'start_time' => '07:00',
            'end_time' => '07:00',
            'break_minutes' => 120,
            'anchor_date' => '2026-10-01',
        ], $this->adminUser);

        $cltEmployee = $this->createEmployee('Servidor CLT', $this->sectorA, LegalRegime::CLT);

        $result = app(ConfigureWorkScheduleAction::class)->applyToEmployees(
            schedule: $shift24,
            employeeIds: [$cltEmployee->id],
            effectiveFrom: '2026-09-01',
            actor: $this->adminUser,
        );

        $this->assertEquals(0, $result['applied_count']);
        $this->assertNotEmpty($result['errors']);

        // Estatutário sem norma configurada: bloqueado sem presumir norma.
        $statutoryEmployee = $this->createEmployee('Servidor Estatutário', $this->sectorA, LegalRegime::MunicipalStatutory);

        $deniedResult = app(ConfigureWorkScheduleAction::class)->applyToEmployees(
            schedule: $shift24,
            employeeIds: [$statutoryEmployee->id],
            effectiveFrom: '2026-09-01',
            actor: $this->adminUser,
        );

        $this->assertEquals(0, $deniedResult['applied_count']);
        $this->assertNotEmpty($deniedResult['errors']);

        // Com norma municipal configurada pelo RH: permitido.
        LaborRuleProfile::createMunicipalStatutoryProfile(
            'Guarda Municipal (Lei 24x72)',
            'guarda-municipal-24x72',
            'Lei Municipal nº 2.000/2024, art. 5º (plantão 24x72 da Guarda)',
        );

        $okResult = app(ConfigureWorkScheduleAction::class)->applyToEmployees(
            schedule: $shift24,
            employeeIds: [$statutoryEmployee->id],
            effectiveFrom: '2026-09-01',
            actor: $this->adminUser,
        );

        $this->assertEquals(1, $okResult['applied_count']);
    }

    public function test_assigned_schedule_is_used_by_ptrp(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Servidor Apurado', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            effectiveFrom: '2026-09-01',
            assignedBy: $this->adminUser,
        );

        // Segunda-feira com expediente administrativo de 8h
        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $emp->fresh(),
            date: Carbon::parse('2026-10-05'),
        );

        $this->assertTrue($journey->hasSchedule);
        $this->assertEquals(480, $journey->scheduledMinutes);
    }

    public function test_manager_cannot_assign_other_sector_employee(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $empB = $this->createEmployee('Servidor Outro Setor', $this->sectorB);

        $this->actingAs($this->managerA);
        Livewire::test('admin.shifts-management')
            ->call('openAssignModal', $schedule->id, $empB->id)
            ->assertForbidden();
    }

    public function test_deactivate_blocked_when_in_use_and_allowed_when_unused(): void
    {
        $schedule = $this->createAdministrativeSchedule('Horário Em Uso');
        $emp = $this->createEmployee('Servidor Ativo', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            effectiveFrom: Carbon::today()->subDays(5)->format('Y-m-d'),
            assignedBy: $this->adminUser,
        );

        $this->actingAs($this->adminUser);

        // Em uso: bloqueio
        Livewire::test('admin.shifts-management')
            ->call('openDeactivateModal', $schedule->id)
            ->call('toggleScheduleActive')
            ->assertHasErrors(['deactivating_schedule_id']);

        $this->assertTrue($schedule->fresh()->active);

        // Sem uso: permitido
        $unused = $this->createAdministrativeSchedule('Horário Livre');
        Livewire::test('admin.shifts-management')
            ->call('openDeactivateModal', $unused->id)
            ->call('toggleScheduleActive')
            ->assertHasNoErrors();

        $this->assertFalse($unused->fresh()->active);
        // Histórico preservado: nenhum vínculo foi apagado
        $this->assertEquals(1, WorkScheduleAssignment::where('employee_id', $emp->id)->count());
    }

    public function test_shift_generation_and_listing_preserved_with_specific_empty_state(): void
    {
        $schedule12 = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'Plantão 12x36 Teste',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'break_minutes' => 60,
            'anchor_date' => '2026-10-05',
            'is_night_shift' => false,
        ], $this->adminUser);

        $emp = $this->createEmployee('Plantoneiro', $this->sectorA);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $emp,
            schedule: $schedule12,
            effectiveFrom: '2026-10-01',
            assignedBy: $this->adminUser,
        );

        $shifts = app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp,
            schedule: $schedule12,
            anchorDate: '2026-10-05',
            startDate: '2026-10-05',
            endDate: '2026-10-11',
            assignedBy: $this->adminUser,
        );

        $this->assertNotEmpty($shifts);

        $this->actingAs($this->adminUser);
        Livewire::test('admin.shifts-management')
            ->call('switchTab', 'shifts')
            ->assertSet('activeTab', 'shifts')
            ->assertSee('Plantoneiro');

        // Estado vazio específico quando não há plantões
        $empWithoutShifts = $this->createEmployee('Sem Plantão', $this->sectorA);
        Livewire::test('admin.shifts-management')
            ->call('switchTab', 'shifts')
            ->set('selected_employee_id', (string) $empWithoutShifts->id)
            ->assertSee('Nenhum plantão gerado nesta competência.');
    }

    public function test_admin_can_create_and_deactivate_model(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test('admin.shifts-management')
            ->call('openCreateScheduleModal', 'administrative')
            ->assertSet('showScheduleModal', true)
            ->set('sched_name', 'Modelo RH Novo')
            ->call('saveSchedule')
            ->assertSet('showScheduleModal', false)
            ->assertHasNoErrors();

        $schedule = WorkSchedule::where('name', 'Modelo RH Novo')->first();
        $this->assertNotNull($schedule);

        Livewire::test('admin.shifts-management')
            ->call('openDeactivateModal', $schedule->id)
            ->assertSet('showDeactivateModal', true)
            ->call('toggleScheduleActive')
            ->assertHasNoErrors();

        $this->assertFalse($schedule->fresh()->active);
    }

    public function test_manager_cannot_manage_global_models(): void
    {
        $schedule = $this->createAdministrativeSchedule();

        $this->actingAs($this->managerA);

        Livewire::test('admin.shifts-management')
            ->call('openCreateScheduleModal', 'administrative')
            ->assertForbidden();

        Livewire::test('admin.shifts-management')
            ->set('sched_name', 'Modelo Gestor')
            ->call('saveSchedule')
            ->assertForbidden();

        $this->assertDatabaseMissing('work_schedules', ['name' => 'Modelo Gestor']);

        Livewire::test('admin.shifts-management')
            ->call('openDeactivateModal', $schedule->id)
            ->assertForbidden();

        Livewire::test('admin.shifts-management')
            ->set('deactivating_schedule_id', $schedule->id)
            ->call('toggleScheduleActive')
            ->assertForbidden();

        $this->assertTrue($schedule->fresh()->active);
    }

    public function test_manager_views_shared_model_and_assigns_own_employees(): void
    {
        $schedule = $this->createAdministrativeSchedule('Modelo Compartilhado');
        $emp = $this->createEmployee('Servidor Gestor', $this->sectorA);

        $this->actingAs($this->managerA);

        Livewire::test('admin.shifts-management')
            ->assertSee('Modelo Compartilhado');

        Livewire::test('admin.shifts-management')
            ->call('openAssignModal', $schedule->id, $emp->id)
            ->assertSet('showAssignModal', true)
            ->set('assign_effective_from', '2026-09-01')
            ->set('assign_reason', 'Atribuição do gestor')
            ->call('assignSchedule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $emp->id,
            'work_schedule_id' => $schedule->id,
        ]);
    }

    public function test_clt_12x36_allowed_with_standard_profile(): void
    {
        $shift12 = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12 CLT', 'start_time' => '07:00', 'end_time' => '19:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-05', 'is_night_shift' => false,
        ], $this->adminUser);

        $emp = $this->createEmployee('CLT 12x36', $this->sectorA, LegalRegime::CLT);

        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $emp, schedule: $shift12,
            effectiveFrom: '2026-10-01', reason: 'CLT com norma padrão', assignedBy: $this->adminUser,
        );

        $this->assertEquals($shift12->id, $assignment->work_schedule_id);
    }

    public function test_statutory_12x36_without_norm_blocked_with_reason(): void
    {
        $shift12 = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12 Sem Norma', 'start_time' => '07:00', 'end_time' => '19:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-05', 'is_night_shift' => false,
        ], $this->adminUser);

        $emp = $this->createEmployee('Estatutário Sem Norma', $this->sectorA, LegalRegime::MunicipalStatutory);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $emp, schedule: $shift12,
                effectiveFrom: '2026-10-01', reason: 'Tentativa sem norma', assignedBy: $this->adminUser,
            );
            $this->fail('Atribuição sem norma configurada deveria ser bloqueada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('perfil normativo', $e->getMessage());
            $this->assertStringContainsString('registro de ponto segue liberado', $e->getMessage());
        }

        $this->assertDatabaseMissing('work_schedule_assignments', ['employee_id' => $emp->id]);
    }

    public function test_statutory_12x36_with_configured_norm_allowed(): void
    {
        LaborRuleProfile::createMunicipalStatutoryProfile(
            'Norma Municipal 12x36',
            'norma-municipal-12x36',
            'Lei Municipal nº 4.000/2024, art. 3º (plantão 12x36)',
        );

        $shift12 = app(ConfigureWorkScheduleAction::class)->createShift12h([
            'name' => 'P12 Com Norma', 'start_time' => '07:00', 'end_time' => '19:00',
            'break_minutes' => 60, 'anchor_date' => '2026-10-05', 'is_night_shift' => false,
        ], $this->adminUser);

        $emp = $this->createEmployee('Estatutário Com Norma', $this->sectorA, LegalRegime::MunicipalStatutory);

        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $emp, schedule: $shift12,
            effectiveFrom: '2026-10-01', reason: 'Com norma municipal', assignedBy: $this->adminUser,
        );

        $this->assertEquals($shift12->id, $assignment->work_schedule_id);
    }

    public function test_fixed_generation_blocked_via_livewire(): void
    {
        $schedule = $this->createAdministrativeSchedule();
        $emp = $this->createEmployee('Fixo Livewire', $this->sectorA);

        $this->actingAs($this->adminUser);
        Livewire::test('admin.shifts-management')
            ->set('gen_employee_id', (string) $emp->id)
            ->set('gen_schedule_id', (string) $schedule->id)
            ->set('gen_anchor_date', '2026-10-01')
            ->set('gen_start_date', '2026-10-01')
            ->set('gen_end_date', '2026-10-07')
            ->call('generateCyclicShifts')
            ->assertHasErrors(['gen_work_schedule_id']);

        $this->assertEquals(0, ShiftAssignment::where('employee_id', $emp->id)->count());
    }
}
