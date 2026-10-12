<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Enums\AttendanceReviewStatus;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyAttendanceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected User $adminUser;

    protected User $managerA;

    protected User $managerB;

    protected WorkSchedule $standardSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Prefeitura Municipal Teste',
            'trade_name' => 'Prefeitura Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Paço Municipal',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);

        $this->adminUser = User::create([
            'name' => 'Administrador RH',
            'email' => 'admin@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerA = User::create([
            'name' => 'Gestor Saúde',
            'email' => 'gestor.saude@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->managerB = User::create([
            'name' => 'Gestor Obras',
            'email' => 'gestor.obras@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->sectorA = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Saúde e Vigilância',
            'manager_id' => $this->managerA->id,
        ]);

        $this->sectorB = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Infraestrutura e Obras',
            'manager_id' => $this->managerB->id,
        ]);

        $this->standardSchedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Administrativo Padrão',
            'code' => 'ADM-40',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => [
                'monday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'tuesday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'wednesday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'thursday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'friday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
            ],
        ]);
    }

    protected function createEmployee(string $name, Sector $sector, ?WorkSchedule $schedule = null): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule?->id,
            'registration_number' => 'MAT-'.rand(1000, 9999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Servidor Público',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
            'weekly_workload_minutes' => 2400,
            'admission_date' => '2026-01-01',
        ]);
    }

    protected function registerPunch(Employee $employee, Carbon $time, string $direction): PunchEvent
    {
        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => rand(1000, 999999),
            'direction' => $direction,
            'occurred_at_utc' => $time->copy()->utc(),
            'occurred_at_local' => $time,
            'fiscal_hash' => hash('sha256', (string) rand()),
            'audit_chain_hash' => hash('sha256', (string) rand()),
            'payload_hash' => hash('sha256', (string) rand()),
            'hash_type_7' => hash('sha256', $direction.'-'.$time->timestamp),
            'integrity_hash' => hash('sha256', $direction.'-'.$time->timestamp),
        ]);
    }

    /**
     * 1. Gestores conferem apenas equipes autorizadas; Admin confere qualquer servidor.
     */
    public function test_gestores_conferem_apenas_equipes_autorizadas(): void
    {
        $empA = $this->createEmployee('Servidor Setor A', $this->sectorA, $this->standardSchedule);
        $empB = $this->createEmployee('Servidor Setor B', $this->sectorB, $this->standardSchedule);

        $action = app(ReviewMonthlyAttendanceAction::class);

        // Gestor A confere servidor de sua equipe com sucesso
        $reviewA = $action->reviewEmployee(
            employee: $empA,
            year: 2026,
            month: 10,
            reviewer: $this->managerA,
        );
        $this->assertEquals(AttendanceReviewStatus::Reviewed, $reviewA->status);
        $this->assertEquals($this->managerA->id, $reviewA->reviewed_by);

        // Gestor A tenta conferir servidor do Setor B -> Proibido
        $this->expectException(AuthorizationException::class);
        $action->reviewEmployee(
            employee: $empB,
            year: 2026,
            month: 10,
            reviewer: $this->managerA,
        );
    }

    /**
     * Admin pode conferir colaboradores de qualquer setor.
     */
    public function test_admin_pode_conferir_qualquer_equipe(): void
    {
        $empB = $this->createEmployee('Servidor Setor B', $this->sectorB, $this->standardSchedule);
        $action = app(ReviewMonthlyAttendanceAction::class);

        $review = $action->reviewEmployee(
            employee: $empB,
            year: 2026,
            month: 10,
            reviewer: $this->adminUser,
        );

        $this->assertEquals(AttendanceReviewStatus::Reviewed, $review->status);
        $this->assertEquals($this->adminUser->id, $review->reviewed_by);
    }

    /**
     * 2. Idempotência e Concorrência: Atualizações sucessivas não duplicam registros.
     */
    public function test_conferencia_idempotente_e_concorrente(): void
    {
        $emp = $this->createEmployee('Servidor Idempotente', $this->sectorA, $this->standardSchedule);
        $action = app(ReviewMonthlyAttendanceAction::class);

        $review1 = $action->reviewEmployee($emp, 2026, 10, $this->managerA, 'Primeira checagem');
        $review2 = $action->reviewEmployee($emp, 2026, 10, $this->managerA, 'Segunda checagem atualizada');

        $this->assertEquals($review1->id, $review2->id);
        $this->assertEquals(1, MonthlyAttendanceReview::where('employee_id', $emp->id)->where('year', 2026)->where('month', 10)->count());
        $this->assertEquals('Segunda checagem atualizada', $review2->reservation_notes);
    }

    /**
     * 3. Ausência de escala e plantões: apuração identifica ocorrências e escala de plantão.
     */
    public function test_ausencia_de_escala_e_plantoes(): void
    {
        // Colaborador sem escala
        $empSemEscala = $this->createEmployee('Servidor Sem Escala', $this->sectorA, null);

        $service = app(MonthlyAttendanceReviewService::class);
        $dataSemEscala = $service->resolveEmployeeReviewData($empSemEscala, 2026, 10);

        $this->assertTrue($dataSemEscala['has_unresolved_occurrences']);
        $this->assertTrue(collect($dataSemEscala['occurrences'])->some(fn ($o) => str_contains($o, 'sem escala')));

        $empPlantao = $this->createEmployee('Plantoneiro Samu', $this->sectorA, $this->standardSchedule);
        $startLocal = Carbon::parse('2026-10-01 07:00:00');
        $endLocal = Carbon::parse('2026-10-01 19:00:00');
        ShiftAssignment::create([
            'employee_id' => $empPlantao->id,
            'start_at_utc' => $startLocal->copy()->utc(),
            'end_at_utc' => $endLocal->copy()->utc(),
            'start_at_local' => $startLocal,
            'end_at_local' => $endLocal,
            'timezone' => 'America/Sao_Paulo',
            'shift_type' => ShiftType::Regular,
            'origin' => ShiftOrigin::GeneratedCycle,
            'expected_work_minutes' => 720,
            'break_minutes' => 60,
        ]);

        $dataPlantao = $service->resolveEmployeeReviewData($empPlantao, 2026, 10);
        $this->assertNotNull($dataPlantao['schedule_name']);
    }

    /**
     * 4. Exigência de ressalva/justificativa fundamentada para ocorrências.
     */
    public function test_exigencia_de_ressalva_para_servidor_com_ocorrencia(): void
    {
        $emp = $this->createEmployee('Servidor Com Batida Impar', $this->sectorA, $this->standardSchedule);

        // Registra batida ímpar (incompleta) no dia 01/10/2026
        $this->registerPunch($emp, Carbon::parse('2026-10-01 08:00:00'), 'in');

        $action = app(ReviewMonthlyAttendanceAction::class);

        // Tentar confirmar sem ressalva -> Falha com InvalidArgumentException
        try {
            $action->reviewEmployee($emp, 2026, 10, $this->managerA, null);
            $this->fail('Deveria ter lançado InvalidArgumentException devido à ausência de ressalva.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('É obrigatório fornecer uma ressalva/justificativa', $e->getMessage());
        }

        // Confirmar com ressalva fundamentada -> Sucesso com ReviewedWithReservation
        $review = $action->reviewEmployee(
            employee: $emp,
            year: 2026,
            month: 10,
            reviewer: $this->managerA,
            reservationNotes: 'Esqueceu o crachá no fim do expediente, saída autorizada verbalmente.'
        );

        $this->assertEquals(AttendanceReviewStatus::ReviewedWithReservation, $review->status);
        $this->assertEquals('Esqueceu o crachá no fim do expediente, saída autorizada verbalmente.', $review->reservation_notes);
    }

    /**
     * 5. Conferência em lote apenas de registros elegíveis (sem ocorrências pendentes).
     */
    public function test_conferencia_em_lote_apenas_registros_elegiveis(): void
    {
        $empElegivel1 = $this->createEmployee('Elegível Um', $this->sectorA, $this->standardSchedule);
        $empElegivel2 = $this->createEmployee('Elegível Dois', $this->sectorA, $this->standardSchedule);

        $empComOcorrencia = $this->createEmployee('Com Batida Impar', $this->sectorA, $this->standardSchedule);
        $this->registerPunch($empComOcorrencia, Carbon::parse('2026-10-01 08:00:00'), 'in');

        $action = app(ReviewMonthlyAttendanceAction::class);

        $result = $action->reviewBatch(
            employeeIds: [$empElegivel1->id, $empElegivel2->id, $empComOcorrencia->id],
            year: 2026,
            month: 10,
            reviewer: $this->managerA,
        );

        $this->assertEquals(2, $result['reviewed_count']);
        $this->assertEquals(1, $result['skipped_count']);
        $this->assertContains($empComOcorrencia->id, $result['skipped_ids']);

        $this->assertEquals(AttendanceReviewStatus::Reviewed, MonthlyAttendanceReview::where('employee_id', $empElegivel1->id)->value('status'));
        $this->assertEquals(AttendanceReviewStatus::Reviewed, MonthlyAttendanceReview::where('employee_id', $empElegivel2->id)->value('status'));
        $this->assertNull(MonthlyAttendanceReview::where('employee_id', $empComOcorrencia->id)->first());
    }

    /**
     * 6. Bloqueio de fechamento ordinário se houver conferências obrigatórias ausentes ou ocorrências não resolvidas.
     */
    public function test_bloqueio_de_fechamento_ordinario_por_pendencias(): void
    {
        $emp = $this->createEmployee('Servidor Teste Fechamento', $this->sectorA, $this->standardSchedule);
        $closeAction = app(CloseMonthlyPeriodAction::class);

        // Tentativa de fechar sem ter conferido o servidor -> Bloqueado
        try {
            $closeAction->execute(
                year: 2026,
                month: 10,
                closedBy: $this->adminUser,
                enforceAttendanceReview: true,
            );
            $this->fail('Deveria ter bloqueado o fechamento por falta de conferência.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sem conferência concluída', $e->getMessage());
        }

        // Realizar conferência pelo gestor
        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);

        // Agora fechar -> Sucesso!
        $closedPeriod = $closeAction->execute(
            year: 2026,
            month: 10,
            closedBy: $this->adminUser,
            enforceAttendanceReview: true,
        );

        $this->assertEquals('closed', $closedPeriod->status);

        // As reviews foram vinculadas ao closed_period_id
        $review = MonthlyAttendanceReview::where('employee_id', $emp->id)->where('year', 2026)->where('month', 10)->first();
        $this->assertEquals($closedPeriod->id, $review->closed_period_id);
    }

    /**
     * 7. Consulta da competência fechada utiliza exclusivamente snapshots, nunca recálculo ao vivo.
     */
    public function test_consulta_competencia_fechada_utiliza_exclusivamente_snapshots(): void
    {
        $emp = $this->createEmployee('Servidor Snapshot Imutavel', $this->sectorA, $this->standardSchedule);

        // Batidas no dia 01/10/2026: 08:00 - 12:00, 13:00 - 17:00 (8h = 480 min)
        $day = Carbon::parse('2026-10-01');
        $this->registerPunch($emp, $day->copy()->setTime(8, 0), 'in');
        $this->registerPunch($emp, $day->copy()->setTime(12, 0), 'out');
        $this->registerPunch($emp, $day->copy()->setTime(13, 0), 'in');
        $this->registerPunch($emp, $day->copy()->setTime(17, 0), 'out');

        // Homologar e fechar
        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser, enforceAttendanceReview: true);

        // Consulta através do serviço
        $service = app(MonthlyAttendanceReviewService::class);
        $dataBefore = $service->resolveEmployeeReviewData($emp, 2026, 10);
        $this->assertTrue($dataBefore['is_closed']);
        $this->assertTrue($dataBefore['from_snapshot']);

        // Simular alteração da escala atual no banco DEPOIS do fechamento (ex: muda escala para 20h semanais)
        $newSchedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala Reduzida Posterior',
            'code' => 'ESC-20',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 240,
            'expected_weekly_minutes' => 1200,
            'schedule_data' => [],
        ]);
        $emp->update(['work_schedule_id' => $newSchedule->id]);

        // A consulta da competência FECHADA deve manter os dados congelados do snapshot original
        $dataAfter = $service->resolveEmployeeReviewData($emp, 2026, 10);
        $this->assertEquals($dataBefore['scheduled_minutes'], $dataAfter['scheduled_minutes']);
        $this->assertEquals('Administrativo Padrão', $dataAfter['schedule_name']);
        $this->assertTrue($dataAfter['from_snapshot']);
    }

    /**
     * 8. Reabertura auditada desvincula as reviews e permite nova homologação e fechamento.
     */
    public function test_reabertura_auditada_desvincula_reviews_e_permite_refechamento(): void
    {
        $emp = $this->createEmployee('Servidor Reabertura', $this->sectorA, $this->standardSchedule);
        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerA);

        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser, enforceAttendanceReview: true);
        $this->assertEquals('closed', $closed->status);

        // Tentativa de reabrir sem justificativa suficiente (< 10 caracteres) -> Falha
        try {
            app(ReopenMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser, 'Curta');
            $this->fail('Deveria ter lançado InvalidArgumentException por justificativa curta.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('mínimo 10 caracteres', $e->getMessage());
        }

        // Reabertura com justificativa formal válida
        $reopened = app(ReopenMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            reopenedBy: $this->adminUser,
            reason: 'Reabertura para inclusão de atestado médico retrospectivo deferido pela junta.'
        );

        $this->assertEquals('reopened', $reopened->status);

        // Closed period id foi desvinculado das reviews
        $review = MonthlyAttendanceReview::where('employee_id', $emp->id)->where('year', 2026)->where('month', 10)->first();
        $this->assertNull($review->closed_period_id);
    }

    /**
     * 9. Componente Livewire: RH consegue visualizar lista, conferir e fechar mês sem acessar Banco de Horas.
     */
    public function test_componente_livewire_permite_rh_identificar_pendencias_conferir_e_fechar(): void
    {
        $emp = $this->createEmployee('Servidor Livewire', $this->sectorA, $this->standardSchedule);

        $this->actingAs($this->adminUser);

        Livewire::test('admin.monthly-review', ['year' => 2026, 'month' => 10])
            ->assertSee('Conferência Mensal de Frequência')
            ->assertSee('Servidor Livewire')
            ->assertSee('Aguardando conferência')
            ->call('openReviewModal', $emp->id)
            ->assertSet('showReviewModal', true)
            ->set('reservationNotes', 'Conferência regular aprovada pelo RH.')
            ->call('confirmSingleReview')
            ->assertSet('showReviewModal', false)
            ->call('openCloseModal')
            ->assertSet('showCloseModal', true)
            ->set('closeNotes', 'Mês homologado com sucesso pelo RH.')
            ->call('confirmClosePeriod')
            ->assertSet('showCloseModal', false);

        $period = ClosedPeriod::findForPeriod(2026, 10);
        $this->assertNotNull($period);
        $this->assertEquals('closed', $period->status);
    }
}
