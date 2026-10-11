<?php

namespace Tests\Feature;

use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Domain\Settlement\Services\WorkTimeSettlementManager;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use App\Models\TimeBankTransaction;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTimeSettlementAuditingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Employee $employee;

    protected WorkSchedule $standardSchedule;

    protected WorkTimeSettlementManager $settlementManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin.settlement@test.com',
        ]);

        $this->standardSchedule = WorkSchedule::create([
            'name' => 'Comercial Padrão 40h',
            'monday_entry' => '08:00',
            'monday_exit' => '17:00',
            'monday_interval_minutes' => 60,
            'tuesday_entry' => '08:00',
            'tuesday_exit' => '17:00',
            'tuesday_interval_minutes' => 60,
            'wednesday_entry' => '08:00',
            'wednesday_exit' => '17:00',
            'wednesday_interval_minutes' => 60,
            'thursday_entry' => '08:00',
            'thursday_exit' => '17:00',
            'thursday_interval_minutes' => 60,
            'friday_entry' => '08:00',
            'friday_exit' => '17:00',
            'friday_interval_minutes' => 60,
            'weekly_hours' => 40,
        ]);

        $empUser = User::factory()->create(['name' => 'Colaborador Teste']);
        $this->employee = Employee::create([
            'user_id' => $empUser->id,
            'cpf' => '11122233344',
            'registration_number' => 'MAT-SETTLE-01',
            'job_title' => 'Analista Administrativo',
            'legal_regime' => LegalRegime::CLT,
            'work_schedule_id' => $this->standardSchedule->id,
            'weekly_workload_minutes' => 2400,
            'daily_workload_minutes' => 480,
            'admission_date' => '2026-01-01',
        ]);

        $this->settlementManager = app(WorkTimeSettlementManager::class);
    }

    /**
     * 1. Saldo positivo: registra valor assinado credor (+120 min) preservando natureza e sinal.
     */
    public function test_saldo_positivo_destinacao_credora(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'justification' => 'Horas extras trabalhadas em projeto',
        ], $this->admin);

        $this->assertEquals(120, $settlement->minutes);
        $this->assertTrue($settlement->isCredit());
        $this->assertFalse($settlement->isDebit());
        $this->assertEquals('+02:00', $settlement->formattedMinutes());
        $this->assertEquals(WorkTimeSettlementStatus::Pending, $settlement->status);
    }

    /**
     * 2. Saldo negativo: registra valor assinado devedor (-45 min) preservando natureza devedora.
     */
    public function test_saldo_negativo_destinacao_devedora(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => -45,
            'settlement_type' => WorkTimeSettlementType::DeficitCompensation,
            'origin_type' => SettlementOriginType::DailyJourney,
            'justification' => 'Atraso tolerado para compensação autorizada',
        ], $this->admin);

        $this->assertEquals(-45, $settlement->minutes);
        $this->assertTrue($settlement->isDebit());
        $this->assertFalse($settlement->isCredit());
        $this->assertEquals('-00:45', $settlement->formattedMinutes());
        $this->assertEquals(WorkTimeSettlementStatus::Pending, $settlement->status);
    }

    /**
     * 3. Compensação parcial e conservação de valores:
     * Destina parcialmente +120 min de uma origem com +180 min; tentativa de destinar além de +60 min remanescentes é bloqueada.
     */
    public function test_compensacao_parcial_conservacao_de_valores(): void
    {
        $first = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 180,
            'justification' => 'Primeira parcela de folga compensatória',
        ], $this->admin);

        $this->assertEquals(120, $first->minutes);

        // Tentativa de destinar +70 minutos (totalizaria 190 min, excedendo os 180 min de origem)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('excede o saldo disponível de origem');

        $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 70,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 180,
            'justification' => 'Tentativa de exceder saldo remanescente',
        ], $this->admin);
    }

    /**
     * 4. Pagamento aprovado e não executado:
     * Aprovação não significa pagamento realizado nem quitação fática.
     */
    public function test_pagamento_aprovado_nao_executado_nao_quita_nem_apaga_saldo(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'justification' => 'Horas extraordinárias aprovadas pela chefia',
        ], $this->admin);

        $this->settlementManager->approveSettlement($settlement, $this->admin, 'Aprovado pelo gestor do setor');

        $settlement->refresh();
        $this->assertEquals(WorkTimeSettlementStatus::Approved, $settlement->status);
        $this->assertNull($settlement->execution_date);
        $this->assertNull($settlement->document_reference);
        $this->assertEquals(90, $this->settlementManager->getPendingMinutes($this->employee, '2026-10'));
        $this->assertEquals(0, $this->settlementManager->getExecutedMinutes($this->employee, '2026-10'));
    }

    /**
     * 5. Pagamento confirmado:
     * Transição para Executed com data e referência documental de holerite.
     */
    public function test_pagamento_confirmado_com_comprovante_documental(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 90,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'status' => WorkTimeSettlementStatus::Approved,
            'justification' => 'Horas extras aprovadas para folha',
        ], $this->admin);

        $this->settlementManager->confirmExecution(
            settlement: $settlement,
            actor: $this->admin,
            executionDate: '2026-11-05',
            documentReference: 'HOLERITE-2026-10-FOLHA-982',
            notes: 'Pagamento creditado em conta bancária na folha de Outubro'
        );

        $settlement->refresh();
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $settlement->status);
        $this->assertEquals('2026-11-05', $settlement->execution_date->toDateString());
        $this->assertEquals('HOLERITE-2026-10-FOLHA-982', $settlement->document_reference);
        $this->assertEquals(90, $this->settlementManager->getExecutedMinutes($this->employee, '2026-10'));

        // Verifica quitação documentada correspondente
        $discharge = WorkTimeSettlementDischarge::where('employee_id', $this->employee->id)
            ->where('document_reference', 'HOLERITE-2026-10-FOLHA-982')
            ->first();
        $this->assertNotNull($discharge);
        $this->assertEquals(90, $discharge->minutes);
    }

    /**
     * 6. Folga concedida e efetivamente usufruída:
     * Compensação por descanso confirmada com comprovação de gozo.
     */
    public function test_folga_concedida_e_efetivamente_usufruida(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 480,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'status' => WorkTimeSettlementStatus::Approved,
            'justification' => 'Folga de 1 dia integral (8 horas)',
        ], $this->admin);

        $this->settlementManager->confirmExecution(
            settlement: $settlement,
            actor: $this->admin,
            executionDate: '2026-11-14',
            documentReference: 'PORTARIA-FOLGA-142/2026',
            notes: 'Descanso usufruído conforme escala autorizada'
        );

        $settlement->refresh();
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $settlement->status);
        $this->assertEquals('2026-11-14', $settlement->execution_date->toDateString());
        $this->assertEquals('PORTARIA-FOLGA-142/2026', $settlement->document_reference);
    }

    /**
     * 7. Zeramento sem comprovação:
     * Sob política MONTHLY_RESET, o fechamento mensal NÃO zera o saldo no banco sem autorização prévia;
     * o saldo permanece no banco e fica registrado como Pendente de quitação.
     */
    public function test_zeramento_sem_comprovacao_nao_elimina_saldo_e_permanece_pendente(): void
    {
        // Política moderna com modalidade LegacyMonthlyReset
        WorkTimeSettlementPolicy::create([
            'name' => 'Política Teste Zeramento',
            'employee_id' => $this->employee->id,
            'modality' => SettlementModality::LegacyMonthlyReset,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo Coletivo 2026',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 180,
            'reference_date' => '2026-01-15',
            'description' => 'Horas extras de teste',
        ]);

        $this->assertEquals(180, $account->currentBalance());

        // Fechamento SEM destinação aprovada prévia
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        // O saldo no banco de horas NÃO foi zerado! Permanece intacto em +180 min
        $account->refresh();
        $this->assertEquals(180, $account->currentBalance());

        // Foi criado um WorkTimeSettlement com status Pendente
        $pendingSettlement = WorkTimeSettlement::where('employee_id', $this->employee->id)
            ->where('reference_period', '2026-01')
            ->where('status', WorkTimeSettlementStatus::Pending)
            ->first();

        $this->assertNotNull($pendingSettlement);
        $this->assertEquals(180, $pendingSettlement->minutes);
        $this->assertStringContainsString('sem destinação comprovada', $pendingSettlement->justification);
    }

    /**
     * 8. Quitação parcial e conservação remanescente:
     */
    public function test_quitacao_parcial_e_conservacao_remanescente(): void
    {
        // Cria settlement 1 (+120 min) e quita
        $settlement1 = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'status' => WorkTimeSettlementStatus::Approved,
            'justification' => 'Primeira parcela',
        ], $this->admin);

        $this->settlementManager->confirmExecution($settlement1, $this->admin, '2026-11-05', 'RECIBO-PARCIAL-1');

        // Cria settlement 2 (+60 min) pendente
        $settlement2 = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'justification' => 'Segunda parcela pendente',
        ], $this->admin);

        $this->assertEquals(120, $this->settlementManager->getExecutedMinutes($this->employee, '2026-10'));
        $this->assertEquals(60, $this->settlementManager->getPendingMinutes($this->employee, '2026-10'));
    }

    /**
     * 9. Transporte de saldo (CarryOver):
     */
    public function test_transporte_de_saldo_em_banco_acumulativo(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco Acumulativo 2026',
            'employee_id' => $this->employee->id,
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT Art. 59 § 2º',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 240,
            'reference_date' => '2026-01-20',
            'description' => 'Saldo para transporte',
        ]);

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $carryOverSettlement = WorkTimeSettlement::where('employee_id', $this->employee->id)
            ->where('reference_period', '2026-01')
            ->where('settlement_type', WorkTimeSettlementType::CarryOver)
            ->first();

        $this->assertNotNull($carryOverSettlement);
        $this->assertEquals(240, $carryOverSettlement->minutes);
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $carryOverSettlement->status);
        $this->assertEquals(240, $account->currentBalance());
    }

    /**
     * 10. Modelo misto (Hybrid) sem dupla contabilização:
     */
    public function test_modelo_misto_sem_dupla_contabilizacao(): void
    {
        $settlementBank = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::CarryOver,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 120,
            'justification' => 'Cota máxima mensal para o banco',
        ], $this->admin);

        $settlementPayroll = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 120,
            'justification' => 'Excedente destinado à folha de pagamento',
        ], $this->admin);

        $this->assertEquals(60, $settlementBank->minutes);
        $this->assertEquals(60, $settlementPayroll->minutes);

        // Terceira tentativa é rejeitada por extrapolar os 120 min totais
        $this->expectException(DomainException::class);
        $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 10,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 120,
            'justification' => 'Excesso não autorizado',
        ], $this->admin);
    }

    /**
     * 11. Tentativa duplicada bloqueada por idempotência:
     */
    public function test_tentativa_duplicada_bloqueada_por_idempotencia(): void
    {
        $key = 'IDEMP-TEST-2026-10-ABC';

        $s1 = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 75,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'idempotency_key' => $key,
            'justification' => 'Primeira submissão',
        ], $this->admin);

        $s2 = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 75,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'idempotency_key' => $key,
            'justification' => 'Tentativa de submissão concorrente/duplicada',
        ], $this->admin);

        $this->assertEquals($s1->id, $s2->id);
        $this->assertEquals(1, WorkTimeSettlement::where('idempotency_key', $key)->count());
    }

    /**
     * 12. Estorno auditável com operação compensatória:
     */
    public function test_estorno_auditavel_com_operacao_compensatoria(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::DailyJourney,
            'status' => WorkTimeSettlementStatus::Approved,
            'justification' => 'Horas extras a pagar',
        ], $this->admin);

        $this->settlementManager->confirmExecution($settlement, $this->admin, '2026-11-05', 'HOLERITE-ERRONEO');

        // Estorno auditável
        $reversal = $this->settlementManager->reverseSettlement(
            settlement: $settlement,
            actor: $this->admin,
            justification: 'Horas extras lançadas indevidamente por erro de apontamento',
            documentReference: 'ESTORNO-DOC-001'
        );

        $settlement->refresh();
        $this->assertEquals(WorkTimeSettlementStatus::Reversed, $settlement->status);

        $this->assertNotNull($reversal);
        $this->assertEquals(-60, $reversal->minutes); // Inverte exatamente o valor
        $this->assertEquals($settlement->id, $reversal->reversal_of_id);
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $reversal->status);
        $this->assertEquals('ESTORNO-DOC-001', $reversal->document_reference);
    }

    /**
     * 13. Concorrência e integridade transacional:
     */
    public function test_concorrencia_e_integridade_transacional(): void
    {
        $this->expectException(DomainException::class);

        // Simula bloqueio de saldo disponível quando a soma dos dois exceder o limite
        $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 100,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 150,
            'justification' => 'Reserva de lote 1',
        ], $this->admin);

        $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'available_origin_minutes' => 150,
            'justification' => 'Reserva de lote 2 concorrente',
        ], $this->admin);
    }

    /**
     * 14. Competência fechada registra pendências sem declarar falso quitado:
     */
    public function test_competencia_fechada_registra_pendencias_sem_falso_quitado(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Compensação Mensal',
            'employee_id' => $this->employee->id,
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT Art. 59 § 6º',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 90,
            'reference_date' => '2026-01-22',
            'description' => 'Saldo não compensado no mês',
        ]);

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($snapshot);
        $tbSnap = $snapshot->time_bank_snapshot;
        $this->assertEquals(90, $tbSnap['pending_settlements_minutes']);
        $this->assertEquals(0, $tbSnap['executed_settlements_minutes']);
    }

    /**
     * 15. Operação posterior ao fechamento não altera o snapshot histórico:
     */
    public function test_operacao_posterior_ao_fechamento_nao_altera_snapshot_historico(): void
    {
        $settlement = $this->settlementManager->createSettlement([
            'employee_id' => $this->employee->id,
            'reference_period' => '2026-01',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'status' => WorkTimeSettlementStatus::Approved,
            'justification' => 'Pagamento a ser feito no mês seguinte',
        ], $this->admin);

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $originalHash = $snapshot->snapshot_hash;
        $originalPayload = json_encode($snapshot->time_bank_snapshot);

        // Operação executada dias depois do fechamento formal
        $this->settlementManager->confirmExecution($settlement, $this->admin, '2026-02-10', 'RECIBO-FEV-PAGO');

        $snapshot->refresh();
        $this->assertEquals($originalHash, $snapshot->snapshot_hash);
        $this->assertEquals($originalPayload, json_encode($snapshot->time_bank_snapshot));
    }

    /**
     * 16. Empresa sem banco de horas apura sem criar transações bancárias:
     */
    public function test_empresa_sem_banco_apura_sem_criar_transacoes_bancarias(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Regime Sem Banco',
            'employee_id' => $this->employee->id,
            'modality' => SettlementModality::NoBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Jornada Padrão sem Compensação',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $initialTxCount = TimeBankTransaction::where('time_bank_account_id', $account->id)->count();

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        // Nenhuma transação bancária foi criada
        $this->assertEquals($initialTxCount, TimeBankTransaction::where('time_bank_account_id', $account->id)->count());
        $this->assertEquals(0, $account->currentBalance());
    }

    /**
     * 17. Legado MONTHLY_RESET preserva transações anteriores sem reinterpretação retroativa:
     */
    public function test_legado_monthly_reset_preserva_operacoes_anteriores(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Transação antiga de MONTHLY_RESET simulando fechamento passado
        $oldReset = TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::MonthlyReset,
            'minutes' => -120,
            'reference_date' => '2025-12-31',
            'description' => 'Zeramento legado de 2025-12',
        ]);

        $this->assertDatabaseHas('time_bank_transactions', [
            'id' => $oldReset->id,
            'type' => TimeBankTransactionType::MonthlyReset->value,
            'minutes' => -120,
        ]);

        // Fechamento de novo período não altera a transação antiga
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $oldReset->refresh();
        $this->assertEquals(-120, $oldReset->minutes);
        $this->assertEquals(TimeBankTransactionType::MonthlyReset, $oldReset->type);
    }
}
