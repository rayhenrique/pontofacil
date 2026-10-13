<?php

namespace Tests\Feature;

use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Services\TimeBankReconciliationService;
use App\Domain\PTRP\Services\TimeBankStatementService;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementDischargeType;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Domain\Settlement\Services\WorkTimeSettlementManager;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkTimeSettlementSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $managerUser;

    protected User $otherManagerUser;

    protected User $employeeUser;

    protected User $otherEmployeeUser;

    protected User $auditorUser;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected Sector $otherSector;

    protected WorkSchedule $schedule;

    protected Employee $employee;

    protected Employee $otherEmployee;

    protected WorkTimeSettlementManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'legal_name' => 'Empresa Exemplo Tecnologia Ltda',
            'trade_name' => 'Exemplo Tech',
            'cnpj' => '12345678000190',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Principal',
            'code' => 'SP-001',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000190',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $this->admin = User::create([
            'name' => 'Carlos Administrador',
            'email' => 'admin@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerUser = User::create([
            'name' => 'Roberto Gestor TI',
            'email' => 'gestor.ti@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->otherManagerUser = User::create([
            'name' => 'Fernando Gestor RH',
            'email' => 'gestor.rh@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Juliana Colaboradora',
            'email' => 'juliana@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->otherEmployeeUser = User::create([
            'name' => 'Marcos Outro Colaborador',
            'email' => 'marcos@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->auditorUser = User::create([
            'name' => 'Lucas Auditor Fiscal',
            'email' => 'auditor@exemplotech.com.br',
            'password' => 'secret123',
            'role' => UserRole::Auditor,
        ]);

        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Engenharia de Software',
            'code' => 'ENG',
            'manager_id' => $this->managerUser->id,
        ]);

        $this->otherSector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Recursos Humanos',
            'code' => 'RH',
            'manager_id' => $this->otherManagerUser->id,
        ]);

        $this->schedule = WorkSchedule::create([
            'name' => 'Administrativo 40h',
            'code' => 'ADM-40',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-1001',
            'cpf' => '11122233344',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $this->otherEmployee = Employee::create([
            'user_id' => $this->otherEmployeeUser->id,
            'sector_id' => $this->otherSector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-2002',
            'cpf' => '55566677788',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $this->manager = app(WorkTimeSettlementManager::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Helper para criar pares de batidas válidas no dia.
     */
    protected function registerDayPunches(Employee $employee, Carbon $date, string $in = '08:00', string $out = '17:00'): void
    {
        $inTime = $date->copy()->setTimeFromTimeString($in);
        $outTime = $date->copy()->setTimeFromTimeString($out);

        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => rand(1000, 99999),
            'occurred_at_utc' => $inTime->copy()->utc(),
            'occurred_at_local' => $inTime,
            'direction' => 'in',
            'fiscal_hash' => hash('sha256', (string) rand()),
            'audit_chain_hash' => hash('sha256', (string) rand()),
            'payload_hash' => hash('sha256', (string) rand()),
        ]);

        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => rand(1000, 99999),
            'occurred_at_utc' => $outTime->copy()->utc(),
            'occurred_at_local' => $outTime,
            'direction' => 'out',
            'fiscal_hash' => hash('sha256', (string) rand()),
            'audit_chain_hash' => hash('sha256', (string) rand()),
            'payload_hash' => hash('sha256', (string) rand()),
        ]);
    }

    /**
     * 1. Zeramento sem comprovação: fechamento não zera saldo se não houver quitação executada e comprovada.
     * Preserva o saldo como pendente em WorkTimeSettlement.
     */
    public function test_01_closing_does_not_reset_balance_without_proven_execution(): void
    {
        TimeBankPolicy::create([
            'name' => 'Política Mensal de Teste',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => '2026-01-01',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 180, // +03:00
            'reference_date' => '2026-10-15',
            'description' => 'Horas extras outubro',
        ]);

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->admin
        );

        $this->assertNotNull($closedPeriod);

        // Nenhuma transação indevida de zeramento no ledger
        $resetTx = TimeBankTransaction::where('type', TimeBankTransactionType::MonthlyReset)->first();
        $this->assertNull($resetTx, 'Fechamento não pode apagar horas sem quitação documental prévia.');

        // Saldo preservado como Pendente
        $settlement = WorkTimeSettlement::where('employee_id', $this->employee->id)
            ->where('reference_period', '2026-10')
            ->first();

        $this->assertNotNull($settlement);
        $this->assertEquals(180, $settlement->minutes);
        $this->assertEquals(WorkTimeSettlementStatus::Pending, $settlement->status);

        // Nenhuma quitação documentada fictícia
        $discharge = WorkTimeSettlementDischarge::where('employee_id', $this->employee->id)->first();
        $this->assertNull($discharge, 'Quitação exige execução comprovada.');
    }

    /**
     * 2. Aprovação sem execução: aprovação autoriza o procedimento, mas não altera o saldo nem gera quitação fática no ledger.
     */
    public function test_02_approval_does_not_modify_ledger_or_create_fictitious_discharge(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        $initialBalance = $account->currentBalance();

        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Pagamento de horas extras autorizado',
        ], $this->admin);

        $approved = $this->manager->approveSettlement($settlement, $this->admin, 'Aprovado pelo RH para lote de folha');

        $this->assertEquals(WorkTimeSettlementStatus::Approved, $approved->status);
        $this->assertEquals($initialBalance, $account->currentBalance(), 'Aprovação administrativa não altera saldo do ledger sem execução fática.');
        $this->assertNull($approved->discharge, 'Aprovação não gera quitação fática.');
    }

    /**
     * 3. Quitação confirmada: execução confirmada com documento comprobatório e data registra quitação formal vinculada.
     */
    public function test_03_confirmed_execution_creates_unique_document_discharge(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 90,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras plantão',
        ]);

        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Horas extras aprovadas para inclusão em folha',
        ], $this->admin);

        $this->manager->approveSettlement($settlement, $this->admin);

        $executed = $this->manager->confirmExecution(
            settlement: $settlement,
            actor: $this->admin,
            executionDate: '2026-10-31',
            documentReference: 'HOLERITE-10/2026-RUBRICA-101',
            notes: 'Comprovante de pagamento emitido na folha mensal',
            dischargeFromTimeBank: true
        );

        $this->assertEquals(WorkTimeSettlementStatus::Executed, $executed->status);
        $this->assertEquals('2026-10-31', $executed->execution_date->toDateString());
        $this->assertEquals('HOLERITE-10/2026-RUBRICA-101', $executed->document_reference);

        // Quitação única gravada no banco de dados vinculada ao settlement
        $discharge = WorkTimeSettlementDischarge::where('work_time_settlement_id', $executed->id)->first();
        $this->assertNotNull($discharge);
        $this->assertEquals(90, $discharge->minutes);
        $this->assertEquals('HOLERITE-10/2026-RUBRICA-101', $discharge->document_reference);
        $this->assertEquals(SettlementDischargeType::PayrollPaid, $discharge->discharge_type);

        // Saldo do time bank baixado
        $this->assertEquals(0, $account->currentBalance());
    }

    /**
     * 4. Crédito parcialmente compensado: destinação de parte do crédito consome apenas a parcela autorizada,
     * mantendo o saldo residual disponível.
     */
    public function test_04_partial_credit_destination_preserves_remaining_credit(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 150, // +02:30 total
            'reference_date' => '2026-10-05',
            'description' => 'Horas extras projeto',
        ]);

        // Destina 90 min (1h30)
        $settlement1 = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Folga compensatória de 1h30',
        ], $this->admin);

        $this->assertEquals(90, $settlement1->minutes);

        // Destina os 60 min restantes
        $settlement2 = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Pagamento do saldo residual de 1h',
        ], $this->admin);

        $this->assertEquals(60, $settlement2->minutes);

        // Tentativa de destinar mais 10 min excede o saldo e é bloqueada
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Este saldo já foi destinado ou excede as horas disponíveis');

        $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 10,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Excesso indevido de minutos',
        ], $this->admin);
    }

    /**
     * 5. Débito parcialmente regularizado: regularização de parte do débito consome apenas os minutos indicados.
     */
    public function test_05_partial_debit_regularization(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::CompensationDebit,
            'minutes' => -100, // -01:40
            'reference_date' => '2026-10-08',
            'description' => 'Atrasos compensáveis',
        ]);

        // Regulariza -60 min
        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => -60,
            'settlement_type' => WorkTimeSettlementType::PayrollDeduction,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Desconto em folha de 1 hora de atraso',
        ], $this->admin);

        $this->assertEquals(-60, $settlement->minutes);

        // Tentativa de descontar -50 min quando só restam -40 min é bloqueada
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Este débito já foi regularizado ou excede o débito apurado disponível');

        $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => -50,
            'settlement_type' => WorkTimeSettlementType::PayrollDeduction,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Tentativa de desconto acima do débito real',
        ], $this->admin);
    }

    /**
     * 6. Pagamento e banco tentando consumir a mesma origem:
     * impede que o mesmo saldo de horas extras seja destinado simultaneamente para pagamento e banco além do total apurado.
     */
    public function test_06_cannot_consume_same_credit_origin_simultaneously(): void
    {
        // Cria batidas para gerar 120 minutos extras (08:00 às 18:00 = 10h trabalhadas, 8h previstas = 2h extras)
        $this->registerDayPunches($this->employee, Carbon::parse('2026-10-14'), '08:00', '18:00');

        // Congela o "hoje" no fim do mês para apurar dia passado como definitivo.
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00'));

        // Destina 80 min para folha de pagamento a partir da apuração diária
        $s1 = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 80,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'origin_identifier' => '2026-10-14',
            'justification' => 'Destinação de 80 min para folha',
        ], $this->admin);

        $this->assertNotNull($s1);

        // Tentativa de destinar mais 60 min (80 + 60 = 140 > 120 disponíveis) deve falhar
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Este saldo já foi destinado ou excede as horas disponíveis');

        $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::DailyJourney,
            'origin_identifier' => '2026-10-14',
            'justification' => 'Tentativa de consumir o mesmo crédito excedendo o dia',
        ], $this->admin);
    }

    /**
     * 7. Concorrência: tentativa de dupla execução da mesma destinação não gera quitação duplicada.
     */
    public function test_07_cannot_double_execute_same_settlement(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-12',
            'description' => 'Horas extras',
        ]);

        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Horas para folha',
        ], $this->admin);

        $this->manager->approveSettlement($settlement, $this->admin);

        $this->manager->confirmExecution(
            settlement: $settlement,
            actor: $this->admin,
            executionDate: '2026-10-31',
            documentReference: 'DOC-123'
        );

        // Segunda tentativa com a mesma destinação
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta destinação já foi efetivada e possui quitação documentada.');

        $this->manager->confirmExecution(
            settlement: $settlement,
            actor: $this->admin,
            executionDate: '2026-10-31',
            documentReference: 'DOC-123-REPEAT'
        );
    }

    /**
     * 8. Chave de idempotência repetida com mesmo payload: retorna o settlement original sem duplicar.
     */
    public function test_08_idempotency_key_with_identical_payload_returns_existing_record(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        $payload = [
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Destinação com chave de idempotência',
            'idempotency_key' => 'IDEMPOTENT-TEST-KEY-001',
        ];

        $first = $this->manager->createSettlement($payload, $this->admin);
        $second = $this->manager->createSettlement($payload, $this->admin);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, WorkTimeSettlement::where('idempotency_key', 'IDEMPOTENT-TEST-KEY-001')->count());
    }

    /**
     * 9. Chave repetida com payload diferente: gera conflito controlado de idempotência.
     */
    public function test_09_idempotency_key_with_different_payload_throws_controlled_conflict(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Primeira tentativa',
            'idempotency_key' => 'SAME-KEY-DIFFERENT-PAYLOAD',
        ], $this->admin);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Conflito de idempotência');

        // Mesma chave com minutos diferentes (90 em vez de 60)
        $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Segunda tentativa com valor alterado',
            'idempotency_key' => 'SAME-KEY-DIFFERENT-PAYLOAD',
        ], $this->admin);
    }

    /**
     * 10. Estorno: destinação efetivada estornada gera lançamento inverso compensatório e marca original como Reversed.
     */
    public function test_10_reversal_creates_compensatory_settlement_and_audits(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 100,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 100,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Folga acordada',
        ], $this->admin);

        $this->manager->approveSettlement($settlement, $this->admin);
        $this->manager->confirmExecution($settlement, $this->admin, '2026-10-25', 'FOLGA-OUTUBRO-01', dischargeFromTimeBank: true);

        // Saldo do time bank foi a 0
        $this->assertEquals(0, $account->currentBalance());

        // Estorno
        $reversal = $this->manager->reverseSettlement(
            settlement: $settlement,
            actor: $this->admin,
            justification: 'Colaborador foi convocado para trabalhar no dia da folga e folga foi cancelada',
            reverseTimeBank: true
        );

        $this->assertEquals(WorkTimeSettlementStatus::Reversed, $settlement->fresh()->status);
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $reversal->status);
        $this->assertEquals(-100, $reversal->minutes); // Sinal invertido
        $this->assertEquals($settlement->id, $reversal->reversal_of_id);

        // Saldo do banco restituído
        $this->assertEquals(100, $account->currentBalance());
    }

    /**
     * 11. Competência fechada: consulta e destinações respeitam snapshot congelado.
     */
    public function test_11_closed_period_respects_frozen_snapshot(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 8,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->admin->id,
            'closed_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Juliana Colaboradora'],
            'schedule_snapshot' => ['name' => 'ADM-40'],
            'treatment_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2026-08-01',
                    'is_day_off' => false,
                    'is_holiday' => false,
                    'worked_minutes' => 480,
                    'daily_difference_minutes' => 0,
                    'effective_punches' => [],
                    'treatments' => [],
                    'status' => 'CONCLUIDA',
                ],
            ],
            'time_bank_snapshot' => [
                'balance_before' => 0,
                'balance_at_closing' => 150,
                'reset_applied' => 0,
                'final_balance' => 150,
            ],
            'snapshot_hash' => hash('sha256', 'snap-test-11'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->employeeUser, 2026, 8);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertEquals(150, $data['timeBankSummary']->closingBalanceMinutes);
    }

    /**
     * 12. Operação posterior ao fechamento: registrada separadamente sem modificar o snapshot congelado.
     */
    public function test_12_post_closing_settlement_does_not_modify_frozen_snapshot(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 8,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->admin->id,
            'closed_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        $snapshot = ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Juliana Colaboradora'],
            'schedule_snapshot' => ['name' => 'ADM-40'],
            'treatment_snapshot' => [],
            'journey_snapshot' => [],
            'time_bank_snapshot' => [
                'balance_before' => 0,
                'balance_at_closing' => 120,
                'reset_applied' => 0,
                'final_balance' => 120,
            ],
            'snapshot_hash' => hash('sha256', 'snap-test-12'),
        ]);

        // Destinação criada em outubro referenciando a competência de agosto
        $postClosingSettlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'closed_period_id' => $closedPeriod->id,
            'reference_period' => '2026-08',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'operation_date' => '2026-10-15',
            'justification' => 'Quitação em folha posterior ao fechamento',
        ], $this->admin);

        $this->assertNotNull($postClosingSettlement);

        // Snapshot congelado permanece intacto
        $snapshot->refresh();
        $this->assertEquals(120, $snapshot->time_bank_snapshot['final_balance']);
        $this->assertEquals(hash('sha256', 'snap-test-12'), $snapshot->snapshot_hash);
    }

    /**
     * 13. Usuário sem permissão: colaborador comum tentando aprovar ou efetivar quitação é bloqueado no servidor.
     */
    public function test_13_unauthorized_user_cannot_approve_or_execute(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        $settlement = $this->manager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Horas extras solicitadas',
        ], $this->admin);

        // Colaborador tentando aprovar
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Colaboradores não possuem permissão para autorizar, aprovar ou efetivar quitações de horas.');

        $this->manager->approveSettlement($settlement, $this->employeeUser);
    }

    /**
     * 14. Gestor fora do escopo ou autoaprovação:
     * - Gestor tentando operar colaborador de outro setor é bloqueado;
     * - Gestor tentando aprovar a própria quitação é bloqueado por segregação de funções.
     */
    public function test_14_manager_out_of_scope_or_self_approval_is_blocked(): void
    {
        // 1. Gestor de TI (Roberto) tentando operar funcionário de RH (Marcos)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O gestor não possui autorização para operar colaboradores fora de seu escopo de gestão.');

        $this->manager->createSettlement([
            'employee_id' => $this->otherEmployee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::AdministrativeDiscrepancy,
            'justification' => 'Tentativa de gestor operar outro setor',
        ], $this->managerUser);
    }

    /**
     * 14b. Gestor tentando aprovar o próprio settlement é bloqueado por segregação de funções.
     */
    public function test_14b_manager_self_approval_is_blocked_by_segregation_of_duties(): void
    {
        // Cria um vínculo de funcionário para o gestor Roberto
        $managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-GEST-01',
            'cpf' => '99988877766',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($managerEmployee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras gestor',
        ]);

        $settlement = $this->manager->createSettlement([
            'employee_id' => $managerEmployee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::TimeBankBalance,
            'justification' => 'Horas extras do gestor',
        ], $this->admin);

        // Gestor tentando aprovar o próprio saldo
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Segregação de funções: um gestor não pode aprovar ou efetivar a própria quitação.');

        $this->manager->approveSettlement($settlement, $this->managerUser);
    }

    /**
     * 15. Empresa sem banco de horas: destinação das diferenças diretamente em folha sem saldo fictício de banco.
     */
    public function test_15_disabled_time_bank_direct_payroll_destination(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Sem Banco - Folha Direta',
            'modality' => SettlementModality::DirectPayroll,
            'legal_framework' => 'Art. 59 da CLT',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $this->registerDayPunches($this->employee, Carbon::parse('2026-10-15'), '08:00', '18:00'); // 1h extra

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->employeeUser, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNull($data['timeBankSummary']);
        $this->assertStringContainsString('folha de pagamento', $data['differencesSummary']->destinationDescription);
    }

    /**
     * 16. Histórico legado preservado: transações antigas MONTHLY_RESET continuam existindo e são auditadas no extrato.
     */
    public function test_16_legacy_monthly_reset_transactions_are_preserved_and_audited(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Cria transação histórica antiga de zeramento
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-06-15',
            'description' => 'Horas extras junho',
        ]);

        $legacyResetTx = TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::MonthlyReset,
            'minutes' => -120,
            'reference_date' => '2026-06-30',
            'description' => 'Zeramento histórico gravado no sistema legado',
        ]);

        $this->assertNotNull($legacyResetTx->id);

        // Consulta extrato
        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 6);

        $this->assertEquals(-120, $summary->closingResetMinutes);
        $this->assertEquals('-02:00', $summary->formattedClosingReset());

        // Reconciliação audita o zeramento histórico sem apagá-lo
        $reconciliation = app(TimeBankReconciliationService::class)->reconcile(
            $this->employee,
            2026,
            6,
            $summary
        );

        $this->assertTrue($reconciliation->hasLegacyReset);
        $this->assertEquals('legacy_reset', $reconciliation->status);
        $this->assertTrue(collect($reconciliation->issues)->contains(fn ($i) => str_contains($i, 'Zeramento histórico registrado')));
    }
}
