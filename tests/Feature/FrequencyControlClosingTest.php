<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FrequencyControlClosingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $adminUser;

    protected WorkSchedule $standardSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa Frequência Sem Banco Ltda',
            'trade_name' => 'Frequência Pura',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Operacional',
            'code' => 'EST-01',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Curitiba',
            'state' => 'PR',
        ]);

        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operações',
            'code' => 'OPS-01',
        ]);

        $this->adminUser = User::create([
            'name' => 'Gestor RH',
            'email' => 'gestor.frequencia@empresa.com',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Admin,
        ]);

        $this->standardSchedule = WorkSchedule::create([
            'name' => 'Escala Administrativa 40h',
            'code' => 'ADM-40H',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => [
                'monday' => ['enabled' => true, 'expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]],
                'tuesday' => ['enabled' => true, 'expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]],
                'wednesday' => ['enabled' => true, 'expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]],
                'thursday' => ['enabled' => true, 'expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]],
                'friday' => ['enabled' => true, 'expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]],
                'saturday' => ['enabled' => false, 'expected_minutes' => 0, 'periods' => []],
                'sunday' => ['enabled' => false, 'expected_minutes' => 0, 'periods' => []],
            ],
        ]);
    }

    protected function createEmployee(string $name, string $cpf): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@empresa.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $this->standardSchedule->id,
            'registration_number' => 'MAT-'.rand(1000, 9999),
            'cpf' => $cpf,
            'job_title' => 'Analista',
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
     * ACEITE 1: Empresa sem banco registra, apura, confere e fecha normalmente.
     * Diferenças positivas e negativas permanecem identificáveis.
     * Nenhuma operação financeira/PayrollPayment é criada pelo fechamento.
     */
    public function test_empresa_sem_banco_registra_apura_confere_e_fecha_normalmente(): void
    {
        $employee = $this->createEmployee('Carla Silva', '12345678901');

        WorkTimeSettlementPolicy::create([
            'company_id' => $this->company->id,
            'name' => 'Regime Exclusivo Frequência (Sem Banco)',
            'modality' => SettlementModality::NoBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Jornada Padrão sem Banco de Horas',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Dia 1 (quinta 2026-10-01): 08:00-12:00, 13:00-19:00 = 10h (600 min trabalhados = +120 min de diferença positiva)
        $day1 = Carbon::parse('2026-10-01');
        $this->registerPunch($employee, $day1->copy()->setTime(8, 0), 'in');
        $this->registerPunch($employee, $day1->copy()->setTime(12, 0), 'out');
        $this->registerPunch($employee, $day1->copy()->setTime(13, 0), 'in');
        $this->registerPunch($employee, $day1->copy()->setTime(19, 0), 'out');

        // Dia 2 (sexta 2026-10-02): 08:45-12:00, 13:00-17:00 = 7h15 (435 min trabalhados = -45 min de diferença negativa/atraso)
        $day2 = Carbon::parse('2026-10-02');
        $this->registerPunch($employee, $day2->copy()->setTime(8, 45), 'in');
        $this->registerPunch($employee, $day2->copy()->setTime(12, 0), 'out');
        $this->registerPunch($employee, $day2->copy()->setTime(13, 0), 'in');
        $this->registerPunch($employee, $day2->copy()->setTime(17, 0), 'out');

        // 1. Apuração diária e conferência
        $journeyCalc = app(CalculateDailyJourneyAction::class);
        $j1 = $journeyCalc->execute($employee, $day1);
        $j2 = $journeyCalc->execute($employee, $day2);

        $this->assertEquals(600, $j1->workedMinutes);
        $this->assertEquals(480, $j1->scheduledMinutes);
        $this->assertEquals(120, $j1->dailyDifferenceMinutes());
        $this->assertEquals('Diferença: +120 minutos.', $j1->humanDifference());

        $this->assertEquals(435, $j2->workedMinutes);
        $this->assertEquals(480, $j2->scheduledMinutes);
        $this->assertEquals(-45, $j2->dailyDifferenceMinutes());
        $this->assertEquals('Diferença: -45 minutos.', $j2->humanDifference());

        // 2. Fechamento mensal de frequência
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->adminUser,
            notes: 'Fechamento de conferência de frequência de Outubro/2026'
        );

        $this->assertEquals('closed', $closedPeriod->status);

        // 3. Critério de Aceite: Nenhuma operação financeira / PayrollPayment executada pelo fechamento
        $payrollPaymentsCount = WorkTimeSettlement::where('settlement_type', WorkTimeSettlementType::PayrollPayment)->count();
        $this->assertEquals(0, $payrollPaymentsCount, 'O fechamento mensal não deve gerar settlements fictícios do tipo PayrollPayment.');

        // 4. Critério de Aceite: Diferenças positivas e negativas permanecem intactas no snapshot imutável
        $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($snapshot);
        $journeys = collect($snapshot->journey_snapshot);

        $snapDay1 = $journeys->firstWhere('date', '2026-10-01');
        $this->assertNotNull($snapDay1);
        $this->assertEquals(600, $snapDay1['worked_minutes']);
        $this->assertEquals(480, $snapDay1['scheduled_minutes']);
        $this->assertEquals(120, $snapDay1['daily_difference_minutes']);

        $snapDay2 = $journeys->firstWhere('date', '2026-10-02');
        $this->assertNotNull($snapDay2);
        $this->assertEquals(435, $snapDay2['worked_minutes']);
        $this->assertEquals(480, $snapDay2['scheduled_minutes']);
        $this->assertEquals(-45, $snapDay2['daily_difference_minutes']);

        // 5. Banco de horas desativado sem resíduos fictícios
        $tbSnap = $snapshot->time_bank_snapshot;
        $this->assertEquals('DISABLED', $tbSnap['policy_mode']);
        $this->assertEquals(0, $tbSnap['final_balance']);
        $this->assertEmpty($tbSnap['transactions']);
    }

    /**
     * ACEITE 2: Fechamento mensal não cria operações fictícias PayrollPayment em qualquer regime.
     */
    public function test_fechamento_mensal_nao_cria_operacoes_ficticias_payroll_payment(): void
    {
        $employee = $this->createEmployee('Fernando Costa', '98765432100');

        WorkTimeSettlementPolicy::create([
            'company_id' => $this->company->id,
            'name' => 'Compensação Mensal',
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'CLT Art. 59 § 6º',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $day = Carbon::parse('2026-10-05');
        $this->registerPunch($employee, $day->copy()->setTime(8, 0), 'in');
        $this->registerPunch($employee, $day->copy()->setTime(12, 0), 'out');
        $this->registerPunch($employee, $day->copy()->setTime(13, 0), 'in');
        $this->registerPunch($employee, $day->copy()->setTime(18, 0), 'out'); // 9h = +60 min

        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->adminUser
        );

        $this->assertEquals('closed', $closedPeriod->status);

        // Nenhuma criação de PayrollPayment
        $this->assertEquals(0, WorkTimeSettlement::where('settlement_type', WorkTimeSettlementType::PayrollPayment)->count());
    }

    /**
     * ACEITE 3: Preservar integridade das tabelas históricas de settlement sem perda de compatibilidade.
     */
    public function test_preserva_tabelas_e_movimentacoes_historicas_para_auditoria(): void
    {
        $employee = $this->createEmployee('Auditável Antigo', '55566677788');

        // Cria registro histórico prévio
        $legacySettlement = WorkTimeSettlement::create([
            'employee_id' => $employee->id,
            'reference_period' => '2025-12',
            'minutes' => 120,
            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
            'status' => WorkTimeSettlementStatus::Executed,
            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
            'operation_date' => '2025-12-31',
            'execution_date' => '2026-01-05',
            'document_reference' => 'HOLERITE-LEGADO-12-2025',
            'justification' => 'Registro histórico preservado para conformidade',
        ]);

        $this->assertDatabaseHas('work_time_settlements', [
            'id' => $legacySettlement->id,
            'document_reference' => 'HOLERITE-LEGADO-12-2025',
            'settlement_type' => WorkTimeSettlementType::PayrollPayment->value,
        ]);

        // Fechamento de novo período não altera nem remove registros legados
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 10,
            closedBy: $this->adminUser
        );

        $legacySettlement->refresh();
        $this->assertEquals('HOLERITE-LEGADO-12-2025', $legacySettlement->document_reference);
        $this->assertEquals(WorkTimeSettlementStatus::Executed, $legacySettlement->status);
    }
}
