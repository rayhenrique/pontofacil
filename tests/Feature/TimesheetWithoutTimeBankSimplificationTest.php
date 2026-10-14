<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\TimeBankPolicy;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetWithoutTimeBankSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $user;

    protected Employee $employee;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->user = User::create([
            'name' => 'Maria Silva',
            'email' => 'maria.silva@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-770',
            'cpf' => '98765432100',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createPunchesForDay(Carbon $date, array $times): void
    {
        $action = app(RecordPunchEventAction::class);
        $dirs = ['in', 'out', 'in', 'out', 'in', 'out'];

        foreach ($times as $idx => $timeStr) {
            Carbon::setTestNow(Carbon::parse($date->format('Y-m-d').' '.$timeStr.':00'));
            $dir = $dirs[$idx % count($dirs)];
            $action->execute(user: $this->user, direction: $dir, establishment: $this->establishment);
        }

        Carbon::setTestNow();
    }

    /**
     * 1. Sem banco configurado: oculta card e indicadores de banco, exibe apuração de jornada.
     */
    public function test_employee_without_configured_bank_hides_time_bank_and_shows_journey_differences(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));

        $date = Carbon::parse('2026-10-05'); // Segunda-feira
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '17:30']); // 8h30 (30m extra)

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNull($data['timeBankSummary']);
        $this->assertNotNull($data['differencesSummary']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Resumo das diferenças de jornada')
            ->assertSee('Total Trabalhado')
            ->assertSee('08h 30m')
            ->assertSee('+00:30')
            ->assertDontSee('Banco de Horas')
            ->assertDontSee('Saldo Anterior')
            ->assertDontSee('Saldo ao Final da Competência')
            ->assertDontSee('Auditoria e Conciliação de Horas');
    }

    /**
     * 2. Política NoBank: tem precedência mesmo que haja política legada ativada no banco.
     */
    public function test_employee_with_nobank_policy_hides_bank_block_even_if_legacy_policy_enabled(): void
    {
        // Política legada ativada
        TimeBankPolicy::create([
            'name' => 'Banco Legado Geral',
            'enabled' => true,
            'closing_mode' => 'CARRY_OVER',
            'valid_from' => '2026-01-01',
        ]);

        // Política específica NoBank para a empresa/colaborador
        WorkTimeSettlementPolicy::create([
            'name' => 'Regime Geral Sem Banco',
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Acordo Individual - Art. 59 da CLT',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '17:00']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNull($data['timeBankSummary']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Resumo das diferenças de jornada')
            ->assertDontSee('Banco de Horas')
            ->assertDontSee('Auditoria e Conciliação de Horas');
    }

    /**
     * 3. Banco cumulativo: exibe bloco de banco de horas e conciliação de forma compacta.
     */
    public function test_employee_with_cumulative_bank_policy_shows_compact_bank_block(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Acumulativo CLT',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Art. 59 § 2º da CLT',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '18:00']); // 9h (1h extra)

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertTrue($data['operatesTimeBank']);
        $this->assertNotNull($data['timeBankSummary']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Banco de Horas')
            ->assertSee('Saldo Anterior')
            ->assertSee('Saldo ao Final da Competência')
            ->assertSee('Auditoria e Conciliação de Horas')
            ->assertSee('Resumo das diferenças de jornada')
            ->assertSee('+01:00');
    }

    /**
     * 4. Modelo híbrido que opera banco: preserva indicadores do banco.
     */
    public function test_employee_with_hybrid_model_operates_time_bank(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Regime Híbrido Banco + Folha',
            'modality' => SettlementModality::Hybrid,
            'legal_framework' => 'Acordo Coletivo 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
            'hybrid_rules' => ['bank_monthly_limit_minutes' => 600],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '17:00']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertTrue($data['operatesTimeBank']);
        $this->assertNotNull($data['timeBankSummary']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Banco de Horas')
            ->assertSee('Resumo das diferenças de jornada');
    }

    /**
     * 5. Compensação mensal sem ledger de banco: oculta card escuro de banco.
     */
    public function test_monthly_compensation_without_ledger_hides_time_bank_block(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Compensação Mensal Fechada',
            'modality' => SettlementModality::MonthlyCompensation,
            'legal_framework' => 'Art. 59 § 6º da CLT',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '17:30']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNull($data['timeBankSummary']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Resumo das diferenças de jornada')
            ->assertSee('Compensação Mensal')
            ->assertDontSee('Banco de Horas')
            ->assertDontSee('Auditoria e Conciliação de Horas');
    }

    /**
     * 6. Política legada ativa e inativa.
     */
    public function test_legacy_policy_enabled_vs_disabled(): void
    {
        // 6a. Desativada:
        TimeBankPolicy::create([
            'name' => 'Banco Legado Inativo',
            'enabled' => false,
            'closing_mode' => 'CARRY_OVER',
            'valid_from' => '2026-01-01',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '13:00', '17:00']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertDontSee('Banco de Horas');

        // 6b. Ativada:
        TimeBankPolicy::query()->delete();
        TimeBankPolicy::create([
            'name' => 'Banco Legado Ativo',
            'enabled' => true,
            'closing_mode' => 'CARRY_OVER',
            'valid_from' => '2026-01-01',
        ]);

        $dataAtivo = $service->resolveMonthData($this->user, 2026, 10);
        $this->assertTrue($dataAtivo['operatesTimeBank']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Banco de Horas');
    }

    /**
     * 7. Competência fechada: respeita a modalidade congelada no snapshot.
     */
    public function test_closed_competence_preserves_frozen_snapshot_mode(): void
    {
        // Competência de Agosto/2026 foi formalmente fechada com Banco de Horas
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 8,
            'status' => 'closed',
            'snapshot_version' => '1',
            'closed_at' => Carbon::parse('2026-09-02 10:00:00'),
            'closed_by' => $this->user->id,
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'employee_snapshot' => ['name' => 'Maria Silva'],
            'schedule_snapshot' => ['name' => 'Escala 40h', 'modality' => 'standard'],
            'treatment_snapshot' => [],
            'snapshot_version' => 1,
            'journey_snapshot' => [
                [
                    'date' => '2026-08-10',
                    'worked_minutes' => 480,
                    'scheduled_minutes' => 480,
                    'has_schedule' => true,
                    'is_incomplete' => false,
                    'effective_punches' => [
                        ['time' => '08:00', 'type' => 'in'],
                        ['time' => '12:00', 'type' => 'out'],
                        ['time' => '13:00', 'type' => 'in'],
                        ['time' => '17:00', 'type' => 'out'],
                    ],
                ],
            ],
            'time_bank_snapshot' => [
                'policy_mode' => 'CARRY_OVER',
                'balance_before' => 60,
                'balance_at_closing' => 120,
                'final_balance' => 120,
                'reset_applied' => 0,
                'transactions' => [
                    ['date' => '2026-08-10', 'minutes' => 60, 'type' => 'CREDIT'],
                ],
                'settlement_policy' => [
                    'name' => 'Banco de Horas Congelado v1',
                    'modality' => 'cumulative_bank',
                ],
            ],
            'snapshot_hash' => hash('sha256', 'snapshot-august-2026'),
        ]);

        // Atualmente (em Outubro), a empresa opera NoBank
        WorkTimeSettlementPolicy::create([
            'name' => 'Empresa Sem Banco a partir de Outubro',
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Art. 59 CLT',
            'effective_from' => '2026-10-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Ao consultar Agosto/2026 (Fechada), deve preservar o Banco de Horas congelado!
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 8, 'year' => 2026])
            ->assertSee('Competência Fechada')
            ->assertSee('Banco de Horas')
            ->assertSee('Banco de Horas Congelado v1');

        // Ao consultar Outubro/2026 (Aberta), o Banco de Horas NÃO deve aparecer!
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertDontSee('Banco de Horas');
    }

    /**
     * 8. Mudança de política entre competências distintas.
     */
    public function test_policy_change_between_open_competences(): void
    {
        // Setembro/2026: Banco de horas ativo
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Setembro',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Acordo Coletivo Anterior',
            'effective_from' => '2026-09-01',
            'effective_until' => '2026-09-30',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Outubro/2026: Sem banco de horas
        WorkTimeSettlementPolicy::create([
            'name' => 'Sem Banco Outubro',
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Novo Acordo Coletivo',
            'effective_from' => '2026-10-01',
            'effective_until' => '2026-10-31',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));
        $dateSep = Carbon::parse('2026-09-10');
        $this->createPunchesForDay($dateSep, ['08:00', '12:00', '13:00', '17:00']);

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $dateOct = Carbon::parse('2026-10-08');
        $this->createPunchesForDay($dateOct, ['08:00', '12:00', '13:00', '17:00']);

        // Setembro exibe Banco de Horas
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 9, 'year' => 2026])
            ->assertSee('Banco de Horas');

        // Outubro oculta Banco de Horas
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertDontSee('Banco de Horas')
            ->assertSee('Resumo das diferenças de jornada');
    }

    /**
     * 9. Dias concluídos e incompletos destacados no resumo responsivo.
     */
    public function test_concluded_and_incomplete_days_highlighted_in_summary_grid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));

        // Dia 1: 05/10/2026 - Concluída (08:00 - 12:00, 13:00 - 17:00) = 8h
        $date1 = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date1, ['08:00', '12:00', '13:00', '17:00']);

        // Dia 2: 06/10/2026 - Incompleta (apenas entrada 08:00)
        $date2 = Carbon::parse('2026-10-06');
        $this->createPunchesForDay($date2, ['08:00']);

        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Total Trabalhado')
            ->assertSee('08h 00m')
            ->assertSee('Concluídas')
            ->assertSee('1 dia')
            ->assertSee('Incompletas')
            ->assertSee('Batidas em aberto');
    }

    /**
     * 10. Totais apurados antes e depois idênticos matematicamente.
     */
    public function test_totals_remain_mathematically_identical_with_and_without_bank(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 18:00:00'));

        // 3 dias de expediente:
        // Dia 1: 8h (previsto 8h -> diff 0)
        $this->createPunchesForDay(Carbon::parse('2026-10-05'), ['08:00', '12:00', '13:00', '17:00']);
        // Dia 2: 9h (previsto 8h -> diff +1h = +60m)
        $this->createPunchesForDay(Carbon::parse('2026-10-06'), ['08:00', '12:00', '13:00', '18:00']);
        // Dia 3: 7h (previsto 8h -> diff -1h = -60m)
        $this->createPunchesForDay(Carbon::parse('2026-10-07'), ['08:00', '12:00', '13:00', '16:00']);

        // Sem banco de horas
        $service = app(TimesheetJourneyService::class);
        $dataNoBank = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertEquals(1440, $dataNoBank['totalWorkedMinutes']); // 24 horas = 1440m
        $this->assertEquals('24h 00m', $dataNoBank['totalMonthFormatted']);
        $this->assertEquals(3, $dataNoBank['completedDaysCount']);
        $this->assertEquals(0, $dataNoBank['incompleteDaysCount']);
        $this->assertEquals(60, $dataNoBank['differencesSummary']->positiveMinutes);
        $this->assertEquals(-60, $dataNoBank['differencesSummary']->negativeMinutes);
        $this->assertEquals(0, $dataNoBank['differencesSummary']->netMinutes);
        $this->assertFalse($dataNoBank['operatesTimeBank']);

        // Agora configurando Banco Cumulativo
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco Ativo',
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Art. 59 CLT',
            'effective_from' => '2026-01-01',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        $dataWithBank = $service->resolveMonthData($this->user, 2026, 10);

        // Os totais físicos de apuração de jornada devem ser RIGOROSAMENTE idênticos
        $this->assertEquals($dataNoBank['totalWorkedMinutes'], $dataWithBank['totalWorkedMinutes']);
        $this->assertEquals($dataNoBank['totalMonthFormatted'], $dataWithBank['totalMonthFormatted']);
        $this->assertEquals($dataNoBank['completedDaysCount'], $dataWithBank['completedDaysCount']);
        $this->assertEquals($dataNoBank['incompleteDaysCount'], $dataWithBank['incompleteDaysCount']);
        $this->assertEquals($dataNoBank['differencesSummary']->positiveMinutes, $dataWithBank['differencesSummary']->positiveMinutes);
        $this->assertEquals($dataNoBank['differencesSummary']->negativeMinutes, $dataWithBank['differencesSummary']->negativeMinutes);
        $this->assertEquals($dataNoBank['differencesSummary']->netMinutes, $dataWithBank['differencesSummary']->netMinutes);
        $this->assertTrue($dataWithBank['operatesTimeBank']);
    }
}
