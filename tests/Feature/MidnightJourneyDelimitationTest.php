<?php

namespace Tests\Feature;

use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyBatchContext;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidnightJourneyDelimitationTest extends TestCase
{
    use RefreshDatabase;

    protected Establishment $establishment;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(
            ['cnpj' => '11780685000152'],
            [
                'legal_name' => 'Prefeitura Municipal Teste',
                'trade_name' => 'PontoFácil Etapa 2',
            ]
        );

        $this->establishment = Establishment::firstOrCreate(
            ['company_id' => $this->company->id],
            [
                'code' => 'SEDE',
                'name' => 'Sede Central',
                'identifier_type' => 'CNPJ',
                'identifier_number' => '11780685000152',
                'timezone' => 'America/Maceio',
                'nsr_next' => 1,
            ]
        );
    }

    protected function createEmployeeUser(
        string $name,
        string $reg = '000001',
        ?WorkSchedule $schedule = null,
        ?Establishment $est = null,
        LegalRegime $regime = LegalRegime::CLT
    ): array {
        $est = $est ?? $this->establishment;

        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@test.gov.br',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'establishment_id' => $est->id,
            'registration_number' => $reg,
            'cpf' => sprintf('123456%05d', (int) $reg),
            'job_title' => 'Servidor Público',
            'legal_regime' => $regime,
            'work_schedule_id' => $schedule?->id,
        ]);

        return [$user, $employee];
    }

    protected function createPunch(
        Employee $employee,
        string $localDateTimeString,
        string $direction,
        ?Establishment $est = null
    ): PunchEvent {
        $est = $est ?? $this->establishment;
        $tz = $est->timezone ?? 'America/Maceio';

        $local = Carbon::parse($localDateTimeString, $tz);
        $utc = $local->copy()->utc();

        $nsr = $est->nsr_next++;
        $est->save();

        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $est->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $nsr,
            'occurred_at_utc' => $utc,
            'occurred_at_local' => $local,
            'direction' => $direction,
            'timezone' => $tz,
            'fiscal_hash' => hash('sha256', (string) $nsr),
            'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
            'payload_hash' => hash('sha256', 'payload'.$nsr),
        ]);
    }

    /**
     * 1. Administrativo 08h–17h com 1h de almoço: respeita dia civil, calcula 8h.
     */
    public function test_01_jornada_administrativa_08h_as_17h(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Administrativo', '000001', $schedule);

        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 12:00:00', 'out');
        $this->createPunch($employee, '2026-10-05 13:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 17:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $journey = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertSame(480, $journey->workedMinutes);
        $this->assertSame(60, $journey->breakMinutes);
        $this->assertFalse($journey->isIncomplete);
        $this->assertCount(4, $journey->effectivePunches);
    }

    /**
     * 2. Corrido 07h–13h (6h sem intervalo).
     */
    public function test_02_jornada_corrida_07h_as_13h(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'CORR-6H',
            'name' => 'Jornada Corrida 6h',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 360,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Servidor Corrido', '000002', $schedule);

        $this->createPunch($employee, '2026-10-05 07:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 13:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $journey = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertSame(360, $journey->workedMinutes);
        $this->assertSame(0, $journey->breakMinutes);
        $this->assertFalse($journey->isIncomplete);
    }

    /**
     * 3. Entrada 23h50, sem saída, nova entrada 00h05 (Jornada comum).
     * Não vincula marcações entre dias civis. Dia D fica incompleto, Dia D+1 inicia nova sequência.
     */
    public function test_03_entrada_23h50_sem_saida_nova_entrada_00h05_jornada_comum(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Noturno Sem Plantao', '000003', $schedule);

        $this->createPunch($employee, '2026-10-05 23:50:00', 'in');
        $this->createPunch($employee, '2026-10-06 00:05:00', 'in');
        $this->createPunch($employee, '2026-10-06 08:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        // Dia 05: entrada às 23:50 sem saída no dia civil
        $journeyDay5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertTrue($journeyDay5->isIncomplete);
        $this->assertSame(0, $journeyDay5->workedMinutes);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $journeyDay5->attentionReason());

        // Dia 06: nova sequência iniciada às 00:05 e encerrada às 08:00 (475 min)
        $journeyDay6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertFalse($journeyDay6->isIncomplete);
        $this->assertSame(475, $journeyDay6->workedMinutes);
    }

    /**
     * 4. Primeira batida exatamente às 00h00.
     * Intervalo [00:00:00, 00:00:00 do dia seguinte) inclui 00:00:00.
     * Auto-direção determina 'in' e apuração inclui no novo dia civil.
     */
    public function test_04_primeira_batida_exatamente_as_00h00(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Meia Noite Exata', '000004', $schedule);

        // Testa auto-direção às 00:00:00 de um novo dia
        $directionAction = app(DetermineNextPunchDirectionAction::class);
        $dir = $directionAction->execute($user, Carbon::parse('2026-10-06 00:00:00', 'America/Maceio'), $this->establishment);
        $this->assertSame('in', $dir);

        // Registra a batida às 00:00:00 (in) e às 06:00:00 (out)
        $this->createPunch($employee, '2026-10-06 00:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 06:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $journey = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));

        $this->assertFalse($journey->isIncomplete);
        $this->assertSame(360, $journey->workedMinutes);
    }

    /**
     * 5. Virada de mês e ano (31/12 -> 01/01).
     * Jornada comum não vaza entre anos/meses.
     */
    public function test_05_virada_de_mes_e_ano(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Reveillon', '000005', $schedule);

        $this->createPunch($employee, '2025-12-31 23:30:00', 'in');
        $this->createPunch($employee, '2026-01-01 08:00:00', 'in');
        $this->createPunch($employee, '2026-01-01 17:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        // 31/12/2025: Incompleto
        $journeyOldYear = $action->execute($employee, Carbon::parse('2025-12-31', 'America/Maceio'));
        $this->assertTrue($journeyOldYear->isIncomplete);
        $this->assertSame(0, $journeyOldYear->workedMinutes);

        // 01/01/2026: Nova jornada independente de 08h às 17h (540 min)
        $journeyNewYear = $action->execute($employee, Carbon::parse('2026-01-01', 'America/Maceio'));
        $this->assertFalse($journeyNewYear->isIncomplete);
        $this->assertSame(540, $journeyNewYear->workedMinutes);
    }

    /**
     * 6. Entrada anterior sem saída.
     * Não gera jornada de 24h ou 30h fictícias.
     */
    public function test_06_entrada_anterior_sem_saida(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Esquecimento', '000006', $schedule);

        // Dia 1: Bateu às 08:00 e esqueceu de bater a saída
        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');

        // Dia 2: Bateu normalmente às 08:00 e às 17:00
        $this->createPunch($employee, '2026-10-06 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 17:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        $day1 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertTrue($day1->isIncomplete);
        $this->assertSame(0, $day1->workedMinutes);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day1->attentionReason());

        $day2 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertFalse($day2->isIncomplete);
        $this->assertSame(540, $day2->workedMinutes);
    }

    /**
     * 7. Saída órfã no novo dia.
     * Marcação explícita de saída sem entrada no novo dia fica como ocorrência órfã, sem ligar a ontem.
     */
    public function test_07_saida_orfa_no_novo_dia(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Saida Orfa', '000007', $schedule);

        // Dia 1: Jornada normal
        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 17:00:00', 'out');

        // Dia 2: Apenas uma batida com direção 'out' às 07:00
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $day2 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));

        $this->assertTrue($day2->isIncomplete);
        $this->assertSame(0, $day2->workedMinutes);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day2->attentionReason());
    }

    /**
     * 8. Plantão 19h–07h (12x36 noturno autorizado).
     * Continuidade após 00:00 autorizada para o plantão.
     */
    public function test_08_plantao_19h_as_07h_12x36_autorizado(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-NOT',
            'name' => 'Plantão 12x36 Noturno',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Enfermeiro Plantonista', '000008', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        // Dia 05: Contabiliza o plantão completo de 12 horas (720 min)
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertFalse($day5->isIncomplete);
        $this->assertSame(720, $day5->workedMinutes);

        // Dia 06: A saída das 07:00 pertenceu ao dia anterior, então não gera batida residual/órfã
        $day6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertSame(0, $day6->workedMinutes);
        $this->assertFalse($day6->isIncomplete);
    }

    /**
     * 9. Plantão 08h–08h com 24h (24x72 autorizado).
     */
    public function test_09_plantao_08h_as_08h_com_24h_autorizado(): void
    {
        LaborRuleProfile::createFederalStatutoryProfile();

        $schedule = WorkSchedule::create([
            'code' => 'PL-24X72',
            'name' => 'Plantão 24x72',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'expected_daily_minutes' => 1440,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser(
            'Médico Plantonista 24h',
            '000009',
            $schedule,
            null,
            LegalRegime::FederalStatutory
        );

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 08:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 08:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 11:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 11:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift24x72,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 120,
            'expected_work_minutes' => 1320,
        ]);

        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 08:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertFalse($day5->isIncomplete);
        $this->assertSame(1440, $day5->workedMinutes);
    }

    /**
     * 10. Múltiplos intervalos atravessando 00h em plantão.
     * 19h in, 23h30 out, 00h30 in, 07h out.
     */
    public function test_10_multiplos_intervalos_atravessando_00h(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-INT',
            'name' => 'Plantão com Intervalo',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Plantonista com Ceia', '000010', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 23:30:00', 'out');
        $this->createPunch($employee, '2026-10-06 00:30:00', 'in');
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertFalse($day5->isIncomplete);
        // Período 1: 19:00 às 23:30 = 270 min. Período 2: 00:30 às 07:00 = 390 min. Total = 660 min (11h)
        $this->assertSame(660, $day5->workedMinutes);
        $this->assertSame(60, $day5->breakMinutes);

        // Dia 06 não fica com batidas residuais
        $day6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertSame(0, $day6->workedMinutes);
        $this->assertFalse($day6->isIncomplete);
    }

    /**
     * 11. Plantão sem programação válida (continuidade bloqueada).
     */
    public function test_11_plantao_sem_programacao_valida_bloqueia_continuidade(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Sem Plantao Atribuido', '000011', $schedule);

        // Não há ShiftAssignment para o colaborador
        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertTrue($day5->isIncomplete);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day5->attentionReason());

        $day6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertTrue($day6->isIncomplete);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day6->attentionReason());
    }

    /**
     * 12. Plantão cancelado (status Cancelled bloqueia continuidade).
     */
    public function test_12_plantao_cancelado_bloqueia_continuidade(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-CANC',
            'name' => 'Plantão 12x36',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Plantonista Cancelado', '000012', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Cancelled, // Cancelado!
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertTrue($day5->isIncomplete);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day5->attentionReason());
    }

    /**
     * 13. Sobreaviso / Folga não habilitam continuidade automática.
     */
    public function test_13_sobreaviso_nao_habilita_trabalho_continuo_automatico(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'PL-SOBREAVISO',
            'name' => 'Sobreaviso Plantão',
            'modality' => WorkScheduleModality::VariableShifts,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Servidor Sobreaviso', '000013', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::OnCall, // Sobreaviso
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 0,
            'expected_work_minutes' => 0,
        ]);

        $this->createPunch($employee, '2026-10-05 23:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 05:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        // Bloqueado para continuidade automática de plantão
        $this->assertTrue($day5->isIncomplete);
    }

    /**
     * 14. Ajuste manual aprovado depois da virada (TreatmentEvent).
     */
    public function test_14_ajuste_manual_aprovado_fecha_jornada_do_dia_anterior(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Com Ajuste', '000014', $schedule);

        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');

        // Cria e aprova TreatmentEvent para a saída às 17:00 do dia 05
        TreatmentEvent::create([
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'requested_by' => $user->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => 'approved',
            'effective_at' => Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            'new_value_json' => ['direction' => 'out'],
            'reason_text' => 'Esquecimento de registro no relógio',
            'decision_at' => now(),
        ]);

        $action = app(CalculateDailyJourneyAction::class);
        $journey = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));

        $this->assertFalse($journey->isIncomplete);
        $this->assertSame(540, $journey->workedMinutes);
        $this->assertCount(2, $journey->effectivePunches);
    }

    /**
     * 15. Competência fechada preserva snapshot congelado.
     */
    public function test_15_competencia_fechada_preserva_snapshot_congelado(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Competencia Fechada', '000015', $schedule);

        $period = ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'closed_at' => now(),
            'closed_by' => $user->id,
            'status' => 'closed',
            'snapshot_hash' => hash('sha256', 'snapshot_test'),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $period->id,
            'employee_id' => $employee->id,
            'version' => 1,
            'employee_snapshot' => ['name' => $user->name, 'cpf' => $employee->cpf],
            'schedule_snapshot' => ['modality' => '40h'],
            'journey_snapshot' => ['worked_minutes' => 9600, 'balance_minutes' => 0],
            'treatment_snapshot' => [],
            'time_bank_snapshot' => ['balance' => 0],
            'snapshot_hash' => hash('sha256', 'emp_snap'),
        ]);

        $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(9600, $snapshot->journey_snapshot['worked_minutes']);
        $this->assertSame($user->name, $snapshot->employee_snapshot['name']);
    }

    /**
     * 16. Duplo clique e idempotência do registro de ponto.
     * DetermineNextPunchDirectionAction e NSR monotônico sem salto errático.
     */
    public function test_16_duplo_clique_e_idempotencia(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Duplo Clique', '000016', $schedule);

        $directionAction = app(DetermineNextPunchDirectionAction::class);
        $now = Carbon::parse('2026-10-05 08:00:00', 'America/Maceio');

        $dir1 = $directionAction->execute($user, $now, $this->establishment);
        $this->assertSame('in', $dir1);

        $punch1 = $this->createPunch($employee, '2026-10-05 08:00:00', $dir1);

        // Se uma nova batida ocorrer 2 segundos depois, a próxima direção é 'out'
        $dir2 = $directionAction->execute($user, $now->copy()->addSeconds(2), $this->establishment);
        $this->assertSame('out', $dir2);

        $punch2 = $this->createPunch($employee, '2026-10-05 08:00:02', $dir2);
        $this->assertGreaterThan($punch1->nsr, $punch2->nsr);
    }

    /**
     * 17. Fuso do estabelecimento: Manaus (UTC-4) vs Maceió (UTC-3).
     * O dia civil local respeita o fuso do estabelecimento.
     */
    public function test_17_fuso_do_estabelecimento_manaus(): void
    {
        $estManaus = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'MANAUS',
            'name' => 'Unidade Manaus',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '11780685000233',
            'timezone' => 'America/Manaus',
            'nsr_next' => 1,
        ]);

        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Manaus', '000017', $schedule, $estManaus);

        // 23:30 em Manaus (UTC-4) corresponde a 03:30 UTC do dia seguinte
        $this->createPunch($employee, '2026-10-05 23:30:00', 'in', $estManaus);

        $action = app(CalculateDailyJourneyAction::class);
        // Calcula o dia 2026-10-05 no fuso de Manaus
        $journeyDay5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Manaus'));

        // A batida de 23:30 local pertence ao dia 05 em Manaus!
        $this->assertCount(1, $journeyDay5->effectivePunches);
        $this->assertSame('23:30', $journeyDay5->effectivePunches[0]['time']);

        // Calcula o dia 2026-10-06 no fuso de Manaus
        $journeyDay6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Manaus'));
        // Não há batida no dia 06
        $this->assertCount(0, $journeyDay6->effectivePunches);
    }

    /**
     * 18. Paridade processamento individual vs lote (MonthlyBatchContext).
     */
    public function test_18_paridade_processamento_individual_vs_lote(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-PAR',
            'name' => 'Plantão 12x36 Paridade',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Servidor Paridade', '000018', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        // 1. Processamento individual
        $action = app(CalculateDailyJourneyAction::class);
        $indDay5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $indDay6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));

        // 2. Processamento em lote
        $batch = new MonthlyBatchContext(collect([$employee]), 2026, 10);
        $batchDay5 = $batch->journeyFor($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $batchDay6 = $batch->journeyFor($employee, Carbon::parse('2026-10-06', 'America/Maceio'));

        // Validação da paridade absoluta
        $this->assertSame($indDay5->workedMinutes, $batchDay5->workedMinutes);
        $this->assertSame($indDay5->isIncomplete, $batchDay5->isIncomplete);
        $this->assertSame($indDay6->workedMinutes, $batchDay6->workedMinutes);
        $this->assertSame($indDay6->isIncomplete, $batchDay6->isIncomplete);
    }
}
