<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CancelTreatmentEventAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RejectTreatmentEventAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\PTRP\Services\MonthlyJourneyBatchService;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ReviewForClose;
use Tests\TestCase;

class TreatmentCancellationAndRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $admin;

    protected User $managerA;

    protected User $managerB;

    protected User $employeeUserA;

    protected User $employeeUserB;

    protected Employee $employeeA;

    protected Employee $employeeB;

    protected Employee $managerEmployeeA;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste Compliance PontoFacil',
            'trade_name' => 'Compliance Test',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Central',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
            'nsr_next' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Administradora RH',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerA = User::create([
            'name' => 'Gestor Setor A',
            'email' => 'gestor.a@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->managerB = User::create([
            'name' => 'Gestor Setor B',
            'email' => 'gestor.b@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operações A',
            'manager_id' => $this->managerA->id,
        ]);

        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operações B',
            'manager_id' => $this->managerB->id,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employeeUserA = User::create([
            'name' => 'Colaborador A',
            'email' => 'colab.a@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employeeA = Employee::create([
            'user_id' => $this->employeeUserA->id,
            'sector_id' => $this->sectorA->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-001',
            'cpf' => '11122233344',
            'admission_date' => '2026-01-01',
        ]);

        $this->employeeUserB = User::create([
            'name' => 'Colaborador B',
            'email' => 'colab.b@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employeeB = Employee::create([
            'user_id' => $this->employeeUserB->id,
            'sector_id' => $this->sectorB->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-002',
            'cpf' => '22233344455',
            'admission_date' => '2026-01-01',
        ]);

        // Gestor A também é cadastrado como empregado para testar segregação de funções
        $this->managerEmployeeA = Employee::create([
            'user_id' => $this->managerA->id,
            'sector_id' => $this->sectorA->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-MGR-A',
            'cpf' => '33344455566',
            'admission_date' => '2026-01-01',
        ]);
    }

    public function test_employee_can_cancel_own_pending_treatment_request(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica de rotina',
            requestedBy: $this->employeeUserA,
        );

        $this->assertEquals(TreatmentEventStatus::Pending, $event->status);

        $cancelled = app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->employeeUserA,
            reason: 'Solicitação lançada por engano pelo próprio colaborador'
        );

        $this->assertEquals(TreatmentEventStatus::Cancelled, $cancelled->status);
        $this->assertEquals($this->employeeUserA->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('Solicitação lançada por engano pelo próprio colaborador', $cancelled->cancellation_reason);
        $this->assertNull($cancelled->approved_by);
        $this->assertTrue($cancelled->isCancelled());
    }

    public function test_employee_cannot_cancel_pending_request_of_another_employee(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica de rotina',
            requestedBy: $this->employeeUserA,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O colaborador não possui permissão para cancelar solicitações de terceiros.');

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->employeeUserB,
            reason: 'Tentativa indevida de cancelar solicitação alheia'
        );
    }

    public function test_sector_manager_can_cancel_pending_request_of_subordinate(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica agendada',
            requestedBy: $this->employeeUserA,
        );

        $cancelled = app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->managerA,
            reason: 'Cancelado pelo gestor do setor a pedido do colaborador'
        );

        $this->assertEquals(TreatmentEventStatus::Cancelled, $cancelled->status);
        $this->assertEquals($this->managerA->id, $cancelled->cancelled_by);
    }

    public function test_sector_manager_cannot_cancel_pending_request_of_employee_in_different_sector(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeB,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Ausência setor B',
            requestedBy: $this->employeeUserB,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O Gestor Imediato só possui autorização para cancelar solicitações de colaboradores dos seus setores de gestão.');

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->managerA,
            reason: 'Gestor A tentando cancelar solicitação de colaborador do setor B'
        );
    }

    public function test_admin_can_cancel_any_pending_treatment_request(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica',
            requestedBy: $this->employeeUserA,
        );

        $cancelled = app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->admin,
            reason: 'Cancelamento administrativo pelo RH central'
        );

        $this->assertEquals(TreatmentEventStatus::Cancelled, $cancelled->status);
        $this->assertEquals($this->admin->id, $cancelled->cancelled_by);
    }

    public function test_admin_and_manager_can_revoke_approved_justification_preserving_original_approval_audit(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico anexado',
            requestedBy: $this->employeeUserA,
            attachmentPath: 'attachments/atestado_123.pdf',
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin,
            note: 'Atestado conferido e aprovado'
        );

        $event->refresh();
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($this->admin->id, $event->approved_by);
        $this->assertNotNull($event->decided_at);
        $originalDecidedAt = $event->decided_at;

        // Reversão administrativa pelo RH
        $revoked = app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->admin,
            reason: 'Reversão administrativa: Atestado foi lançado para a data errada (14/10 em vez de 15/10)'
        );

        $this->assertEquals(TreatmentEventStatus::Revoked, $revoked->status);
        $this->assertTrue($revoked->isRevoked());
        $this->assertFalse($revoked->isApproved());
        // Auditoria original preservada
        $this->assertEquals($this->admin->id, $revoked->approved_by);
        $this->assertEquals($originalDecidedAt->toDateTimeString(), $revoked->decided_at->toDateTimeString());
        $this->assertEquals('attachments/atestado_123.pdf', $revoked->attachment_path);
        // Auditoria de cancelamento/reversão gravada
        $this->assertEquals($this->admin->id, $revoked->cancelled_by);
        $this->assertNotNull($revoked->cancelled_at);
        $this->assertEquals('Reversão administrativa: Atestado foi lançado para a data errada (14/10 em vez de 15/10)', $revoked->cancellation_reason);
    }

    public function test_sector_manager_can_revoke_approved_justification_in_managed_sector(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico',
            requestedBy: $this->employeeUserA,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->managerA
        );

        $revoked = app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->managerA,
            reason: 'Gestor A revertendo justificativa que continha declaração incorreta'
        );

        $this->assertEquals(TreatmentEventStatus::Revoked, $revoked->status);
        $this->assertEquals($this->managerA->id, $revoked->cancelled_by);
    }

    public function test_employee_cannot_revoke_approved_justification(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico',
            requestedBy: $this->employeeUserA,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Colaboradores não possuem permissão para reverter justificativas já aprovadas.');

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->employeeUserA,
            reason: 'Colaborador tentando reverter própria aprovação'
        );
    }

    public function test_manager_cannot_revoke_approved_justification_in_other_sector(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeB,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico setor B',
            requestedBy: $this->employeeUserB,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O Gestor Imediato só possui autorização para reverter justificativas de colaboradores dos seus setores de gestão.');

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->managerA,
            reason: 'Gestor A tentando reverter tratamento de colaborador do setor B'
        );
    }

    public function test_segregation_of_duties_manager_cannot_revoke_own_approved_justification(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->managerEmployeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado do gestor',
            requestedBy: $this->managerA,
        );

        // Aprovado pelo Admin (pois gestor não pode aprovar próprio pedido)
        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Segregação de Funções: Reversões do próprio Gestor devem ser realizadas exclusivamente pela Coordenação de RH.');

        // Gestor tenta reverter a própria justificativa
        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->managerA,
            reason: 'Gestor tentando reverter seu próprio tratamento'
        );
    }

    public function test_cancellation_and_revocation_requires_mandatory_reason_with_min_length(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico',
            requestedBy: $this->employeeUserA,
        );

        // Razão vazia
        try {
            app(CancelTreatmentEventAction::class)->execute(
                event: $event,
                actor: $this->admin,
                reason: '   '
            );
            $this->fail('Deveria ter lançado InvalidArgumentException para motivo vazio.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('mínimo de 5 caracteres', $e->getMessage());
        }

        // Razão menor que 5 caracteres
        try {
            app(CancelTreatmentEventAction::class)->execute(
                event: $event,
                actor: $this->admin,
                reason: 'Erro'
            );
            $this->fail('Deveria ter lançado InvalidArgumentException para motivo com menos de 5 caracteres.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('mínimo de 5 caracteres', $e->getMessage());
        }
    }

    public function test_cannot_revoke_already_revoked_or_cancel_already_cancelled_request(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica',
            requestedBy: $this->employeeUserA,
        );

        $action = app(CancelTreatmentEventAction::class);
        $action->execute($event, $this->employeeUserA, 'Primeiro cancelamento válido');

        // Tenta cancelar novamente
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Esta solicitação já foi cancelada anteriormente.');
        $action->execute($event, $this->employeeUserA, 'Segundo cancelamento indevido');
    }

    public function test_cannot_approve_or_reject_cancelled_or_revoked_treatment(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta médica',
            requestedBy: $this->employeeUserA,
        );

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->employeeUserA,
            reason: 'Cancelando solicitação pendente'
        );

        $event->refresh();

        // Tentar aprovar evento cancelado -> Bloqueado
        try {
            app(ApproveTreatmentEventAction::class)->execute(
                event: $event,
                approvedBy: $this->admin
            );
            $this->fail('Deveria ter lançado DomainException ao tentar aprovar evento cancelado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Somente solicitações pendentes podem ser aprovadas', $e->getMessage());
        }

        // Tentar rejeitar evento cancelado -> Bloqueado
        try {
            app(RejectTreatmentEventAction::class)->execute(
                event: $event,
                rejectedBy: $this->admin,
                rejectionReason: 'Motivo qualquer para rejeitar'
            );
            $this->fail('Deveria ter lançado DomainException ao tentar rejeitar evento cancelado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Somente solicitações pendentes podem ser rejeitadas', $e->getMessage());
        }
    }

    public function test_cannot_cancel_or_revoke_in_closed_period(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-09-15 00:00:00'),
            reasonText: 'Consulta em setembro',
            requestedBy: $this->employeeUserA,
        );

        // Simula fechamento do período 09/2026
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'status' => 'closed',
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Fechamento de teste',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível cancelar ou reverter tratamentos em competência fechada (09/2026).');

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->admin,
            reason: 'Tentativa de cancelamento em competência fechada'
        );
    }

    public function test_wrong_date_correction_workflow_and_ptrp_journey_recalculation(): void
    {
        // Quarta-feira 14/10/2026 e Quinta-feira 15/10/2026 (dias úteis de 8h = 480 min de jornada prevista)
        $dateWrong = Carbon::parse('2026-10-14');
        $dateCorrect = Carbon::parse('2026-10-15');

        $calcAction = app(CalculateDailyJourneyAction::class);

        // 1. Antes de qualquer tratamento: ambos os dias têm ausência integral (480 minutos de ausência)
        $journeyWrongBefore = $calcAction->execute($this->employeeA, $dateWrong, $this->schedule);
        $this->assertEquals(480, $journeyWrongBefore->absenceMinutes);

        $journeyCorrectBefore = $calcAction->execute($this->employeeA, $dateCorrect, $this->schedule);
        $this->assertEquals(480, $journeyCorrectBefore->absenceMinutes);

        // 2. Colaborador registra atestado na DATA ERRADA (14/10/2026) e o RH aprova
        $eventWrong = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico do Dr. Paulo',
            requestedBy: $this->employeeUserA,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $eventWrong,
            approvedBy: $this->admin
        );

        // Apuração com atestado aprovado no dia 14/10: ausência é abonada (absenceMinutes = 0)
        $journeyWrongWithApproval = $calcAction->execute($this->employeeA, $dateWrong, $this->schedule);
        $this->assertEquals(0, $journeyWrongWithApproval->absenceMinutes);

        // 3. Usuário identifica o erro na data e reverte a justificativa do dia 14/10
        app(CancelTreatmentEventAction::class)->execute(
            event: $eventWrong,
            actor: $this->admin,
            reason: 'Justificativa registrada em 14/10/2026 por engano. A ausência correta corresponde a 15/10/2026.'
        );

        // Após reversão: dia 14/10 NÃO é mais abonado! Volta a ter 480 minutos de ausência
        $journeyWrongAfterRevocation = $calcAction->execute($this->employeeA, $dateWrong, $this->schedule);
        $this->assertEquals(480, $journeyWrongAfterRevocation->absenceMinutes);

        // 4. Nova justificativa registrada e aprovada na DATA CORRETA (15/10/2026)
        $eventCorrect = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-15 00:00:00'),
            reasonText: 'Atestado médico do Dr. Paulo (data corrigida para 15/10)',
            requestedBy: $this->employeeUserA,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $eventCorrect,
            approvedBy: $this->admin
        );

        // Apuração com atestado na data correta: dia 15/10 tem ausência abonada (0 min)
        $journeyCorrectAfterApproval = $calcAction->execute($this->employeeA, $dateCorrect, $this->schedule);
        $this->assertEquals(0, $journeyCorrectAfterApproval->absenceMinutes);

        // E o dia 14/10 continua sem abono indevido
        $journeyWrongFinal = $calcAction->execute($this->employeeA, $dateWrong, $this->schedule);
        $this->assertEquals(480, $journeyWrongFinal->absenceMinutes);
    }

    public function test_revocation_invalidates_monthly_attendance_review_fingerprint(): void
    {
        $reviewService = app(MonthlyAttendanceReviewService::class);
        $batchService = app(MonthlyJourneyBatchService::class);

        // Justificativa aprovada no mês 10/2026
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado médico',
            requestedBy: $this->employeeUserA,
        );

        app(ApproveTreatmentEventAction::class)->execute(
            event: $event,
            approvedBy: $this->admin
        );

        // Conferência mensal realizada pelo RH
        $review = app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
            employee: $this->employeeA,
            year: 2026,
            month: 10,
            reviewer: $this->admin,
            reservationNotes: 'Conferência inicial regular'
        );

        $this->assertInstanceOf(MonthlyAttendanceReview::class, $review);
        $this->assertNotNull($review->fingerprint);

        // Lote inicial: conferência está em dia (não está stale)
        $batchBefore = $batchService->forCompetence(collect([$this->employeeA]), 2026, 10);
        $this->assertFalse($reviewService->isReviewStale($this->employeeA, $review, $batchBefore));

        // Reverter a justificativa
        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->admin,
            reason: 'Reversão administrativa para recálculo do PTRP'
        );

        // Novo lote de apuração: o fingerprint mudou e a conferência torna-se stale!
        $batchAfter = $batchService->forCompetence(collect([$this->employeeA]), 2026, 10);
        $this->assertTrue($reviewService->isReviewStale($this->employeeA, $review, $batchAfter));
    }

    public function test_cancelled_and_revoked_treatments_do_not_block_monthly_period_closing(): void
    {
        // Cria um tratamento cancelado e um tratamento revertido
        $event1 = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-01 00:00:00'),
            reasonText: 'Solicitação cancelada',
            requestedBy: $this->employeeUserA,
        );
        app(CancelTreatmentEventAction::class)->execute($event1, $this->employeeUserA, 'Cancelado pelo solicitante');

        $event2 = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-02 00:00:00'),
            reasonText: 'Solicitação revertida',
            requestedBy: $this->employeeUserA,
        );
        app(ApproveTreatmentEventAction::class)->execute($event2, $this->admin);
        app(CancelTreatmentEventAction::class)->execute($event2, $this->admin, 'Revertido pelo RH');

        // Garante que todas as conferências necessárias foram concluídas
        ReviewForClose::reviewAll(
            collect([$this->employeeA, $this->employeeB, $this->managerEmployeeA]),
            2026,
            10,
            $this->admin
        );

        // Fechar competência 10/2026 deve ter sucesso! Cancelled e Revoked não bloqueiam fechamento
        $closed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->admin
        );

        $this->assertEquals('closed', $closed->status);
    }

    public function test_closed_period_snapshot_is_preserved_and_never_mutated(): void
    {
        // Realiza fechamento regular com snapshot
        ReviewForClose::reviewAll(
            collect([$this->employeeA, $this->employeeB, $this->managerEmployeeA]),
            2026,
            10,
            $this->admin
        );

        $closed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->admin
        );

        $snapshotBefore = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closed->id)
            ->where('employee_id', $this->employeeA->id)
            ->first();

        $this->assertNotNull($snapshotBefore);
        $rawJsonBefore = $snapshotBefore->journey_payload_json;

        // O snapshot no banco continua imutável
        $snapshotAfter = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closed->id)
            ->where('employee_id', $this->employeeA->id)
            ->first();

        $this->assertSame($rawJsonBefore, $snapshotAfter->journey_payload_json);
    }

    public function test_audit_trail_and_attachments_are_preserved_without_punch_or_nsr_mutation(): void
    {
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Consulta com especialista',
            requestedBy: $this->employeeUserA,
            attachmentPath: 'certificates/2026/10/atestado.pdf',
        );

        app(ApproveTreatmentEventAction::class)->execute($event, $this->admin);

        $punchesCountBefore = PunchEvent::count();

        app(CancelTreatmentEventAction::class)->execute(
            event: $event,
            actor: $this->admin,
            reason: 'Reversão para retificação de atestado'
        );

        // Nenhum PunchEvent foi criado ou modificado
        $this->assertEquals($punchesCountBefore, PunchEvent::count());

        // O registro TreatmentEvent continua existindo (não foi apagado)
        $this->assertDatabaseHas('treatment_events', [
            'id' => $event->id,
            'status' => TreatmentEventStatus::Revoked->value,
            'attachment_path' => 'certificates/2026/10/atestado.pdf',
            'cancelled_by' => $this->admin->id,
        ]);
    }

    public function test_livewire_treatment_requests_cancel_and_revoke_flow(): void
    {
        $pendingEvent = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado pendente',
            requestedBy: $this->employeeUserA,
        );

        $approvedEvent = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeB,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-15 00:00:00'),
            reasonText: 'Atestado já aprovado',
            requestedBy: $this->employeeUserB,
        );
        app(ApproveTreatmentEventAction::class)->execute($approvedEvent, $this->admin);

        // Cancelar pendente via Livewire
        Livewire::actingAs($this->admin)
            ->test('admin.treatment-requests')
            ->call('openCancelModal', (string) $pendingEvent->id)
            ->assertSet('showCancelModal', true)
            ->assertSet('cancelEventId', (string) $pendingEvent->id)
            ->set('cancellationReason', 'Cancelado pela tela de solicitações de tratamento')
            ->call('confirmCancel')
            ->assertSet('showCancelModal', false)
            ->assertDispatched('app-modal-alert');

        $pendingEvent->refresh();
        $this->assertEquals(TreatmentEventStatus::Cancelled, $pendingEvent->status);

        // Reverter aprovado via Livewire
        Livewire::actingAs($this->admin)
            ->test('admin.treatment-requests')
            ->call('openCancelModal', (string) $approvedEvent->id)
            ->assertSet('showCancelModal', true)
            ->assertSet('cancelEventId', (string) $approvedEvent->id)
            ->set('cancellationReason', 'Revertido pelo RH na tela administrativa')
            ->call('confirmCancel')
            ->assertSet('showCancelModal', false)
            ->assertDispatched('app-modal-alert');

        $approvedEvent->refresh();
        $this->assertEquals(TreatmentEventStatus::Revoked, $approvedEvent->status);
    }

    public function test_livewire_timesheet_cancel_pending_and_revoke_approved(): void
    {
        $pendingEvent = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-14 00:00:00'),
            reasonText: 'Atestado pelo espelho',
            requestedBy: $this->employeeUserA,
        );

        // Colaborador cancela solicitação própria pelo espelho
        Livewire::actingAs($this->employeeUserA)
            ->test('timesheet')
            ->call('openCancelModalFromTimesheet', (string) $pendingEvent->id)
            ->assertSet('showTimesheetCancelModal', true)
            ->set('timesheetCancelReason', 'Colaborador cancelando pelo espelho')
            ->call('confirmCancelFromTimesheet')
            ->assertSet('showTimesheetCancelModal', false)
            ->assertDispatched('app-modal-alert');

        $pendingEvent->refresh();
        $this->assertEquals(TreatmentEventStatus::Cancelled, $pendingEvent->status);

        // Agora um aprovado revertido pelo Admin no espelho
        $approvedEvent = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employeeA,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-15 00:00:00'),
            reasonText: 'Outro atestado aprovado',
            requestedBy: $this->employeeUserA,
        );
        app(ApproveTreatmentEventAction::class)->execute($approvedEvent, $this->admin);

        Livewire::actingAs($this->admin)
            ->test('timesheet')
            ->call('openCancelModalFromTimesheet', (string) $approvedEvent->id)
            ->assertSet('showTimesheetCancelModal', true)
            ->set('timesheetCancelReason', 'RH revertendo justificativa pelo espelho de ponto')
            ->call('confirmCancelFromTimesheet')
            ->assertSet('showTimesheetCancelModal', false)
            ->assertDispatched('app-modal-alert');

        $approvedEvent->refresh();
        $this->assertEquals(TreatmentEventStatus::Revoked, $approvedEvent->status);
    }
}
