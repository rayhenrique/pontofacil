<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\LaborRules\Actions\CalculateNightWorkAction;
use App\Domain\LaborRules\DTOs\WorkInterval;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RecordTimeBankTransactionAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Domain\Settlement\Services\WorkTimeSettlementManager;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Enums\WorkScheduleModality;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegratedJourneyAndSettlementAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $adminUser;

    protected WorkSchedule $standardSchedule;

    protected WorkTimeSettlementManager $settlementManager;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA AUDITORIA INTEGRADA LTDA',
            'trade_name' => 'Auditoria Integrada',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'MATRIZ',
            'name' => 'Sede Maceió',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceió',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operações & RH',
            'code' => 'RH-OPS',
        ]);

        $this->adminUser = User::create([
            'name' => 'Gestor do RH',
            'email' => 'gestor.rh@auditoria.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->standardSchedule = WorkSchedule::createDefault40h();

        $this->settlementManager = app(WorkTimeSettlementManager::class);
    }

    protected function createEmployee(string $name, string $cpf, LegalRegime $regime = LegalRegime::CLT, ?WorkSchedule $schedule = null): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@auditoria.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $schedule?->id ?? $this->standardSchedule->id,
            'registration_number' => 'MAT-'.rand(1000, 9999),
            'cpf' => $cpf,
            'job_title' => 'Especialista Operacional',
            'legal_regime' => $regime,
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

    protected function registerStandardPunches(Employee $employee, Carbon $date, array $times = ['08:00', '12:00', '13:00', '17:00']): void
    {
        $directions = ['in', 'out', 'in', 'out', 'in', 'out'];
        foreach ($times as $index => $timeStr) {
            $direction = $directions[$index % count($directions)];
            $dt = $date->copy()->setTimeFromTimeString($timeStr);
            $this->registerPunch($employee, $dt, $direction);
        }
    }

    // =========================================================================
    // 1. FLUXO COMPLETO DE JORNADA E COMPENSAÇÃO
    // =========================================================================

    public function test_fluxo_completo_cadastro_ate_fechamento_e_espelho(): void
    {
        // 1. Cadastro do colaborador com regime CLT e escala vigente
        $employee = $this->createEmployee('Marcos Vinicius', '11122233344', LegalRegime::CLT, $this->standardSchedule);

        // 2. Registro do ponto (08:00 - 12:00, 13:00 - 18:00 = 9h físicas = 540 min trabalhados)
        $workDate = Carbon::parse('2026-10-15');
        $this->registerStandardPunches($employee, $workDate, ['08:00', '12:00', '13:00', '18:00']);

        // 3. Apuração PTRP
        $journey = app(CalculateDailyJourneyAction::class)->execute($employee, $workDate);
        $this->assertTrue($journey->hasSchedule);
        $this->assertEquals(540, $journey->workedMinutes);
        $this->assertEquals(480, $journey->scheduledMinutes);

        // 4. Diferença de jornada apurada (+60 min)
        $this->assertEquals(60, $journey->dailyDifferenceMinutes());
        $this->assertEquals('calculated', $journey->differenceState());
        $this->assertEquals('Você trabalhou 9h de uma jornada prevista de 8h.', $journey->humanWorkStatement());
        $this->assertEquals('Diferença: +60 minutos.', $journey->humanDifference());

        // 5. Política aplicável (RH define pagamento em folha ou compensação)
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'origin_identifier' => '2026-10-15',
            'justification' => 'Horas extras aprovadas para fechamento de balanço',
        ], $this->adminUser);

        $this->assertEquals(WorkTimeSettlementStatus::Pending, $settlement->status);
        $this->assertEquals(60, $settlement->minutes);

        // 6. Aprovação pelo RH
        $approvedSettlement = $this->settlementManager->approveSettlement($settlement, $this->adminUser, 'Aprovado pelo gestor de RH');
        $this->assertEquals(WorkTimeSettlementStatus::Approved, $approvedSettlement->status);
        $this->assertEquals($this->adminUser->id, $approvedSettlement->approved_by);

        // 7. Execução com quitação documental fática
        $executedSettlement = $this->settlementManager->confirmExecution(
            $approvedSettlement,
            $this->adminUser,
            '2026-10-31',
            'FOLHA-PAGTO-10-2026-RUBRICA-050',
            'Pago no holerite de Outubro/2026'
        );
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $executedSettlement->status);

        // 8. Ledger de quitação documentada registrado
        $discharge = WorkTimeSettlementDischarge::where('work_time_settlement_id', $executedSettlement->id)->first();
        $this->assertNotNull($discharge);
        $this->assertEquals('FOLHA-PAGTO-10-2026-RUBRICA-050', $discharge->document_reference);

        // 9. Fechamento mensal
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser, 'Fechamento de Outubro/2026');
        $this->assertEquals('closed', $closedPeriod->status);

        // 10. Espelho / Folha reflete snapshot imutável sem cálculos duplicados
        $timesheetService = app(TimesheetJourneyService::class);
        $timesheetData = $timesheetService->resolveMonthData($employee->user, 2026, 10);

        $this->assertTrue($timesheetData['isClosedPeriod']);
        $this->assertEquals('closed', $timesheetData['closedPeriod']->status);
        $this->assertNotEmpty($timesheetData['daysCalculated']);
    }

    // =========================================================================
    // 2. PERFIS DE UTILIZAÇÃO
    // =========================================================================

    public function test_perfil_empresa_privada_sem_banco(): void
    {
        // Empresa sem banco: funcionário CLT registra ponto com diferenças
        $employee = $this->createEmployee('Ana Souza', '22233344455', LegalRegime::CLT, $this->standardSchedule);

        // Dia 1: +60 min de diferença (9h trabalhadas)
        $this->registerStandardPunches($employee, Carbon::parse('2026-10-05'), ['08:00', '12:00', '13:00', '18:00']);
        // Dia 2: -60 min de diferença (7h trabalhadas)
        $this->registerStandardPunches($employee, Carbon::parse('2026-10-06'), ['08:00', '12:00', '13:00', '16:00']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($employee->user, 2026, 10);

        // Não opera banco de horas
        $this->assertFalse($data['operatesTimeBank']);

        // O espelho apresenta diferenças positivas e negativas
        $this->assertEquals(60, $data['differencesSummary']->positiveMinutes);
        $this->assertEquals(-60, $data['differencesSummary']->negativeMinutes);

        // Nenhuma transação ou conta de banco é criada automaticamente
        $account = TimeBankAccount::where('employee_id', $employee->id)->first();
        $this->assertTrue($account === null || TimeBankTransaction::where('time_bank_account_id', $account->id)->count() === 0);

        // Pagamento NÃO é presumido: diferenças permanecem como pendentes de quitação fática
        $this->assertEquals(0, $data['dischargesCount'] ?? 0);
    }

    public function test_perfil_empresa_com_compensacao_mensal(): void
    {
        $employee = $this->createEmployee('Bruno Ferreira', '33344455566', LegalRegime::CLT, $this->standardSchedule);

        TimeBankPolicy::create([
            'name' => 'Acordo Individual - Compensação Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-10-01'),
            'created_by' => $this->adminUser->id,
        ]);

        // Registra saldo no ledger para o mês
        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 180,
            referenceDate: Carbon::parse('2026-10-10'),
            description: 'Saldo do mês para compensação'
        );

        $account = TimeBankAccount::getOrCreateForEmployee($employee);
        $this->assertEquals(180, $account->currentBalance());

        // Fechamento da competência NÃO extingue pendências automaticamente nem apaga saldo sem quitação
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser, 'Fechamento Mensal');
        $this->assertEquals('closed', $closedPeriod->status);

        // Saldo residual é identificado como pendência documentada
        $settlement = WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('reference_period', '2026-10')
            ->first();

        $this->assertNotNull($settlement);
        $this->assertEquals(180, $settlement->minutes);
        $this->assertEquals(WorkTimeSettlementStatus::Pending, $settlement->status);

        // O saldo da conta contábil continua preservado
        $account->refresh();
        $this->assertEquals(180, $account->currentBalance());
    }

    public function test_perfil_empresa_com_banco_acumulativo(): void
    {
        $employee = $this->createEmployee('Carla Ribeiro', '44455566677', LegalRegime::CLT, $this->standardSchedule);

        TimeBankPolicy::create([
            'name' => 'Acordo Coletivo - Banco Semestral',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::CarryOver,
            'valid_from' => Carbon::parse('2026-09-01'),
            'created_by' => $this->adminUser->id,
        ]);

        $recordTxAction = app(RecordTimeBankTransactionAction::class);

        // Mês anterior (Setembro) gera +240 min
        $recordTxAction->execute(
            employee: $employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 240,
            referenceDate: Carbon::parse('2026-09-20'),
            description: 'Crédito Setembro'
        );

        // Mês atual (Outubro) gera débito compensatório (-60 min)
        $recordTxAction->execute(
            employee: $employee,
            type: TimeBankTransactionType::CompensationDebit,
            minutes: -60,
            referenceDate: Carbon::parse('2026-10-10'),
            description: 'Compensação Outubro'
        );

        $account = TimeBankAccount::getOrCreateForEmployee($employee);

        // Saldo de Setembro deve ser exatamente 240 min, sem contaminação por Outubro
        $this->assertEquals(240, $account->balanceUntil('2026-09-30'));

        // Saldo consolidado até Outubro deve ser 180 min
        $this->assertEquals(180, $account->balanceUntil('2026-10-31'));

        // Fechar Setembro não afeta movimentações de Outubro
        $closedSept = app(CloseMonthlyPeriodAction::class)->execute(2026, 9, $this->adminUser, 'Fechamento Setembro');
        $this->assertEquals('closed', $closedSept->status);

        $snapshotSept = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedSept->id)
            ->where('employee_id', $employee->id)
            ->first();
        $this->assertEquals(240, $snapshotSept->time_bank_snapshot['balance_at_closing']);

        $account->refresh();
        $this->assertEquals(180, $account->currentBalance());
    }

    public function test_perfil_orgao_publico_estatutario(): void
    {
        // Estatutário municipal: regime jurídico MunicipalStatutory
        $servidor = $this->createEmployee('Dr. Fernando Estatutário', '55566677788', LegalRegime::MunicipalStatutory, $this->standardSchedule);

        $this->assertEquals(LegalRegime::MunicipalStatutory, $servidor->legal_regime);
        $this->assertTrue($servidor->legal_regime->isStatutory());

        // Registra batidas normalmente (08:00 - 12:00, 13:00 - 18:00 = 9h)
        $workDate = Carbon::parse('2026-10-15');
        $this->registerStandardPunches($servidor, $workDate, ['08:00', '12:00', '13:00', '18:00']);

        // Apuração PTRP preserva batidas físicas intactas
        $journey = app(CalculateDailyJourneyAction::class)->execute($servidor, $workDate);
        $this->assertEquals(540, $journey->workedMinutes);

        // Na ausência de fundamentação estatutária configurada, a apuração permanece pendente de definição legal
        $this->assertTrue($journey->isPendingSettlement);
        $this->assertNotEmpty($journey->settlementNotes);

        // O servidor não herda regras tácitas da CLT automaticamente
        $this->assertFalse($journey->requiresCompensation);

        // As batidas fiscais originais continuam intactas
        $this->assertCount(4, PunchEvent::where('employee_id', $servidor->id)->get());
    }

    // =========================================================================
    // 3. SIMPLIFICAÇÃO DO ESPELHO PARA O COLABORADOR
    // =========================================================================

    public function test_espelho_linguagem_humana_e_sem_banco_ficticio(): void
    {
        $employee = $this->createEmployee('Juliana Lima', '66677788899', LegalRegime::CLT, $this->standardSchedule);

        // 7h27 de trabalho: 08:00 às 12:00 (4h) e 13:00 às 16:27 (3h27) = 7h27 = 447 min
        $workDate = Carbon::parse('2026-10-14');
        $this->registerStandardPunches($employee, $workDate, ['08:00', '12:00', '13:00', '16:27']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($employee, $workDate);

        $this->assertEquals('Você trabalhou 7h27 de uma jornada prevista de 8h.', $journey->humanWorkStatement());
        $this->assertEquals('Diferença: -33 minutos.', $journey->humanDifference());
        $this->assertNotNull($journey->attentionReason());

        // Colaborador sem escala configurada:
        $noScheduleEmployee = $this->createEmployee('Sem Escala', '77788899900', LegalRegime::CLT, $this->standardSchedule);
        $noScheduleEmployee->update(['work_schedule_id' => null]);

        $journeyNoSched = app(CalculateDailyJourneyAction::class)->execute($noScheduleEmployee, $workDate);
        $this->assertEquals(
            'Não foi possível calcular o saldo porque sua escala ainda precisa ser configurada.',
            $journeyNoSched->formattedDifference()
        );
    }

    // =========================================================================
    // 5. INTEGRIDADE HISTÓRICA
    // =========================================================================

    public function test_integridade_historica_snapshot_escalas_politicas_e_fiscal(): void
    {
        $employee = $this->createEmployee('Roberto Santos', '88899900011', LegalRegime::CLT, $this->standardSchedule);
        $workDate = Carbon::parse('2026-09-10');
        $this->registerStandardPunches($employee, $workDate, ['08:00', '12:00', '13:00', '18:00']);

        // Fecha a competência de Setembro
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(2026, 9, $this->adminUser, 'Fechamento Setembro');
        $this->assertEquals('closed', $closedPeriod->status);

        $snapshotBefore = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
            ->where('employee_id', $employee->id)
            ->first();
        $this->assertNotNull($snapshotBefore);
        $this->assertNotEmpty($snapshotBefore->journey_snapshot);

        // 1. Mudança de escala posterior não altera o snapshot de mês fechado
        $newSchedule = WorkSchedule::create([
            'name' => 'Nova Escala Reduzida 30h',
            'weekly_hours' => 30,
            'monday_entry' => '08:00',
            'monday_exit' => '14:00',
            'monday_interval_minutes' => 0,
            'tuesday_entry' => '08:00',
            'tuesday_exit' => '14:00',
            'tuesday_interval_minutes' => 0,
            'wednesday_entry' => '08:00',
            'wednesday_exit' => '14:00',
            'wednesday_interval_minutes' => 0,
            'thursday_entry' => '08:00',
            'thursday_exit' => '14:00',
            'thursday_interval_minutes' => 0,
            'friday_entry' => '08:00',
            'friday_exit' => '14:00',
            'friday_interval_minutes' => 0,
        ]);

        $employee->update(['work_schedule_id' => $newSchedule->id]);

        $service = app(TimesheetJourneyService::class);
        $timesheetSept = $service->resolveMonthData($employee->user, 2026, 9);

        // Permanece fechado com os dados do snapshot imutável (480 min da escala original de Setembro)
        $this->assertTrue($timesheetSept['isClosedPeriod']);
        $this->assertEquals(480, $timesheetSept['daysCalculated']['2026-09-10']['scheduled_minutes']);

        // 2. Batidas fiscais originais permanecem 100% intactas
        $punches = PunchEvent::where('employee_id', $employee->id)->get();
        $this->assertCount(4, $punches);
        foreach ($punches as $punch) {
            $this->assertNotNull($punch->fiscal_hash);
            $this->assertNotNull($punch->audit_chain_hash);
        }
    }

    // =========================================================================
    // 6. CASOS COMPLEXOS
    // =========================================================================

    public function test_casos_complexos_jornada_e_compensacao(): void
    {
        // 1. Jornada incompleta (apenas 1 batida registrada)
        $empIncomplete = $this->createEmployee('Incompleto', '12312312301', LegalRegime::CLT, $this->standardSchedule);
        $date1 = Carbon::parse('2026-10-01');
        $this->registerPunch($empIncomplete, $date1->copy()->setTime(8, 0), 'in');

        $journeyIncomp = app(CalculateDailyJourneyAction::class)->execute($empIncomplete, $date1);
        $this->assertTrue($journeyIncomp->isIncomplete);
        $this->assertEquals('Jornada incompleta (marcação em aberto).', $journeyIncomp->attentionReason());

        // 2. Jornada 12x36 diurna
        $sched12x36 = WorkSchedule::create([
            'name' => 'Escala 12x36 Diurna Especial',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_days' => 2,
            'cycle_data' => [
                'start_time' => '07:00',
                'end_time' => '19:00',
                'break_minutes' => 60,
                'is_night_shift' => false,
            ],
            'active' => true,
        ]);
        $emp12x36 = $this->createEmployee('Plantonista 12x36', '12312312302', LegalRegime::CLT, $sched12x36);
        $shifts12x36 = app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp12x36,
            schedule: $sched12x36,
            anchorDate: '2026-10-01',
            startDate: '2026-10-01',
            endDate: '2026-10-02',
            includeOffDays: true
        );
        $this->assertCount(2, $shifts12x36);
        $this->assertFalse($shifts12x36[0]->is_day_off);
        $this->assertTrue($shifts12x36[1]->is_day_off);

        // 3. Plantão 24x72 autorizado para regime estatutário
        $sched24x72 = WorkSchedule::create([
            'name' => 'Plantão 24x72 Socorrista',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => ['municipal_statutory'],
            'cycle_days' => 4,
            'cycle_data' => [
                'start_time' => '07:00',
                'end_time' => '07:00',
                'break_minutes' => 120,
            ],
            'active' => true,
        ]);
        $empStat = $this->createEmployee('Socorrista', '12312312303', LegalRegime::MunicipalStatutory, $sched24x72);
        $shifts24x72 = app(GenerateCyclicShiftsAction::class)->execute(
            employee: $empStat,
            schedule: $sched24x72,
            anchorDate: '2026-10-01',
            startDate: '2026-10-01',
            endDate: '2026-10-04',
            includeOffDays: true
        );
        $this->assertCount(4, $shifts24x72);
        $this->assertEquals(24, $shifts24x72[0]->durationInHours());

        // 4. Jornada Noturna com virada de dia (22h às 05h)
        $nightAction = app(CalculateNightWorkAction::class);
        $profile = LaborRuleProfile::createStandardCltProfile();
        $startNight = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $endNight = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');
        $nightResult = $nightAction->execute([new WorkInterval($startNight, $endNight)], 'America/Sao_Paulo', $profile);
        $this->assertEquals(420, $nightResult->physicalWorkedMinutes()); // 7h físicas
        $this->assertEquals(480, $nightResult->legalNightEquivalentMinutes()); // 8h ficto-legais

        // 5. Feriado e Folga
        CalendarEvent::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Feriado Nacional Nossa Senhora Aparecida',
            'event_date' => '2026-10-12',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::Establishment,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'requires_compensation' => false,
            'created_by' => $this->adminUser->id,
        ]);
        $journeyHoliday = app(CalculateDailyJourneyAction::class)->execute($empIncomplete, Carbon::parse('2026-10-12'));
        $this->assertEquals(0, $journeyHoliday->scheduledMinutes);

        // 6. Tratamento aprovado (justificativa de atestado)
        $requestAction = app(RequestTreatmentEventAction::class);
        $treatment = $requestAction->execute(
            employee: $empIncomplete,
            type: TreatmentEventType::AbsenceJustified,
            effectiveAt: Carbon::parse('2026-10-05 08:00:00'),
            reasonText: 'Atestado médico de 1 dia',
            requestedBy: $empIncomplete->user,
            attachmentPath: 'atestados/atestado-10.pdf'
        );
        $treatment->update([
            'status' => TreatmentEventStatus::Approved,
            'approved_by' => $this->adminUser->id,
            'decided_at' => now(),
        ]);
        $journeyWithTreat = app(CalculateDailyJourneyAction::class)->execute($empIncomplete, Carbon::parse('2026-10-05'));
        $this->assertNotEmpty($journeyWithTreat->treatmentNotes);
        $this->assertEquals(0, $journeyWithTreat->absenceMinutes);

        // 7. Estorno auditável
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $empIncomplete->id,
            'reference_period' => '2026-10',
            'minutes' => -60,
            'settlement_type' => WorkTimeSettlementType::PayrollDeduction,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Desconto por atraso a estornar',
        ], $this->adminUser);
        $this->settlementManager->approveSettlement($settlement, $this->adminUser);
        $this->settlementManager->confirmExecution($settlement, $this->adminUser, '2026-10-31', 'REC-DESCONTO-1');

        $reversed = $this->settlementManager->reverseSettlement(
            $settlement,
            $this->adminUser,
            'Erro de cálculo justificado com atestado',
            'PORTARIA-REV-01'
        );
        $this->assertEquals(WorkTimeSettlementStatus::Reversed, $settlement->refresh()->status);
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $reversed->status);
        $this->assertEquals(60, $reversed->minutes);
    }

    // =========================================================================
    // 7. TESTES DE CONCORRÊNCIA E CONTROLE ESTRITO
    // =========================================================================

    public function test_concorrencia_duas_quitacoes_simultaneas_bloqueadas(): void
    {
        $employee = $this->createEmployee('Concorrência Quitação', '99911122233', LegalRegime::CLT, $this->standardSchedule);

        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $employee->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Horas extras a liquidar',
        ], $this->adminUser);

        $this->settlementManager->approveSettlement($settlement, $this->adminUser);

        // Primeira quitação deve passar
        $this->settlementManager->confirmExecution(
            $settlement,
            $this->adminUser,
            '2026-10-31',
            'DOC-QUITACAO-PRIMEIRA'
        );

        $this->assertEquals(WorkTimeSettlementStatus::Executed, $settlement->refresh()->status);

        // Segunda tentativa concorrente deve ser rejeitada com DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta destinação já foi efetivada e possui quitação documentada.');

        $this->settlementManager->confirmExecution(
            $settlement,
            $this->adminUser,
            '2026-10-31',
            'DOC-QUITACAO-SEGUNDA'
        );
    }

    public function test_concorrencia_duas_aprovacoes_simultaneas_bloqueadas(): void
    {
        $employee = $this->createEmployee('Concorrência Aprovação', '99922233344', LegalRegime::CLT, $this->standardSchedule);

        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Horas a aprovar',
        ], $this->adminUser);

        // Primeira aprovação
        $this->settlementManager->approveSettlement($settlement, $this->adminUser);
        $this->assertEquals(WorkTimeSettlementStatus::Approved, $settlement->refresh()->status);

        // Segunda aprovação deve falhar informando que apenas destinações pendentes podem ser aprovadas
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Apenas destinações pendentes podem ser aprovadas.');

        $this->settlementManager->approveSettlement($settlement, $this->adminUser);
    }

    public function test_concorrencia_estorno_repetido_bloqueado(): void
    {
        $employee = $this->createEmployee('Concorrência Estorno', '99933344455', LegalRegime::CLT, $this->standardSchedule);

        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Horas extras',
        ], $this->adminUser);

        $this->settlementManager->approveSettlement($settlement, $this->adminUser);
        $this->settlementManager->confirmExecution($settlement, $this->adminUser, '2026-10-31', 'DOC-ESTORNO-1');

        // Primeiro estorno
        $this->settlementManager->reverseSettlement($settlement, $this->adminUser, 'Estorno inicial');
        $this->assertEquals(WorkTimeSettlementStatus::Reversed, $settlement->refresh()->status);

        // Segundo estorno
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta destinação já foi estornada anteriormente.');

        $this->settlementManager->reverseSettlement($settlement, $this->adminUser, 'Tentativa de estorno repetido');
    }

    public function test_concorrencia_idempotencia_chave_repetida(): void
    {
        $employee = $this->createEmployee('Idempotência', '99944455566', LegalRegime::CLT, $this->standardSchedule);
        $idempotencyKey = 'IDEMPOTENT-KEY-ABC-123';

        $payload = [
            'employee_id' => $employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Horas extras com chave idempotente',
            'idempotency_key' => $idempotencyKey,
        ];

        // 1ª execução com a chave: cria o registro
        $settlement1 = $this->settlementManager->createSettlement($payload, $this->adminUser);
        $this->assertNotNull($settlement1->id);

        // 2ª execução com exatamente a mesma chave e payload: retorna o mesmo sem duplicar
        $settlement2 = $this->settlementManager->createSettlement($payload, $this->adminUser);
        $this->assertEquals($settlement1->id, $settlement2->id);
        $this->assertEquals(1, WorkTimeSettlement::where('idempotency_key', $idempotencyKey)->count());

        // 3ª execução com a mesma chave porém minutos conflitantes (+120 min): deve lançar DomainException
        $conflictingPayload = array_merge($payload, ['minutes' => 120]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Conflito de idempotência: a chave informada já foi utilizada para uma operação com parâmetros diferentes.');

        $this->settlementManager->createSettlement($conflictingPayload, $this->adminUser);
    }
}
