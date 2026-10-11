<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
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
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $schedule = WorkSchedule::createDefault40h();
        $employee->update(['work_schedule_id' => $schedule->id]);

        // Trabalho das 22h às 05h = 7 horas físicas noturnas
        // Pela CLT (52m30s), 7h físicas equivalem a 8h de apuração legal
        $this->createPunch($user, 'in', '2026-10-05 22:00:00');
        $this->createPunch($user, 'out', '2026-10-06 05:00:00');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('Horas Noturnas (Físicas)')
            ->assertSee('Equiv. Legal Noturna')
            ->assertSee('07h 00m')
            ->assertSee('08h 00m');
    }
}
