<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\Settlement\Enums\SettlementDischargeType;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
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
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkTimeSettlementPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected WorkSchedule $schedule;

    protected Employee $cltEmployee;

    protected Employee $statutoryEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'PREFEITURA MUNICIPAL DE TESTE',
            'trade_name' => 'Prefeitura Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Administrativa',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sector = Sector::create([
            'name' => 'Gabinete',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Gestor RH',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        // Colaborador CLT
        $userClt = User::create([
            'name' => 'Maria CLT',
            'email' => 'maria.clt@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->cltEmployee = Employee::create([
            'user_id' => $userClt->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'CLT-001',
            'cpf' => '11111111111',
            'job_title' => 'Assistente Administrativo',
            'legal_regime' => LegalRegime::CLT,
        ]);

        // Servidor Estatutário Municipal
        $userStatutory = User::create([
            'name' => 'João Estatutário',
            'email' => 'joao.statutory@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->statutoryEmployee = Employee::create([
            'user_id' => $userStatutory->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'EST-002',
            'cpf' => '22222222222',
            'job_title' => 'Fiscal Tributário',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);
    }

    /**
     * 1. Empresa sem banco de horas:
     * Apuração identifica horas previstas, trabalhadas e extraordinárias,
     * mas nenhum crédito ou débito é lançado no banco de horas.
     */
    public function test_company_without_time_bank_records_daily_differences_without_crediting_or_debiting_bank(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Política Sem Banco de Horas',
            'modality' => SettlementModality::NoBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Regulamento interno da empresa - quitação direta',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02'); // Segunda-feira (escala 08:00 às 17:00, 480 min previstos)
        $this->createPunchesForDay($this->cltEmployee, $date, ['08:00', '12:00', '13:00', '18:00']); // 540 min trabalhados (+60 min de extra)

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->cltEmployee, $date);

        $this->assertEquals(480, $journey->scheduledMinutes);
        $this->assertEquals(540, $journey->workedMinutes);
        $this->assertEquals(60, $journey->overtimeMinutes);

        // Banco de horas NÃO recebe créditos nem débitos
        $this->assertEquals(0, $journey->bankCreditMinutes);
        $this->assertEquals(0, $journey->bankDebitMinutes);

        // Excedente é destinado para controle de folha
        $this->assertEquals(60, $journey->destinedToPayrollMinutes);
        $this->assertEquals(SettlementModality::NoBank, $journey->settlementModality);
    }

    /**
     * 2. Compensação dentro da competência mensal:
     * Positivos e negativos compensam no mês apurado sem acumular para o mês seguinte.
     */
    public function test_monthly_compensation_settles_within_month_without_carrying_over(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Compensação Mensal',
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT, Art. 59, § 6º - Acordo individual tácito para compensação no mesmo mês',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02');
        $this->createPunchesForDay($this->cltEmployee, $date, ['08:00', '12:00', '13:00', '19:00']); // +120 min de extra

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->cltEmployee, $date);

        $this->assertEquals(120, $journey->overtimeMinutes);
        $this->assertEquals(0, $journey->bankCreditMinutes); // Não alimenta banco de transporte
        $this->assertEquals(120, $journey->destinedToCompensationMinutes);
        $this->assertEquals(SettlementModality::MonthlyCompensation, $journey->settlementModality);
    }

    /**
     * 3. Banco de horas acumulativo:
     * Diferenças são creditadas e debitadas no ledger do banco de horas com transporte de saldo.
     */
    public function test_cumulative_bank_credits_and_debits_ledger_with_carry_over(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Acumulativo CLT',
            'modality' => SettlementModality::CumulativeBank,
            'legal_regime' => LegalRegime::CLT,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT, Art. 59, § 5º - Acordo individual escrito de banco de horas (até 6 meses)',
            'compensation_terms' => ['max_months' => 6],
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02');
        $this->createPunchesForDay($this->cltEmployee, $date, ['08:00', '12:00', '13:00', '18:30']); // +90 min de extra

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->cltEmployee, $date);

        $this->assertEquals(90, $journey->overtimeMinutes);
        $this->assertEquals(90, $journey->bankCreditMinutes);
        $this->assertEquals(0, $journey->bankDebitMinutes);
        $this->assertEquals(SettlementModality::CumulativeBank, $journey->settlementModality);
    }

    /**
     * 4. Modelo misto:
     * Até o teto diário alimenta o banco de horas; o saldo excedente é destinado à folha.
     */
    public function test_hybrid_model_splits_overtime_between_bank_and_payroll(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Modelo Misto (Banco até 60 min, Excedente em Folha)',
            'modality' => SettlementModality::Hybrid,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo Coletivo de Trabalho 2026 - Cláusula 14',
            'hybrid_rules' => ['daily_bank_limit_minutes' => 60],
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02');
        $this->createPunchesForDay($this->cltEmployee, $date, ['08:00', '12:00', '13:00', '19:30']); // +150 min de extra

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->cltEmployee, $date);

        $this->assertEquals(150, $journey->overtimeMinutes);
        $this->assertEquals(60, $journey->bankCreditMinutes); // Limite diário no banco
        $this->assertEquals(90, $journey->destinedToPayrollMinutes); // 150 - 60 = 90 min para folha
        $this->assertEquals(SettlementModality::Hybrid, $journey->settlementModality);
    }

    /**
     * 5. Empregado CLT com tolerância do Art. 58, § 1º:
     * Variações de até 5 minutos por batida (limite diário de 10 min) são toleradas e não geram crédito nem débito.
     */
    public function test_clt_employee_applies_art_58_tolerance(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas CLT',
            'modality' => SettlementModality::CumulativeBank,
            'legal_regime' => LegalRegime::CLT,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT, Art. 59, § 5º',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02');
        // Batida com 4 minutos de acréscimo na saída (17:04 em vez de 17:00)
        $this->createPunchesForDay($this->cltEmployee, $date, ['08:00', '12:00', '13:00', '17:04']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->cltEmployee, $date);

        $this->assertEquals(484, $journey->ordinaryMinutes); // 480 previstas + 4 trabalhadas toleradas
        $this->assertEquals(0, $journey->overtimeMinutes); // Tolerado legalmente
        $this->assertEquals(0, $journey->bankCreditMinutes);
    }

    /**
     * 6. Servidor estatutário municipal:
     * O sistema NÃO aplica automaticamente regras da CLT a servidores públicos estatutários.
     * Quando faltar parametrização normativa do ente, apresenta estado pendente de análise jurídica.
     */
    public function test_municipal_statutory_employee_does_not_inherit_clt_rules_blindly(): void
    {
        // Política geral criada na empresa com fundamento unicamente na CLT privada
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Geral da Empresa',
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT, Art. 59, § 5º (Acordo individual)',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-03-02');
        $this->createPunchesForDay($this->statutoryEmployee, $date, ['08:00', '12:00', '13:00', '19:00']); // +120 min

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->statutoryEmployee, $date);

        // Como o servidor é estatutário municipal e a política é CLT, o sistema NÃO credita banco de horas automaticamente
        $this->assertTrue($journey->isPendingSettlement);
        $this->assertEquals(0, $journey->bankCreditMinutes);
        $this->assertStringContainsString('Servidor estatutário', implode(' ', $journey->treatmentNotes));

        // Agora cadastramos política municipal específica aprovada
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Municipal - Lei 1.234/2020',
            'modality' => SettlementModality::CumulativeBank,
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Lei Municipal nº 1.234/2020, Art. 45 - Regime de compensação de horário',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $journeyConfigured = app(CalculateDailyJourneyAction::class)->execute($this->statutoryEmployee, $date);
        $this->assertFalse($journeyConfigured->isPendingSettlement);
        $this->assertEquals(120, $journeyConfigured->bankCreditMinutes);
    }

    /**
     * 7. Precedência determinística:
     * Política do Vínculo > Política do Estabelecimento > Política Geral da Empresa > Fallback Legado.
     */
    public function test_deterministic_precedence_hierarchy(): void
    {
        $date = Carbon::parse('2026-03-02');

        // Política Geral da Empresa: NoBank
        WorkTimeSettlementPolicy::create([
            'name' => 'Empresa - Sem Banco',
            'modality' => SettlementModality::NoBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Geral Empresa',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        // Política do Estabelecimento: MonthlyCompensation
        WorkTimeSettlementPolicy::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Estabelecimento - Compensação Mensal',
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo Unidade',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        // Política Individual do Colaborador: CumulativeBank
        WorkTimeSettlementPolicy::create([
            'employee_id' => $this->cltEmployee->id,
            'name' => 'Individual - Banco Acumulativo',
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT Art. 59 § 5º Individual',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $service = app(WorkTimeSettlementService::class);

        // Colaborador com política individual recebe CumulativeBank
        $policyClt = $service->resolvePolicy($this->cltEmployee, $date);
        $this->assertEquals(SettlementModality::CumulativeBank, $policyClt->modality);
        $this->assertEquals('Individual - Banco Acumulativo', $policyClt->name);

        // Outro colaborador no mesmo estabelecimento (sem política individual) recebe a do Estabelecimento
        $policyOther = $service->resolvePolicy($this->statutoryEmployee, $date);
        $this->assertEquals(SettlementModality::MonthlyCompensation, $policyOther->modality);
    }

    /**
     * 8. Políticas por vigência temporal:
     * Resolução respeita estritamente o intervalo de datas da política.
     */
    public function test_time_based_validity_periods(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Janeiro - Compensação Mensal',
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-01-31',
            'legal_framework' => 'Vigência Janeiro',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        WorkTimeSettlementPolicy::create([
            'name' => 'Fevereiro em diante - Banco Acumulativo',
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-02-01',
            'effective_until' => null,
            'legal_framework' => 'Vigência Fevereiro+',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $service = app(WorkTimeSettlementService::class);

        $janPolicy = $service->resolvePolicy($this->cltEmployee, Carbon::parse('2026-01-15'));
        $this->assertEquals(SettlementModality::MonthlyCompensation, $janPolicy->modality);

        $febPolicy = $service->resolvePolicy($this->cltEmployee, Carbon::parse('2026-02-15'));
        $this->assertEquals(SettlementModality::CumulativeBank, $febPolicy->modality);
    }

    /**
     * 9. Política não configurada (Fallback Legado):
     * O sistema continua funcionando transparente quando a nova política não estiver configurada.
     */
    public function test_unconfigured_policy_falls_back_to_legacy_time_bank_policy(): void
    {
        // Sem WorkTimeSettlementPolicy configurada
        // Criar TimeBankPolicy legada com MONTHLY_RESET ativa
        TimeBankPolicy::create([
            'name' => 'Regra Legada',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => '2026-01-01',
        ]);

        $service = app(WorkTimeSettlementService::class);
        $resolved = $service->resolvePolicy($this->cltEmployee, Carbon::parse('2026-03-01'));

        $this->assertEquals(SettlementModality::LegacyMonthlyReset, $resolved->modality);
    }

    /**
     * 10. Competência fechada preserva snapshot e não é recalculada:
     * Fechar o período consolida a política e os snapshots imutáveis.
     */
    public function test_closed_period_preserves_immutable_settlement_snapshot(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Política Original de Janeiro',
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo Janeiro',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Fechamento de teste'
        );

        $snapshot = $closedPeriod->snapshots()->where('employee_id', $this->cltEmployee->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertArrayHasKey('settlement_policy', $snapshot->time_bank_snapshot);
        $this->assertEquals('Política Original de Janeiro', $snapshot->time_bank_snapshot['settlement_policy']['name']);

        // Se uma nova política for criada posteriormente, o snapshot do período fechado permanece inalterado
        WorkTimeSettlementPolicy::create([
            'name' => 'Nova Política Posterior de Fevereiro',
            'modality' => SettlementModality::NoBank,
            'effective_from' => '2026-02-01',
            'legal_framework' => 'Nova regra',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $snapshot->refresh();
        $this->assertEquals('Política Original de Janeiro', $snapshot->time_bank_snapshot['settlement_policy']['name']);
    }

    /**
     * 11. Compatibilidade com dados legados e quitação documentada:
     * Fechamento sob modo legado MONTHLY_RESET registra transação de zeramento e quitação documentada.
     */
    public function test_legacy_monthly_reset_records_time_bank_transaction_and_discharge(): void
    {
        TimeBankPolicy::create([
            'name' => 'Banco Legado Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => '2026-01-01',
        ]);

        // Simula crédito no banco
        $account = TimeBankAccount::getOrCreateForEmployee($this->cltEmployee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-01-15',
            'description' => 'Horas extras de teste',
        ]);

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        // Transação de MonthlyReset gravada
        $resetTx = TimeBankTransaction::where('type', TimeBankTransactionType::MonthlyReset)->first();
        $this->assertNotNull($resetTx);
        $this->assertEquals(-120, $resetTx->minutes);

        // Registro de quitação WorkTimeSettlementDischarge gravado
        $discharge = WorkTimeSettlementDischarge::where('employee_id', $this->cltEmployee->id)
            ->where('discharge_type', SettlementDischargeType::MonthlyResetLegacy)
            ->first();

        $this->assertNotNull($discharge);
        $this->assertEquals(-120, $discharge->minutes);
        $this->assertEquals('2026-01', $discharge->reference_period);
    }

    protected static int $testNsr = 1;

    /**
     * Helper para criar PunchEvents no dia.
     */
    protected function createPunchesForDay(Employee $employee, Carbon $date, array $times): void
    {
        foreach ($times as $index => $time) {
            $occurredAt = $date->copy()->setTimeFromTimeString($time);
            $nsr = self::$testNsr++;
            PunchEvent::create([
                'id' => (string) Str::ulid(),
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'establishment_id' => $this->establishment->id,
                'nsr' => $nsr,
                'occurred_at_utc' => $occurredAt->copy()->utc(),
                'occurred_at_local' => $occurredAt,
                'direction' => ($index % 2 === 0) ? 'in' : 'out',
                'type' => ($index % 2 === 0) ? 'in' : 'out',
                'fiscal_hash' => hash('sha256', (string) $nsr),
                'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
                'payload_hash' => hash('sha256', 'payload'.$nsr),
            ]);
        }
    }
}
