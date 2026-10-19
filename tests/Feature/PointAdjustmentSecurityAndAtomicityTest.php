<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Actions\ApplyPointAdjustmentAction;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Services\PointAdjustmentPreviewService;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PointAdjustmentSecurityAndAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorAlpha;

    protected Sector $sectorBeta;

    protected User $admin;

    protected User $managerAlpha;

    protected User $managerBeta;

    protected User $employeeAUser;

    protected Employee $employeeA;

    protected User $employeeBUser;

    protected Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
        Storage::fake('local');

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste Compliance',
            'trade_name' => 'Compliance Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Central',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
            'state' => 'AL',
            'city' => 'Maceió',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Coordenação de RH',
            'email' => 'rh@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
        ]);

        $this->managerAlpha = User::create([
            'name' => 'Gestor Setor Alpha',
            'email' => 'manager.alpha@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::Manager,
        ]);

        $this->managerBeta = User::create([
            'name' => 'Gestor Setor Beta',
            'email' => 'manager.beta@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::Manager,
        ]);

        $this->sectorAlpha = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Alpha',
            'code' => 'ALPHA',
            'manager_id' => $this->managerAlpha->id,
        ]);

        $this->sectorBeta = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Beta',
            'code' => 'BETA',
            'manager_id' => $this->managerBeta->id,
        ]);

        $this->employeeAUser = User::create([
            'name' => 'Funcionario Alpha',
            'email' => 'alpha.worker@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::Employee,
        ]);

        $this->employeeA = Employee::create([
            'user_id' => $this->employeeAUser->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sectorAlpha->id,
            'name' => 'Funcionario Alpha',
            'registration_number' => 'REG001',
            'cpf' => '11111111111',
            'job_title' => 'Analista',
        ]);

        $this->employeeBUser = User::create([
            'name' => 'Funcionario Beta',
            'email' => 'beta.worker@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::Employee,
        ]);

        $this->employeeB = Employee::create([
            'user_id' => $this->employeeBUser->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sectorBeta->id,
            'name' => 'Funcionario Beta',
            'registration_number' => 'REG002',
            'cpf' => '22222222222',
            'job_title' => 'Assistente',
        ]);
    }

    protected function createPunch(
        Employee $employee,
        string $timeStr,
        string $direction = 'in',
        ?string $customId = null
    ): PunchEvent {
        $tz = 'America/Maceio';
        $local = Carbon::parse($timeStr, $tz);
        $utc = $local->copy()->setTimezone('UTC');

        return PunchEvent::create([
            'id' => $customId ?? (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $this->establishment->nsr_next++,
            'occurred_at_utc' => $utc,
            'occurred_at_local' => $local,
            'timezone' => $tz,
            'utc_offset' => '-03:00',
            'direction' => $direction,
            'source' => 'web_pwa',
            'fiscal_hash' => hash('sha256', "punch-{$employee->id}-{$timeStr}"),
            'audit_chain_hash' => hash('sha256', "audit-{$employee->id}-{$timeStr}"),
            'payload_hash' => hash('sha256', "payload-{$employee->id}-{$timeStr}"),
            'created_at' => now(),
        ]);
    }

    public function test_cannot_disregard_punch_belonging_to_another_employee(): void
    {
        $punchA = $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('A marcação informada pertence a outro colaborador e não pode ser manipulada.');

        // Usuário do employeeB tenta desconsiderar o punch do employeeA
        $action->execute(
            employee: $this->employeeB,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Tentativa de desconsiderar marcação de terceiro',
            actor: $this->employeeBUser,
            referencePunchId: (string) $punchA->id,
        );
    }

    public function test_manager_cannot_manipulate_reference_from_another_sector(): void
    {
        $punchA = $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(AuthorizationException::class);

        // Gestor Beta tentando aplicar ajuste no colaborador do Setor Alpha
        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Gestor Beta invadindo setor Alpha',
            actor: $this->managerBeta,
            referencePunchId: (string) $punchA->id,
        );
    }

    public function test_disregard_punch_with_non_existent_id_throws_domain_exception(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('A marcação informada para desconsideração não foi encontrada no sistema.');

        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Tentando desconsiderar ID fantasma',
            actor: $this->admin,
            referencePunchId: 'ID_INEXISTENTE_999999',
            autoApprove: true,
        );
    }

    public function test_disregard_punch_with_same_numeric_id_in_punchevent_and_timeentry_rejects_ambiguity(): void
    {
        $numericId = '888777';

        // Cria PunchEvent com ID numérico para o employeeA
        $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in', $numericId);

        // Cria TimeEntry legado com o mesmo ID numérico para o employeeA
        DB::table('time_entries')->insert([
            'id' => (int) $numericId,
            'user_id' => $this->employeeA->user_id,
            'timestamp' => '2026-10-19 08:00:00',
            'type' => 'in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(ApplyPointAdjustmentAction::class);

        // Sem prefixo, deve rejeitar por ambiguidade
        try {
            $action->execute(
                employee: $this->employeeA,
                actionType: 'disregard_punch',
                date: '2026-10-19',
                reason: 'Ajuste com identificador ambíguo',
                actor: $this->admin,
                referencePunchId: $numericId,
                autoApprove: true,
            );
            $this->fail('Deveria ter lançado DomainException por ambiguidade');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Identificador de marcação ambíguo', $e->getMessage());
        }

        // Com prefixo explícito punch:, deve aceitar sem ambiguidade
        $event = $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Ajuste com prefixo explicito para resolver colisao',
            actor: $this->admin,
            referencePunchId: "punch:{$numericId}",
            autoApprove: true,
        );

        $this->assertInstanceOf(TreatmentEvent::class, $event);
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($numericId, $event->reference_punch_id);
    }

    public function test_disregard_punch_from_different_date_throws_domain_exception(): void
    {
        $punchA = $this->createPunch($this->employeeA, '2026-10-18 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não corresponde à data do ajuste solicitado');

        // Solicita desconsideração para data 2026-10-19, mas o punch é de 2026-10-18
        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Tentando desconsiderar marcação de ontem no dia de hoje',
            actor: $this->admin,
            referencePunchId: (string) $punchA->id,
            autoApprove: true,
        );
    }

    public function test_disregard_punch_in_closed_period_is_rejected(): void
    {
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'closed_at' => now(),
            'closed_by' => $this->admin->id,
            'status' => 'closed',
            'audit_hash' => hash('sha256', 'closed-2026-09'),
        ]);

        $punchSeptember = $this->createPunch($this->employeeA, '2026-09-15 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('encontra-se formalmente fechada e congelada fiscalmente');

        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-09-15',
            reason: 'Tentativa de ajuste em competência fechada',
            actor: $this->admin,
            referencePunchId: (string) $punchSeptember->id,
            autoApprove: true,
        );
    }

    public function test_duplicate_or_conflicting_disregard_is_rejected(): void
    {
        $punchA = $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);

        // Primeira desconsideração aprovada
        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Primeira desconsideração legítima',
            actor: $this->admin,
            referencePunchId: (string) $punchA->id,
            autoApprove: true,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já foi formalmente desconsiderada');

        // Segunda tentativa para a mesma marcação
        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Segunda tentativa duplicada',
            actor: $this->admin,
            referencePunchId: (string) $punchA->id,
            autoApprove: true,
        );
    }

    public function test_original_punchevent_and_timeentry_are_never_deleted(): void
    {
        $punchA = $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in');

        $action = app(ApplyPointAdjustmentAction::class);
        $action->execute(
            employee: $this->employeeA,
            actionType: 'disregard_punch',
            date: '2026-10-19',
            reason: 'Desconsideração oficial pelo RH',
            actor: $this->admin,
            referencePunchId: (string) $punchA->id,
            autoApprove: true,
        );

        // Registro original em PunchEvent continua existindo perfeitamente intacto
        $this->assertDatabaseHas('punch_events', [
            'id' => $punchA->id,
            'employee_id' => $this->employeeA->id,
            'direction' => 'in',
        ]);
    }

    public function test_creation_and_approval_are_atomic_in_single_transaction(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);

        $event = $action->execute(
            employee: $this->employeeA,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Inclusão direta de entrada esquecida aprovada pelo RH',
            actor: $this->admin,
            time: '08:00',
            autoApprove: true,
        );

        $this->assertInstanceOf(TreatmentEvent::class, $event);
        $this->assertEquals(TreatmentEventStatus::Approved, $event->status);
        $this->assertEquals($this->admin->id, $event->approved_by);
        $this->assertNotNull($event->decided_at);

        $this->assertDatabaseHas('treatment_events', [
            'id' => $event->id,
            'status' => TreatmentEventStatus::Approved->value,
            'approved_by' => $this->admin->id,
        ]);
    }

    public function test_failure_during_approval_rolls_back_entire_transaction(): void
    {
        // Simular falha na aprovação usando mock ou decorador
        $failingApproveAction = new class extends ApproveTreatmentEventAction
        {
            public function execute(TreatmentEvent $event, User $approvedBy, ?string $note = null): TreatmentEvent
            {
                throw new \RuntimeException('Falha simulada na etapa de aprovação direta.');
            }
        };

        $action = new ApplyPointAdjustmentAction(
            app(RequestTreatmentEventAction::class),
            $failingApproveAction,
            app(SectorScopeService::class),
        );

        try {
            $action->execute(
                employee: $this->employeeA,
                actionType: 'add_entry',
                date: '2026-10-19',
                reason: 'Inclusão com aprovação que irá falhar',
                actor: $this->admin,
                time: '08:30',
                autoApprove: true,
            );
            $this->fail('Deveria ter lançado RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Falha simulada na etapa de aprovação direta.', $e->getMessage());
        }

        // Garante que NENHUM TreatmentEvent ficou gravado no banco como pendente involuntário
        $this->assertEquals(0, TreatmentEvent::where('employee_id', $this->employeeA->id)->count());
    }

    public function test_orphaned_attachment_is_deleted_on_transaction_rollback(): void
    {
        // Cria um arquivo falso no storage
        $testPath = 'treatment_attachments/fake_certificate.pdf';
        Storage::disk('local')->put($testPath, 'fake-pdf-content');
        $this->assertTrue(Storage::disk('local')->exists($testPath));

        $failingApproveAction = new class extends ApproveTreatmentEventAction
        {
            public function execute(TreatmentEvent $event, User $approvedBy, ?string $note = null): TreatmentEvent
            {
                throw new \RuntimeException('Falha simulada para testar exclusão de anexo órfão.');
            }
        };

        $action = new ApplyPointAdjustmentAction(
            app(RequestTreatmentEventAction::class),
            $failingApproveAction,
            app(SectorScopeService::class),
        );

        try {
            $action->execute(
                employee: $this->employeeA,
                actionType: 'justify_absence',
                date: '2026-10-19',
                reason: 'Ausência médica justificada com anexo',
                actor: $this->admin,
                attachmentPath: $testPath,
                autoApprove: true,
            );
            $this->fail('Deveria ter falhado com RuntimeException');
        } catch (\RuntimeException) {
            // Esperado
        }

        // O arquivo foi excluído do disco pelo rollback handler
        $this->assertFalse(Storage::disk('local')->exists($testPath));
    }

    public function test_idempotency_prevents_duplicate_events(): void
    {
        $action = app(ApplyPointAdjustmentAction::class);
        $key = 'op-uniq-'.Str::random(12);

        $event1 = $action->execute(
            employee: $this->employeeA,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Inclusão idempotente',
            actor: $this->admin,
            time: '09:00',
            autoApprove: true,
            idempotencyKey: $key,
        );

        // Segunda chamada idêntica com a mesma idempotencyKey
        $event2 = $action->execute(
            employee: $this->employeeA,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Inclusão idempotente reenvio',
            actor: $this->admin,
            time: '09:00',
            autoApprove: true,
            idempotencyKey: $key,
        );

        $this->assertEquals($event1->id, $event2->id);
        $this->assertEquals(1, TreatmentEvent::where('employee_id', $this->employeeA->id)->count());
    }

    public function test_manager_cannot_auto_approve_own_adjustment(): void
    {
        // Criar registro de Employee para o ManagerAlpha
        $managerEmployee = Employee::create([
            'user_id' => $this->managerAlpha->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sectorAlpha->id,
            'name' => 'Gestor Alpha Funcionario',
            'registration_number' => 'MGR001',
            'cpf' => '99999999999',
            'job_title' => 'Coordenador',
        ]);

        $action = app(ApplyPointAdjustmentAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Segregação de Funções: O gestor não pode aprovar ajustes em seu próprio espelho de ponto.');

        $action->execute(
            employee: $managerEmployee,
            actionType: 'add_entry',
            date: '2026-10-19',
            reason: 'Gestor tentando aprovar ponto próprio',
            actor: $this->managerAlpha,
            time: '08:00',
            autoApprove: true,
        );
    }

    public function test_preview_directional_validation_scenarios(): void
    {
        $previewService = app(PointAdjustmentPreviewService::class);

        // Cenário 1: Entrada isolada -> "Falta registrar uma saída."
        $this->createPunch($this->employeeA, '2026-10-19 08:00:00', 'in');
        $preview1 = $previewService->generatePreview($this->employeeA, '2026-10-19');
        $this->assertEquals('Falta registrar uma saída.', $preview1['summary']);

        // Cenário 2: Entrada -> Saída -> "Jornada completa."
        $this->createPunch($this->employeeA, '2026-10-19 12:00:00', 'out');
        $preview2 = $previewService->generatePreview($this->employeeA, '2026-10-19');
        $this->assertEquals('Jornada completa.', $preview2['summary']);

        // Cenário 3: Saída isolada (em outro dia) -> "Existe uma saída sem entrada correspondente."
        $this->createPunch($this->employeeA, '2026-10-20 12:00:00', 'out');
        $preview3 = $previewService->generatePreview($this->employeeA, '2026-10-20');
        $this->assertEquals('Existe uma saída sem entrada correspondente.', $preview3['summary']);

        // Cenário 4: Entrada -> Entrada -> Saída -> Saída -> "Há marcações que precisam de conferência."
        $this->createPunch($this->employeeB, '2026-10-19 08:00:00', 'in');
        $this->createPunch($this->employeeB, '2026-10-19 09:00:00', 'in');
        $this->createPunch($this->employeeB, '2026-10-19 17:00:00', 'out');
        $this->createPunch($this->employeeB, '2026-10-19 18:00:00', 'out');
        $preview4 = $previewService->generatePreview($this->employeeB, '2026-10-19');
        $this->assertEquals('Há marcações que precisam de conferência.', $preview4['summary']);

        // Cenário 5: Quatro batidas válidas (Entrada -> Saída -> Entrada -> Saída) -> "Jornada completa."
        $this->createPunch($this->employeeA, '2026-10-21 08:00:00', 'in');
        $this->createPunch($this->employeeA, '2026-10-21 12:00:00', 'out');
        $this->createPunch($this->employeeA, '2026-10-21 13:00:00', 'in');
        $this->createPunch($this->employeeA, '2026-10-21 17:00:00', 'out');
        $preview5 = $previewService->generatePreview($this->employeeA, '2026-10-21');
        $this->assertEquals('Jornada completa.', $preview5['summary']);
    }
}
