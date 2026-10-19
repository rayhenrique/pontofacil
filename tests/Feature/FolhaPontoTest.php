<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Enums\WorkScheduleModality;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ReviewForClose;
use Tests\TestCase;

class FolhaPontoTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'ORGANIZACAO PUBLICA TESTE LTDA',
            'trade_name' => 'Org Teste',
            'cnpj' => '11222333000199',
            'address' => 'Rua Principal, 100',
            'city' => 'Maceio',
            'state' => 'AL',
            'phone' => '8233334444',
            'email' => 'contato@org.gov.br',
            'header_entity' => 'SECRETARIA MUNICIPAL DE SAÚDE',
            'header_state' => 'ESTADO DE ALAGOAS',
            'header_sub_entity' => 'COORDENADORIA DE ATENÇÃO BÁSICA',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Central',
            'identifier_type' => 'cnpj',
            'identifier_number' => '11222333000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sector = Sector::create([
            'name' => 'Unidade de Pronto Atendimento',
            'establishment_id' => $this->establishment->id,
        ]);
    }

    protected function createEmployee(string $name, UserRole $role = UserRole::Employee, LegalRegime $regime = LegalRegime::CLT): array
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@teste.com',
            'password' => 'secret123',
            'role' => $role,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '123.456.789-00',
            'registration_number' => 'MAT-'.$user->id,
            'job_title' => 'Enfermeiro Plantonista',
            'contract_type' => $regime->label(),
            'legal_regime' => $regime,
            'workload' => '40h',
            'zone' => 'Urbana',
        ]);

        return [$user, $employee];
    }

    protected function createPunch(User $user, string $direction, string $localTime): PunchEvent
    {
        Carbon::setTestNow(Carbon::parse($localTime, 'America/Maceio'));

        $punch = app(RecordPunchEventAction::class)->execute(
            user: $user,
            direction: $direction,
            establishment: $this->establishment,
        );

        Carbon::setTestNow();

        return $punch;
    }

    /**
     * 1. Preservar o modelo administrativo tradicional
     */
    public function test_01_folha_ponto_administrative_model(): void
    {
        [$user, $employee] = $this->createEmployee('Maria Administrativa');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Batidas normais em uma segunda-feira (05/10/2026)
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');
        $this->createPunch($user, 'in', '2026-10-05 13:00:00');
        $this->createPunch($user, 'out', '2026-10-05 17:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('FOLHA DE PONTO DE FUNCIONÁRIO')
            ->assertSee('MARIA ADMINISTRATIVA')
            ->assertSee('SECRETARIA MUNICIPAL DE SAÚDE')
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('17:00')
            ->assertSee('Horário Matutino')
            ->assertSee('Horário Vespertino')
            ->assertSee('SERVIDOR (a)')
            ->assertSee('Setor de Recursos Humanos');
    }

    /**
     * 2. Jornada noturna adequada a horários que atravessam meia-noite
     */
    public function test_02_folha_ponto_nocturnal_model(): void
    {
        [$user, $employee] = $this->createEmployee('Paula Vigilante Noturna');

        // Turno noturno: 22h às 06h do dia seguinte (atravessa meia-noite)
        $schedule = WorkSchedule::create([
            'code' => 'PL-NOTURNO',
            'name' => 'Escala Plantão Noturno',
            'modality' => WorkScheduleModality::VariableShifts,
            'expected_daily_minutes' => 480,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 22:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 06:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 01:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 09:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Extraordinary,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 420,
        ]);

        $this->createPunch($user, 'in', '2026-10-05 22:00:00');
        $this->createPunch($user, 'out', '2026-10-06 06:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('FOLHA DE PONTO — JORNADA NOTURNA')
            ->assertSee('22:00')
            ->assertSee('06:00 (+1d)')
            ->assertSee('H. NOTURNA')
            ->assertSee('EQ. LEGAL');
    }

    /**
     * 3. Escala 12x36
     */
    public function test_03_folha_ponto_12x36_shift_model(): void
    {
        [$user, $employee] = $this->createEmployee('Marcos Plantonista 12x36');

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36',
            'name' => 'Escala 12x36 Hospitalar',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($user, 'in', '2026-10-06 07:00:00');
        $this->createPunch($user, 'out', '2026-10-06 19:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('FOLHA DE FREQUÊNCIA — ESCALA DE PLANTÕES')
            ->assertSee('06/10/2026')
            ->assertSee('07:00')
            ->assertSee('19:00');
    }

    /**
     * 4. Escala 24x72 configurada
     */
    public function test_04_folha_ponto_24x72_shift_model(): void
    {
        [$user, $employee] = $this->createEmployee('Socorrista SAMU 24x72');

        $schedule = WorkSchedule::create([
            'code' => 'PL-24X72',
            'name' => 'Escala 24x72 SAMU',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'expected_daily_minutes' => 1440,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-08 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-09 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-08 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-09 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift24x72,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'break_minutes' => 120,
            'expected_work_minutes' => 1320,
        ]);

        $this->createPunch($user, 'in', '2026-10-08 07:00:00');
        $this->createPunch($user, 'out', '2026-10-09 07:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('FOLHA DE FREQUÊNCIA — ESCALA DE PLANTÕES')
            ->assertSee('08/10/2026')
            ->assertSee('09/10/2026')
            ->assertSee('Plantão 24×72');
    }

    /**
     * 5. Plantões atravessando datas (10/10/2026 19:00 -> 11/10/2026 07:00)
     * Nunca apresentar como duas jornadas independentes.
     */
    public function test_05_plantao_atravessando_datas_como_jornada_integra(): void
    {
        [$user, $employee] = $this->createEmployee('Enfermeiro UTI');

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'start_at_local' => Carbon::parse('2026-10-10 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-11 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-10 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-11 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'is_night_shift' => true,
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
        ]);

        $this->createPunch($user, 'in', '2026-10-10 19:00:00');
        $this->createPunch($user, 'out', '2026-10-11 07:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('10/10/2026')
            ->assertSee('19:00')
            ->assertSee('11/10/2026')
            ->assertSee('07:00');
    }

    /**
     * 6. Mais de quatro marcações no dia
     */
    public function test_06_mais_de_quatro_marcacoes_no_dia(): void
    {
        [$user, $employee] = $this->createEmployee('Tecnico Flexivel');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // 6 batidas no mesmo dia (05/10/2026)
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');
        $this->createPunch($user, 'in', '2026-10-05 13:00:00');
        $this->createPunch($user, 'out', '2026-10-05 15:00:00');
        $this->createPunch($user, 'in', '2026-10-05 15:30:00');
        $this->createPunch($user, 'out', '2026-10-05 18:30:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('15:00')
            ->assertSee('Batidas extras: 15:30 (E), 18:30 (S)');
    }

    /**
     * 7. Marcação incompleta (não inventar saída)
     */
    public function test_07_marcacao_incompleta_sem_inventar_saida(): void
    {
        [$user, $employee] = $this->createEmployee('Colaborador Esquecido');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Apenas uma entrada no dia
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('Incompleta: Saída pendente');
    }

    /**
     * 8. Sábado trabalhado: não carimbar automaticamente como descanso
     */
    public function test_08_sabado_trabalhado_nao_carimba_como_descanso(): void
    {
        [$user, $employee] = $this->createEmployee('Atendente Sabado');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Sábado 10/10/2026 trabalhado
        $this->createPunch($user, 'in', '2026-10-10 08:00:00');
        $this->createPunch($user, 'out', '2026-10-10 12:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Registro eletrônico');
    }

    /**
     * 9. Domingo de plantão: dia normal de trabalho
     */
    public function test_09_domingo_de_plantao_e_dia_normal(): void
    {
        [$user, $employee] = $this->createEmployee('Medico Domingo');

        // Domingo 11/10/2026
        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'start_at_local' => Carbon::parse('2026-10-11 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-11 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-11 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-11 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $this->createPunch($user, 'in', '2026-10-11 07:00:00');
        $this->createPunch($user, 'out', '2026-10-11 19:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('11/10/2026')
            ->assertSee('07:00')
            ->assertSee('19:00');
    }

    /**
     * 10. Feriado no calendário laboral
     */
    public function test_10_feriado_no_calendario_laboral(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Com Feriado');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Feriado Nacional: 12 de Outubro
        CalendarEvent::create([
            'establishment_id' => $this->establishment->id,
            'event_date' => '2026-10-12',
            'name' => 'Nossa Senhora Aparecida',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('FERIADO');
    }

    /**
     * 11. Folha em branco para preenchimento manual
     */
    public function test_11_folha_em_branco_preenchimento_manual(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Form Manual');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'blank'])
            ->assertOk()
            ->assertSee('DOCUMENTO PARA PREENCHIMENTO MANUAL')
            ->assertSee(': ');
    }

    /**
     * 12. Usuário sem autorização é bloqueado (403)
     */
    public function test_12_usuario_sem_autorizacao_recebe_403(): void
    {
        [$userA, $employeeA] = $this->createEmployee('Colaborador A');
        [$userB, $employeeB] = $this->createEmployee('Colaborador B');

        $response = $this->actingAs($userA)->get(route('folha-ponto', ['userId' => $userB->id, 'month' => 10, 'year' => 2026]));
        $response->assertStatus(403);
    }

    /**
     * 13. Dados institucionais ausentes tratados com graciosidade
     */
    public function test_13_dados_institucionais_ausentes_tratados_graciosamente(): void
    {
        $this->company->update([
            'address' => '',
            'header_entity' => '',
        ]);

        [$user, $employee] = $this->createEmployee('Colaborador Sem Empresa');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('Atenção:');
    }

    /**
     * 14. Competência fechada utiliza snapshot imutável e indica status
     */
    public function test_14_competencia_fechada_respeita_snapshot_imutavel(): void
    {
        [$admin, $adminEmp] = $this->createEmployee('Admin RH Fechamento', UserRole::Admin);
        [$user, $employee] = $this->createEmployee('Colaborador Fechado');

        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id, 'job_title' => 'Cargo Antigo de 2026']);

        // Registrar batidas em 05/10/2026
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');

        // Fechar a competência 10/2026
        ReviewForClose::reviewAll(Employee::all(), 2026, 10, $admin);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $admin);

        // Alterar o cargo atual do colaborador no banco (depois do fechamento)
        $employee->update(['job_title' => 'Cargo Novo Promovido em 2027']);

        Livewire::actingAs($admin)
            ->test('folha-ponto', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('COMPETÊNCIA FECHADA')
            ->assertSee('Cargo Antigo de 2026')
            ->assertDontSee('Cargo Novo Promovido em 2027');
    }

    /**
     * 15. Rubricas exibem "Registro eletrônico" e nunca o caractere "✓" falso
     */
    public function test_15_rubricas_exibem_registro_eletronico_sem_checkmark_ficticio(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Conferencia');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('Registro eletrônico')
            ->assertDontSee('✓');
    }

    /**
     * 16. Separação explícita entre horas físicas noturnas e equivalência legal
     */
    public function test_16_horarios_noturnos_fisicos_e_apurados_separados(): void
    {
        [$user, $employee] = $this->createEmployee('Vigilante Noturno Misto');
        $schedule = WorkSchedule::create([
            'code' => 'PL-NOT-7H',
            'name' => 'Escala Plantão Noturno 7h',
            'modality' => WorkScheduleModality::VariableShifts,
            'expected_daily_minutes' => 420,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 22:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 05:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 01:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 08:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Extraordinary,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 0,
            'expected_work_minutes' => 420,
        ]);

        // Trabalho das 22h às 05h = 7 horas físicas noturnas
        // Pela CLT (52m30s), 7h físicas equivalem a 8h de apuração legal
        $this->createPunch($user, 'in', '2026-10-05 22:00:00');
        $this->createPunch($user, 'out', '2026-10-06 05:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('Horas Noturnas (Físicas)')
            ->assertSee('07h 00m')
            ->assertSee('08h 00m');
    }

    /**
     * 17. Batida manual aprovada no mesmo dia participa da apuração e é identificada como "Ajuste aprovado"
     */
    public function test_17_batida_manual_aprovada_no_mesmo_dia(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Com Ajuste Manual');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');
        $this->createPunch($user, 'in', '2026-10-05 13:00:00');

        // Saída às 17:00 inserida manualmente e aprovada
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            'reason_text' => 'Falha de leitura biometrica na saida',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 17:30:00'),
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('17:00')
            ->assertSee('Ajuste aprovado')
            ->assertSee('Falha de leitura biometrica na saida')
            ->assertDontSee('✓');
    }

    /**
     * 18. Dia contendo exclusivamente batidas manuais aprovadas: não rotula como "Sem registro / Ausência"
     */
    public function test_18_dia_contendo_exclusivamente_batida_manual_aprovada(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor So Batida Manual');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Duas batidas manuais aprovadas no dia 05/10/2026 (segunda-feira)
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 08:00:00', 'America/Maceio'),
            'reason_text' => 'Esquecimento de cracha pela manha',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 09:00:00'),
        ]);

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 12:00:00', 'America/Maceio'),
            'reason_text' => 'Esquecimento de cracha ao meio-dia',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 12:30:00'),
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Ajuste aprovado')
            ->assertSee('Esquecimento de cracha pela manha')
            ->assertSee('4h 00m');
    }

    /**
     * 19. Entrada original + saída manual aprovada formam par regular
     */
    public function test_19_entrada_original_e_saida_manual_aprovada(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Par Misto');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 12:00:00', 'America/Maceio'),
            'reason_text' => 'Terminal travou no fechamento do turno',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 13:00:00'),
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Ajuste aprovado')
            ->assertDontSee('Incompleta: Saída pendente');
    }

    /**
     * 20. Marcação desconsiderada pelo RH: excluída da apuração efetiva e exibe observação
     */
    public function test_20_marcacao_desconsiderada_excluida_da_frequencia_efetiva(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Batida Desconsiderada');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $punchIn = $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $punchDup = $this->createPunch($user, 'in', '2026-10-05 08:02:00');
        $punchOut = $this->createPunch($user, 'out', '2026-10-05 12:00:00');

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'reference_punch_id' => $punchDup->id,
            'type' => TreatmentEventType::PunchDisregarded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 08:02:00', 'America/Maceio'),
            'reason_text' => 'Duplicidade involuntaria por biometria rapida',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 09:00:00'),
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Duplicidade involuntaria por biometria rapida');

        // PunchDup continua existindo no banco de dados (REP íntegro)
        $this->assertDatabaseHas('punch_events', ['id' => $punchDup->id]);
    }

    /**
     * 21. Ausência justificada sem batidas: exibe status aprovado e não 'Sem registro / Ausência'
     */
    public function test_21_ausencia_justificada_sem_batidas(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Com Atestado');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Ausência justificada na segunda-feira 05/10/2026
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::AbsenceJustified,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 08:00:00', 'America/Maceio'),
            'reason_text' => 'Atestado Medico Odontologico',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 10:00:00'),
        ]);

        $test = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('Ausência justificada: Atestado Medico Odontologico')
            ->assertSee('JUSTIFICADA');

        $rows = $test->viewData('administrativeRows') ?? [];
        $day05 = collect($rows)->firstWhere('day', 5);
        $this->assertNotNull($day05);
        $this->assertSame('JUSTIFICADA', $day05['rubrica1']);
        $this->assertSame('JUSTIFICADA', $day05['rubrica2']);
        $this->assertStringContainsString('Ausência justificada', $day05['observation']);
        $this->assertStringNotContainsString('Sem registro / Ausência', $day05['observation']);
    }

    /**
     * 22. Ajuste pendente não altera totais definitivos e exibe indicação de aguardando análise
     */
    public function test_22_ajuste_pendente_nao_altera_totais_e_exibe_aviso(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Com Solicitacao Pendente');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');

        // Solicitação manual pendente de análise
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Pending,
            'effective_at' => Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            'reason_text' => 'Esquecimento ao sair',
            'requested_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('Ajuste aguardando análise')
            ->assertSee('Pendente de Análise')
            ->assertSee('Incompleta: Saída pendente');
    }

    /**
     * 23. Ajuste rejeitado não altera apuração e exibe status correspondente
     */
    public function test_23_ajuste_rejeitado(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Ajuste Indeferido');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Rejected,
            'effective_at' => Carbon::parse('2026-10-05 08:00:00', 'America/Maceio'),
            'reason_text' => 'Solicito inclusao sem comprovante',
            'requested_by' => $user->id,
            'rejected_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 15:00:00'),
            'rejection_reason' => 'Sem comprovacao idonea',
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('Rejeitado')
            ->assertSee('Sem registro / Ajuste rejeitado')
            ->assertSee('Sem comprovacao idonea')
            ->assertSee('--:--');
    }

    /**
     * 24. Competência aberta atualizada imediatamente após nova aprovação pelo RH
     */
    public function test_24_competencia_aberta_atualizada_imediatamente_apos_aprovacao(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Tempo Real');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Inicialmente dia 05/10/2026 sem batidas
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('DOCUMENTO PROVISÓRIO')
            ->assertSee('Sem registro / Ausência');

        // RH aprova batidas manuais
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 08:00:00', 'America/Maceio'),
            'reason_text' => 'Declaracao de servico externo matutino',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::now(),
        ]);
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 12:00:00', 'America/Maceio'),
            'reason_text' => 'Declaracao de servico externo vespertino',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::now(),
        ]);

        // Ao recarregar a Folha de Ponto na competência aberta, já reflete imediatamente
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Ajuste aprovado')
            ->assertSee('4h 00m');
    }

    /**
     * 25. Servidor sem escala configurada exibe explicitamente "Escala não configurada"
     */
    public function test_25_escala_ausente_exibe_escala_nao_configurada(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Sem Escala');
        // Garantir que não há escala nem plantão
        $employee->update(['work_schedule_id' => null]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('Escala não configurada')
            ->assertDontSee('Padrão Administrativo');
    }

    /**
     * 26. Horários e totais perfeitamente coerentes entre Espelho, Folha e apuração PTRP
     */
    public function test_26_coerencia_totais_entre_espelho_folha_e_ptrp(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Coerencia Total');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Batidas eletrônicas
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');
        $this->createPunch($user, 'in', '2026-10-05 13:00:00');

        // Saída manual aprovada
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            'reason_text' => 'Liberacao de expediente em servico externo',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'decided_at' => Carbon::parse('2026-10-05 17:30:00'),
        ]);

        // Cálculo PTRP direto
        $service = app(TimesheetJourneyService::class);
        $calculatedData = $service->resolveMonthData($user, 2026, 10);
        $ptrpWorkedMinutes = $calculatedData['totalWorkedMinutes'];
        $this->assertSame(480, $ptrpWorkedMinutes); // 8 horas = 480 minutos

        // Espelho de Ponto
        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('17:00')
            ->assertSee('8h 00m');

        // Folha de Ponto
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('17:00')
            ->assertSee('8h 00m');
    }

    /**
     * 27. Competência fechada preserva dados e tratamentos congelados do snapshot
     */
    public function test_27_competencia_fechada_utiliza_snapshot_preservando_tratamentos_e_totais(): void
    {
        [$admin, $adminEmp] = $this->createEmployee('Admin Snapshot RH', UserRole::Admin);
        [$user, $employee] = $this->createEmployee('Servidor Snapshot');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 13:00:00', 'America/Maceio'),
            'reason_text' => 'Turno extra da tarde aprovado',
            'requested_by' => $user->id,
            'approved_by' => $admin->id,
            'decided_at' => Carbon::parse('2026-10-05 14:00:00'),
        ]);

        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            'reason_text' => 'Saida do turno extra aprovada',
            'requested_by' => $user->id,
            'approved_by' => $admin->id,
            'decided_at' => Carbon::parse('2026-10-05 17:30:00'),
        ]);

        // Fechar competência 10/2026
        ReviewForClose::reviewAll(Employee::all(), 2026, 10, $admin);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $admin);

        // Inserir novo tratamento POSTERIOR ao fechamento (tentativa de alteração pós-fechamento)
        TreatmentEvent::create([
            'employee_id' => $employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-10-06 08:00:00', 'America/Maceio'),
            'reason_text' => 'Tentativa de ajuste apos fechamento',
            'requested_by' => $user->id,
            'approved_by' => $admin->id,
            'decided_at' => Carbon::parse('2026-11-01 10:00:00'),
        ]);

        // A Folha da competência fechada deve usar EXCLUSIVAMENTE os dados congelados do snapshot
        Livewire::actingAs($admin)
            ->test('folha-ponto', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('COMPETÊNCIA FECHADA')
            ->assertSee('Turno extra da tarde aprovado')
            ->assertDontSee('Tentativa de ajuste apos fechamento');
    }

    /**
     * 28. Modelo Horário Corrido com duas marcações e orientação A4 retrato
     */
    public function test_28_modelo_corrido_com_duas_marcacoes_e_orientacao_retrato(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Horario Corrido');
        $schedule = WorkSchedule::create([
            'code' => 'CORR-6H',
            'name' => 'Jornada Corrida 6h Diárias',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 360,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 14:00:00');

        $component = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'continuous'])
            ->assertOk()
            ->assertSee('FOLHA DE PONTO — HORÁRIO CORRIDO')
            ->assertSee('08:00')
            ->assertSee('14:00')
            ->assertSee('06h 00m')
            ->assertSee('Sem intervalo')
            ->assertSee('size: A4 portrait');

        $rows = $component->viewData('continuousRows') ?? [];
        $day05 = collect($rows)->firstWhere('day', '05');
        $this->assertNotNull($day05);
        $this->assertSame('08:00', $day05['entryReal']);
        $this->assertSame('14:00', $day05['exitReal']);
        $this->assertSame('06h 00m', $day05['physicalWorkedFormatted']);
    }

    /**
     * 29. Jornada noturna iniciando à meia-noite (00h às 06h)
     */
    public function test_29_noturno_00h_06h(): void
    {
        [$user, $employee] = $this->createEmployee('Vigilante Madrugada');

        $this->createPunch($user, 'in', '2026-10-05 00:00:00');
        $this->createPunch($user, 'out', '2026-10-05 06:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('FOLHA DE PONTO — JORNADA NOTURNA')
            ->assertSee('00:00')
            ->assertSee('06:00')
            ->assertSee('06h 00m')
            ->assertSee('H. NOTURNA')
            ->assertSee('EQ. LEGAL');
    }

    /**
     * 30. Plantão com entrada real diferente da prevista
     */
    public function test_30_plantao_entrada_real_diferente_da_prevista(): void
    {
        [$user, $employee] = $this->createEmployee('Plantonista Atraso');

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-ATR',
            'name' => 'Escala 12x36',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        // Entrada real 18 minutos após o previsto: 07:18
        $this->createPunch($user, 'in', '2026-10-06 07:18:00');
        $this->createPunch($user, 'out', '2026-10-06 19:00:00');

        $component = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('07:00')
            ->assertSee('07:18');

        $rows = $component->viewData('shiftsRows') ?? [];
        $row = collect($rows)->first();
        $this->assertNotNull($row);
        $this->assertSame('07:00', $row['scheduledStartTime']);
        $this->assertSame('07:18', $row['realEntryTime']);
        $this->assertNotSame($row['scheduledStartTime'], $row['realEntryTime']);
    }

    /**
     * 31. Plantão com saída real diferente da prevista
     */
    public function test_31_plantao_saida_real_diferente_da_prevista(): void
    {
        [$user, $employee] = $this->createEmployee('Plantonista Hora Extra');

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-EXT',
            'name' => 'Escala 12x36 Extra',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-06 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-06 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        // Entrada normal às 07:00, saída estendida às 19:25
        $this->createPunch($user, 'in', '2026-10-06 07:00:00');
        $this->createPunch($user, 'out', '2026-10-06 19:25:00');

        $component = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('19:00')
            ->assertSee('19:25');

        $rows = $component->viewData('shiftsRows') ?? [];
        $row = collect($rows)->first();
        $this->assertNotNull($row);
        $this->assertSame('19:00', $row['scheduledEndTime']);
        $this->assertSame('19:25', $row['realExitTime']);
        $this->assertNotSame($row['scheduledEndTime'], $row['realExitTime']);
    }

    /**
     * 32. Plantão iniciado no mês anterior e virada de mês sem duplicação de horas
     */
    public function test_32_plantao_iniciado_mes_anterior_e_virada_mes_sem_duplicacao_horas(): void
    {
        [$user, $employee] = $this->createEmployee('Enfermeiro Transicao Mes');

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-MES',
            'name' => 'Escala 12x36 Transicao',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Plantão iniciado em 31/10/2026 às 19:00 e terminando em 01/11/2026 às 07:00
        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => Carbon::parse('2026-10-31 19:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-11-01 07:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-31 22:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-11-01 10:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'crosses_midnight' => true,
            'is_night_shift' => true,
            'break_minutes' => 0,
            'expected_work_minutes' => 720,
        ]);

        $this->createPunch($user, 'in', '2026-10-31 19:00:00');
        $this->createPunch($user, 'out', '2026-11-01 07:00:00');

        // 1. Em Outubro (competência de início): a jornada deve conter as 12 horas
        $octData = app(TimesheetJourneyService::class)->resolveMonthData($user, 2026, 10);
        $this->assertSame(720, $octData['totalWorkedMinutes']);

        // 2. Em Novembro (competência de término): as horas NÃO podem ser duplicadas
        $novData = app(TimesheetJourneyService::class)->resolveMonthData($user, 2026, 11);
        $this->assertSame(0, $novData['totalWorkedMinutes']);

        // 3. Na Folha de Ponto de Novembro: o plantão deve constar na escala com identificação clara
        $component = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 11, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('31/10/2026')
            ->assertSee('01/11/2026')
            ->assertSee('Mês anterior')
            ->assertSee('apurado na competência anterior sem duplicação');

        $rows = $component->viewData('shiftsRows') ?? [];
        $row = collect($rows)->first();
        $this->assertNotNull($row);
        $this->assertSame('Mês anterior', $row['statusLabel']);
        $this->assertSame('00h 00m', $row['physicalWorkedFormatted']);
    }

    /**
     * 33. Múltiplos intervalos no dia sem fallback fictício de 60 minutos
     */
    public function test_33_multiplos_intervalos_sem_fallback_ficticio(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Multiplos Intervalos');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // 6 marcações no dia 05/10/2026:
        // Turno 1: 08:00 - 10:00
        // Intervalo 1: 10:00 - 10:15 (15 min)
        // Turno 2: 10:15 - 12:00
        // Intervalo 2: 12:00 - 13:00 (60 min)
        // Turno 3: 13:00 - 17:00
        $this->createPunch($user, 'in', '2026-10-05 08:00:00');
        $this->createPunch($user, 'out', '2026-10-05 10:00:00');
        $this->createPunch($user, 'in', '2026-10-05 10:15:00');
        $this->createPunch($user, 'out', '2026-10-05 12:00:00');
        $this->createPunch($user, 'in', '2026-10-05 13:00:00');
        $this->createPunch($user, 'out', '2026-10-05 17:00:00');

        $component = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('10:00')
            ->assertSee('10:15')
            ->assertSee('12:00')
            ->assertSee('Batidas extras: 13:00 (E), 17:00 (S)');

        $rows = $component->viewData('administrativeRows') ?? [];
        $day05 = collect($rows)->firstWhere('day', 5);
        $this->assertNotNull($day05);
        $this->assertSame('15 min', $day05['intervalFormatted']);
    }

    /**
     * 34. Feriado ou folga trabalhada: exibe horas trabalhadas e observação correspondente
     */
    public function test_34_feriado_folga_trabalhada(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Feriado Trabalhado');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Feriado em 12/10/2026
        CalendarEvent::create([
            'establishment_id' => $this->establishment->id,
            'event_date' => '2026-10-12',
            'name' => 'Nossa Senhora Aparecida',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'active' => true,
        ]);

        // Trabalho realizado no feriado: 08:00 às 12:00
        $this->createPunch($user, 'in', '2026-10-12 08:00:00');
        $this->createPunch($user, 'out', '2026-10-12 12:00:00');

        $test = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('4h 00m')
            ->assertSee('Feriado trabalhado: Nossa Senhora Aparecida');

        $rows = $test->viewData('administrativeRows') ?? [];
        $day12 = collect($rows)->firstWhere('day', 12);
        $this->assertNotNull($day12);
        $this->assertSame('04h 00m', $day12['physicalWorkedFormatted']);
        $this->assertSame('Feriado trabalhado: Nossa Senhora Aparecida', $day12['observation']);
        $this->assertStringNotContainsString('Sem registro / Ausência', $day12['observation']);
    }

    /**
     * 35. Regras CSS de paginação e impressão A4
     */
    public function test_35_regras_css_paginacao_e_impressao_a4(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Paginacao A4');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('@media print')
            ->assertSee('display: table-header-group !important;')
            ->assertSee('break-inside: avoid !important;')
            ->assertSee('border-collapse: collapse !important;');
    }

    /**
     * 36. Orientação A4 adaptativa por modelo (retrato no corrido, paisagem nos demais)
     */
    public function test_36_orientacao_a4_adaptativa_por_modelo(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Orientacao A4');

        // Corrido -> portrait
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'continuous'])
            ->assertOk()
            ->assertSee('size: A4 portrait;');

        // Administrativo -> landscape
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative'])
            ->assertOk()
            ->assertSee('size: A4 landscape;');

        // Noturno -> landscape
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('size: A4 landscape;');

        // Plantões -> landscape
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk()
            ->assertSee('size: A4 landscape;');
    }

    /**
     * 37. Feriado isolado em 12/10 não contamina os dias 01-11 nem 13-31
     */
    public function test_37_feriado_isolado_nao_contamina_outros_dias(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Teste Calendario');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Criar exclusivamente o feriado de 12/10/2026
        CalendarEvent::create([
            'establishment_id' => $this->establishment->id,
            'event_date' => '2026-10-12',
            'name' => 'Nossa Senhora Aparecida',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'active' => true,
        ]);

        $models = ['administrative', 'continuous', 'nocturnal'];

        foreach ($models as $model) {
            $test = Livewire::actingAs($user)
                ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => $model])
                ->assertOk();

            $rowsProp = match ($model) {
                'administrative' => 'administrativeRows',
                'continuous' => 'continuousRows',
                'nocturnal' => 'nocturnalRows',
            };

            $rows = $test->viewData($rowsProp) ?? [];
            $this->assertCount(31, $rows, "O modelo {$model} deve conter exatamente 31 dias para outubro.");

            foreach ($rows as $row) {
                $day = (int) $row['day'];
                $isHoliday = (bool) ($row['isHoliday'] ?? false);
                $obs = $row['observation'] ?? '';

                if ($day === 12) {
                    $this->assertTrue($isHoliday, "O dia 12 deve identificar o feriado no modelo {$model}");
                    $this->assertStringContainsString('Nossa Senhora Aparecida', $obs);
                } else {
                    $this->assertFalse($isHoliday, "O dia {$day} NÃO deve ser feriado no modelo {$model}");
                    $this->assertStringNotContainsString('Nossa Senhora Aparecida', $obs, "O dia {$day} não pode conter o nome do feriado no modelo {$model}");
                    $this->assertStringNotContainsString('Feriado trabalhado', $obs, "O dia {$day} não pode conter 'Feriado trabalhado' no modelo {$model}");
                }
            }
        }
    }

    /**
     * 38. Fidelidade histórica: dados congelados no snapshot em competência fechada e fallback gracioso
     */
    public function test_38_fidelidade_historica_snapshot_fechado_e_fallback(): void
    {
        [$admin, $adminEmp] = $this->createEmployee('Admin Fechamento', UserRole::Admin);
        [$user, $employee] = $this->createEmployee('Servidor Historico Original');
        $schedule = WorkSchedule::createDefault40h();
        $employee->update([
            'work_schedule_id' => $schedule->id,
            'job_title' => 'Enfermeiro Historico',
        ]);

        // Registrar batidas e fechar a competência 10/2026
        $this->createPunch($user, 'in', '2026-10-01 08:00:00');
        $this->createPunch($user, 'out', '2026-10-01 12:00:00');

        ReviewForClose::reviewAll([$employee, $adminEmp], 2026, 10, $admin);
        $closeAction = app(CloseMonthlyPeriodAction::class);
        $closeAction->execute(2026, 10, $admin);

        // Após fechamento, alterar os dados atuais do usuário e funcionário
        $user->update(['name' => 'Nome Alterado Posteriormente']);
        $employee->update(['job_title' => 'Cargo Alterado Posteriormente']);

        // Acessar a Folha da competência fechada: deve exibir os dados históricos do snapshot
        $testClosed = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'userId' => $user->id])
            ->assertOk()
            ->assertSee('SERVIDOR HISTORICO ORIGINAL')
            ->assertDontSee('NOME ALTERADO POSTERIORMENTE')
            ->assertSee('Enfermeiro Historico')
            ->assertDontSee('Cargo Alterado Posteriormente');

        $this->assertTrue((bool) $testClosed->viewData('isClosedPeriod'));

        // Testar fallback quando snapshot não contém campos específicos (snapshot legado)
        $closedPeriod = ClosedPeriod::findForPeriod(2026, 10);
        $this->assertNotNull($closedPeriod);

        $snapshot = $closedPeriod->currentSnapshots()
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($snapshot);

        // Simular snapshot legado com dados parciais
        $snapEmp = $snapshot->employee_snapshot;
        unset($snapEmp['establishment_name'], $snapEmp['job_title']);
        $snapshot->update(['employee_snapshot' => $snapEmp]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'userId' => $user->id])
            ->assertOk()
            ->assertSee('Informação histórica não disponível');
    }

    /**
     * 39. Sincronização entre modelo de relatório e modo de preenchimento (Branco <-> Automático)
     */
    public function test_39_sincronizacao_modelo_em_branco_e_fill_mode(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Sincronizacao');

        // Selecionar modelo 'blank' sincroniza fillMode para 'blank'
        $test = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'administrative', 'fillMode' => 'punches'])
            ->set('reportModel', 'blank')
            ->assertSet('fillMode', 'blank');

        // Alternar fillMode de volta para 'punches' restaura reportModel para o padrão auto
        $test->set('fillMode', 'punches')
            ->assertSet('reportModel', 'auto');
    }

    /**
     * 40. Plantões sem batidas reais exibem data "—", hora "Não registrada" e intervalo previsto
     */
    public function test_40_plantoes_sem_batida_real_exibe_nao_registrada_e_intervalo_previsto(): void
    {
        [$user, $employee] = $this->createEmployee('Servidor Plantao Sem Batida');

        $shiftSchedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala 12x36 Diurno',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'active' => true,
        ]);

        $employee->update(['work_schedule_id' => $shiftSchedule->id]);

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $shiftSchedule->id,
            'start_at_local' => Carbon::parse('2026-10-05 07:00:00', 'America/Maceio'),
            'end_at_local' => Carbon::parse('2026-10-05 19:00:00', 'America/Maceio'),
            'start_at_utc' => Carbon::parse('2026-10-05 10:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-05 22:00:00', 'UTC'),
            'timezone' => 'America/Maceio',
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
            'break_minutes' => 60,
            'expected_work_minutes' => 660,
        ]);

        $test = Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'shifts'])
            ->assertOk();

        $rows = $test->viewData('shiftsRows') ?? [];
        $shiftRow = collect($rows)->firstWhere('scheduledStartDate', '05/10/2026');

        $this->assertNotNull($shiftRow);
        $this->assertSame('—', $shiftRow['realEntryDate']);
        $this->assertSame('Não registrada', $shiftRow['realEntryTime']);
        $this->assertSame('—', $shiftRow['realExitDate']);
        $this->assertSame('Não registrada', $shiftRow['realExitTime']);
        $this->assertStringContainsString('(Previsto)', $shiftRow['intervalFormatted']);
    }
}
