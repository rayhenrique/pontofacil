<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetDailyDifferencesTest extends TestCase
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
            'name' => 'Carlos Alberto',
            'email' => 'carlos@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-500',
            'cpf' => '12345678901',
            'legal_regime' => LegalRegime::CLT,
        ]);
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
     * 1. Jornada acima do previsto gera diferença positiva (+HH:MM).
     */
    public function test_journey_above_scheduled_yields_positive_difference(): void
    {
        // 05/10/2026 (Segunda-feira) - Previsto: 480 min (08h00)
        // Trabalhado: 08:00 às 12:00 (240 min) e 14:00 às 18:34 (274 min) = 514 min (08h34)
        $date = Carbon::parse('2026-10-05');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:34']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertEquals(480, $journey->scheduledMinutes);
        $this->assertEquals(514, $journey->workedMinutes);
        $this->assertEquals(34, $journey->dailyDifferenceMinutes());
        $this->assertEquals('calculated', $journey->differenceState());
        $this->assertEquals('+00:34', $journey->formattedDifference());
    }

    /**
     * 2. Jornada abaixo do previsto gera diferença negativa (-HH:MM).
     */
    public function test_journey_below_scheduled_yields_negative_difference(): void
    {
        // 06/10/2026 (Terça-feira) - Previsto: 480 min (08h00)
        // Trabalhado: 08:00 às 12:00 (240 min) e 14:00 às 17:27 (207 min) = 447 min (07h27)
        $date = Carbon::parse('2026-10-06');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '17:27']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertEquals(480, $journey->scheduledMinutes);
        $this->assertEquals(447, $journey->workedMinutes);
        $this->assertEquals(-33, $journey->dailyDifferenceMinutes());
        $this->assertEquals('calculated', $journey->differenceState());
        $this->assertEquals('-00:33', $journey->formattedDifference());
    }

    /**
     * 3. Jornada igual ao previsto gera saldo neutro (00:00).
     */
    public function test_journey_equal_to_scheduled_yields_neutral_difference(): void
    {
        // 07/10/2026 (Quarta-feira) - Previsto: 480 min (08h00)
        // Trabalhado: 08:00 às 12:00 e 14:00 às 18:00 = 480 min
        $date = Carbon::parse('2026-10-07');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertEquals(480, $journey->scheduledMinutes);
        $this->assertEquals(480, $journey->workedMinutes);
        $this->assertEquals(0, $journey->dailyDifferenceMinutes());
        $this->assertEquals('calculated', $journey->differenceState());
        $this->assertEquals('00:00', $journey->formattedDifference());
    }

    /**
     * 4. Tolerância aplicável (CLT Art. 58, § 1º) é identificada nos detalhes e não vira hora extra indevida.
     */
    public function test_applicable_tolerance_is_identified(): void
    {
        // 08/10/2026 (Quinta-feira) - Previsto: 480 min
        // Trabalhado: 08:00 às 12:00 e 14:00 às 18:04 = 484 min (+4 min, dentro da tolerância de 5 min)
        $date = Carbon::parse('2026-10-08');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:04']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertEquals(480, $journey->scheduledMinutes);
        $this->assertEquals(484, $journey->workedMinutes);
        $this->assertEquals(4, $journey->dailyDifferenceMinutes());
        $this->assertEquals(0, $journey->overtimeMinutes); // Não vira hora extra por causa da tolerância!
        $this->assertEquals(4, $journey->toleratedMinutes); // Tolerância registrada
        $this->assertEquals('+00:04', $journey->formattedDifference());
    }

    /**
     * 5. Jornada incompleta sinaliza apuração pendente sem inventar zero.
     */
    public function test_incomplete_journey_signals_pending_without_inventing_zero(): void
    {
        // 09/10/2026 (Sexta-feira) - Apenas batida de entrada às 08:00 (sem par de saída)
        $date = Carbon::parse('2026-10-09');
        $this->createPunchesForDay($date, ['08:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertTrue($journey->isIncomplete);
        $this->assertNull($journey->dailyDifferenceMinutes());
        $this->assertEquals('pending', $journey->differenceState());
        $this->assertEquals('Apuração pendente', $journey->formattedDifference());
    }

    /**
     * 6. Plantão 12x36 respeita horas previstas da escala e apura diferença real.
     */
    public function test_shift_12x36_difference(): void
    {
        $shiftDate = Carbon::parse('2026-10-10');
        $shift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $this->schedule->id,
            'shift_code' => 'PLANT-12X36',
            'shift_type' => ShiftType::Shift12x36,
            'start_at_local' => Carbon::parse('2026-10-10 07:00:00'),
            'end_at_local' => Carbon::parse('2026-10-10 19:00:00'),
            'start_at_utc' => Carbon::parse('2026-10-10 10:00:00'),
            'end_at_utc' => Carbon::parse('2026-10-10 22:00:00'),
            'expected_work_minutes' => 720,
            'break_minutes' => 60,
            'status' => ShiftStatus::Confirmed,
        ]);

        // Trabalhou 07:00 às 20:00 (13 horas = 780 min -> +60 min de diferença)
        $this->createPunchesForDay($shiftDate, ['07:00', '20:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $shiftDate);

        $this->assertEquals(720, $journey->scheduledMinutes);
        $this->assertEquals(780, $journey->workedMinutes);
        $this->assertEquals(60, $journey->dailyDifferenceMinutes());
        $this->assertEquals('+01:00', $journey->formattedDifference());
    }

    /**
     * 7. Feriado sem trabalho não gera déficit de jornada.
     */
    public function test_holiday_without_work_does_not_generate_deficit(): void
    {
        // 12/10/2026 - Feriado Nacional (Nossa Senhora Aparecida)
        $holidayDate = Carbon::parse('2026-10-12');
        CalendarEvent::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Nossa Senhora Aparecida',
            'event_date' => $holidayDate->toDateString(),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'legal_reference' => 'Lei Federal nº 6.802/1980',
        ]);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $holidayDate);

        $this->assertEquals(0, $journey->scheduledMinutes);
        $this->assertEquals(0, $journey->workedMinutes);
        $this->assertEquals(0, $journey->dailyDifferenceMinutes());
        $this->assertEquals('00:00', $journey->formattedDifference());
    }

    /**
     * 8. Folga semanal (DSR) sem expediente previsto.
     */
    public function test_day_off_weekly_rest(): void
    {
        // 11/10/2026 - Domingo (Folga)
        $sunday = Carbon::parse('2026-10-11');
        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $sunday);

        $this->assertEquals(0, $journey->scheduledMinutes);
        $this->assertEquals(0, $journey->workedMinutes);
        $this->assertEquals(0, $journey->dailyDifferenceMinutes());
        $this->assertEquals('00:00', $journey->formattedDifference());
    }

    /**
     * 9. Jornada noturna apura horas físicas e redução ficta sem distorcer saldo.
     */
    public function test_night_journey_difference(): void
    {
        $date = Carbon::parse('2026-10-13');
        // Trabalhou das 22:00 às 23:30 (90 minutos físicos noturnos)
        $this->createPunchesForDay($date, ['22:00', '23:30']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $date);

        $this->assertGreaterThan(0, $journey->nightWorkedMinutes());
        $this->assertNotNull($journey->dailyDifferenceMinutes());
    }

    /**
     * 10. Servidor Estatutário não assimila banco privado CLT automaticamente.
     */
    public function test_statutory_regime_without_private_time_bank(): void
    {
        $statutoryUser = User::create([
            'name' => 'Dr. Fernando Auditor',
            'email' => 'fernando@estatutario.gov.br',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $statutoryEmployee = Employee::create([
            'user_id' => $statutoryUser->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'EST-101',
            'cpf' => '55566677788',
            'legal_regime' => LegalRegime::FederalStatutory,
        ]);

        $date = Carbon::parse('2026-10-14');
        $this->user = $statutoryUser;
        $this->employee = $statutoryEmployee;
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:40']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($statutoryEmployee, $date);

        $this->assertEquals(40, $journey->dailyDifferenceMinutes());
        $this->assertEquals('+00:40', $journey->formattedDifference());
    }

    /**
     * 11. Empresa com banco desativado (NoBank / DirectPayroll) exibe destinação sem card escuro de banco.
     */
    public function test_company_without_time_bank_displays_differences_and_hides_bank_card(): void
    {
        // Criar política de destinação NoBank / Pagamento Direto
        WorkTimeSettlementPolicy::create([
            'name' => 'Regime Geral Sem Banco de Horas',
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Art. 59 da CLT e Acordo Coletivo',
            'effective_from' => '2026-01-01',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $date = Carbon::parse('2026-10-15');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:30']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['operatesTimeBank']);
        $this->assertNotNull($data['differencesSummary']);
        $this->assertStringContainsString('sem banco de horas', $data['differencesSummary']->destinationDescription);

        // Testar renderização no componente Livewire
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Resumo das diferenças de jornada')
            ->assertSee('+00:30')
            ->assertDontSee('Regra de apuração: Fechamento com Zeramento');
    }

    /**
     * 12. Sem escala configurada gera "Saldo indisponível — escala não configurada".
     */
    public function test_employee_without_schedule_shows_uncalculable_balance(): void
    {
        $noScheduleUser = User::create([
            'name' => 'Colaborador Sem Escala',
            'email' => 'sem.escala@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $noScheduleEmployee = Employee::create([
            'user_id' => $noScheduleUser->id,
            'work_schedule_id' => null, // Sem escala
            'registration_number' => 'MAT-999',
            'cpf' => '99988877766',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $date = Carbon::parse('2026-10-16');
        $this->user = $noScheduleUser;
        $this->employee = $noScheduleEmployee;
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '18:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($noScheduleEmployee, $date);

        $this->assertFalse($journey->hasSchedule);
        $this->assertNull($journey->dailyDifferenceMinutes());
        $this->assertEquals('uncalculable', $journey->differenceState());
        $this->assertEquals('Saldo indisponível — escala não configurada', $journey->formattedDifference());

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($noScheduleUser, 2026, 10);

        $this->assertEquals(1, $data['differencesSummary']->uncalculableDaysCount);
    }

    /**
     * 13. Período aberto apura dinamicamente via PTRP em tempo real.
     */
    public function test_open_period_realtime_ptrp_calculation(): void
    {
        $date = Carbon::parse('2026-10-19');
        $this->createPunchesForDay($date, ['08:00', '12:00', '14:00', '19:00']); // +1 hora

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $this->assertFalse($data['isClosedPeriod']);
        $this->assertArrayHasKey('2026-10-19', $data['daysCalculated']);
        $this->assertEquals(60, $data['daysCalculated']['2026-10-19']['difference_minutes']);
        $this->assertEquals('+01:00', $data['daysCalculated']['2026-10-19']['formatted_difference']);
    }

    /**
     * 14. Período fechado lê dados imutáveis do snapshot sem recalcular com escala atual.
     */
    public function test_closed_period_uses_immutable_snapshot_without_recalculating(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'snapshot_version' => '1.0',
            'status' => 'closed',
            'closed_by' => $this->user->id,
            'closed_at' => now(),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'employee_snapshot' => ['name' => 'Carlos Alberto'],
            'schedule_snapshot' => ['name' => 'Escala 40h', 'modality' => 'standard'],
            'treatment_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2026-09-15',
                    'scheduled_minutes' => 480,
                    'worked_minutes' => 510,
                    'is_incomplete' => false,
                    'has_schedule' => true,
                    'daily_difference_minutes' => 30,
                    'effective_punches' => [
                        ['type' => 'in', 'time' => '08:00'],
                        ['type' => 'out', 'time' => '12:00'],
                        ['type' => 'in', 'time' => '14:00'],
                        ['type' => 'out', 'time' => '18:30'],
                    ],
                ],
            ],
            'time_bank_snapshot' => [],
            'snapshot_hash' => hash('sha256', 'payload-test'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 9);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertEquals('+00:30', $data['daysCalculated']['2026-09-15']['formatted_difference']);
        $this->assertEquals(30, $data['differencesSummary']->positiveMinutes);
        $this->assertEquals(30, $data['differencesSummary']->netMinutes);
    }

    /**
     * 15. Snapshot legado sem informação de escala sinaliza histórico indisponível sem inventar zeros.
     */
    public function test_legacy_snapshot_without_schedule_data_signals_historical_unavailable(): void
    {
        $closedPeriod = ClosedPeriod::create([
            'year' => 2025,
            'month' => 8,
            'snapshot_version' => 1,
            'status' => 'closed',
            'closed_by' => $this->user->id,
            'closed_at' => now(),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Carlos Alberto'],
            'schedule_snapshot' => [],
            'treatment_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2025-08-10',
                    'worked_minutes' => 480,
                    // scheduled_minutes omitido de propósito (snapshot legado)
                    'is_incomplete' => false,
                    'effective_punches' => [
                        ['type' => 'in', 'time' => '08:00'],
                        ['type' => 'out', 'time' => '17:00'],
                    ],
                ],
            ],
            'time_bank_snapshot' => [],
            'snapshot_hash' => hash('sha256', 'legacy-payload'),
        ]);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2025, 8);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertEquals('historical_unavailable', $data['daysCalculated']['2025-08-10']['difference_state']);
        $this->assertEquals('Histórico indisponível', $data['daysCalculated']['2025-08-10']['formatted_difference']);
        $this->assertNull($data['daysCalculated']['2025-08-10']['difference_minutes']);
        $this->assertEquals('historical', $data['differencesSummary']->status);
    }

    /**
     * 16. Saldo positivo e negativo no mesmo mês apura o resumo de diferenças líquido com ressalva matemática.
     */
    public function test_positive_and_negative_differences_in_same_month_net_summary(): void
    {
        // Dia 1: +34 min (514 min trabalhados contra 480 previstos)
        $date1 = Carbon::parse('2026-10-20');
        $this->createPunchesForDay($date1, ['08:00', '12:00', '14:00', '18:34']);

        // Dia 2: -33 min (447 min trabalhados contra 480 previstos)
        $date2 = Carbon::parse('2026-10-21');
        $this->createPunchesForDay($date2, ['08:00', '12:00', '14:00', '17:27']);

        // Dia 3: 00:00 (480 min trabalhados contra 480 previstos)
        $date3 = Carbon::parse('2026-10-22');
        $this->createPunchesForDay($date3, ['08:00', '12:00', '14:00', '18:00']);

        $service = app(TimesheetJourneyService::class);
        $data = $service->resolveMonthData($this->user, 2026, 10);

        $summary = $data['differencesSummary'];

        $this->assertEquals(34, $summary->positiveMinutes);
        $this->assertEquals(-33, $summary->negativeMinutes);
        $this->assertEquals(1, $summary->netMinutes); // +34 - 33 = +1 min líquido
        $this->assertEquals('+00h 34m', $summary->formattedPositive());
        $this->assertEquals('-00h 33m', $summary->formattedNegative());
        $this->assertEquals('+00h 01m', $summary->formattedNet());
        $this->assertEquals(3, $summary->concludedDaysCount);

        // Testar renderização no Blade Livewire
        Livewire::actingAs($this->user)
            ->test('timesheet', ['userId' => $this->user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Resumo das diferenças de jornada')
            ->assertSee('+00h 34m')
            ->assertSee('-00h 33m')
            ->assertSee('+00h 01m')
            ->assertSee('+00:34')
            ->assertSee('-00:33')
            ->assertSee('00:00')
            ->assertSee('A diferença líquida é uma apuração puramente matemática');
    }
}
