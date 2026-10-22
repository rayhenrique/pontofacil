<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Domain\WorkSchedule\Services\FlexibleShiftService;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Flexible12x36ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $adminUser;

    protected User $doctorUser;

    protected Employee $doctor;

    protected WorkSchedule $flexibleSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'America/Sao_Paulo'));

        $this->company = Company::firstOrCreate(
            ['cnpj' => '11780685000152'],
            [
                'legal_name' => 'Hospital Municipal de Urgência de Teste',
                'trade_name' => 'Hospital Municipal',
            ]
        );

        $this->establishment = Establishment::firstOrCreate(
            ['company_id' => $this->company->id],
            [
                'code' => 'HOSP',
                'name' => 'Unidade Hospitalar Central',
                'identifier_type' => 'CNPJ',
                'identifier_number' => '11780685000152',
                'timezone' => 'America/Sao_Paulo',
                'nsr_next' => 1,
            ]
        );

        $this->sector = Sector::create([
            'name' => 'Pronto-Socorro Adulto',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Coordenador de Escalas',
            'email' => 'coordenador@hospital.gov.br',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Admin,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dra. Mariana Plantonista',
            'email' => 'mariana.plantonista@hospital.gov.br',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Employee,
        ]);

        // Perfil normativo para CLT / Estatutário que autoriza 12x36
        LaborRuleProfile::createStandardCltProfile();

        $this->doctor = Employee::create([
            'user_id' => $this->doctorUser->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MED-9988',
            'cpf' => '98765432100',
            'job_title' => 'Médica Plantonista',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(ConfigureWorkScheduleAction::class);
        $this->flexibleSchedule = $action->createFlexible12h([
            'name' => 'Plantonista Flexível 12x36 Geral',
            'day_start_time' => '07:00',
            'day_end_time' => '19:00',
            'night_start_time' => '19:00',
            'night_end_time' => '07:00',
            'break_minutes' => 60,
            'anchor_date' => '2026-11-01',
            'auto_detect_turn' => true,
        ], $this->adminUser);

        WorkScheduleAssignment::create([
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'effective_from' => '2026-11-01',
            'reason' => 'Atribuição de escala flexível 12x36',
            'assigned_by' => $this->adminUser->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createPunch(
        Employee $employee,
        string $localDateTimeString,
        string $direction
    ): PunchEvent {
        $tz = 'America/Sao_Paulo';
        $local = Carbon::parse($localDateTimeString, $tz);
        $utc = $local->copy()->utc();

        $nsr = $this->establishment->nsr_next++;
        $this->establishment->save();

        return PunchEvent::create([
            'id' => (string) Str::uuid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $nsr,
            'direction' => $direction,
            'occurred_at_utc' => $utc,
            'occurred_at_local' => $local,
            'timezone' => $tz,
            'source' => 'web_portal',
            'received_at_utc' => $utc,
            'received_at_local' => $local,
            'raw_payload' => ['test' => true],
            'payload_hash' => hash('sha256', 'payload'.$nsr),
            'fiscal_hash' => hash('sha256', "flex_{$employee->id}_{$localDateTimeString}_{$direction}_{$nsr}"),
            'is_synchronized' => true,
        ]);
    }

    /**
     * Teste 1: Reconhecimento de plantão diurno (07:02 às 19:01)
     */
    public function test_day_shift_is_automatically_recognized_and_calculated(): void
    {
        // Gerar o ciclo para o dia 01/11/2026
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-01',
            assignedBy: $this->adminUser
        );

        $shift = ShiftAssignment::where('employee_id', $this->doctor->id)
            ->whereDate('start_at_local', '2026-11-01')
            ->first();

        $this->assertNotNull($shift);
        $this->assertTrue($shift->isFlexible());

        // Colaborador bate ponto de dia: 07:02 e 19:01
        $this->createPunch($this->doctor, '2026-11-01 07:02:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 19:01:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_DAY, $journey->identifiedShiftTurn);
        $this->assertSame('Diurno', $journey->identifiedShiftTurnLabel);
        $this->assertSame(0, $journey->absenceMinutes);
        $this->assertGreaterThan(650, $journey->workedMinutes);

        // O shift no banco foi sincronizado para diurno
        $shift->refresh();
        $this->assertSame(FlexibleShiftService::TURN_DAY, $shift->recognized_turn);
        $this->assertFalse($shift->is_night_shift);
    }

    /**
     * Teste 2: Reconhecimento de plantão noturno (18:58 às 07:03 do dia seguinte)
     */
    public function test_night_shift_is_automatically_recognized_with_correct_next_punch_direction(): void
    {
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-01',
            assignedBy: $this->adminUser
        );

        // Colaborador inicia plantão noturno em 01/11 às 18:58
        $this->createPunch($this->doctor, '2026-11-01 18:58:00', 'in');
        // Saída em 02/11 às 07:03
        $this->createPunch($this->doctor, '2026-11-02 07:03:00', 'out');

        // Testar que em 02/11 de manhã, a direção esperada é 'out' (concluindo o noturno da véspera)
        $directionAction = app(DetermineNextPunchDirectionAction::class);
        $morningTime = Carbon::parse('2026-11-02 07:00:00', 'America/Sao_Paulo');
        $nextDir = $directionAction->execute($this->doctorUser, $morningTime);
        $this->assertSame('out', $nextDir);

        // Apuração da jornada do dia 01/11
        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $journey->identifiedShiftTurn);
        $this->assertSame('Noturno', $journey->identifiedShiftTurnLabel);
        $this->assertGreaterThan(0, $journey->physicalNightMinutes);
        $this->assertGreaterThan(0, $journey->legalNightEquivalentMinutes);

        $shift = ShiftAssignment::where('employee_id', $this->doctor->id)
            ->whereDate('start_at_local', '2026-11-01')
            ->first();
        $this->assertNotNull($shift);
        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $shift->recognized_turn);
        $this->assertTrue($shift->is_night_shift);
        $this->assertTrue($shift->crosses_midnight);
    }

    /**
     * Teste 3: Alternância no mesmo mês — diurno em 01/11 e noturno em 03/11
     */
    public function test_single_employee_alternates_day_and_night_shifts_in_same_month(): void
    {
        // Ciclo 12x36: dias de trabalho são 01/11 e 03/11
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-04',
            assignedBy: $this->adminUser
        );

        // Plantão 1: Diurno em 01/11 (07:00 às 19:00)
        $this->createPunch($this->doctor, '2026-11-01 07:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 19:00:00', 'out');

        // Plantão 2: Noturno em 03/11 (19:00 às 07:00 do dia 04/11)
        $this->createPunch($this->doctor, '2026-11-03 19:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-04 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journeyDay1 = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);
        $journeyDay3 = $calc->execute($this->doctor, Carbon::parse('2026-11-03', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_DAY, $journeyDay1->identifiedShiftTurn);
        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $journeyDay3->identifiedShiftTurn);

        $shifts = ShiftAssignment::where('employee_id', $this->doctor->id)
            ->where('is_day_off', false)
            ->orderBy('start_at_local')
            ->get();

        $this->assertCount(2, $shifts);
        $this->assertSame(FlexibleShiftService::TURN_DAY, $shifts[0]->recognized_turn);
        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $shifts[1]->recognized_turn);
    }

    /**
     * Teste 4: Alerta de descanso interjornada quando intervalo for inferior a 36h
     */
    public function test_insufficient_rest_under_36_hours_generates_warning(): void
    {
        // Criar plantão prévio encerrado às 19:00 em 01/11
        ShiftAssignment::create([
            'shift_code' => 'PREV-001',
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => Carbon::parse('2026-11-01 07:00:00', 'America/Sao_Paulo'),
            'end_at_local' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo'),
            'start_at_utc' => Carbon::parse('2026-11-01 07:00:00', 'America/Sao_Paulo')->utc(),
            'end_at_utc' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo')->utc(),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::GeneratedCycle,
            'status' => ShiftStatus::Completed,
            'is_day_off' => false,
        ]);

        // Plantão seguinte inicia em 02/11 às 19:00 (apenas 24h depois do término de 01/11 19:00)
        $this->createPunch($this->doctor, '2026-11-02 19:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-03 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-02', 'America/Sao_Paulo'), $this->flexibleSchedule);

        // Deve conter nota de alerta de descanso interjornada < 36h
        $hasRestWarning = collect($journey->treatmentNotes)->contains(
            fn ($note) => str_contains($note, 'Alerta de descanso interjornada: intervalo de 24h apurado')
        );
        $this->assertTrue($hasRestWarning, 'Nota de aviso de descanso interjornada não foi encontrada: '.json_encode($journey->treatmentNotes));
    }

    /**
     * Teste 5: Descanso regular de 36 horas não gera aviso de déficit
     */
    public function test_sufficient_rest_of_36_hours_does_not_generate_rest_warning(): void
    {
        // Plantão prévio encerra em 01/11 às 19:00
        ShiftAssignment::create([
            'shift_code' => 'PREV-002',
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => Carbon::parse('2026-11-01 07:00:00', 'America/Sao_Paulo'),
            'end_at_local' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo'),
            'start_at_utc' => Carbon::parse('2026-11-01 07:00:00', 'America/Sao_Paulo')->utc(),
            'end_at_utc' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo')->utc(),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::GeneratedCycle,
            'status' => ShiftStatus::Completed,
            'is_day_off' => false,
        ]);

        // Plantão seguinte inicia em 03/11 às 07:00 (36h exatas após o término)
        $this->createPunch($this->doctor, '2026-11-03 07:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-03 19:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-03', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $hasRestWarning = collect($journey->treatmentNotes)->contains(
            fn ($note) => str_contains($note, 'Alerta de descanso interjornada')
        );
        $this->assertFalse($hasRestWarning, 'Descanso de 36h não deveria gerar alerta: '.json_encode($journey->treatmentNotes));
    }

    /**
     * Teste 6: Dia previsto pelo ciclo sem batidas tem turno 'undefined' e não lança falta presumida
     */
    public function test_cyclic_day_without_punches_does_not_presume_unearned_absence(): void
    {
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-01',
            assignedBy: $this->adminUser
        );

        $shift = ShiftAssignment::where('employee_id', $this->doctor->id)
            ->whereDate('start_at_local', '2026-11-01')
            ->first();

        $this->assertSame(FlexibleShiftService::TURN_UNDEFINED, $shift->recognized_turn);

        // Nenhuma batida realizada no dia 01/11
        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(0, $journey->absenceMinutes);
        $this->assertSame(0, $journey->scheduledMinutes);
        $hasReviewNote = collect($journey->treatmentNotes)->contains(
            fn ($note) => str_contains($note, 'sem lançamento de falta presumida')
        );
        $this->assertTrue($hasReviewNote, 'Nota de conferência administrativa sem falta presumida não foi encontrada: '.json_encode($journey->treatmentNotes));
    }

    /**
     * Teste 7: Perfil normativo aprovado para 12x36 autoriza automaticamente a modalidade flexível
     */
    public function test_labor_rule_profile_authorizes_flexible_12x36(): void
    {
        $error = $this->flexibleSchedule->modalityAuthorizationErrorFor($this->doctor);
        $this->assertNull($error, 'A modalidade flexível 12x36 deveria ser autorizada pelo perfil normativo aprovado para 12x36.');
    }
}
