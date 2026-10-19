<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApplyPointAdjustmentAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\PTRP\Services\MonthlyJourneyBatchService;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PointAdjustmentUnifiedTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorAlpha;

    protected Sector $sectorBeta;

    protected User $admin;

    protected User $managerAlpha;

    protected User $managerBeta;

    protected User $employeeUser;

    protected Employee $employee;

    protected Employee $managerEmployee;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa PontoFacil Teste',
            'trade_name' => 'PontoFacil Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Maceió',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
            'state' => 'AL',
            'city' => 'Maceió',
            'timezone' => 'America/Maceio',
        ]);

        $this->admin = User::create([
            'name' => 'Coordenador RH Admin',
            'email' => 'rh.admin@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerAlpha = User::create([
            'name' => 'Gestor Setor Alpha',
            'email' => 'gestor.alpha@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->managerBeta = User::create([
            'name' => 'Gestor Setor Beta',
            'email' => 'gestor.beta@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Servidor Operacional',
            'email' => 'servidor@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->sectorAlpha = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Alpha',
            'manager_id' => $this->managerAlpha->id,
        ]);

        $this->sectorBeta = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Beta',
            'manager_id' => $this->managerBeta->id,
        ]);

        $this->schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM 40h',
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00',
            'morning_end' => '12:00',
            'afternoon_start' => '13:00',
            'afternoon_end' => '17:00',
        ], $this->admin);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'sector_id' => $this->sectorAlpha->id,
            'establishment_id' => $this->establishment->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-1001',
            'cpf' => '11122233344',
            'job_title' => 'Analista Técnico',
        ]);

        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerAlpha->id,
            'sector_id' => $this->sectorAlpha->id,
            'establishment_id' => $this->establishment->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-9001',
            'cpf' => '99988877766',
            'job_title' => 'Gestor Alpha',
        ]);
    }

    /**
     * Teste 1: Inclusão de entrada e saída com data e direção explícitas.
     */
    public function test_can_include_manual_entry_and_exit_with_correct_direction(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        // Inclusão de Entrada
        $eventEntry = $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-14',
            reason: 'Esquecimento de registro na entrada da manhã',
            actor: $this->admin,
            time: '08:02',
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventType::ManualPunchAdded, $eventEntry->type);
        $this->assertEquals(TreatmentEventStatus::Approved, $eventEntry->status);
        $this->assertEquals('in', $eventEntry->new_value_json['direction']);
        $this->assertEquals('08:02', $eventEntry->new_value_json['suggested_time']);
        $this->assertEquals($this->admin->id, $eventEntry->requested_by);
        $this->assertEquals($this->admin->id, $eventEntry->approved_by);

        // Inclusão de Saída
        $eventExit = $action->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-14',
            reason: 'Esquecimento de registro na saída da tarde',
            actor: $this->admin,
            time: '17:05',
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventType::ManualPunchAdded, $eventExit->type);
        $this->assertEquals(TreatmentEventStatus::Approved, $eventExit->status);
        $this->assertEquals('out', $eventExit->new_value_json['direction']);
        $this->assertEquals('17:05', $eventExit->new_value_json['suggested_time']);
    }

    /**
     * Teste 2: Ajuste em dia com PunchEvent original do REP-P (não altera NSR original nem cria NSR falso).
     */
    public function test_adjustment_on_day_with_existing_punch_event_preserves_raw_records(): void
    {
        // Registrar batida eletrônica original no REP-P às 08:00
        Carbon::setTestNow('2026-10-14 08:00:00');
        $punchAction = app(RecordPunchEventAction::class);
        $rawPunch = $punchAction->execute(
            user: $this->employeeUser,
            direction: 'in',
            source: 'app'
        );
        Carbon::setTestNow();

        $initialNsr = $rawPunch->nsr;
        $this->assertGreaterThan(0, $initialNsr);

        // Inserir saída manual às 17:00
        $action = app(ApplyPointAdjustmentAction::class);
        $adjEvent = $action->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-14',
            reason: 'Saída esquecida por reunião externa autorizada',
            actor: $this->admin,
            time: '17:00',
            autoApprove: true
        );

        // Verifica que o PunchEvent original permanece intacto
        $rawPunchFresh = PunchEvent::find($rawPunch->id);
        $this->assertEquals($initialNsr, $rawPunchFresh->nsr);

        // Verifica que nenhum PunchEvent adicional foi criado para a batida manual
        $this->assertEquals(1, PunchEvent::where('employee_id', $this->employee->id)->count());

        // Apuração PTRP combina os dois
        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $this->employee,
            date: Carbon::parse('2026-10-14')
        );

        $this->assertCount(2, $journey->effectivePunches);
        $this->assertFalse($journey->isIncomplete);
        $this->assertGreaterThan(0, $journey->workedMinutes);
    }

    /**
     * Teste 3: Ajuste em dia sem registros (inclusão de entrada e saída em dia vazio).
     */
    public function test_adjustment_on_day_without_records(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-15',
            reason: 'Entrada esquecida',
            actor: $this->admin,
            time: '08:00',
            autoApprove: true
        );

        $action->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-15',
            reason: 'Saída esquecida',
            actor: $this->admin,
            time: '12:00',
            autoApprove: true
        );

        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $this->employee,
            date: Carbon::parse('2026-10-15')
        );

        $this->assertEquals(240, $journey->workedMinutes); // 4 horas
    }

    /**
     * Teste 4: Ajuste duplicado no mesmo minuto e direção é rejeitado.
     */
    public function test_duplicate_adjustment_is_rejected(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-16',
            reason: 'Primeiro registro de entrada',
            actor: $this->admin,
            time: '08:15',
            autoApprove: true
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Já existe uma batida manual de entrada aprovada');

        // Tentativa de duplicar às 08:15
        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-16',
            reason: 'Tentativa duplicada de entrada',
            actor: $this->admin,
            time: '08:15',
            autoApprove: false
        );
    }

    /**
     * Teste 5: Desconsideração de marcação eletrônica via PTRP sem apagar o registro bruto.
     */
    public function test_disregard_punch_disregards_in_ptrp_without_deleting_punch_event(): void
    {
        Carbon::setTestNow('2026-10-17 08:00:00');
        $rawPunch = app(RecordPunchEventAction::class)->execute(
            user: $this->employeeUser,
            direction: 'in',
            source: 'app'
        );
        Carbon::setTestNow();

        $action = app(ApplyPointAdjustmentAction::class);
        $disregardEvent = $action->execute(
            employee: $this->employee,
            actionType: 'disregard_punch',
            date: '2026-10-17',
            reason: 'Batida acidental de teste efetuada pelo colaborador',
            actor: $this->admin,
            referencePunchId: (string) $rawPunch->id,
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventType::PunchDisregarded, $disregardEvent->type);
        $this->assertEquals(TreatmentEventStatus::Approved, $disregardEvent->status);

        // O PunchEvent continua existindo no banco de dados!
        $this->assertDatabaseHas('punch_events', ['id' => $rawPunch->id]);

        // Mas o PTRP calcula o dia como sem batidas efetivas
        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $this->employee,
            date: Carbon::parse('2026-10-17')
        );

        $this->assertEquals(0, $journey->workedMinutes);
        $this->assertStringContainsString('desconsiderada por justificativa', implode(' ', $journey->treatmentNotes));
    }

    /**
     * Teste 6: Solicitação do servidor segue aprovação normal (status Pending).
     */
    public function test_employee_request_creates_pending_event_without_auto_approval(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $event = $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-18',
            reason: 'Esqueci de registrar meu ponto ao chegar',
            actor: $this->employeeUser,
            time: '08:05',
            autoApprove: false
        );

        $this->assertEquals(TreatmentEventStatus::Pending, $event->status);
        $this->assertEquals($this->employeeUser->id, $event->requested_by);
        $this->assertNull($event->approved_by);
    }

    /**
     * Teste 7: Servidor comum tentando auto-aprovação é bloqueado com erro de autorização.
     */
    public function test_employee_cannot_auto_approve(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(AuthorizationException::class);

        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-18',
            reason: 'Tentativa indevida de auto-aprovação',
            actor: $this->employeeUser,
            time: '08:05',
            autoApprove: true
        );
    }

    /**
     * Teste 8: Gestor aprova ajuste de colaborador do seu setor com sucesso.
     */
    public function test_manager_can_adjust_and_approve_employee_in_managed_sector(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $event = $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Batida inserida pelo gestor imediato por falha de conectividade',
            actor: $this->managerAlpha,
            time: '08:00',
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($this->managerAlpha->id, $event->approved_by);
    }

    /**
     * Teste 9: Gestor de OUTRO setor é bloqueado de solicitar ou aprovar ajuste.
     */
    public function test_manager_cannot_adjust_employee_from_different_sector(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(AuthorizationException::class);

        // managerBeta é do Setor Beta, mas employee é do Setor Alpha
        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Tentativa de ajuste intersetorial indevida',
            actor: $this->managerBeta,
            time: '08:00',
            autoApprove: false
        );
    }

    /**
     * Teste 10: Segregação de Funções - Gestor NÃO pode auto-aprovar solicitação própria.
     */
    public function test_manager_cannot_auto_approve_their_own_adjustment_request(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Segregação de Funções: O gestor não pode aprovar ajustes em seu próprio espelho');

        // Gestor tentando auto-aprovar seu próprio espelho
        $action->execute(
            employee: $this->managerEmployee,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Tentando auto-aprovar ajuste próprio',
            actor: $this->managerAlpha,
            time: '08:00',
            autoApprove: true
        );
    }

    /**
     * Teste 11: Competência formalmente fechada rejeita qualquer ajuste.
     */
    public function test_adjustment_is_rejected_on_closed_period(): void
    {
        // Fechar competência 09/2026
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'status' => 'closed',
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('encontra-se formalmente fechada e congelada fiscalmente');

        $action->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-09-15',
            reason: 'Tentativa de ajuste retroativo em mês congelado',
            actor: $this->admin,
            time: '08:00',
            autoApprove: true
        );
    }

    /**
     * Teste 12: Invalidação de Fingerprint da Conferência Mensal ao aprovar ajuste.
     */
    public function test_approval_invalidates_previous_attendance_review_fingerprint(): void
    {
        $reviewAction = app(ReviewMonthlyAttendanceAction::class);
        $reviewService = app(MonthlyAttendanceReviewService::class);

        // 1. Homologar conferência mensal de 10/2026 para o colaborador
        $review = $reviewAction->reviewEmployee(
            employee: $this->employee,
            year: 2026,
            month: 10,
            reviewer: $this->admin,
            reservationNotes: 'Conferência inicial regular'
        );

        $this->assertInstanceOf(MonthlyAttendanceReview::class, $review);
        $this->assertNotNull($review->fingerprint);

        // 2. Verificar que inicialmente não é stale
        $batchService = app(MonthlyJourneyBatchService::class);
        $batchBefore = $batchService->forCompetence(collect([$this->employee]), 2026, 10);
        $this->assertFalse($reviewService->isReviewStale($this->employee, $review, $batchBefore));

        // 3. Aplicar e aprovar novo ajuste de ponto no mês
        $adjAction = app(ApplyPointAdjustmentAction::class);
        $adjAction->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-06',
            reason: 'Ajuste posterior à conferência inicial',
            actor: $this->admin,
            time: '08:00',
            autoApprove: true
        );

        // 4. Novo lote de apuração: o fingerprint mudou e a conferência torna-se stale!
        $batchAfter = $batchService->forCompetence(collect([$this->employee]), 2026, 10);
        $this->assertTrue($reviewService->isReviewStale($this->employee, $review, $batchAfter));
    }

    /**
     * Teste 13: Paridade total entre PTRP, Espelho de Ponto e Folha.
     */
    public function test_parity_of_results_between_ptrp_timesheet_and_folha(): void
    {
        $adjAction = app(ApplyPointAdjustmentAction::class);

        // Inserir jornada de 8h em dia útil
        $adjAction->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-07',
            reason: 'Entrada manhã',
            actor: $this->admin,
            time: '08:00',
            autoApprove: true
        );

        $adjAction->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-07',
            reason: 'Saída almoço',
            actor: $this->admin,
            time: '12:00',
            autoApprove: true
        );

        $adjAction->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-07',
            reason: 'Retorno almoço',
            actor: $this->admin,
            time: '13:00',
            autoApprove: true
        );

        $adjAction->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-07',
            reason: 'Saída tarde',
            actor: $this->admin,
            time: '17:00',
            autoApprove: true
        );

        // 1. Apuração diária pelo motor PTRP
        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $this->employee,
            date: Carbon::parse('2026-10-07')
        );
        $this->assertEquals(480, $journey->workedMinutes);

        // 2. Apuração pelo TimesheetJourneyService
        $timesheetService = app(TimesheetJourneyService::class);
        $monthData = $timesheetService->resolveMonthData(
            targetUser: $this->employeeUser,
            year: 2026,
            month: 10
        );

        $dayCalc = $monthData['daysCalculated']['2026-10-07'];
        $this->assertEquals(480, $dayCalc['minutes']);
        $this->assertEquals('08h 00m', $dayCalc['formatted']);
    }

    /**
     * Teste 14: Preservação de registros legados de TimeEntry e TimeAdjustment.
     */
    public function test_legacy_time_entries_and_adjustments_are_preserved_and_not_deleted(): void
    {
        // Criar apontamento legado direto em TimeEntry
        $legacyEntry = TimeEntry::create([
            'user_id' => $this->employeeUser->id,
            'timestamp' => Carbon::parse('2026-10-08 08:00:00'),
            'type' => 'in',
            'is_manual' => true,
        ]);

        $legacyAdj = TimeAdjustment::create([
            'time_entry_id' => $legacyEntry->id,
            'adjusted_by' => $this->admin->id,
            'old_timestamp' => null,
            'new_timestamp' => Carbon::parse('2026-10-08 08:00:00'),
            'justification' => 'Registro legado preservado da versão anterior',
        ]);

        // Executar novo fluxo de ajuste do PTRP para a mesma data (adicionar saída)
        $action = app(ApplyPointAdjustmentAction::class);
        $action->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-08',
            reason: 'Saída adicionada pelo novo fluxo PTRP',
            actor: $this->admin,
            time: '17:00',
            autoApprove: true
        );

        // O registro legado em time_entries e time_adjustments permanece exatamente como estava!
        $this->assertDatabaseHas('time_entries', ['id' => $legacyEntry->id]);
        $this->assertDatabaseHas('time_adjustments', ['id' => $legacyAdj->id]);
    }

    /**
     * Teste 15: Componente Livewire de Ajuste Manual (/admin/adjustment) opera fluxo seguro.
     */
    public function test_livewire_manual_adjustment_component_uses_unified_ptrp_flow(): void
    {
        $this->actingAs($this->admin);

        Livewire::test('admin.manual-adjustment')
            ->assertSee('Ajustes e Correções de Ponto')
            ->assertSee('PTRP • Portaria 671 MTP')
            ->call('startAdjustment', $this->employee->id, '2026-10-20')
            ->assertSet('showAdjustmentModal', true)
            ->assertSet('adjEmployeeId', $this->employee->id)
            ->set('adjAction', 'add_entry')
            ->set('adjTime', '08:30')
            ->set('adjReason', 'Ajuste manual realizado via Livewire unificado')
            ->call('submitPointAdjustment')
            ->assertHasNoErrors()
            ->assertSet('showAdjustmentModal', false);

        // Confirma que foi criado um TreatmentEvent e NENHUM TimeEntry novo foi escrito diretamente
        $this->assertDatabaseHas('treatment_events', [
            'employee_id' => $this->employee->id,
            'type' => TreatmentEventType::ManualPunchAdded->value,
            'status' => TreatmentEventStatus::Approved->value,
        ]);

        $this->assertEquals(0, TimeEntry::count());
    }
}
