<?php

namespace Tests\Feature;

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
use App\Models\TimeBankTransaction;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TimeBankHistoricalBalanceAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Employee $employee;

    protected Company $company;

    protected Establishment $establishment;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'legal_name' => 'Tech Corp Tecnologia Ltda',
            'trade_name' => 'Tech Corp Ltda',
            'cnpj' => '12345678000199',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Matriz São Paulo',
            'code' => 'SP-01',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Tecnologia da Informação',
            'code' => 'TI',
        ]);

        $this->schedule = WorkSchedule::create([
            'name' => 'Escala Administrativa 40h',
            'code' => 'ADM-40H',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
        ]);

        $this->user = User::create([
            'name' => 'Mariana Oliveira',
            'email' => 'mariana@techcorp.com.br',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'EMP-777',
            'cpf' => '11122233344',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    /**
     * 1. Banco desativado: não mostra card escuro de banco com saldo zero fictício,
     * e exibe destinação direta para folha de pagamento.
     */
    public function test_disabled_time_bank_does_not_display_time_bank_card_and_shows_payroll_destination(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Política Sem Banco de Horas',
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Art. 59 da CLT e Acordo Coletivo 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $this->employee->id,
            'user_id' => $this->user->id,
            'nsr' => 1,
            'occurred_at_utc' => Carbon::parse('2026-10-15 08:00:00')->utc(),
            'occurred_at_local' => Carbon::parse('2026-10-15 08:00:00'),
            'direction' => 'in',
            'fiscal_hash' => hash('sha256', 'nsr1'),
            'audit_chain_hash' => hash('sha256', 'chain1'),
            'payload_hash' => hash('sha256', 'payload1'),
        ]);

        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $this->employee->id,
            'user_id' => $this->user->id,
            'nsr' => 2,
            'occurred_at_utc' => Carbon::parse('2026-10-15 17:00:00')->utc(),
            'occurred_at_local' => Carbon::parse('2026-10-15 17:00:00'),
            'direction' => 'out',
            'fiscal_hash' => hash('sha256', 'nsr2'),
            'audit_chain_hash' => hash('sha256', 'chain2'),
            'payload_hash' => hash('sha256', 'payload2'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNull($data['timeBankSummary']);
        $this->assertStringContainsString('sem banco de horas', $data['differencesSummary']->destinationDescription);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertDontSee('Regra de apuração:')
            ->assertSee('Resumo das diferenças de jornada');
    }

    /**
     * 2. Banco acumulativo: preserva saldo em transporte contínuo e histórico.
     */
    public function test_cumulative_bank_preserves_running_balance_and_distinct_destinations(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Acumulativo Semestral',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'CLT Art. 59 §2º e CCT 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Saldo anterior de setembro (+120 min = +02:00)
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-09-20',
            'description' => 'Horas extras de setembro',
        ]);

        // Movimentação de outubro (+60 min = +01:00)
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-15',
            'description' => 'Horas extras de outubro',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $this->assertEquals(120, $summary->previousBalanceMinutes);
        $this->assertEquals('+02:00', $summary->formattedPreviousBalance());
        $this->assertEquals(60, $summary->monthCreditsMinutes);
        $this->assertEquals('01:00', $summary->formattedMonthCredits());
        $this->assertEquals(180, $summary->closingBalanceMinutes);
        $this->assertEquals('+03:00', $summary->formattedClosingBalance());
    }

    /**
     * 3. Compensação mensal: identifica o que foi compensado, quitado e pendente.
     */
    public function test_monthly_compensation_identifies_compensated_settled_and_pending_amounts(): void
    {
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Acordo de Compensação Mensal',
            'modality' => SettlementModality::MonthlyCompensation,
            'legal_framework' => 'CLT Art. 59 §6º',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Criar quitação e pendência para o período 2026-10
        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'status' => WorkTimeSettlementStatus::Executed,
            'origin_type' => SettlementOriginType::DailyJourney,
            'operation_date' => '2026-10-25',
            'justification' => 'Compensação concedida em folga acordada',
        ]);

        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 45,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'status' => WorkTimeSettlementStatus::Pending,
            'origin_type' => SettlementOriginType::DailyJourney,
            'operation_date' => '2026-10-31',
            'justification' => 'Horas residuais pendentes de inclusão na folha',
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertEquals(60, $data['differencesSummary']->settledMinutes);
        $this->assertEquals(45, $data['differencesSummary']->pendingSettlementMinutes);
    }

    /**
     * 4. Crédito e débito na mesma competência mantidos separados sem mesclagem indevida.
     */
    public function test_credits_and_debits_in_same_competence(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 150, // +02:30
            'reference_date' => '2026-10-05',
            'description' => 'Horas extras projeto',
        ]);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::CompensationDebit,
            'minutes' => -60, // -01:00
            'reference_date' => '2026-10-18',
            'description' => 'Saída antecipada acordada',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $this->assertEquals(150, $summary->monthCreditsMinutes);
        $this->assertEquals(-60, $summary->monthDebitsMinutes);
        $this->assertEquals(90, $summary->closingBalanceMinutes);
        $this->assertEquals('+01:30', $summary->formattedClosingBalance());
    }

    /**
     * 5. Saldo final negativo assinado no banco de horas.
     */
    public function test_negative_final_balance(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::CompensationDebit,
            'minutes' => -90, // -01:30
            'reference_date' => '2026-10-10',
            'description' => 'Atrasos compensáveis',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $this->assertEquals(-90, $summary->closingBalanceMinutes);
        $this->assertEquals('-01:30', $summary->formattedClosingBalance());
        $this->assertEquals('-01:30', $summary->formattedMonthDebits());
    }

    /**
     * 6. Saldo final positivo assinado no banco de horas.
     */
    public function test_positive_final_balance(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 165, // +02:45
            'reference_date' => '2026-10-12',
            'description' => 'Plantão extra',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $this->assertEquals(165, $summary->closingBalanceMinutes);
        $this->assertEquals('+02:45', $summary->formattedClosingBalance());
        $this->assertEquals('02:45', $summary->formattedMonthCredits());
    }

    /**
     * 7. Destinação parcial de saldo.
     */
    public function test_partial_settlement_of_competence_balance(): void
    {
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Flex',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'CCT 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Saldo total de 180 min, quitação de 100 min, resta 80 min pendente
        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 100,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'status' => WorkTimeSettlementStatus::Executed,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'operation_date' => '2026-10-20',
            'justification' => 'Quitação parcial em folga',
        ]);

        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 80,
            'settlement_type' => WorkTimeSettlementType::CarryOver,
            'status' => WorkTimeSettlementStatus::Pending,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'operation_date' => '2026-10-20',
            'justification' => 'Saldo residual pendente de transporte',
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertEquals(100, $data['differencesSummary']->settledMinutes);
        $this->assertEquals(80, $data['differencesSummary']->pendingSettlementMinutes);
    }

    /**
     * 8. Pagamento em folha ainda não confirmado (AwaitingExecution) não é tratado como quitação efetuada.
     */
    public function test_payroll_payment_awaiting_execution_is_not_treated_as_settled(): void
    {
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Regime de Horas Extras em Folha',
            'modality' => SettlementModality::DirectPayroll,
            'legal_framework' => 'CLT Art. 59',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'status' => WorkTimeSettlementStatus::AwaitingExecution,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'operation_date' => '2026-10-31',
            'justification' => 'Enviado para a folha de pagamento de novembro',
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        // AwaitingExecution NÃO entra em settledMinutes (apenas Executed entra)
        $this->assertEquals(0, $data['differencesSummary']->settledMinutes);
    }

    /**
     * 9. Quitação confirmada via WorkTimeSettlementDischarge.
     */
    public function test_executed_discharge_records_formal_settlement(): void
    {
        $discharge = WorkTimeSettlementDischarge::create([
            'employee_id' => $this->employee->id,
            'discharge_type' => SettlementDischargeType::PayrollPaid,
            'minutes' => 90,
            'reference_period' => '2026-10',
            'execution_date' => '2026-10-30',
            'document_reference' => 'HOLERITE-10/2026',
            'description' => 'Horas extras quitadas na folha',
            'approved_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertNotNull($discharge->id);
        $this->assertEquals('+01:30', $discharge->formattedMinutes());
    }

    /**
     * 10. Estorno registrado em WorkTimeSettlement é auditado e listado pelo reconciliation service.
     */
    public function test_reversal_settlement_is_audited_by_reconciliation(): void
    {
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Banco CCT',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'CCT 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        WorkTimeSettlement::create([
            'employee_id' => $this->employee->id,
            'settlement_policy_id' => $policy->id,
            'reference_period' => '2026-10',
            'minutes' => 60,
            'settlement_type' => WorkTimeSettlementType::TimeOffCompensation,
            'status' => WorkTimeSettlementStatus::Reversed,
            'origin_type' => SettlementOriginType::DailyJourney,
            'operation_date' => '2026-10-15',
            'justification' => 'Estorno por cancelamento da folga programada',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $reconciliation = app(TimeBankReconciliationService::class)->reconcile($this->employee, 2026, 10, $summary);

        $this->assertNotEmpty($reconciliation->issues);
        $this->assertTrue(collect($reconciliation->issues)->contains(fn ($i) => str_contains($i, 'Estorno registrado')));
    }

    /**
     * 11. Zeramento legado MONTHLY_RESET: preservado e exibido como zeramento histórico,
     * sem afirmar que houve quitação fiscal comprovada.
     */
    public function test_legacy_monthly_reset_is_displayed_as_historic_event_without_fictitious_payment_evidence(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Lançamento com saldo e posterior zeramento legado
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-10-10',
            'description' => 'Horas extras',
        ]);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::MonthlyReset,
            'minutes' => -120,
            'reference_date' => '2026-10-31',
            'description' => 'Zeramento legado de fechamento',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $this->assertEquals(-120, $summary->closingResetMinutes);
        $this->assertEquals('-02:00', $summary->formattedClosingReset());
        $this->assertEquals(0, $summary->closingBalanceMinutes);

        $reconciliation = app(TimeBankReconciliationService::class)->reconcile($this->employee, 2026, 10, $summary);

        $this->assertTrue($reconciliation->hasLegacyReset);
        $this->assertEquals('legacy_reset', $reconciliation->status);
        $this->assertTrue(collect($reconciliation->issues)->contains(fn ($i) => str_contains($i, 'Zeramento histórico registrado')));
    }

    /**
     * 12. Período fechado: lê dados congelados do snapshot sem recalcular pelo ledger vivo.
     */
    public function test_closed_period_uses_immutable_snapshot_without_rebuilding_from_live_ledger(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->user->id,
            'closed_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Mariana Oliveira'],
            'schedule_snapshot' => ['name' => 'Escala 40h', 'modality' => 'standard'],
            'treatment_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2026-09-15',
                    'scheduled_minutes' => 480,
                    'worked_minutes' => 540,
                    'is_incomplete' => false,
                    'has_schedule' => true,
                    'daily_difference_minutes' => 60,
                    'effective_punches' => [
                        ['type' => 'in', 'time' => '08:00'],
                        ['type' => 'out', 'time' => '17:00'],
                    ],
                ],
            ],
            'time_bank_snapshot' => [
                'policy_mode' => 'CARRY_OVER',
                'balance_before' => 60,
                'balance_at_closing' => 120,
                'reset_applied' => 0,
                'final_balance' => 120,
                'transactions' => [
                    [
                        'id' => 'tx-snap-1',
                        'reference_date' => '2026-09-15',
                        'minutes' => 60,
                        'type' => 'overtime_credit',
                    ],
                ],
                'settlement_policy' => ['name' => 'Banco Acumulativo Congelado'],
            ],
            'snapshot_hash' => hash('sha256', 'snap-test'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 9);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertNotNull($data['timeBankSummary']);
        $this->assertEquals(60, $data['timeBankSummary']->previousBalanceMinutes);
        $this->assertEquals(120, $data['timeBankSummary']->closingBalanceMinutes);
        $this->assertEquals('+02:00', $data['timeBankSummary']->formattedClosingBalance());
        $this->assertTrue($data['timeBankSummary']->isClosedPeriod);
    }

    /**
     * 13. Consulta a mês antigo: resumo de outubro não incorpora transações de novembro ou dezembro.
     */
    public function test_querying_past_month_does_not_incorporate_future_transactions(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Outubro: +60 min
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 60,
            'reference_date' => '2026-10-15',
            'description' => 'Horas extras outubro',
        ]);

        // Novembro: +180 min
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 180,
            'reference_date' => '2026-11-05',
            'description' => 'Horas extras novembro',
        ]);

        // Dezembro: +120 min
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-12-10',
            'description' => 'Horas extras dezembro',
        ]);

        $statementService = app(TimeBankStatementService::class);

        // Consultar OUTUBRO
        $octoberSummary = $statementService->getMonthlySummary($this->employee, 2026, 10);
        $this->assertEquals(60, $octoberSummary->closingBalanceMinutes);
        $this->assertEquals('+01:00', $octoberSummary->formattedClosingBalance());
        $this->assertEquals(360, $octoberSummary->todayBalanceMinutes); // Saldo geral hoje é 360, mas o saldo de outubro é estritamente 60!

        // Consultar NOVEMBRO
        $novemberSummary = $statementService->getMonthlySummary($this->employee, 2026, 11);
        $this->assertEquals(60, $novemberSummary->previousBalanceMinutes);
        $this->assertEquals(180, $novemberSummary->monthCreditsMinutes);
        $this->assertEquals(240, $novemberSummary->closingBalanceMinutes);
    }

    /**
     * 14. Transação gravada após o fechamento formal é flaggada pelo TimeBankReconciliationService.
     */
    public function test_post_closing_transaction_is_flagged_by_reconciliation(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 8,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->user->id,
            'closed_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Transação criada em 15/09 referenciando agosto (após o fechamento formal de 01/09)
        $tx = TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::ManualCredit,
            'minutes' => 30,
            'reference_date' => '2026-08-20',
            'description' => 'Ajuste retroativo pós-fechamento',
        ]);
        $tx->created_at = Carbon::parse('2026-09-15 14:00:00');
        $tx->save();

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 8);

        $reconciliation = app(TimeBankReconciliationService::class)->reconcile(
            $this->employee,
            2026,
            8,
            $summary,
            null,
            $closedPeriod
        );

        $this->assertTrue($reconciliation->hasPostClosingTransactions);
        $this->assertEquals('warning', $reconciliation->status);
        $this->assertTrue(collect($reconciliation->issues)->contains(fn ($i) => str_contains($i, 'Lançamento posterior ao fechamento')));
    }

    /**
     * 15. Operação duplicada no mesmo dia com mesmos minutos é flaggada na conciliação.
     */
    public function test_duplicate_transaction_is_flagged_by_reconciliation(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 45,
            'reference_date' => '2026-10-14',
            'description' => 'Horas extras',
        ]);

        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 45,
            'reference_date' => '2026-10-14',
            'description' => 'Horas extras (inserção duplicada acidental)',
        ]);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $reconciliation = app(TimeBankReconciliationService::class)->reconcile($this->employee, 2026, 10, $summary);

        $this->assertTrue($reconciliation->hasDuplicates);
        $this->assertEquals('warning', $reconciliation->status);
        $this->assertTrue(collect($reconciliation->issues)->contains(fn ($i) => str_contains($i, 'Operação potencialmente duplicada')));
    }

    /**
     * 16. Snapshot histórico legado sem detalhamento sinaliza limitação histórica sem inventar saldos.
     */
    public function test_legacy_snapshot_without_time_bank_detail_signals_historical_limitation_without_inventing_zero(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2025,
            'month' => 6,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->user->id,
            'closed_at' => Carbon::parse('2025-07-01 10:00:00'),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Mariana Oliveira'],
            'schedule_snapshot' => ['name' => 'Escala 40h', 'modality' => 'standard'],
            'treatment_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2025-06-10',
                    'scheduled_minutes' => 480,
                    'worked_minutes' => 480,
                    'is_incomplete' => false,
                    'effective_punches' => [
                        ['type' => 'in', 'time' => '08:00'],
                        ['type' => 'out', 'time' => '17:00'],
                    ],
                ],
            ],
            'time_bank_snapshot' => [], // Snapshot legado vazio
            'snapshot_hash' => hash('sha256', 'legacy-snap'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2025, 6);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertNotNull($data['timeBankSummary']);
        $this->assertTrue($data['timeBankSummary']->isHistoricalLimitation);
        $this->assertStringContainsString('legado não contém detalhamento', $data['timeBankSummary']->historicalLimitationMessage);
    }

    /**
     * 17. Garantia de que consultas ao espelho e ao banco de horas são estritamente de leitura
     * e nunca criam novos registros no banco de dados.
     */
    public function test_statement_and_reconciliation_queries_do_not_produce_new_database_records(): void
    {
        $initialTxCount = TimeBankTransaction::count();
        $initialSettlementCount = WorkTimeSettlement::count();

        $service = app(TimesheetJourneyService::class);
        $service->resolveMonthData($this->user, 2026, 10);

        $statementService = app(TimeBankStatementService::class);
        $summary = $statementService->getMonthlySummary($this->employee, 2026, 10);

        $reconciliation = app(TimeBankReconciliationService::class)->reconcile($this->employee, 2026, 10, $summary);

        $this->assertEquals($initialTxCount, TimeBankTransaction::count(), 'Consulta ao extrato não pode inserir transações.');
        $this->assertEquals($initialSettlementCount, WorkTimeSettlement::count(), 'Conciliação não pode criar destinações.');
    }
}
