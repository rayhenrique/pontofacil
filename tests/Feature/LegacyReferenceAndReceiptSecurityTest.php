<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApplyPointAdjustmentAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\PointAdjustmentPreviewService;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyReferenceAndReceiptSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
    }

    public function test_disregard_punch_event_stores_ulid_in_reference_punch_id(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Um',
            'email' => 'colab1@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-001',
            'cpf' => '12345678901',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $punch = app(RecordPunchEventAction::class)->execute(
            user: $employeeUser,
            direction: 'in',
            establishment: $est
        );

        $action = app(ApplyPointAdjustmentAction::class);
        $event = $action->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: $punch->occurred_at_local->toDateString(),
            reason: 'Marcação acidental duplicada no relógio físico',
            actor: $admin,
            referencePunchId: (string) $punch->id,
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventType::PunchDisregarded, $event->type);
        $this->assertEquals($punch->id, $event->reference_punch_id);
        $this->assertNull($event->reference_time_entry_id);
        $this->assertEquals('punch_event', $event->reference_source);
        $this->assertTrue($event->isForPunchEvent());
        $this->assertFalse($event->isForTimeEntry());
    }

    public function test_disregard_legacy_time_entry_stores_numeric_id_in_reference_time_entry_id(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Legado',
            'email' => 'legado@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-LEG-01',
            'cpf' => '98765432100',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $legacyDate = '2026-03-10';
        $entry = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'in',
            'timestamp' => Carbon::parse("{$legacyDate} 08:00:00"),
            'source' => 'legacy_import',
        ]);

        $action = app(ApplyPointAdjustmentAction::class);
        $event = $action->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: $legacyDate,
            reason: 'Registro histórico duplicado em migração de sistema anterior',
            actor: $admin,
            referencePunchId: (string) $entry->id,
            autoApprove: true
        );

        $this->assertEquals(TreatmentEventType::PunchDisregarded, $event->type);
        $this->assertEquals($entry->id, $event->reference_time_entry_id);
        $this->assertNull($event->reference_punch_id);
        $this->assertEquals('time_entry', $event->reference_source);
        $this->assertTrue($event->isForTimeEntry());
        $this->assertFalse($event->isForPunchEvent());
        $this->assertEquals((string) $entry->id, $event->targetReferenceId());
    }

    public function test_disregard_legacy_time_entry_with_prefixed_id(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Prefixo',
            'email' => 'prefixo@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-LEG-02',
            'cpf' => '98765432102',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $legacyDate = '2026-03-12';
        $entry = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'in',
            'timestamp' => Carbon::parse("{$legacyDate} 08:30:00"),
            'source' => 'legacy_import',
        ]);

        $action = app(ApplyPointAdjustmentAction::class);
        $event = $action->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: $legacyDate,
            reason: 'Registro com prefixo originado da interface de seleção',
            actor: $admin,
            referencePunchId: "legacy:{$entry->id}",
            autoApprove: true
        );

        $this->assertEquals($entry->id, $event->reference_time_entry_id);
        $this->assertEquals('time_entry', $event->reference_source);
    }

    public function test_disregard_rejects_unowned_legacy_entry(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $user1 = User::create([
            'name' => 'Colaborador Um',
            'email' => 'user1@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $user2 = User::create([
            'name' => 'Colaborador Dois',
            'email' => 'user2@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee1 = Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-E1',
            'cpf' => '11111111111',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        // Registro pertencente a user2
        $entryOfUser2 = TimeEntry::create([
            'user_id' => $user2->id,
            'type' => 'in',
            'timestamp' => Carbon::parse('2026-04-10 09:00:00'),
            'source' => 'legacy_import',
        ]);

        $this->expectException(AuthorizationException::class);

        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $employee1,
            actionType: 'disregard_punch',
            date: '2026-04-10',
            reason: 'Tentativa indevida de desconsiderar registro de outro colaborador',
            actor: $admin,
            referencePunchId: (string) $entryOfUser2->id,
            autoApprove: true
        );
    }

    public function test_disregard_rejects_closed_period(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Fechado',
            'email' => 'fechado@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-FECH-01',
            'cpf' => '22222222222',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $entry = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'in',
            'timestamp' => Carbon::parse('2026-01-15 08:00:00'),
            'source' => 'legacy_import',
        ]);

        // Fecha a competência 01/2026
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 1,
            'closed_by' => $admin->id,
            'closed_at' => now(),
            'notes' => 'Fechamento fiscal janeiro 2026',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('encontra-se formalmente fechada');

        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: '2026-01-15',
            reason: 'Tentativa de ajuste em competência fechada',
            actor: $admin,
            referencePunchId: (string) $entry->id,
            autoApprove: true
        );
    }

    public function test_disregard_rejects_duplicate_treatment(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Duplo',
            'email' => 'duplo@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-DUP-01',
            'cpf' => '33333333333',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $entry = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'in',
            'timestamp' => Carbon::parse('2026-05-10 08:00:00'),
            'source' => 'legacy_import',
        ]);

        // Primeira desconsideração
        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: '2026-05-10',
            reason: 'Primeiro pedido de desconsideração legítimo',
            actor: $admin,
            referencePunchId: (string) $entry->id,
            autoApprove: true
        );

        // Segunda tentativa para o mesmo TimeEntry
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('formalmente desconsiderada');

        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: '2026-05-10',
            reason: 'Tentativa duplicada para o mesmo registro legado',
            actor: $admin,
            referencePunchId: (string) $entry->id,
            autoApprove: true
        );
    }

    public function test_ptrp_calculation_and_preview_reflect_disregarded_legacy_entry(): void
    {
        $admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Colaborador Calculo',
            'email' => 'calculo@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Operações',
        ]);

        $schedule = WorkSchedule::create([
            'establishment_id' => $est->id,
            'name' => 'Comum 8h',
            'modality' => 'fixed_weekly',
            'expected_daily_minutes' => 480,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-CALC-01',
            'cpf' => '44444444444',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $dateStr = '2026-06-15';
        $entryIn = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'in',
            'timestamp' => Carbon::parse("{$dateStr} 08:00:00"),
            'source' => 'legacy_import',
        ]);

        $entryOut = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'type' => 'out',
            'timestamp' => Carbon::parse("{$dateStr} 17:00:00"),
            'source' => 'legacy_import',
        ]);

        // Desconsidera a entrada
        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $employee,
            actionType: 'disregard_punch',
            date: $dateStr,
            reason: 'Entrada legada indevida desconsiderada',
            actor: $admin,
            referencePunchId: (string) $entryIn->id,
            autoApprove: true
        );

        // 1. Verifica prévia do dia
        $previewService = app(PointAdjustmentPreviewService::class);
        $preview = $previewService->generatePreview(
            employee: $employee,
            date: $dateStr
        );

        $this->assertNotEmpty($preview['available_punches']);
        $entryInPreview = collect($preview['items'])->firstWhere('id', "legacy:{$entryIn->id}");
        $this->assertNotNull($entryInPreview);
        $this->assertEquals('disregarded', $entryInPreview['type']);
        $this->assertFalse($entryInPreview['effective']);

        // 2. Verifica cálculo da jornada PTRP (CalculateDailyJourneyAction)
        $journey = app(CalculateDailyJourneyAction::class)->execute(
            employee: $employee,
            date: Carbon::parse($dateStr)
        );

        // A entrada desconsiderada foi excluída do cálculo de horas trabalhadas
        $this->assertCount(1, $journey->effectivePunches); // apenas a saída restante
    }

    public function test_receipt_verification_masks_cpf_for_lgpd(): void
    {
        $employeeUser = User::create([
            'name' => 'Carlos Andrade',
            'email' => 'carlos@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sector = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Geral',
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $sector->id,
            'registration_number' => 'MAT-CARLOS',
            'cpf' => '12345678909',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $punch = app(RecordPunchEventAction::class)->execute(
            user: $employeeUser,
            direction: 'in',
            establishment: $est
        );

        $receipt = $punch->receipt;
        $this->assertNotNull($receipt);

        // Acessa a rota pública de verificação
        $response = $this->get("/verificar-comprovante?codigo={$receipt->verification_code}");
        $response->assertStatus(200);

        // CPF deve estar ofuscado nos moldes da LGPD
        $response->assertSee('***.456.789-**');
        $response->assertDontSee('12345678909');
    }

    public function test_receipt_access_authorization_matrix(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin.rh@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $auditor = User::create([
            'name' => 'Auditor Fiscal MTE',
            'email' => 'auditor@mte.gov.br',
            'password' => 'secret123',
            'role' => UserRole::Auditor,
        ]);

        $managerSectorA = User::create([
            'name' => 'Gestor Setor A',
            'email' => 'gestor.a@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $managerSectorB = User::create([
            'name' => 'Gestor Setor B',
            'email' => 'gestor.b@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $workerA = User::create([
            'name' => 'Trabalhador Setor A',
            'email' => 'worker.a@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $workerB = User::create([
            'name' => 'Trabalhador Setor B',
            'email' => 'worker.b@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sectorA = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Setor A',
            'manager_id' => $managerSectorA->id,
        ]);

        $sectorB = Sector::create([
            'company_id' => $est->company_id,
            'establishment_id' => $est->id,
            'name' => 'Setor B',
            'manager_id' => $managerSectorB->id,
        ]);

        Employee::create([
            'user_id' => $workerA->id,
            'sector_id' => $sectorA->id,
            'registration_number' => 'MAT-WA',
            'cpf' => '11111111111',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        Employee::create([
            'user_id' => $workerB->id,
            'sector_id' => $sectorB->id,
            'registration_number' => 'MAT-WB',
            'cpf' => '22222222222',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
        ]);

        $punchA = app(RecordPunchEventAction::class)->execute(user: $workerA, direction: 'in', establishment: $est);
        $receiptA = $punchA->receipt;
        $codeA = $receiptA->verification_code;

        // 1. Trabalhador titular acessa seu próprio comprovante (200)
        $this->actingAs($workerA)->get("/comprovante/{$codeA}/imprimir")->assertStatus(200);
        $this->actingAs($workerA)->get("/comprovante/{$codeA}/pdf")->assertStatus(200);

        // 2. Trabalhador alheio é bloqueado (403)
        $this->actingAs($workerB)->get("/comprovante/{$codeA}/imprimir")->assertStatus(403);
        $this->actingAs($workerB)->get("/comprovante/{$codeA}/pdf")->assertStatus(403);

        // 3. Gestor do setor A acessa comprovante de trabalhador do seu setor (200)
        $this->actingAs($managerSectorA)->get("/comprovante/{$codeA}/imprimir")->assertStatus(200);
        $this->actingAs($managerSectorA)->get("/comprovante/{$codeA}/pdf")->assertStatus(200);

        // 4. Gestor do setor B é bloqueado (403)
        $this->actingAs($managerSectorB)->get("/comprovante/{$codeA}/imprimir")->assertStatus(403);
        $this->actingAs($managerSectorB)->get("/comprovante/{$codeA}/pdf")->assertStatus(403);

        // 5. Administrador tem acesso irrestrito (200)
        $this->actingAs($admin)->get("/comprovante/{$codeA}/imprimir")->assertStatus(200);
        $this->actingAs($admin)->get("/comprovante/{$codeA}/pdf")->assertStatus(200);

        // 6. Auditor tem acesso legal para fiscalização (200)
        $this->actingAs($auditor)->get("/comprovante/{$codeA}/imprimir")->assertStatus(200);
        $this->actingAs($auditor)->get("/comprovante/{$codeA}/pdf")->assertStatus(200);

        // 7. Usuário anônimo acessando por código de alta entropia (portador) recebe o documento (200)
        $this->app['auth']->forgetGuards();
        $anonPrint = $this->get("/comprovante/{$codeA}/imprimir");
        $anonPrint->assertStatus(200);
        $anonPrint->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        $anonPdf = $this->get("/comprovante/{$codeA}/pdf");
        $anonPdf->assertStatus(200);
        $anonPdf->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        // 8. Código inexistente retorna 404
        $this->get('/comprovante/PF-INEXISTENTE-0000/pdf')->assertStatus(404);
        $this->get('/comprovante/PF-INEXISTENTE-0000/imprimir')->assertStatus(404);
    }

    public function test_determine_direction_throws_runtime_exception_on_unexpected_error_and_does_not_mask_as_in(): void
    {
        $user = User::create([
            'name' => 'Teste Direcao',
            'email' => 'direcao@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $invalidEst = new Establishment;
        $invalidEst->timezone = 'Invalid/NonExistent_Timezone_Trigger_Error';

        $action = new DetermineNextPunchDirectionAction;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha técnica inesperada ao determinar a direção');

        $action->execute($user, Carbon::now(), $invalidEst);
    }
}
