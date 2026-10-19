<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\WorkSchedule\Services\ShiftMidnightService;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShiftAssociationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Establishment $establishment;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::firstOrCreate(
            ['cnpj' => '11780685000152'],
            [
                'legal_name' => 'Prefeitura Municipal Teste Compliance',
                'trade_name' => 'PontoFácil Plantões',
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
        LegalRegime $regime = LegalRegime::CLT
    ): array {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@test.gov.br',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'establishment_id' => $this->establishment->id,
            'registration_number' => $reg,
            'cpf' => sprintf('123456%05d', (int) $reg),
            'job_title' => 'Servidor de Plantão',
            'legal_regime' => $regime,
            'work_schedule_id' => $schedule?->id,
        ]);

        return [$user, $employee];
    }

    protected function createPunch(
        Employee $employee,
        string $localDateTimeString,
        string $direction
    ): PunchEvent {
        $tz = 'America/Maceio';
        $local = Carbon::parse($localDateTimeString, $tz);
        $utc = $local->copy()->utc();

        $nsr = $this->establishment->nsr_next++;
        $this->establishment->save();

        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $nsr,
            'occurred_at_utc' => $utc,
            'occurred_at_local' => $local,
            'direction' => $direction,
            'timezone' => $tz,
            'source' => 'web_pwa',
            'fiscal_hash' => hash('sha256', (string) $nsr),
            'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
            'payload_hash' => hash('sha256', 'payload'.$nsr),
            'created_at' => now(),
        ]);
    }

    /**
     * CENÁRIO A: Administrativo
     * Dia 1: 08:00 entrada, sem saída.
     * Dia 2: 08:00 entrada.
     * Resultado: Dia 1 incompleto; Dia 2 independente.
     */
    public function test_cenario_a_administrativo_dia1_incompleto_dia2_independente(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Servidor Admin A', '1001', $schedule);

        $this->createPunch($employee, '2026-10-19 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-20 08:00:00', 'in');

        $calc = app(CalculateDailyJourneyAction::class);

        // Apuração do Dia 1
        $journeyDay1 = $calc->execute($employee, Carbon::parse('2026-10-19', 'America/Maceio'));
        $this->assertTrue($journeyDay1->isIncomplete, 'Dia 1 deve ser incompleto');
        $this->assertEquals(0, $journeyDay1->workedMinutes);
        $this->assertCount(1, $journeyDay1->effectivePunches);

        // Apuração do Dia 2
        $journeyDay2 = $calc->execute($employee, Carbon::parse('2026-10-20', 'America/Maceio'));
        $this->assertTrue($journeyDay2->isIncomplete, 'Dia 2 deve ser independente e incompleto');
        $this->assertEquals(0, $journeyDay2->workedMinutes);
        $this->assertCount(1, $journeyDay2->effectivePunches);
        $this->assertEquals('08:00', $journeyDay2->effectivePunches[0]['time']);
    }

    /**
     * CENÁRIO B: Plantão normal
     * Dia 1: 19:00 entrada.
     * Dia 2: 07:00 saída.
     * Resultado: Mesma jornada do plantão autorizado (12h trabalhadas).
     */
    public function test_cenario_b_plantao_normal_atravessando_meia_noite(): void
    {
        $schedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala Plantão 12x36 Noturno',
            'code' => 'PL12X36N',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'expected_weekly_minutes' => 2520,
            'tolerance_minutes' => 10,
            'daily_tolerance_minutes' => 10,
            'requires_legal_authorization' => false,
            'allowed_legal_regimes' => [LegalRegime::CLT->value],
        ]);

        [$user, $employee] = $this->createEmployeeUser('Plantonista Normal B', '1002', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-B-1',
            'start_at_utc' => Carbon::parse('2026-10-19 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 07:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        $this->createPunch($employee, '2026-10-19 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-20 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);

        // Apuração do Dia 1: consome saída das 07:00 do dia 2
        $journeyDay1 = $calc->execute($employee, Carbon::parse('2026-10-19', 'America/Maceio'));
        $this->assertFalse($journeyDay1->isIncomplete);
        $this->assertEquals(720, $journeyDay1->workedMinutes, 'Plantão 19h às 07h deve computar exatamente 720 minutos (12h)');
        $this->assertCount(2, $journeyDay1->effectivePunches);

        // Apuração do Dia 2: saída consumida pelo dia 1 não deve duplicar no dia 2
        $journeyDay2 = $calc->execute($employee, Carbon::parse('2026-10-20', 'America/Maceio'));
        $this->assertEquals(0, $journeyDay2->workedMinutes);
        $this->assertFalse($journeyDay2->isIncomplete);
    }

    /**
     * CENÁRIO C: Plantão com saída esquecida
     * Dia 1: 19:00 entrada.
     * Dia 2: 07:00 saída esquecida.
     * Dia 2: 08:00 nova entrada.
     * Resultado: Não transformar a nova entrada automaticamente em saída do plantão anterior;
     * preservar a marcação e identificar a inconsistência.
     */
    public function test_cenario_c_plantao_com_saida_esquecida_e_nova_entrada(): void
    {
        $schedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala Plantão 12x36 Noturno C',
            'code' => 'PL12X36NC',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'expected_weekly_minutes' => 2520,
            'tolerance_minutes' => 10,
            'daily_tolerance_minutes' => 10,
            'requires_legal_authorization' => false,
            'allowed_legal_regimes' => [LegalRegime::CLT->value],
        ]);

        [$user, $employee] = $this->createEmployeeUser('Plantonista Saida Esquecida C', '1003', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-C-1',
            'start_at_utc' => Carbon::parse('2026-10-19 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 07:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        // Dia 1: 19:00 entrada
        $this->createPunch($employee, '2026-10-19 19:00:00', 'in');
        // Dia 2: 07:00 saída esquecida, bate nova entrada às 08:00
        $this->createPunch($employee, '2026-10-20 08:00:00', 'in');

        $calc = app(CalculateDailyJourneyAction::class);

        // Apuração do Dia 1: NÃO deve converter a entrada das 08h do dia 2 em saída!
        $journeyDay1 = $calc->execute($employee, Carbon::parse('2026-10-19', 'America/Maceio'));
        $this->assertTrue($journeyDay1->isIncomplete, 'Dia 1 deve ser incompleto porque a saída foi esquecida');
        $this->assertEquals(0, $journeyDay1->workedMinutes);

        // Apuração do Dia 2: marcação das 08:00 DEVE ser preservada como entrada no dia 2
        $journeyDay2 = $calc->execute($employee, Carbon::parse('2026-10-20', 'America/Maceio'));
        $this->assertCount(1, $journeyDay2->effectivePunches);
        $this->assertEquals('08:00', $journeyDay2->effectivePunches[0]['time']);
        $this->assertEquals('in', $journeyDay2->effectivePunches[0]['direction']);
        $this->assertTrue($journeyDay2->isIncomplete);

        // Verificar se os treatment notes identificam a inconsistência
        $notesText = implode(' ', $journeyDay2->treatmentNotes);
        $this->assertStringContainsString('Plantão anterior (iniciado em 2026-10-19) sem marcação de saída registrada', $notesText);
    }

    /**
     * CENÁRIO D: Plantões sobrepostos
     * Tratar sobreposição de plantões como situação ambígua, sem escolher silenciosamente a primeira.
     */
    public function test_cenario_d_plantoes_sobrepostos_sinaliza_ambiguidade(): void
    {
        $schedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala D',
            'code' => 'PL-D',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'expected_weekly_minutes' => 2520,
            'tolerance_minutes' => 10,
            'daily_tolerance_minutes' => 10,
            'requires_legal_authorization' => false,
            'allowed_legal_regimes' => [LegalRegime::CLT->value],
        ]);

        [$user, $employee] = $this->createEmployeeUser('Plantonista Sobreposto D', '1004', $schedule);

        // Cria dois plantões sobrepostos na mesma véspera
        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-D-1',
            'start_at_utc' => Carbon::parse('2026-10-19 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 07:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-D-2',
            'start_at_utc' => Carbon::parse('2026-10-19 23:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 11:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 20:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 08:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        $this->createPunch($employee, '2026-10-20 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journeyDay2 = $calc->execute($employee, Carbon::parse('2026-10-20', 'America/Maceio'));

        // Deve emitir alerta de múltiplos plantões detectados
        $notesText = implode(' ', $journeyDay2->treatmentNotes);
        $this->assertStringContainsString('múltiplos plantões autorizados com virada de meia-noite detectados', $notesText);
    }

    /**
     * CENÁRIO E: Plantão 24x72
     * Continuidade real por 24 horas, inclusive intervalos e tratamentos aprovados.
     */
    public function test_cenario_e_plantao_24x72_continuidade_com_intervalo(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();
        $config = $profile->configuration;
        $config['allowed_modalities'][] = '24x72';
        $profile->configuration = $config;
        $profile->save();

        $schedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala Plantão 24x72',
            'code' => 'PL24X72',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'expected_daily_minutes' => 1440,
            'expected_weekly_minutes' => 2880,
            'tolerance_minutes' => 15,
            'daily_tolerance_minutes' => 15,
            'requires_legal_authorization' => false,
            'allowed_legal_regimes' => [LegalRegime::CLT->value],
        ]);

        [$user, $employee] = $this->createEmployeeUser('Plantonista 24x72 E', '1005', $schedule);
        $employee->labor_rule_profile_id = $profile->id;
        $employee->save();

        // Plantão das 07:00 do dia 19 às 07:00 do dia 20 (24 horas)
        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-E-24',
            'start_at_utc' => Carbon::parse('2026-10-19 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 07:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 60,
            'expected_work_minutes' => 1380,
            'shift_type' => ShiftType::Shift24x72,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        // Batidas:
        // Dia 1: 07:00 in, 12:00 out (intervalo), 13:00 in
        $this->createPunch($employee, '2026-10-19 07:00:00', 'in');
        $this->createPunch($employee, '2026-10-19 12:00:00', 'out');
        $this->createPunch($employee, '2026-10-19 13:00:00', 'in');

        // Dia 2: 07:00 out (término do plantão)
        $this->createPunch($employee, '2026-10-20 07:00:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journeyDay1 = $calc->execute($employee, Carbon::parse('2026-10-19', 'America/Maceio'));

        $this->assertFalse($journeyDay1->isIncomplete);
        // Intervalo de 12:00 às 13:00 = 60 min. Total trabalhado: (5h antes almoço) + (18h após almoço até 07:00) = 23h = 1380 minutos
        $this->assertEquals(1380, $journeyDay1->workedMinutes);
        $this->assertEquals(60, $journeyDay1->breakMinutes);
        $this->assertCount(4, $journeyDay1->effectivePunches);
    }

    /**
     * CENÁRIO F: Marcação além do fim previsto
     * Não presumir associação com o plantão anterior apenas por estar dentro da tolerância.
     */
    public function test_cenario_f_marcacao_apos_fim_sem_sequencia_aberta_nao_associa(): void
    {
        $schedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala F',
            'code' => 'PL-F',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'expected_weekly_minutes' => 2520,
            'tolerance_minutes' => 10,
            'daily_tolerance_minutes' => 10,
            'requires_legal_authorization' => false,
            'allowed_legal_regimes' => [LegalRegime::CLT->value],
        ]);

        [$user, $employee] = $this->createEmployeeUser('Plantonista F', '1006', $schedule);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'shift_code' => 'SHIFT-F-1',
            'start_at_utc' => Carbon::parse('2026-10-19 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-19 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-20 07:00:00', 'America/Maceio'),
            'timezone' => 'America/Maceio',
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Confirmed,
            'is_day_off' => false,
            'crosses_midnight' => true,
        ]);

        // O colaborador já havia batido saída antes da meia-noite (abandonou plantão às 22:00)
        $this->createPunch($employee, '2026-10-19 19:00:00', 'in');
        $this->createPunch($employee, '2026-10-19 22:00:00', 'out');

        // No dia seguinte, dentro da janela de tolerância das 07:00 às 10:00 (às 08:30), bate saída isolada
        $this->createPunch($employee, '2026-10-20 08:30:00', 'out');

        $calc = app(CalculateDailyJourneyAction::class);
        $journeyDay1 = $calc->execute($employee, Carbon::parse('2026-10-19', 'America/Maceio'));

        // O dia 1 já estava fechado (19:00 - 22:00 = 180 min). NÃO deve puxar a batida das 08:30!
        $this->assertEquals(180, $journeyDay1->workedMinutes);
        $this->assertCount(2, $journeyDay1->effectivePunches);

        // O dia 2 deve registrar a saída das 08:30 como saída isolada do dia 2
        $journeyDay2 = $calc->execute($employee, Carbon::parse('2026-10-20', 'America/Maceio'));
        $this->assertCount(1, $journeyDay2->effectivePunches);
        $this->assertEquals('08:30', $journeyDay2->effectivePunches[0]['time']);
    }

    /**
     * TESTES DO ITEM 5: DIREÇÃO AUTOMÁTICA E RESILIÊNCIA
     */
    public function test_direction_action_first_punch_of_day_returns_in(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Direcao User 1', '2001');

        $action = app(DetermineNextPunchDirectionAction::class);
        $dir = $action->execute($user, Carbon::parse('2026-10-19 08:00:00', 'America/Maceio'));

        $this->assertEquals('in', $dir);
    }

    public function test_direction_action_recovers_gracefully_from_shift_lookup_failure(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Direcao User 2', '2002');

        // Bateu entrada legítima às 08:00 hoje
        $this->createPunch($employee, '2026-10-19 08:00:00', 'in');

        // Cria instância com serviço de plantão que lança exceção técnica
        $failingShiftService = new class extends ShiftMidnightService
        {
            public function findCoveringAuthorizedShift(Employee $employee, CarbonInterface $targetTime, ?Establishment $establishment = null): ?ShiftAssignment
            {
                throw new \RuntimeException('Falha temporária de conexão com o banco de escalas.');
            }
        };

        $action = new DetermineNextPunchDirectionAction($failingShiftService);

        // Mesmo com a falha na consulta de escala, a batida às 12:00 do mesmo dia deve retornar 'out'
        // e não ser silenciosamente corrompida para 'in'!
        $dir = $action->execute($user, Carbon::parse('2026-10-19 12:00:00', 'America/Maceio'));

        $this->assertEquals('out', $dir);
    }
}
