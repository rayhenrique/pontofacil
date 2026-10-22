<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApplyPointAdjustmentAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CancelTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EffectivePunchSequenceResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $adminUser;

    protected User $workerUser;

    protected Employee $employee;

    protected WorkSchedule $schedule;

    protected CalculateDailyJourneyAction $calculateAction;

    protected DetermineNextPunchDirectionAction $determineDirectionAction;

    protected TimesheetJourneyService $timesheetService;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA TESTE PTRP LTDA',
            'trade_name' => 'PontoFacil Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.5.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Central',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1000,
        ]);

        $this->sector = Sector::create([
            'name' => 'Administrativo',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Administrador RH',
            'email' => 'admin@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->workerUser = User::create([
            'name' => 'Carlos Colaborador',
            'email' => 'carlos@pontofacil.test',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->workerUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-1001',
            'cpf' => '12345678901',
            'job_title' => 'Analista Administrativo',
            'legal_regime' => LegalRegime::CLT,
            'work_schedule_id' => $this->schedule->id,
        ]);

        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Geral',
            'company_id' => $this->company->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Acordo Coletivo de Trabalho',
            'effective_from' => Carbon::parse('2020-01-01'),
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $this->calculateAction = app(CalculateDailyJourneyAction::class);
        $this->determineDirectionAction = app(DetermineNextPunchDirectionAction::class);
        $this->timesheetService = app(TimesheetJourneyService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper para registrar PunchEvent com NSR monotônico e timezone correto.
     */
    protected function recordPunch(string $direction, string $localTime): PunchEvent
    {
        Carbon::setTestNow(Carbon::parse($localTime, 'America/Maceio'));

        $punch = app(RecordPunchEventAction::class)->execute(
            user: $this->workerUser,
            direction: $direction,
            establishment: $this->establishment,
        );

        Carbon::setTestNow();

        return $punch;
    }

    /**
     * Cenário Principal Reproduzido:
     * - Colaborador esquece a entrada das 08:00.
     * - Registra saída às 12:52:38 (sistema grava direção bruta 'in' por ser primeira do dia).
     * - Registra retorno às 13:00:31 (sistema grava direção bruta 'out').
     * - RH aprova inclusão manual da entrada das 08:00.
     *
     * Critério de aceite:
     * - 08:00: Entrada manual aprovada.
     * - 12:52: Saída efetiva (reclassificada).
     * - 13:00: Entrada efetiva (reclassificada).
     * - Cômputo: 292 minutos trabalhados (04h 52m) + 8 minutos de intervalo.
     * - Sem erro de sequência ambígua.
     * - Registros brutos em PunchEvent intactos (direção fiscal original preservada).
     */
    public function test_reproduced_problem_0800_in_1252_out_1300_in_and_journey_calculation(): void
    {
        $date = Carbon::parse('2026-10-22', 'America/Maceio');

        // 1. Registros físicos no REP-P
        $punch1252 = $this->recordPunch('in', '2026-10-22 12:52:38');
        $punch1300 = $this->recordPunch('out', '2026-10-22 13:00:31');

        $this->assertSame('in', $punch1252->direction);
        $this->assertSame('out', $punch1300->direction);

        // Antes do ajuste: apuração sem a entrada das 08:00
        $beforeJourney = $this->calculateAction->execute($this->employee, $date);
        $this->assertSame(7, $beforeJourney->workedMinutes); // Apenas 7 minutos entre 12:52 e 13:00

        // 2. RH aprova inclusão manual da entrada das 08:00
        $adjustmentAction = app(ApplyPointAdjustmentAction::class);
        $treatment = $adjustmentAction->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Esquecimento de registro no início da jornada (trabalho externo)',
            actor: $this->adminUser,
            time: '08:00',
            reasonCategory: 'external_work',
            autoApprove: true,
        );

        $this->assertSame(TreatmentEventStatus::Approved, $treatment->status);
        $this->assertSame('in', $treatment->new_value_json['direction']);

        // 3. Apuração após a aprovação do ajuste
        $afterJourney = $this->calculateAction->execute($this->employee, $date);

        // Verificar minutos trabalhados: 08:00 às 12:52 = 292 minutos (04h 52m)
        $this->assertSame(292, $afterJourney->workedMinutes);
        $this->assertSame(7, $afterJourney->breakMinutes); // Intervalo das 12:52:38 às 13:00:31 (7 minutos inteiros)
        $this->assertCount(3, $afterJourney->effectivePunches);

        // Não pode conter a nota de sequência ambígua
        $hasAmbiguousNote = collect($afterJourney->treatmentNotes)
            ->contains(fn ($note) => str_contains($note, 'Sequência ambígua'));
        $this->assertFalse($hasAmbiguousNote, 'A apuração não deve conter aviso de sequência ambígua após o recálculo.');

        // 4. Inspecionar a sequência de batidas efetivas
        $punches = $afterJourney->effectivePunches;

        // 08:00 — Entrada manual
        $this->assertSame('08:00', $punches[0]['time']);
        $this->assertSame('in', $punches[0]['effective_direction']);
        $this->assertTrue($punches[0]['is_manual']);

        // 12:52 — Saída efetiva (reclassificada)
        $this->assertSame('12:52', $punches[1]['time']);
        $this->assertSame('in', $punches[1]['original_direction'], 'Direção original fiscal deve ser preservada como in');
        $this->assertSame('out', $punches[1]['effective_direction'], 'Direção efetiva deve ser recalculada como out');
        $this->assertTrue($punches[1]['is_direction_reclassified']);
        $this->assertNotNull($punches[1]['reclassification_reason']);

        // 13:00 — Entrada efetiva (reclassificada)
        $this->assertSame('13:00', $punches[2]['time']);
        $this->assertSame('out', $punches[2]['original_direction'], 'Direção original fiscal deve ser preservada como out');
        $this->assertSame('in', $punches[2]['effective_direction'], 'Direção efetiva deve ser recalculada como in');
        $this->assertTrue($punches[2]['is_direction_reclassified']);
        $this->assertNotNull($punches[2]['reclassification_reason']);

        // 5. Preservação estrita dos registros originais no banco de dados (zero mutação fiscal)
        $freshPunch1252 = PunchEvent::find($punch1252->id);
        $freshPunch1300 = PunchEvent::find($punch1300->id);
        $this->assertSame('in', $freshPunch1252->direction);
        $this->assertSame('out', $freshPunch1300->direction);
        $this->assertSame($punch1252->nsr, $freshPunch1252->nsr);
        $this->assertSame($punch1300->nsr, $freshPunch1300->nsr);
    }

    /**
     * Cenário: Determinação da próxima batida após aprovação retroativa.
     * Após o ajuste das 08:00 (in), 12:52 (out) e 13:00 (in):
     * A próxima batida registrada no final do expediente (17:00) deve ser determinada automaticamente como 'out'.
     */
    public function test_subsequent_punch_direction_determination_after_retroactive_approval(): void
    {
        $this->recordPunch('in', '2026-10-22 12:52:38');
        $this->recordPunch('out', '2026-10-22 13:00:31');

        // RH aprova 08:00
        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Inclusão da entrada esquecida',
            actor: $this->adminUser,
            time: '08:00',
            autoApprove: true,
        );

        // Ao bater ponto às 17:00, o sistema deve determinar 'out' (pois a última efetiva foi entrada às 13:00)
        $targetTime = Carbon::parse('2026-10-22 17:00:00', 'America/Maceio');
        $nextDir = $this->determineDirectionAction->execute($this->workerUser, $targetTime, $this->establishment);

        $this->assertSame('out', $nextDir, 'A direção da batida das 17:00 deve ser Saída após o retorno efetivo das 13:00.');

        // Registrar o ponto das 17:00 com a direção determinada
        $punch1700 = $this->recordPunch($nextDir, '2026-10-22 17:00:00');
        $this->assertSame('out', $punch1700->direction);

        // Apuração com o dia completo
        $date = Carbon::parse('2026-10-22', 'America/Maceio');
        $journey = $this->calculateAction->execute($this->employee, $date);

        // 08:00 às 12:52 (292m) + 13:00 às 17:00 (240m) = 532 minutos (08h 52m)
        $this->assertSame(532, $journey->workedMinutes);
        $this->assertSame(7, $journey->breakMinutes);
        $this->assertFalse($journey->isIncomplete);
        $this->assertSame(480, $journey->ordinaryMinutes);
        $this->assertSame(52, $journey->overtimeMinutes); // 52 minutos de hora extra
    }

    /**
     * Cenário: Pré-aprovação da batida manual antes das batidas seguintes do dia.
     * Se o RH aprova a inclusão das 08:00 às 10:00 da manhã:
     * A batida de saída do almoço (12:52) já deve ser sugerida como 'out'.
     */
    public function test_pre_approval_scenario_suggests_out_at_lunch(): void
    {
        // 1. Inclusão manual pré-aprovada às 08:00
        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Registro antecipado de trabalho externo matutino',
            actor: $this->adminUser,
            time: '08:00',
            autoApprove: true,
        );

        // 2. Colaborador vai bater o almoço às 12:52
        $lunchTime = Carbon::parse('2026-10-22 12:52:00', 'America/Maceio');
        $directionAtLunch = $this->determineDirectionAction->execute($this->workerUser, $lunchTime, $this->establishment);

        $this->assertSame('out', $directionAtLunch, 'Com a entrada pré-aprovada às 08:00, a próxima batida deve ser Saída.');
    }

    /**
     * Cenário: Revogação da inclusão manual reverte os sentidos efetivos.
     * Se a inclusão de 08:00 for revogada pelo RH, 12:52 e 13:00 voltam ao estado original.
     */
    public function test_revocation_of_manual_punch_reverts_effective_directions(): void
    {
        $date = Carbon::parse('2026-10-22', 'America/Maceio');

        $this->recordPunch('in', '2026-10-22 12:52:38');
        $this->recordPunch('out', '2026-10-22 13:00:31');

        $treatment = app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Inclusão provisória',
            actor: $this->adminUser,
            time: '08:00',
            autoApprove: true,
        );

        // Confirmar apuração com ajuste
        $journeyWithTreatment = $this->calculateAction->execute($this->employee, $date);
        $this->assertSame(292, $journeyWithTreatment->workedMinutes);

        // Reverter a justificativa
        app(CancelTreatmentEventAction::class)->execute(
            event: $treatment,
            actor: $this->adminUser,
            reason: 'Solicitação revogada por duplicidade indevida',
        );

        $treatment->refresh();
        $this->assertSame(TreatmentEventStatus::Revoked, $treatment->status);

        // Apuração após a revogação: deve voltar a apresentar apenas 7 minutos e marcações originais
        $journeyAfterRevocation = $this->calculateAction->execute($this->employee, $date);
        $this->assertSame(7, $journeyAfterRevocation->workedMinutes);

        $punches = $journeyAfterRevocation->effectivePunches;
        $this->assertCount(2, $punches);
        $this->assertSame('in', $punches[0]['effective_direction']);
        $this->assertSame('out', $punches[1]['effective_direction']);
        $this->assertFalse($punches[0]['is_direction_reclassified']);
        $this->assertFalse($punches[1]['is_direction_reclassified']);
    }

    /**
     * Cenário: Saída esquecida com inclusão manual posterior.
     * Colaborador entra às 08:00, esquece de registrar saída para almoço às 12:00,
     * bate ponto no retorno às 13:00 (gravado como 'out') e saída às 17:00 (gravado como 'in').
     * Quando o RH aprova inclusão manual da saída das 12:00:
     * - 08:00: in
     * - 12:00: out (manual)
     * - 13:00: in (reclassificado)
     * - 17:00: out (reclassificado)
     * - Total trabalhado: 480 minutos (08h 00m).
     */
    public function test_forgotten_lunch_exit_with_retroactive_approval(): void
    {
        $date = Carbon::parse('2026-10-22', 'America/Maceio');

        $this->recordPunch('in', '2026-10-22 08:00:00');
        $this->recordPunch('out', '2026-10-22 13:00:00');
        $this->recordPunch('in', '2026-10-22 17:00:00');

        // RH aprova saída manual das 12:00
        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_exit',
            date: '2026-10-22',
            reason: 'Inclusão da saída esquecida de almoço',
            actor: $this->adminUser,
            time: '12:00',
            autoApprove: true,
        );

        $journey = $this->calculateAction->execute($this->employee, $date);

        // 08:00-12:00 (240m) + 13:00-17:00 (240m) = 480 minutos trabalhados
        $this->assertSame(480, $journey->workedMinutes);
        $this->assertSame(60, $journey->breakMinutes);
        $this->assertFalse($journey->isIncomplete);

        $punches = $journey->effectivePunches;
        $this->assertSame('in', $punches[0]['effective_direction']);
        $this->assertSame('out', $punches[1]['effective_direction']);
        $this->assertSame('in', $punches[2]['effective_direction']);
        $this->assertSame('out', $punches[3]['effective_direction']);
    }

    /**
     * Cenário: Batidas duplicadas acidentais (ex: duplo toque em 30 segundos).
     * Não devem inverter a sequência do restante do dia nem quebrar a apuração.
     */
    public function test_rapid_duplicate_punches_do_not_invert_sequence(): void
    {
        $date = Carbon::parse('2026-10-22', 'America/Maceio');

        // Batida normal às 08:00 e duplicada acidental às 08:00:35
        $this->recordPunch('in', '2026-10-22 08:00:00');
        $this->recordPunch('in', '2026-10-22 08:00:35');

        // Saída normal às 12:00
        $this->recordPunch('out', '2026-10-22 12:00:00');

        $journey = $this->calculateAction->execute($this->employee, $date);

        // A segunda batida das 08:00 deve ser reconhecida como duplicada e não interferir na saída das 12:00
        $this->assertSame(240, $journey->workedMinutes); // 4 horas trabalhadas
        $this->assertSame(0, $journey->breakMinutes);

        $punches = $journey->effectivePunches;
        $this->assertCount(3, $punches);
        $this->assertTrue($punches[1]['is_duplicate'], 'A batida ocorrida em 35s deve ser sinalizada como duplicada.');
        $this->assertSame('out', $punches[2]['effective_direction'], 'A batida das 12:00 deve permanecer como saída.');
    }

    /**
     * Cenário: Sincronia entre Apuração e Espelho de Ponto (TimesheetJourneyService).
     * O espelho de ponto deve exibir os sentidos efetivos recalculados e metadados de reclassificação.
     */
    public function test_timesheet_service_grouped_entries_use_effective_directions(): void
    {
        $this->recordPunch('in', '2026-10-22 12:52:38');
        $this->recordPunch('out', '2026-10-22 13:00:31');

        app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Inclusão manual aprovada',
            actor: $this->adminUser,
            time: '08:00',
            autoApprove: true,
        );

        $monthData = $this->timesheetService->resolveMonthData($this->workerUser, 2026, 10);
        $grouped = $monthData['groupedEntries']['2026-10-22'] ?? collect();

        $this->assertCount(3, $grouped);

        // 08:00 - Ajuste Manual Entrada
        $this->assertSame('in', $grouped[0]->type);
        $this->assertTrue($grouped[0]->is_manual);

        // 12:52 - Saída efetiva
        $this->assertSame('out', $grouped[1]->type);
        $this->assertSame('in', $grouped[1]->original_direction);
        $this->assertTrue($grouped[1]->is_direction_reclassified);

        // 13:00 - Entrada efetiva
        $this->assertSame('in', $grouped[2]->type);
        $this->assertSame('out', $grouped[2]->original_direction);
        $this->assertTrue($grouped[2]->is_direction_reclassified);
    }

    /**
     * Cenário: Invalidação de conferência anterior quando um tratamento é aprovado ou revertido em competência aberta.
     */
    public function test_approval_or_revocation_invalidates_open_monthly_review(): void
    {
        $reviewAction = app(ReviewMonthlyAttendanceAction::class);
        $reviewService = app(MonthlyAttendanceReviewService::class);

        // 1. Criar e aprovar uma conferência inicial para Outubro/2026
        $review = $reviewAction->reviewEmployee(
            employee: $this->employee,
            year: 2026,
            month: 10,
            reviewer: $this->adminUser,
            reservationNotes: 'Conferência inicial regular sem ressalvas',
        );

        $this->assertNotNull($review->fingerprint);

        // 2. Novo tratamento aprovado para Outubro/2026
        $treatment = app(ApplyPointAdjustmentAction::class)->execute(
            employee: $this->employee,
            actionType: 'add_entry',
            date: '2026-10-22',
            reason: 'Inclusão superveniente de marcação',
            actor: $this->adminUser,
            time: '08:00',
            autoApprove: true,
        );

        // A conferência existente deve ter sido invalidada (fingerprint null e exigindo nova revisão)
        $review->refresh();
        $this->assertNull($review->fingerprint, 'O fingerprint deve ser anulado após a aprovação de tratamento superveniente.');
        $this->assertTrue($review->has_unresolved_occurrences);

        // 3. O serviço de revisão mensal deve indicar que a conferência está desatualizada (stale)
        $reviewData = $reviewService->resolveEmployeeReviewData($this->employee, 2026, 10);
        $this->assertTrue($reviewData['stale'], 'A conferência deve ser identificada como desatualizada (stale).');
    }
}
