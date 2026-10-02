<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\RejectTreatmentEventAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentEventWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $admin;

    protected User $employeeUser;

    protected Employee $employee;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->admin = User::create([
            'name' => 'Gestor RH',
            'email' => 'rh@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Lucas Ferreira',
            'email' => 'lucas@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-555',
            'cpf' => '11122233344',
        ]);
    }

    public function test_employee_can_request_manual_punch_addition(): void
    {
        $action = app(RequestTreatmentEventAction::class);

        $event = $action->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Esquecimento de registro no retorno do almoço por atendimento urgente',
            requestedBy: $this->employeeUser,
        );

        $this->assertInstanceOf(TreatmentEvent::class, $event);
        $this->assertEquals(TreatmentEventStatus::Pending, $event->status);
        $this->assertEquals($this->employeeUser->id, $event->requested_by);
        $this->assertNull($event->approved_by);
    }

    public function test_employee_cannot_approve_their_own_request(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Tentativa indevida de auto-aprovação',
            requestedBy: $this->employeeUser,
        );

        $this->expectException(\DomainException::class);

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->employeeUser,
        );
    }

    public function test_admin_approves_request_and_engine_includes_it_in_calculation(): void
    {
        $monday = Carbon::parse('2026-10-05');
        $punchAction = app(RecordPunchEventAction::class);

        // Funcionário bateu apenas a entrada às 08:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $punchAction->execute(user: $this->employeeUser, direction: 'in', establishment: $this->establishment);
        Carbon::setTestNow();

        // Antes do tratamento: apenas 1 batida (jornada aberta / incompleta)
        $journeyBefore = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);
        $this->assertTrue($journeyBefore->isIncomplete);
        $this->assertEquals(0, $journeyBefore->workedMinutes);

        // Funcionário solicita inclusão de batida esquecida às 16:00
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 16:00:00'),
            reasonText: 'Saída às 16h esquecida por consulta médica autorizada',
            requestedBy: $this->employeeUser,
        );

        // Admin aprova
        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin,
            note: 'Atestado médico anexado e conferido pelo RH'
        );

        $event->refresh();
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($this->admin->id, $event->approved_by);
        $this->assertNotNull($event->decided_at);

        // Após aprovação: apuração incorpora a batida tratada
        $journeyAfter = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertFalse($journeyAfter->isIncomplete);
        // Trabalhado: 08:00 às 16:00 = 480 min
        $this->assertEquals(480, $journeyAfter->workedMinutes);
        $this->assertEquals(480, $journeyAfter->ordinaryMinutes);
        $this->assertCount(2, $journeyAfter->effectivePunches);
        $this->assertCount(1, $journeyAfter->treatmentNotes);
    }

    public function test_disregarded_punch_is_excluded_from_calculation_while_raw_punch_remains_intact(): void
    {
        $monday = Carbon::parse('2026-10-05');
        $punchAction = app(RecordPunchEventAction::class);

        // Entrada normal 08:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $punch1 = $punchAction->execute(user: $this->employeeUser, direction: 'in', establishment: $this->establishment);

        // Batida duplicada/acidental 08:02
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:02:00'));
        $duplicatePunch = $punchAction->execute(user: $this->employeeUser, direction: 'in', establishment: $this->establishment);

        // Saída normal 17:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 17:00:00'));
        $punch2 = $punchAction->execute(user: $this->employeeUser, direction: 'out', establishment: $this->establishment);
        Carbon::setTestNow();

        // Solicitar desconsideração da batida duplicada
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::PunchDisregarded,
            effectiveAt: $duplicatePunch->occurred_at_local,
            reasonText: 'Batida duplicada registrada por engano 2 minutos após a primeira',
            requestedBy: $this->employeeUser,
            referencePunchId: $duplicatePunch->id,
        );

        app(ApproveTreatmentEventAction::class)->execute(event: $event, approvedBy: $this->admin);

        // O registro em punch_events continua existindo inviolável
        $this->assertDatabaseHas('punch_events', ['id' => $duplicatePunch->id]);

        // A apuração ignora a batida duplicada desconsiderada
        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertFalse($journey->isIncomplete);
        // 08:00 às 17:00 = 540 min trabalhados (9 horas)
        $this->assertEquals(540, $journey->workedMinutes);
        $this->assertCount(2, $journey->effectivePunches);
    }

    public function test_admin_rejects_treatment_with_mandatory_rejection_reason(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Esqueci de registrar',
            requestedBy: $this->employeeUser,
        );

        app(RejectTreatmentEventAction::class)->execute(
            event: $event,
            rejectedBy: $this->admin,
            rejectionReason: 'Não há comprovação de permanência nas dependências da empresa'
        );

        $event->refresh();
        $this->assertEquals(TreatmentEventStatus::Rejected, $event->status);
        $this->assertEquals('Não há comprovação de permanência nas dependências da empresa', $event->rejection_reason);
        $this->assertEquals($this->admin->id, $event->rejected_by);
    }

    public function test_sector_manager_can_approve_treatment_for_employee_in_managed_sector(): void
    {
        $manager = User::create([
            'name' => 'Gestor do Setor',
            'email' => 'gestor@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operacional',
            'manager_id' => $manager->id,
        ]);

        $this->employee->update(['sector_id' => $sector->id]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Batida de saída esquecida',
            requestedBy: $this->employeeUser,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $manager,
            note: 'Aprovado pelo gestor imediato'
        );

        $event->refresh();
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($manager->id, $event->approved_by);
    }

    public function test_manager_cannot_approve_treatment_for_employee_in_another_sector(): void
    {
        $manager = User::create([
            'name' => 'Gestor de Outro Setor',
            'email' => 'gestor2@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Financeiro',
            'manager_id' => $manager->id,
        ]);

        $mySector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operacional',
            'manager_id' => null,
        ]);

        $this->employee->update(['sector_id' => $mySector->id]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Batida esquecida',
            requestedBy: $this->employeeUser,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O Gestor Imediato só possui autorização para aprovar solicitações de colaboradores dos seus setores de gestão.');

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $manager,
        );
    }

    public function test_manager_cannot_approve_their_own_treatment_request_segregation_of_duties(): void
    {
        $manager = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor_operacional@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operacional',
            'manager_id' => $manager->id,
        ]);

        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'GEST-001',
            'cpf' => '99988877766',
        ]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $managerEmployee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Esqueci de bater o ponto na saída da reunião',
            requestedBy: $manager,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Segregação de Funções: Solicitações do próprio Gestor devem ser aprovadas exclusivamente pela Coordenação de RH.');

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $manager,
        );
    }

    public function test_admin_rh_can_approve_manager_treatment_request(): void
    {
        $manager = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor_rh_appr@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operacional',
            'manager_id' => $manager->id,
        ]);

        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'GEST-002',
            'cpf' => '99988877755',
        ]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $managerEmployee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Esqueci de bater o ponto',
            requestedBy: $manager,
        );

        // Admin RH aprova solicitação do próprio gestor
        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin,
            note: 'Aprovado pela coordenação de RH garantindo a segregação de funções'
        );

        $event->refresh();
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($this->admin->id, $event->approved_by);
    }

    public function test_manager_cannot_reject_their_own_treatment_request(): void
    {
        $manager = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor_rej@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operacional',
            'manager_id' => $manager->id,
        ]);

        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'GEST-003',
            'cpf' => '99988877744',
        ]);

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $managerEmployee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 18:00:00'),
            reasonText: 'Esqueci de bater o ponto',
            requestedBy: $manager,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Segregação de Funções: Solicitações do próprio Gestor devem ser decididas exclusivamente pela Coordenação de RH.');

        app(RejectTreatmentEventAction::class)->execute(
            event: $event,
            rejectedBy: $manager,
            rejectionReason: 'Motivo qualquer'
        );
    }
}
