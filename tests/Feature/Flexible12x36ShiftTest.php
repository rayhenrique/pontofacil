<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Domain\WorkSchedule\Actions\ValidateShiftConflictsAction;
use App\Domain\WorkSchedule\Services\FlexibleShiftService;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
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

    /**
     * Teste 8: Entrada diurna antecipada (06:15) reconhecida como turno diurno
     */
    public function test_early_day_entry_is_recognized_as_day_shift(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 06:15:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 19:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_DAY, $journey->identifiedShiftTurn);
        $this->assertSame('Diurno', $journey->identifiedShiftTurnLabel);
        $this->assertGreaterThan(660, $journey->workedMinutes);
    }

    /**
     * Teste 9: Entrada noturna atrasada (20:30) reconhecida como turno noturno
     */
    public function test_late_night_entry_is_recognized_as_night_shift(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 20:30:00', 'in');
        $this->createPunch($this->doctor, '2026-11-02 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $journey->identifiedShiftTurn);
        $this->assertSame('Noturno', $journey->identifiedShiftTurnLabel);
        $this->assertGreaterThan(0, $journey->physicalNightMinutes);
    }

    /**
     * Teste 10: Plantão noturno atravessando a mudança de mês (30/11 às 19:00 até 01/12 às 07:00)
     */
    public function test_shift_crossing_month_boundary_is_associated_with_start_month(): void
    {
        $this->createPunch($this->doctor, '2026-11-30 19:00:00', 'in');
        $this->createPunch($this->doctor, '2026-12-01 07:00:00', 'out');

        $directionAction = app(DetermineNextPunchDirectionAction::class);
        $morningDec1 = Carbon::parse('2026-12-01 06:58:00', 'America/Sao_Paulo');
        $nextDir = $directionAction->execute($this->doctorUser, $morningDec1);
        $this->assertSame('out', $nextDir);

        $calc = app(CalculateDailyJourneyAction::class);
        $journeyNov30 = $calc->execute($this->doctor, Carbon::parse('2026-11-30', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $journeyNov30->identifiedShiftTurn);
        $this->assertSame(720, $journeyNov30->workedMinutes);
    }

    /**
     * Teste 11: Entrada noturna esquecida e posteriormente aprovada via tratamento de ponto
     */
    public function test_forgotten_night_entry_approved_via_treatment_reclassifies_shift(): void
    {
        $this->createPunch($this->doctor, '2026-11-02 07:00:00', 'out');

        TreatmentEvent::create([
            'employee_id' => $this->doctor->id,
            'employment_id' => $this->doctor->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo'),
            'new_value_json' => ['direction' => 'in', 'suggested_time' => '19:00'],
            'reason_code' => 'forgotten_punch',
            'reason_text' => 'Esquecimento de registro de entrada do plantão noturno',
            'requested_by' => $this->doctorUser->id,
            'approved_by' => $this->adminUser->id,
            'decided_at' => Carbon::now(),
        ]);

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_NIGHT, $journey->identifiedShiftTurn);
        $this->assertSame(720, $journey->workedMinutes);
        $this->assertGreaterThan(0, $journey->physicalNightMinutes);
    }

    /**
     * Teste 12: Revogação / Cancelamento do tratamento retroativo
     */
    public function test_revocation_of_treatment_reverts_reclassification(): void
    {
        $this->createPunch($this->doctor, '2026-11-02 07:00:00', 'out');

        TreatmentEvent::create([
            'employee_id' => $this->doctor->id,
            'employment_id' => $this->doctor->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Cancelled,
            'effective_at' => Carbon::parse('2026-11-01 19:00:00', 'America/Sao_Paulo'),
            'new_value_json' => ['direction' => 'in'],
            'reason_text' => 'Solicitação cancelada pelo gestor',
            'requested_by' => $this->doctorUser->id,
            'cancelled_by' => $this->adminUser->id,
            'cancelled_at' => Carbon::now(),
        ]);

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(0, $journey->workedMinutes);
    }

    /**
     * Teste 13: Marcações duplicadas no mesmo minuto ou com intervalo curto
     */
    public function test_duplicate_punches_are_handled_cleanly(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 07:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 07:01:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 19:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_DAY, $journey->identifiedShiftTurn);
        $this->assertFalse($journey->isIncomplete);
        $this->assertGreaterThan(600, $journey->workedMinutes);
    }

    /**
     * Teste 14: Saída esquecida sinaliza jornada incompleta sem corromper o plantão
     */
    public function test_forgotten_exit_flags_incomplete_journey_without_destroying_shift(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 07:00:00', 'in');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertTrue($journey->isIncomplete);
        $this->assertSame(FlexibleShiftService::TURN_DAY, $journey->identifiedShiftTurn);
    }

    /**
     * Teste 15: Marcação em horário ambíguo sinaliza Pendente de Classificação
     */
    public function test_ambiguous_entry_time_is_flagged_as_pending_classification(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 14:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 22:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertSame(FlexibleShiftService::TURN_PENDING, $journey->identifiedShiftTurn);
        $this->assertSame('Pendente de Classificação', $journey->identifiedShiftTurnLabel);
        $hasPendingNote = collect($journey->treatmentNotes)->contains(
            fn ($note) => str_contains(mb_strtolower($note), 'pendente de classificação')
        );
        $this->assertTrue($hasPendingNote);
    }

    /**
     * Teste 16: Sobreposição de plantões gera conflito crítico
     */
    public function test_shift_overlap_generates_critical_conflict(): void
    {
        $shift1 = ShiftAssignment::create([
            'shift_code' => 'OVERLAP-001',
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

        $shift2 = ShiftAssignment::create([
            'shift_code' => 'OVERLAP-002',
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => Carbon::parse('2026-11-01 18:00:00', 'America/Sao_Paulo'),
            'end_at_local' => Carbon::parse('2026-11-02 06:00:00', 'America/Sao_Paulo'),
            'start_at_utc' => Carbon::parse('2026-11-01 18:00:00', 'America/Sao_Paulo')->utc(),
            'end_at_utc' => Carbon::parse('2026-11-02 06:00:00', 'America/Sao_Paulo')->utc(),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::GeneratedCycle,
            'status' => ShiftStatus::Scheduled,
            'is_day_off' => false,
        ]);

        $report = app(ValidateShiftConflictsAction::class)->execute($this->doctor, collect([$shift1, $shift2]));
        $this->assertTrue($report['has_critical_conflicts']);
        $hasOverlap = collect($report['conflicts'])->contains(fn ($c) => $c['type'] === 'overlap');
        $this->assertTrue($hasOverlap, 'Conflito de sobreposição não foi detectado.');
    }

    /**
     * Teste 17: Permuta de plantão entre colaboradores preserva a escala flexível
     */
    public function test_authorized_shift_swap_preserves_flexible_nature(): void
    {
        $nurseUser = User::create([
            'name' => 'Enf. Lucas Plantonista',
            'email' => 'lucas.enfermeiro@hospital.gov.br',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Employee,
        ]);

        $nurse = Employee::create([
            'user_id' => $nurseUser->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'ENF-1122',
            'cpf' => '11223344556',
            'job_title' => 'Enfermeiro Plantonista',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $shift = ShiftAssignment::create([
            'shift_code' => 'SWAP-001',
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => Carbon::parse('2026-11-05 07:00:00', 'America/Sao_Paulo'),
            'end_at_local' => Carbon::parse('2026-11-05 19:00:00', 'America/Sao_Paulo'),
            'start_at_utc' => Carbon::parse('2026-11-05 07:00:00', 'America/Sao_Paulo')->utc(),
            'end_at_utc' => Carbon::parse('2026-11-05 19:00:00', 'America/Sao_Paulo')->utc(),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::GeneratedCycle,
            'status' => ShiftStatus::Scheduled,
            'is_day_off' => false,
            'recognized_turn' => FlexibleShiftService::TURN_UNDEFINED,
        ]);

        $shift->update([
            'status' => ShiftStatus::Swapped,
            'swapped_with_employee_id' => $nurse->id,
            'swap_reason' => 'Permuta acordada entre plantonistas',
        ]);

        $replacementShift = ShiftAssignment::create([
            'shift_code' => (string) Str::uuid(),
            'employee_id' => $nurse->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => $shift->start_at_local,
            'end_at_local' => $shift->end_at_local,
            'start_at_utc' => $shift->start_at_utc,
            'end_at_utc' => $shift->end_at_utc,
            'timezone' => $shift->timezone,
            'break_minutes' => $shift->break_minutes,
            'expected_work_minutes' => $shift->expected_work_minutes,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::Swap,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'recognized_turn' => FlexibleShiftService::TURN_UNDEFINED,
        ]);

        $this->assertTrue($replacementShift->isFlexible());
        $this->assertSame(ShiftStatus::Swapped, $shift->status);
    }

    /**
     * Teste 18: Perfil normativo inválido ou revogado impede atribuição de escala flexível
     */
    public function test_unauthorized_labor_rule_profile_blocks_flexible_schedule_assignment(): void
    {
        $unauthorizedUser = User::factory()->create(['role' => 'employee']);
        $unauthorizedEmployee = Employee::create([
            'user_id' => $unauthorizedUser->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'EST-001',
            'cpf' => '12345678901',
            'job_title' => 'Estagiário',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        $error = $this->flexibleSchedule->modalityAuthorizationErrorFor($unauthorizedEmployee);
        $this->assertNotNull($error);
        $this->assertStringContainsString('não possui um perfil normativo aprovado que autorize o plantão 12x36', $error);
    }

    /**
     * Teste 19: Atribuição de escala a múltiplos funcionários com controle de vigência
     */
    public function test_schedule_assigned_to_multiple_employees_without_overlapping_base_assignments(): void
    {
        $user2 = User::factory()->create(['role' => 'employee']);
        $doctor2 = Employee::create([
            'user_id' => $user2->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MED-7766',
            'cpf' => '55443322110',
            'job_title' => 'Médico Plantonista 2',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(ConfigureWorkScheduleAction::class);
        $res = $action->applyToEmployees(
            schedule: $this->flexibleSchedule,
            employeeIds: [$this->doctor->id, $doctor2->id],
            effectiveFrom: '2026-12-01',
            actor: $this->adminUser
        );

        $this->assertSame(2, $res['applied_count']);
        $this->assertCount(2, $this->doctor->fresh()->workScheduleAssignments);
        $this->assertCount(1, $doctor2->fresh()->workScheduleAssignments);
    }

    /**
     * Teste 20: Competência fechada bloqueia alteração de plantão
     */
    public function test_closed_period_prevents_shift_alteration(): void
    {
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 11,
            'status' => 'closed',
            'closed_by' => $this->adminUser->id,
            'closed_at' => Carbon::now(),
        ]);

        $shift = ShiftAssignment::create([
            'shift_code' => 'CLOSED-001',
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

        $report = app(ValidateShiftConflictsAction::class)->execute($this->doctor, collect([$shift]));
        $hasClosedConflict = collect($report['conflicts'])->contains(fn ($c) => $c['type'] === 'closed_period');
        $this->assertTrue($hasClosedConflict, 'Competência fechada deveria registrar conflito crítico no plantão.');
    }

    /**
     * Teste 21: Apuração correta de adicional noturno e hora noturna reduzida (CLT)
     */
    public function test_night_work_and_reduction_calculated_according_to_labor_rules(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 19:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-02 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journey = $calc->execute($this->doctor, Carbon::parse('2026-11-01', 'America/Sao_Paulo'), $this->flexibleSchedule);

        $this->assertGreaterThanOrEqual(420, $journey->physicalNightMinutes);
        $this->assertGreaterThan($journey->physicalNightMinutes, $journey->legalNightEquivalentMinutes);
    }

    /**
     * Teste 22: Compatibilidade com modalidades legadas (Administrativa e 24x72)
     */
    public function test_legacy_work_schedules_continue_to_function_without_regression(): void
    {
        $action = app(ConfigureWorkScheduleAction::class);
        $adminSched = $action->createAdministrative([
            'name' => 'Escala Administrativa Teste',
            'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00',
            'morning_end' => '12:00',
            'afternoon_start' => '13:00',
            'afternoon_end' => '17:00',
        ], $this->adminUser);

        $this->assertSame(WorkScheduleModality::FixedWeekly, $adminSched->modality);
        $this->assertSame(480, $adminSched->expected_daily_minutes);

        $shift24 = $action->createShift24h([
            'name' => 'Plantão 24x72 Teste',
            'start_time' => '07:00',
            'end_time' => '07:00',
            'break_minutes' => 120,
        ], $this->adminUser);

        $this->assertSame(WorkScheduleModality::TwentyFourBySeventyTwo, $shift24->modality);
        $this->assertSame(1320, $shift24->expected_daily_minutes);
    }

    /**
     * Teste 23: Regeneração de calendário preserva plantões manuais
     */
    public function test_regenerating_calendar_does_not_destroy_manual_shifts(): void
    {
        $genAction = app(GenerateCyclicShiftsAction::class);

        $genAction->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-04',
            assignedBy: $this->adminUser
        );

        $manualShift = ShiftAssignment::create([
            'shift_code' => 'MANUAL-EXTRA-01',
            'employee_id' => $this->doctor->id,
            'work_schedule_id' => $this->flexibleSchedule->id,
            'start_at_local' => Carbon::parse('2026-11-02 07:00:00', 'America/Sao_Paulo'),
            'end_at_local' => Carbon::parse('2026-11-02 19:00:00', 'America/Sao_Paulo'),
            'start_at_utc' => Carbon::parse('2026-11-02 07:00:00', 'America/Sao_Paulo')->utc(),
            'end_at_utc' => Carbon::parse('2026-11-02 19:00:00', 'America/Sao_Paulo')->utc(),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::ManualAssignment,
            'status' => ShiftStatus::Scheduled,
            'is_day_off' => false,
        ]);

        $genAction->execute(
            employee: $this->doctor,
            schedule: $this->flexibleSchedule,
            anchorDate: '2026-11-01',
            startDate: '2026-11-01',
            endDate: '2026-11-04',
            assignedBy: $this->adminUser
        );

        $this->assertDatabaseHas('shift_assignments', [
            'id' => $manualShift->id,
            'shift_code' => 'MANUAL-EXTRA-01',
            'origin' => ShiftOrigin::ManualAssignment->value,
        ]);
    }

    /**
     * Teste 24: TimesheetJourneyService totaliza plantões diurnos e noturnos no mês
     */
    public function test_timesheet_journey_service_returns_accurate_flexible_shift_counts(): void
    {
        $this->createPunch($this->doctor, '2026-11-01 07:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-01 19:00:00', 'out');

        $this->createPunch($this->doctor, '2026-11-03 19:00:00', 'in');
        $this->createPunch($this->doctor, '2026-11-04 07:00:00', 'out');

        $tsService = app(TimesheetJourneyService::class);
        $data = $tsService->resolveMonthData($this->doctorUser, 2026, 11);

        $this->assertTrue($data['hasFlexibleShifts']);
        $this->assertSame(1, $data['flexibleDayShiftsCount']);
        $this->assertSame(1, $data['flexibleNightShiftsCount']);
    }
}
