<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkloadModality;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkScheduleVersioningAndLegalRegimesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $adminUser;

    protected Sector $sector;

    protected WorkSchedule $defaultSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->adminUser = User::create([
            'name' => 'Administrador Geral',
            'email' => 'admin.test@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->sector = Sector::create([
            'name' => 'Secretaria Municipal de Saúde',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->defaultSchedule = WorkSchedule::createDefault40h();
    }

    public function test_clt_employee_registration_and_structured_workload(): void
    {
        $employeeUser = User::create([
            'name' => 'Carlos CLT',
            'email' => 'carlos.clt@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'CLT-1001',
            'cpf' => '11122233344',
            'job_title' => 'Assistente Administrativo',
            'contract_type' => 'CLT Indeterminado',
            'legal_regime' => LegalRegime::CLT,
            'workload' => '40h',
            'daily_workload_minutes' => 480,
            'weekly_workload_minutes' => 2400,
            'monthly_workload_minutes' => 12000,
            'workload_modality' => WorkloadModality::Fixed,
            'is_variable_workload' => false,
        ]);

        $this->assertEquals(LegalRegime::CLT, $employee->legal_regime);
        $this->assertEquals(480, $employee->daily_workload_minutes);
        $this->assertEquals(2400, $employee->weekly_workload_minutes);
        $this->assertEquals('40h semanais (08h diárias)', $employee->getWorkloadDescription());
        $this->assertFalse($employee->is_variable_workload);
    }

    public function test_municipal_statutory_employee_with_normative_data(): void
    {
        $employeeUser = User::create([
            'name' => 'Maria Servidora',
            'email' => 'maria.servidora@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-5002',
            'cpf' => '22233344455',
            'job_title' => 'Enfermeira Chefe',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'workload' => '30h',
            'daily_workload_minutes' => 360,
            'weekly_workload_minutes' => 1800,
            'monthly_workload_minutes' => 9000,
            'normative_jurisdiction' => 'municipal',
            'normative_entity' => 'Prefeitura Municipal de Maceió',
            'normative_reference' => 'Lei Municipal nº 4.567/2012 (Estatuto dos Servidores)',
            'normative_act_number' => 'Decreto nº 1.234/2021',
            'normative_effective_from' => '2021-01-01',
            'normative_validation_status' => 'validated',
        ]);

        $this->assertEquals(LegalRegime::MunicipalStatutory, $employee->legal_regime);
        $this->assertTrue($employee->legal_regime->isStatutory());
        $this->assertTrue($employee->legal_regime->requiresNormativeDetails());
        $this->assertEquals('Prefeitura Municipal de Maceió', $employee->normative_entity);
        $this->assertEquals('validated', $employee->normative_validation_status);
        $this->assertEquals('30h semanais (06h diárias)', $employee->getWorkloadDescription());
    }

    public function test_federal_and_state_statutory_regimes(): void
    {
        $federalUser = User::create([
            'name' => 'Servidor Federal',
            'email' => 'federal@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $federalEmployee = Employee::create([
            'user_id' => $federalUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'FED-001',
            'legal_regime' => LegalRegime::FederalStatutory,
            'normative_reference' => 'Lei Federal 8.112/1990',
        ]);

        $stateUser = User::create([
            'name' => 'Servidor Estadual',
            'email' => 'state@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $stateEmployee = Employee::create([
            'user_id' => $stateUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'EST-001',
            'legal_regime' => LegalRegime::StateStatutory,
            'normative_reference' => 'Lei Estadual 5.247/1991',
        ]);

        $this->assertEquals('Estatutário Federal', $federalEmployee->legal_regime->label());
        $this->assertEquals('Estatutário Estadual', $stateEmployee->legal_regime->label());
        $this->assertTrue($federalEmployee->legal_regime->isStatutory());
        $this->assertTrue($stateEmployee->legal_regime->isStatutory());
    }

    public function test_work_schedule_assignment_preserves_history_and_effective_dates(): void
    {
        $employeeUser = User::create([
            'name' => 'Roberto Escalas',
            'email' => 'roberto.escalas@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'ESC-999',
            'cpf' => '33344455566',
        ]);

        $scheduleA = WorkSchedule::create([
            'name' => 'Escala Administrativa A (40h)',
            'modality' => WorkScheduleModality::FixedWeekly,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => $this->defaultSchedule->schedule_data,
            'active' => true,
        ]);

        $scheduleB = WorkSchedule::create([
            'name' => 'Escala Reduzida B (30h)',
            'modality' => WorkScheduleModality::FixedWeekly,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => $this->defaultSchedule->schedule_data,
            'active' => true,
        ]);

        $action = app(AssignWorkScheduleAction::class);

        // Atribuição 1: Vigente a partir de 2026-01-01
        $assignment1 = $action->execute(
            employee: $employee,
            schedule: $scheduleA,
            effectiveFrom: '2026-01-01',
            reason: 'Lotação inicial',
            assignedBy: $this->adminUser,
        );

        $this->assertEquals('2026-01-01', $assignment1->effective_from->format('Y-m-d'));
        $this->assertNull($assignment1->effective_until);

        // Atribuição 2: Mudança a partir de 2026-06-01
        $assignment2 = $action->execute(
            employee: $employee,
            schedule: $scheduleB,
            effectiveFrom: '2026-06-01',
            reason: 'Redução de jornada autorizada por portaria',
            assignedBy: $this->adminUser,
        );

        // A atribuição 1 anterior deve ter sido automaticamente fechada em 2026-05-31
        $assignment1->refresh();
        $this->assertEquals('2026-05-31', $assignment1->effective_until->format('Y-m-d'));
        $this->assertNull($assignment2->effective_until);

        // Consultas históricas por data
        $resolvedMarch = $employee->getWorkScheduleForDate(Carbon::parse('2026-03-15'));
        $this->assertEquals($scheduleA->id, $resolvedMarch->id);

        $resolvedJuly = $employee->getWorkScheduleForDate(Carbon::parse('2026-07-20'));
        $this->assertEquals($scheduleB->id, $resolvedJuly->id);

        // Total de assignments preservados
        $this->assertEquals(2, $employee->workScheduleAssignments()->count());
    }

    public function test_conflicting_work_schedule_assignments_are_rejected(): void
    {
        $employeeUser = User::create([
            'name' => 'Juliana Conflito',
            'email' => 'juliana.conflito@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'CONF-01',
        ]);

        $action = app(AssignWorkScheduleAction::class);

        // Atribui período fechado 2026-01-01 a 2026-01-31
        $action->execute(
            employee: $employee,
            schedule: $this->defaultSchedule,
            effectiveFrom: '2026-01-01',
            effectiveUntil: '2026-01-31',
            reason: 'Período probatório',
        );

        // Tentar atribuir sobreposição de 2026-01-15 a 2026-02-15 deve falhar
        $this->expectException(ValidationException::class);
        $action->execute(
            employee: $employee,
            schedule: $this->defaultSchedule,
            effectiveFrom: '2026-01-15',
            effectiveUntil: '2026-02-15',
            reason: 'Tentativa conflitante',
        );
    }

    public function test_cannot_assign_schedule_in_closed_period(): void
    {
        // Registrar competência fechada para Outubro/2026
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 10,
            'status' => 'closed',
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $employeeUser = User::create([
            'name' => 'Fabio Fechado',
            'email' => 'fabio.fechado@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'CL-01',
        ]);

        $action = app(AssignWorkScheduleAction::class);

        // Tentar atribuir escala iniciando na competência fechada Outubro/2026
        $this->expectException(ValidationException::class);
        $action->execute(
            employee: $employee,
            schedule: $this->defaultSchedule,
            effectiveFrom: '2026-10-01',
            reason: 'Tentativa retroativa indevida',
        );
    }

    public function test_variable_monthly_workload_configuration(): void
    {
        $employeeUser = User::create([
            'name' => 'Dra. Plantonista',
            'email' => 'dra.plantonista@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MED-12',
            'job_title' => 'Médica Plantonista',
            'is_variable_workload' => true,
            'workload_modality' => WorkloadModality::VariableBySchedule,
        ]);

        $this->assertTrue($employee->is_variable_workload);
        $this->assertEquals('Variável por escala / plantões', $employee->getWorkloadDescription());
    }

    public function test_legacy_employee_without_assignments_uses_fallback_without_invented_regime(): void
    {
        $employeeUser = User::create([
            'name' => 'Antônio Legado',
            'email' => 'antonio.legado@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        // Registro antigo sem legal_regime, com apenas work_schedule_id legado
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->defaultSchedule->id,
            'workload' => '40h',
            'legal_regime' => null, // Não inventar regime jurídico
        ]);

        $this->assertNull($employee->legal_regime);
        $this->assertEquals(0, $employee->workScheduleAssignments()->count());

        // Deve resolver perfeitamente a escala legada
        $resolvedSchedule = $employee->getWorkScheduleForDate(Carbon::today());
        $this->assertNotNull($resolvedSchedule);
        $this->assertEquals($this->defaultSchedule->id, $resolvedSchedule->id);
        $this->assertEquals('40h', $employee->getWorkloadDescription());
    }

    public function test_special_schedule_modality_requires_legal_authorization(): void
    {
        // Escala especial de 24x72 permitida apenas para estatutários municipais (ex: Guarda Municipal)
        $schedule24x72 = WorkSchedule::create([
            'name' => 'Plantão 24×72 Guarda Municipal',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => ['municipal_statutory'],
            'schedule_data' => $this->defaultSchedule->schedule_data,
            'active' => true,
        ]);

        $cltUser = User::create([
            'name' => 'CLT Não Autorizado',
            'email' => 'clt.nao@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $cltEmployee = Employee::create([
            'user_id' => $cltUser->id,
            'sector_id' => $this->sector->id,
            'legal_regime' => LegalRegime::CLT,
        ]);

        $statutoryUser = User::create([
            'name' => 'Guarda Municipal Autorizado',
            'email' => 'guarda@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $statutoryEmployee = Employee::create([
            'user_id' => $statutoryUser->id,
            'sector_id' => $this->sector->id,
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        $action = app(AssignWorkScheduleAction::class);

        // CLT tentando usar 24x72: rejeitado
        try {
            $action->execute(
                employee: $cltEmployee,
                schedule: $schedule24x72,
                effectiveFrom: '2026-11-01',
                reason: 'Atribuição indevida',
            );
            $this->fail('Deveria ter lançado ValidationException para regime não autorizado');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('requer autorização normativa específica', $e->getMessage());
        }

        // Estatutário Municipal usando 24x72: permitido
        $assignment = $action->execute(
            employee: $statutoryEmployee,
            schedule: $schedule24x72,
            effectiveFrom: '2026-11-01',
            reason: 'Atribuição autorizada por lei da Guarda',
        );

        $this->assertEquals($schedule24x72->id, $assignment->work_schedule_id);
    }

    public function test_manager_permission_isolation_for_regimes_and_schedules(): void
    {
        $managerUser = User::create([
            'name' => 'Gestor do Setor',
            'email' => 'gestor.setor@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $mySector = Sector::create([
            'name' => 'Meu Setor de Gestão',
            'manager_id' => $managerUser->id,
        ]);

        $otherSector = Sector::create([
            'name' => 'Outro Setor Não Autorizado',
            'manager_id' => $this->adminUser->id,
        ]);

        // Gestor tentando cadastrar no outro setor via Livewire
        Livewire::actingAs($managerUser)
            ->test('admin.employees')
            ->set('name', 'Novo Tentativa')
            ->set('email', 'tentativa@test.com')
            ->set('cpf', '99988877766')
            ->set('sector_id', (string) $otherSector->id)
            ->set('job_title', 'Auxiliar')
            ->set('contract_type', 'Efetivo')
            ->set('legal_regime', 'clt')
            ->set('workload', '40h')
            ->set('zone', 'Urbana')
            ->call('save')
            ->assertHasErrors(['sector_id']);

        // Gestor cadastrando no seu próprio setor
        Livewire::actingAs($managerUser)
            ->test('admin.employees')
            ->set('name', 'Colaborador Autorizado')
            ->set('email', 'autorizado@test.com')
            ->set('cpf', '88877766655')
            ->set('sector_id', (string) $mySector->id)
            ->set('job_title', 'Técnico de Enfermagem')
            ->set('contract_type', 'Efetivo')
            ->set('legal_regime', 'municipal_statutory')
            ->set('workload', '40h')
            ->set('zone', 'Urbana')
            ->set('work_schedule_id', (string) $this->defaultSchedule->id)
            ->call('save')
            ->assertHasNoErrors();

        $savedEmployee = Employee::where('cpf', '88877766655')->first();
        $this->assertNotNull($savedEmployee);
        $this->assertEquals(LegalRegime::MunicipalStatutory, $savedEmployee->legal_regime);
        $this->assertEquals(1, $savedEmployee->workScheduleAssignments()->count());
    }
}
