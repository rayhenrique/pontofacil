<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkScheduleSimplifiedConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $adminUser;

    protected Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->adminUser = User::create([
            'name' => 'Gestor de RH',
            'email' => 'rh.gestor@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->sector = Sector::create([
            'name' => 'Secretaria de Saúde',
            'establishment_id' => $this->establishment->id,
        ]);
    }

    public function test_rh_configures_administrative_schedule_with_derived_workloads_and_real_monthly_calendar(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);

        // Modelo A: Administrativo padrão (segunda a sexta, 08:00-12:00 e 13:00-17:00)
        $schedule = $action->createAdministrative([
            'name' => 'Administrativo Saúde 40h',
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00',
            'morning_end' => '12:00',
            'afternoon_start' => '13:00',
            'afternoon_end' => '17:00',
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
        ], $this->adminUser);

        $this->assertInstanceOf(WorkSchedule::class, $schedule);
        $this->assertEquals(WorkScheduleModality::FixedWeekly, $schedule->modality);
        $this->assertEquals(480, $schedule->daily_minutes); // 8 horas diárias
        $this->assertEquals(2400, $schedule->calculateWeeklyMinutes()); // 40 horas semanais

        // Em Outubro/2026: 31 dias, 22 dias úteis de seg-sex
        // 22 * 480 min = 10.560 min = 176h (NÃO deve presumir 200h!)
        $monthlyMinutes = $schedule->calculateMonthlyExpectedMinutes(2026, 10);
        $this->assertEquals(10560, $monthlyMinutes);
        $this->assertNotEquals(200 * 60, $monthlyMinutes);

        // Prévia reativa
        $preview = $action->preview('administrative', [
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00',
            'morning_end' => '12:00',
            'afternoon_start' => '13:00',
            'afternoon_end' => '17:00',
        ], 2026, 10);

        $this->assertEquals(480, $preview['daily_minutes']);
        $this->assertEquals(2400, $preview['weekly_minutes']);
        $this->assertEquals(10560, $preview['monthly_expected_minutes']);
    }

    public function test_rh_configures_continuous_schedule_with_break_treatment(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);

        // Modelo B: Horário Corrido (segunda a sexta, 07:00 às 13:00, 15 min de pausa integrada/remunerada)
        $schedule = $action->createContinuous([
            'name' => 'Horário Corrido Atendimento 30h',
            'days' => [1, 2, 3, 4, 5],
            'start_time' => '07:00',
            'end_time' => '13:00',
            'break_minutes' => 15,
            'deduct_break' => false,
        ], $this->adminUser);

        $this->assertInstanceOf(WorkSchedule::class, $schedule);
        $this->assertEquals(360, $schedule->daily_minutes); // 6 horas diárias
        $this->assertEquals(1800, $schedule->calculateWeeklyMinutes()); // 30 horas semanais

        // Horário Corrido com dedução de intervalo
        $scheduleDeduct = $action->createContinuous([
            'name' => 'Corrido com Dedução de 30m',
            'days' => [1, 2, 3, 4, 5],
            'start_time' => '07:00',
            'end_time' => '13:30',
            'break_minutes' => 30,
            'deduct_break' => true,
        ], $this->adminUser);

        $this->assertEquals(360, $scheduleDeduct->daily_minutes); // 390 - 30 = 360 min (6h)
    }

    public function test_rh_configures_12x36_shift_day_and_night_crossing_midnight_as_single_logical_journey(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);

        // Modelo C1: Plantão 12x36 Diurno (07:00 às 19:00, intervalo 60 min)
        $scheduleDay = $action->createShift12h([
            'name' => 'Plantão 12x36 Diurno Hospital',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'break_minutes' => 60,
            'anchor_date' => '2026-10-01',
            'is_night_shift' => false,
        ], $this->adminUser);

        $this->assertEquals(WorkScheduleModality::TwelveByThirtySix, $scheduleDay->modality);
        $this->assertEquals(660, $scheduleDay->daily_minutes); // 720 - 60 = 660 min (11h)

        // Modelo C2: Plantão 12x36 Noturno (19:00 às 07:00 do dia seguinte, atravessa meia-noite)
        $scheduleNight = $action->createShift12h([
            'name' => 'Plantão 12x36 Noturno Pronto Socorro',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'break_minutes' => 60,
            'anchor_date' => '2026-10-01',
            'is_night_shift' => true,
        ], $this->adminUser);

        $this->assertEquals(WorkScheduleModality::TwelveByThirtySix, $scheduleNight->modality);
        $this->assertEquals(660, $scheduleNight->daily_minutes);

        // Criar colaborador e gerar projeção de escala noturna
        $user = User::create([
            'name' => 'Enfermeiro Plantonista',
            'email' => 'plantao.enf@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        $emp = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '111.222.333-44',
            'legal_regime' => LegalRegime::CLT->value,
            'workload' => '12x36',
        ]);

        $shifts = app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp,
            schedule: $scheduleNight,
            anchorDate: '2026-10-01',
            startDate: '2026-10-01',
            endDate: '2026-10-05',
            assignedBy: $this->adminUser,
            includeOffDays: false,
        );

        $this->assertNotEmpty($shifts);
        $firstShift = $shifts->first();
        // Atravessa meia-noite continua uma jornada lógica única
        $this->assertTrue($firstShift->crosses_midnight);
        $this->assertEquals('2026-10-01', $firstShift->start_at_local->format('Y-m-d'));
        $this->assertEquals('2026-10-02', $firstShift->end_at_local->format('Y-m-d'));
        $this->assertEquals(660, $firstShift->expected_work_minutes);
    }

    public function test_rh_configures_24x72_shift_and_cyclic_generation(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);

        // Modelo D: Plantão 24x72 (07:00 às 07:00 do dia seguinte, descanso 72h)
        $schedule = $action->createShift24h([
            'name' => 'Plantão 24x72 SAMU',
            'start_time' => '07:00',
            'end_time' => '07:00',
            'break_minutes' => 120,
            'anchor_date' => '2026-10-01',
        ], $this->adminUser);

        $this->assertEquals(WorkScheduleModality::TwentyFourBySeventyTwo, $schedule->modality);
        $this->assertEquals(1320, $schedule->daily_minutes); // 1440 - 120 = 1320 min (22h de trabalho líquido)

        $user = User::create([
            'name' => 'Condutor SAMU',
            'email' => 'samu.condutor@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        $emp = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '222.333.444-55',
            'legal_regime' => LegalRegime::FederalStatutory->value,
            'workload' => '24x72',
        ]);

        $shifts = app(GenerateCyclicShiftsAction::class)->execute(
            employee: $emp,
            schedule: $schedule,
            anchorDate: '2026-10-01',
            startDate: '2026-10-01',
            endDate: '2026-10-10',
            assignedBy: $this->adminUser,
            includeOffDays: false,
        );

        // Ciclo 24x72: plantão dia 01 (termina dia 02), folga dias 02, 03, 04, próximo plantão dia 05
        $this->assertCount(3, $shifts); // Dias 01, 05, 09
        $shift1 = $shifts->get(0);
        $shift2 = $shifts->get(1);
        $shift3 = $shifts->get(2);

        $this->assertEquals('2026-10-01', $shift1->start_at_local->format('Y-m-d'));
        $this->assertEquals('2026-10-05', $shift2->start_at_local->format('Y-m-d'));
        $this->assertEquals('2026-10-09', $shift3->start_at_local->format('Y-m-d'));
    }

    public function test_applying_schedule_to_multiple_employees_respects_effective_date_and_preserves_history(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);
        $assignAction = app(AssignWorkScheduleAction::class);

        $oldSchedule = WorkSchedule::createDefault40h();

        $user1 = User::create(['name' => 'Servidor A', 'email' => 'a@teste.com', 'password' => '12345678', 'role' => UserRole::Employee]);
        $user2 = User::create(['name' => 'Servidor B', 'email' => 'b@teste.com', 'password' => '12345678', 'role' => UserRole::Employee]);

        $emp1 = Employee::create(['user_id' => $user1->id, 'sector_id' => $this->sector->id, 'cpf' => '333.444.555-66', 'legal_regime' => LegalRegime::CLT->value, 'workload' => '40h']);
        $emp2 = Employee::create(['user_id' => $user2->id, 'sector_id' => $this->sector->id, 'cpf' => '444.555.666-77', 'legal_regime' => LegalRegime::CLT->value, 'workload' => '40h']);

        // Histórico: atribuído à escala antiga desde 2026-01-01
        $assignAction->execute($emp1, $oldSchedule, '2026-01-01', null, 'Carga inicial 2026', $this->adminUser);
        $assignAction->execute($emp2, $oldSchedule, '2026-01-01', null, 'Carga inicial 2026', $this->adminUser);

        // Nova escala administrativa configurada
        $newSchedule = $action->createAdministrative([
            'name' => 'Novo Administrativo 30h',
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '07:30',
            'morning_end' => '11:30',
            'afternoon_start' => '12:30',
            'afternoon_end' => '14:30',
        ], $this->adminUser);

        // Aplicação em lote para os dois servidores a partir de 2026-10-01
        $result = $action->applyToEmployees(
            schedule: $newSchedule,
            employeeIds: [$emp1->id, $emp2->id],
            effectiveFrom: '2026-10-01',
            effectiveUntil: null,
            reason: 'Reestruturação de horários',
            actor: $this->adminUser,
        );

        $this->assertEmpty($result['errors'], 'Erros na aplicação: '.json_encode($result['errors']));
        $this->assertEquals(2, $result['applied_count']);

        $emp1->refresh();
        $emp2->refresh();

        // Histórico preservado: em 2026-09-15 ainda é a escala antiga!
        $pastDate = Carbon::parse('2026-09-15');
        $this->assertEquals($oldSchedule->id, $emp1->getWorkScheduleForDate($pastDate)?->id);
        $this->assertEquals($oldSchedule->id, $emp2->getWorkScheduleForDate($pastDate)?->id);

        // A partir de 2026-10-01 é a nova escala!
        $newDate = Carbon::parse('2026-10-01');
        $this->assertEquals($newSchedule->id, $emp1->getWorkScheduleForDate($newDate)?->id);
        $this->assertEquals($newSchedule->id, $emp2->getWorkScheduleForDate($newDate)?->id);
    }

    public function test_no_presumption_of_200h_or_silent_first_schedule_in_employee_ui(): void
    {
        $this->actingAs($this->adminUser);

        // Garante que existe escala no banco
        $schedule = WorkSchedule::createDefault40h();

        Livewire::test('admin.employees')
            ->call('openCreateModal')
            ->assertSet('monthly_workload_hours', '') // NÃO deve vir preenchido com 200h
            ->assertSet('work_schedule_id', '') // NÃO deve vir silenciosamente selecionado com firstSchedule
            ->set('work_schedule_id', (string) $schedule->id)
            ->assertSet('daily_workload_hours', '8') // Derivado da escala selecionada
            ->assertSet('weekly_workload_hours', '40'); // Derivado da escala selecionada
    }

    public function test_punches_are_never_blocked_when_work_schedule_is_missing(): void
    {
        // Colaborador SEM nenhuma escala atribuída
        $user = User::create([
            'name' => 'Colaborador Sem Escala',
            'email' => 'sem.escala@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '555.666.777-88',
            'legal_regime' => LegalRegime::CLT->value,
            'workload' => 'Sem escala',
        ]);

        $this->assertNull($employee->getWorkScheduleForDate(Carbon::today()));

        // Registro de ponto REP-P NÃO pode ser bloqueado
        $punchAction = app(RecordPunchEventAction::class);
        $punch = $punchAction->execute(
            user: $user,
            direction: 'in',
            latitude: -9.665800,
            longitude: -35.735000,
            accuracy: 10.0,
            qrLocationValid: true,
            locationValid: true,
        );

        $this->assertNotNull($punch);
        $this->assertNotNull($punch->nsr);
        $this->assertNotEmpty($punch->payload_hash);

        // Apuração diária também processa sem exceção
        $calcAction = app(CalculateDailyJourneyAction::class);
        $result = $calcAction->execute(
            employee: $employee,
            date: Carbon::parse('2026-10-12'),
        );

        $this->assertInstanceOf(CalculatedJourney::class, $result);
        $this->assertEquals(0, $result->scheduledMinutes); // Sem escala programada, apuração não quebra
        $this->assertFalse($result->hasSchedule);
        $this->assertTrue($result->isPendingConfiguration);
    }

    public function test_livewire_shifts_management_preview_and_creation_flow(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test('admin.shifts-management')
            ->call('openCreateScheduleModal', 'administrative')
            ->assertSet('showScheduleModal', true)
            ->assertSet('template', 'administrative')
            ->set('admin_morning_start', '08:00')
            ->set('admin_morning_end', '12:00')
            ->set('admin_afternoon_start', '13:00')
            ->set('admin_afternoon_end', '17:00')
            ->assertSee('Carga Diária')
            ->assertSee('Carga Semanal')
            ->assertSee('Previsto no Mês Real')
            ->set('sched_name', 'Escala Criada pelo RH')
            ->call('saveSchedule')
            ->assertSet('showScheduleModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_schedules', [
            'name' => 'Escala Criada pelo RH',
            'expected_daily_minutes' => 480,
        ]);
    }
}
