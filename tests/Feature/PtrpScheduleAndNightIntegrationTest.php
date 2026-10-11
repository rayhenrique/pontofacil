<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AEJ\AejValidator;
use App\Domain\LaborRules\Enums\NightCalculationStatus;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
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
use Tests\TestCase;

/**
 * Suíte de Testes de Integração Completa do PTRP:
 * Escalas Versionadas, Plantões 12x36 e 24x72, Regras Noturnas, Banco de Horas e Fechamento Mensal.
 */
class PtrpScheduleAndNightIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $admin;

    protected User $workerUser;

    protected Employee $employee;

    protected CalculateDailyJourneyAction $calculateJourneyAction;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'ORGANIZACAO MODELO PTRP LTDA',
            'trade_name' => 'Modelo PTRP',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.5.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Central',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sector = Sector::create([
            'name' => 'Operacoes',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Gestor RH',
            'email' => 'admin@ptrp.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->workerUser = User::create([
            'name' => 'Joao Trabalhador',
            'email' => 'joao@ptrp.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->workerUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-500',
            'cpf' => '12345678909',
            'job_title' => 'Operador',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $this->calculateJourneyAction = app(CalculateDailyJourneyAction::class);
    }

    /**
     * 1. Jornada administrativa normal: 40h semanais (08h-12h e 14h-18h), 480 min ordinários, 0 extras.
     */
    public function test_01_jornada_administrativa_normal(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $date = Carbon::parse('2026-10-05', 'America/Maceio'); // Segunda-feira

        $this->createPunch('in', '2026-10-05 08:00:00');
        $this->createPunch('out', '2026-10-05 12:00:00');
        $this->createPunch('in', '2026-10-05 14:00:00');
        $this->createPunch('out', '2026-10-05 18:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertTrue($journey->hasSchedule);
        $this->assertFalse($journey->isPendingConfiguration);
        $this->assertSame(480, $journey->scheduledMinutes);
        $this->assertSame(480, $journey->workedMinutes);
        $this->assertSame(480, $journey->ordinaryMinutes);
        $this->assertSame(0, $journey->overtimeMinutes);
        $this->assertSame(120, $journey->breakMinutes);
        $this->assertSame(0, $journey->missingBreakMinutes);
        $this->assertSame(0, $journey->nightWorkedMinutes());
        $this->assertFalse($journey->isIncomplete);
    }

    /**
     * 2. Jornada CLT 12×36 diurna: plantão regular com 11h trabalhadas e 1h intervalo.
     */
    public function test_02_jornada_clt_12x36_diurna(): void
    {
        $date = Carbon::parse('2026-10-06', 'America/Maceio');

        $shift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::ManualAssignment,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch('in', '2026-10-06 07:00:00');
        $this->createPunch('out', '2026-10-06 12:00:00');
        $this->createPunch('in', '2026-10-06 13:00:00');
        $this->createPunch('out', '2026-10-06 19:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertTrue($journey->hasSchedule);
        $this->assertSame($shift->id, $journey->shiftAssignmentId);
        $this->assertSame(660, $journey->scheduledMinutes);
        $this->assertSame(660, $journey->workedMinutes);
        $this->assertSame(660, $journey->ordinaryMinutes);
        $this->assertSame(0, $journey->overtimeMinutes);
        $this->assertSame(60, $journey->breakMinutes);
        $this->assertSame(0, $journey->nightWorkedMinutes());
    }

    /**
     * 3. Plantão noturno 12×36 (19h às 07h do dia seguinte):
     * Jornada única lógica iniciada em 10/10 com apuração noturna, hora ficta e prorrogação,
     * sem dupla contagem no dia seguinte.
     */
    public function test_03_plantao_noturno_12x36_com_prorrogacao(): void
    {
        $dateDay1 = Carbon::parse('2026-10-10', 'America/Maceio');
        $dateDay2 = Carbon::parse('2026-10-11', 'America/Maceio');

        $shift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-10-10 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-11 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-10 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-11 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'origin' => ShiftOrigin::ManualAssignment,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'is_night_shift' => true,
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
        ]);

        // Batidas físicas: entrada no dia 10 e saída no dia 11
        $this->createPunch('in', '2026-10-10 19:00:00');
        $this->createPunch('out', '2026-10-11 07:00:00');

        // Apuração do Dia 10 (Dia de início da jornada lógica)
        $journeyDay1 = $this->calculateJourneyAction->execute($this->employee, $dateDay1);

        $this->assertTrue($journeyDay1->hasSchedule);
        $this->assertSame(720, $journeyDay1->scheduledMinutes);
        $this->assertSame(720, $journeyDay1->workedMinutes);
        $this->assertSame(720, $journeyDay1->ordinaryMinutes);
        $this->assertSame(0, $journeyDay1->overtimeMinutes);
        $this->assertFalse($journeyDay1->isIncomplete);

        // Apuração Noturna do Dia 10:
        // Janela noturna: 22h às 05h = 7 horas físicas (420 min = 25.200s).
        // Prorrogação noturna (05h às 07h): 2 horas (120 min = 7.200s).
        // Na CLT 12x36, prorrogação após as 05h tem tratamento legal garantido.
        $this->assertGreaterThan(0, $journeyDay1->physicalNightMinutes);
        $this->assertGreaterThan($journeyDay1->physicalNightMinutes, $journeyDay1->legalNightEquivalentMinutes);
        $this->assertGreaterThan(0, $journeyDay1->nightFictionalBonusMinutes);

        // Apuração do Dia 11: A batida das 07h é a saída da jornada anterior
        $journeyDay2 = $this->calculateJourneyAction->execute($this->employee, $dateDay2);

        // Em 11/10 não pode computar as mesmas 12h novamente (evitar dupla contagem)
        $this->assertSame(0, $journeyDay2->workedMinutes);
        $this->assertFalse($journeyDay2->isIncomplete);
        $this->assertTrue($journeyDay2->effectivePunches[0]['is_previous_day_exit'] ?? false);
    }

    /**
     * 4. Plantão 24×72 configurado: 24h contínuas (1440 min).
     */
    public function test_04_plantao_24x72_configurado(): void
    {
        $dateDay1 = Carbon::parse('2026-10-12', 'America/Maceio');
        $dateDay2 = Carbon::parse('2026-10-13', 'America/Maceio');

        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-10-12 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-13 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-12 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-13 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift24x72,
            'origin' => ShiftOrigin::ManualAssignment,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'is_night_shift' => true,
            'break_minutes' => 0,
            'expected_work_minutes' => 1440,
        ]);

        $this->createPunch('in', '2026-10-12 07:00:00');
        $this->createPunch('out', '2026-10-13 07:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $dateDay1);

        $this->assertSame(1440, $journey->scheduledMinutes);
        $this->assertSame(1440, $journey->workedMinutes);
        $this->assertSame(1440, $journey->ordinaryMinutes);
        $this->assertGreaterThan(0, $journey->physicalNightMinutes);
    }

    /**
     * 5. Jornada atravessando meia-noite sem shift explícito (heurística protegida).
     */
    public function test_05_jornada_atravessando_meia_noite_sem_shift(): void
    {
        $dateDay1 = Carbon::parse('2026-10-14', 'America/Maceio');
        $dateDay2 = Carbon::parse('2026-10-15', 'America/Maceio');

        $schedule = WorkSchedule::create([
            'name' => 'Escala Noturna Avulsa',
            'schedule_data' => [
                3 => ['expected_minutes' => 480, 'periods' => [['start' => '22:00', 'end' => '06:00']]],
            ],
            'active' => true,
        ]);
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch('in', '2026-10-14 22:00:00');
        $this->createPunch('out', '2026-10-15 06:00:00');

        $journey1 = $this->calculateJourneyAction->execute($this->employee, $dateDay1);
        $this->assertSame(480, $journey1->workedMinutes);
        $this->assertFalse($journey1->isIncomplete);

        $journey2 = $this->calculateJourneyAction->execute($this->employee, $dateDay2);
        $this->assertSame(0, $journey2->workedMinutes);
    }

    /**
     * 6. Jornada atravessando mês (31/10 às 19h até 01/11 às 07h).
     */
    public function test_06_jornada_atravessando_mes(): void
    {
        $oct31 = Carbon::parse('2026-10-31', 'America/Maceio');
        $nov01 = Carbon::parse('2026-11-01', 'America/Maceio');

        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-10-31 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-11-01 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-31 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-11-01 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'expected_work_minutes' => 720,
        ]);

        $this->createPunch('in', '2026-10-31 19:00:00');
        $this->createPunch('out', '2026-11-01 07:00:00');

        $journeyOct = $this->calculateJourneyAction->execute($this->employee, $oct31);
        $this->assertSame(720, $journeyOct->workedMinutes);

        $journeyNov = $this->calculateJourneyAction->execute($this->employee, $nov01);
        $this->assertSame(0, $journeyNov->workedMinutes);
    }

    /**
     * 7. Jornada atravessando ano (31/12 às 19h até 01/01 às 07h).
     */
    public function test_07_jornada_atravessando_ano(): void
    {
        $dec31 = Carbon::parse('2026-12-31', 'America/Maceio');
        $jan01 = Carbon::parse('2027-01-01', 'America/Maceio');

        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-12-31 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2027-01-01 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-12-31 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2027-01-01 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'expected_work_minutes' => 720,
        ]);

        $this->createPunch('in', '2026-12-31 19:00:00');
        $this->createPunch('out', '2027-01-01 07:00:00');

        $journeyDec = $this->calculateJourneyAction->execute($this->employee, $dec31);
        $this->assertSame(720, $journeyDec->workedMinutes);

        $journeyJan = $this->calculateJourneyAction->execute($this->employee, $jan01);
        $this->assertSame(0, $journeyJan->workedMinutes);
    }

    /**
     * 8. Intervalos: escala prevê 120m, empregado usufrui 60m -> missingBreakMinutes = 60m.
     */
    public function test_08_intervalos_e_supressao(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $date = Carbon::parse('2026-10-07', 'America/Maceio');

        $this->createPunch('in', '2026-10-07 08:00:00');
        $this->createPunch('out', '2026-10-07 12:00:00');
        $this->createPunch('in', '2026-10-07 13:00:00'); // Intervalo de 60 min (previsto era 120 min)
        $this->createPunch('out', '2026-10-07 17:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertSame(60, $journey->breakMinutes);
        $this->assertSame(60, $journey->missingBreakMinutes);
    }

    /**
     * 9. Feriado em plantão previsto 12×36: compensado pela folga de 36h (CLT Art. 59-A).
     */
    public function test_09_feriado_em_plantao_previsto_12x36(): void
    {
        $date = Carbon::parse('2026-11-15', 'America/Maceio'); // Proclamação da República

        CalendarEvent::create([
            'name' => 'Proclamação da República',
            'event_date' => '2026-11-15',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'active' => true,
        ]);

        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_local' => Carbon::parse('2026-11-15 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-11-15 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-11-15 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-11-15 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'expected_work_minutes' => 720,
        ]);

        $this->createPunch('in', '2026-11-15 07:00:00');
        $this->createPunch('out', '2026-11-15 19:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertTrue($journey->requiresCompensation);
        $this->assertSame(720, $journey->workedMinutes);
        $this->assertSame(720, $journey->ordinaryMinutes);
        $this->assertSame(0, $journey->holidayMinutes);
    }

    /**
     * 10. Folga da escala: se trabalhar em dia de folga da escala, gera horas extras.
     */
    public function test_10_trabalho_em_folga_da_escala_gera_hora_extra(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $sunday = Carbon::parse('2026-10-11', 'America/Maceio'); // Domingo (folga da escala)

        $this->createPunch('in', '2026-10-11 08:00:00');
        $this->createPunch('out', '2026-10-11 12:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $sunday);

        $this->assertSame(0, $journey->scheduledMinutes);
        $this->assertSame(240, $journey->workedMinutes);
        $this->assertSame(0, $journey->ordinaryMinutes);
        $this->assertSame(240, $journey->overtimeMinutes);
        $this->assertSame(240, $journey->bankCreditMinutes);
    }

    /**
     * 11. Servidor municipal com regra própria aprovada (adicional 30%, noite 21h-05h).
     */
    public function test_11_servidor_municipal_com_regra_propria(): void
    {
        $munProfile = LaborRuleProfile::create([
            'name' => 'Estatuto Municipal Maceió',
            'code' => 'ESTATUTO_MUN_MCZ',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'jurisdiction' => 'municipal',
            'legal_reference' => 'Lei Municipal 1.234/2020',
            'effective_from' => '2026-01-01',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'configuration' => [
                'night_start_time' => '21:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 30.0,
                'apply_reduced_hour' => false,
            ],
        ]);

        $munWorkerUser = User::create([
            'name' => 'Maria Servidora',
            'email' => 'maria.serv@ptrp.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $munEmp = Employee::create([
            'user_id' => $munWorkerUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-MUN-01',
            'cpf' => '99988877766',
            'job_title' => 'Fiscal Municipal',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'labor_rule_profile_id' => $munProfile->id,
        ]);

        $date = Carbon::parse('2026-10-08', 'America/Maceio');

        $this->createPunchForEmployee($munEmp, 'in', '2026-10-08 21:00:00');
        $this->createPunchForEmployee($munEmp, 'out', '2026-10-09 01:00:00');

        $journey = $this->calculateJourneyAction->execute($munEmp, $date);

        $this->assertSame(240, $journey->physicalNightMinutes);
        $this->assertSame(30.0, $journey->nightWorkResult?->nightAdditionalPercentage);
        $this->assertSame(NightCalculationStatus::Calculated, $journey->nightWorkResult?->calculationStatus);
    }

    /**
     * 12. Servidor municipal sem perfil válido: não herda CLT, apura horas físicas com status pendente de parametrização.
     */
    public function test_12_servidor_municipal_sem_perfil_valido(): void
    {
        $munEmp = Employee::create([
            'user_id' => User::create(['name' => 'Servidor Sem Perfil', 'email' => 'sem.perfil@ptrp.local', 'password' => 'secret123', 'role' => UserRole::Employee])->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-MUN-02',
            'cpf' => '55544433322',
            'job_title' => 'Agente Sem Perfil',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        $date = Carbon::parse('2026-10-08', 'America/Maceio');

        $this->createPunchForEmployee($munEmp, 'in', '2026-10-08 22:00:00');
        $this->createPunchForEmployee($munEmp, 'out', '2026-10-09 02:00:00');

        $journey = $this->calculateJourneyAction->execute($munEmp, $date);

        $this->assertSame(240, $journey->physicalNightMinutes);
        $this->assertSame(NightCalculationStatus::PendingConfiguration, $journey->nightWorkResult?->calculationStatus);
        $this->assertNull($journey->nightWorkResult?->nightAdditionalPercentage);
    }

    /**
     * 13. Trabalho noturno CLT: 22h às 05h (7h físicas = 420m) equivalem a 8h legais (480m), com 60m de hora ficta.
     */
    public function test_13_trabalho_noturno_clt_com_hora_ficta(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Escala Noturna 7h',
            'schedule_data' => [
                4 => ['expected_minutes' => 480, 'periods' => [['start' => '22:00', 'end' => '05:00']]],
            ],
            'active' => true,
        ]);
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $date = Carbon::parse('2026-10-08', 'America/Maceio');

        $this->createPunch('in', '2026-10-08 22:00:00');
        $this->createPunch('out', '2026-10-09 05:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertSame(420, $journey->workedMinutes);
        $this->assertSame(420, $journey->physicalNightMinutes);
        $this->assertSame(480, $journey->legalNightEquivalentMinutes);
        $this->assertSame(60, $journey->nightFictionalBonusMinutes);
    }

    /**
     * 14. Mudança de escala no mês: Escala A de 01/10 a 15/10 e Escala B a partir de 16/10.
     */
    public function test_14_mudanca_de_escala_no_mes(): void
    {
        $sched30h = WorkSchedule::create([
            'name' => 'Escala 30h',
            'code' => 'ESC-30H',
            'schedule_data' => [
                1 => ['expected_minutes' => 360, 'periods' => [['start' => '08:00', 'end' => '14:00']]],
            ],
            'active' => true,
        ]);

        $sched40h = WorkSchedule::create([
            'name' => 'Escala 40h',
            'code' => 'ESC-40H',
            'schedule_data' => [
                1 => ['expected_minutes' => 480, 'periods' => [['start' => '08:00', 'end' => '17:00']]],
            ],
            'active' => true,
        ]);

        WorkScheduleAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $sched30h->id,
            'effective_from' => '2026-10-01',
            'effective_until' => '2026-10-15',
            'reason' => 'Período probatório',
        ]);

        WorkScheduleAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $sched40h->id,
            'effective_from' => '2026-10-16',
            'effective_until' => null,
            'reason' => 'Efetivação contratual',
        ]);

        $date1 = Carbon::parse('2026-10-05', 'America/Maceio'); // Segunda-feira antes do dia 15
        $date2 = Carbon::parse('2026-10-19', 'America/Maceio'); // Segunda-feira após o dia 15

        $journey1 = $this->calculateJourneyAction->execute($this->employee, $date1);
        $journey2 = $this->calculateJourneyAction->execute($this->employee, $date2);

        $this->assertSame(360, $journey1->scheduledMinutes);
        $this->assertSame('ESC-30H', $journey1->workScheduleCode);

        $this->assertSame(480, $journey2->scheduledMinutes);
        $this->assertSame('ESC-40H', $journey2->workScheduleCode);
    }

    /**
     * 15. Mudança de perfil jurídico por vigência.
     */
    public function test_15_mudanca_de_perfil_juridico_por_vigencia(): void
    {
        $profileV1 = LaborRuleProfile::create([
            'name' => 'Acordo Coletivo 2025/2026',
            'code' => 'ACT_V1',
            'legal_regime' => LegalRegime::CLT,
            'jurisdiction' => 'federal',
            'legal_reference' => 'ACT 2025/2026 Cláusula 4ª',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-10-15',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'configuration' => [
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 20.0,
            ],
        ]);

        $profileV2 = LaborRuleProfile::create([
            'name' => 'Acordo Coletivo 2026/2027',
            'code' => 'ACT_V2',
            'legal_regime' => LegalRegime::CLT,
            'jurisdiction' => 'federal',
            'legal_reference' => 'ACT 2026/2027 Cláusula 4ª',
            'effective_from' => '2026-10-16',
            'effective_until' => '2027-10-15',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'configuration' => [
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 25.0,
            ],
        ]);

        $date1 = Carbon::parse('2026-10-10', 'America/Maceio');
        $date2 = Carbon::parse('2026-10-20', 'America/Maceio');

        $p1 = $this->employee->getLaborRuleProfileForDate($date1);
        $p2 = $this->employee->getLaborRuleProfileForDate($date2);

        $this->assertSame('ACT_V1', $p1->code);
        $this->assertSame(20.0, $p1->getAdditionalPercentage());

        $this->assertSame('ACT_V2', $p2->code);
        $this->assertSame(25.0, $p2->getAdditionalPercentage());
    }

    /**
     * 16. Banco de horas sem créditos indevidos: adicional noturno NÃO entra no saldo do banco.
     */
    public function test_16_banco_de_horas_sem_creditos_indevidos(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Escala 6h Noturna',
            'schedule_data' => [
                5 => ['expected_minutes' => 360, 'periods' => [['start' => '22:00', 'end' => '04:00']]],
            ],
            'active' => true,
        ]);
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $date = Carbon::parse('2026-10-09', 'America/Maceio'); // Sexta-feira

        // Trabalhou das 22h às 06h (480 min = 8h físicas). Previsto era 360 min.
        // Overtime = 120 min. Horas noturnas = 420 min físicas.
        $this->createPunch('in', '2026-10-09 22:00:00');
        $this->createPunch('out', '2026-10-10 06:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertSame(120, $journey->overtimeMinutes);
        $this->assertSame(120, $journey->compensableOvertimeMinutes);
        // O crédito do banco deve ser estritamente os 120 min de hora extra, SEM somar o adicional noturno!
        $this->assertSame(120, $journey->bankCreditMinutes);
    }

    /**
     * 17. Competência fechada e imutável: snapshot congelado com plantões e perfil normativo.
     */
    public function test_17_competencia_fechada_e_imutavel(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch('in', '2026-01-05 08:00:00');
        $this->createPunch('out', '2026-01-05 12:00:00');
        $this->createPunch('in', '2026-01-05 14:00:00');
        $this->createPunch('out', '2026-01-05 18:00:00');

        $closed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Fechamento Oficial Janeiro 2026'
        );

        $this->assertSame('closed', $closed->status);
        $snapshot = $closed->currentSnapshots()->where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertNotNull($snapshot->rule_profile_snapshot);
        $this->assertNotNull($snapshot->schedule_snapshot);

        // Tentativa de fechar novamente deve lançar exceção
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já se encontra fechada e congelada');

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );
    }

    /**
     * 18. Reabertura auditada com justificativa legal e versionamento formal.
     */
    public function test_18_reabertura_auditada(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch('in', '2026-01-05 08:00:00');
        $this->createPunch('out', '2026-01-05 18:00:00');

        $closed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $reopened = app(ReopenMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            reopenedBy: $this->admin,
            reason: 'Reabertura formal para inclusão de atestado médico com homologação judicial.'
        );

        $this->assertSame('reopened', $reopened->status);
        $this->assertSame($this->admin->id, $reopened->reopened_by);
        $this->assertNotNull($reopened->reopened_at);

        // Segundo fechamento gera versão 2 sem apagar histórico da versão 1
        $reclosed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $this->assertSame(2, $reclosed->snapshot_version);
        $this->assertSame(2, $reclosed->snapshots()->count());
    }

    /**
     * 19. AEJ compatível e validado após fechamento com plantões.
     */
    public function test_19_aej_compativel(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch('in', '2026-01-05 08:00:00');
        $this->createPunch('out', '2026-01-05 12:00:00');
        $this->createPunch('in', '2026-01-05 13:00:00');
        $this->createPunch('out', '2026-01-05 17:00:00');

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $generator = app(AejGenerator_2026_07_31::class);
        $validator = app(AejValidator::class);

        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: false
        );

        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros no AEJ: '.implode('; ', $validation['errors']));
    }

    /**
     * 20. Ausência de alteração de NSR/ARP: batidas brutas são estritamente imutáveis.
     */
    public function test_20_ausencia_de_alteracao_de_nsr_arp(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $p1 = $this->createPunch('in', '2026-01-05 08:00:00');
        $p2 = $this->createPunch('out', '2026-01-05 17:00:00');

        $nsr1 = $p1->nsr;
        $nsr2 = $p2->nsr;
        $hash1 = $p1->fiscal_hash;

        // Executa cálculo PTRP
        $journey = $this->calculateJourneyAction->execute($this->employee, Carbon::parse('2026-01-05'));

        // Executa fechamento mensal
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $p1->refresh();
        $p2->refresh();

        $this->assertSame($nsr1, $p1->nsr);
        $this->assertSame($nsr2, $p2->nsr);
        $this->assertSame($hash1, $p1->fiscal_hash);
    }

    /**
     * 21. Colaborador legado sem plantão nem regras noturnas especiais apura com precisão.
     */
    public function test_21_legado_sem_regressao(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $this->employee->update(['work_schedule_id' => $schedule->id]);

        $date = Carbon::parse('2026-10-05', 'America/Maceio');

        $this->createPunch('in', '2026-10-05 08:00:00');
        $this->createPunch('out', '2026-10-05 12:00:00');
        $this->createPunch('in', '2026-10-05 14:00:00');
        $this->createPunch('out', '2026-10-05 18:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertSame(480, $journey->scheduledMinutes);
        $this->assertSame(480, $journey->workedMinutes);
        $this->assertSame(480, $journey->ordinaryMinutes);
        $this->assertSame(0, $journey->overtimeMinutes);
        $this->assertSame(0, $journey->lateMinutes);
    }

    /**
     * 22. Ausência total de escala configurada: não bloqueia batidas e não gera extras fictícias.
     */
    public function test_22_ausencia_total_de_escala_preserva_batidas_e_sinaliza_pendencia(): void
    {
        // Colaborador sem escala vinculada
        $this->employee->update(['work_schedule_id' => null]);

        $date = Carbon::parse('2026-10-05', 'America/Maceio');

        $this->createPunch('in', '2026-10-05 08:00:00');
        $this->createPunch('out', '2026-10-05 12:00:00');

        $journey = $this->calculateJourneyAction->execute($this->employee, $date);

        $this->assertFalse($journey->hasSchedule);
        $this->assertTrue($journey->isPendingConfiguration);
        $this->assertSame(0, $journey->scheduledMinutes);
        $this->assertSame(240, $journey->workedMinutes); // Minutos físicos preservados
        $this->assertSame(0, $journey->ordinaryMinutes);
        $this->assertSame(0, $journey->overtimeMinutes); // NÃO inventa horas extras fictícias
        $this->assertSame(0, $journey->bankCreditMinutes); // NÃO inventa crédito de banco
        $this->assertSame(0, $journey->bankDebitMinutes);
        $this->assertCount(2, $journey->effectivePunches);
        $this->assertStringContainsString('Ausência de escala de trabalho válida', $journey->treatmentNotes[0] ?? '');
    }

    /**
     * Helper para registrar PunchEvent com NSR monotônico e timezone correto.
     */
    protected function createPunch(string $direction, string $localTime): PunchEvent
    {
        return $this->createPunchForEmployee($this->employee, $direction, $localTime);
    }

    protected function createPunchForEmployee(Employee $employee, string $direction, string $localTime): PunchEvent
    {
        Carbon::setTestNow(Carbon::parse($localTime, 'America/Maceio'));

        $punch = app(RecordPunchEventAction::class)->execute(
            user: $employee->user,
            direction: $direction,
            establishment: $this->establishment,
        );

        Carbon::setTestNow();

        return $punch;
    }
}
