<?php

namespace Tests\Feature;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishmentA;

    protected Establishment $establishmentB;

    protected Sector $sectorSaude;

    protected Sector $sectorObras;

    protected User $adminUser;

    protected User $managerSaude;

    protected WorkSchedule $standardSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Prefeitura Municipal Teste',
            'trade_name' => 'Prefeitura Teste',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishmentA = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Administrativa',
            'code' => 'EST-01',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);

        $this->establishmentB = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Posto Central de Saúde',
            'code' => 'EST-02',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000276',
        ]);

        $this->adminUser = User::create([
            'name' => 'Diretor RH',
            'email' => 'rh.diretor@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerSaude = User::create([
            'name' => 'Coordenador Saúde',
            'email' => 'coord.saude@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->sectorSaude = Sector::create([
            'establishment_id' => $this->establishmentB->id,
            'name' => 'Saúde e Vigilância',
            'manager_id' => $this->managerSaude->id,
        ]);

        $this->sectorObras = Sector::create([
            'establishment_id' => $this->establishmentA->id,
            'name' => 'Obras e Serviços Públicos',
            'manager_id' => null,
        ]);

        $this->standardSchedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Administrativo 40h',
            'code' => 'ADM-40',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 480,
            'expected_weekly_minutes' => 2400,
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => [
                'monday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'tuesday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'wednesday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'thursday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
                'friday' => ['entry' => '08:00', 'break_start' => '12:00', 'break_end' => '13:00', 'exit' => '17:00'],
            ],
        ]);
    }

    protected function createEmployee(string $name, Sector $sector, ?WorkSchedule $schedule = null): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@pref.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule?->id,
            'registration_number' => 'MAT-'.rand(1000, 9999),
            'cpf' => sprintf('%011d', rand(10000000000, 99999999999)),
            'job_title' => 'Analista Administrativo',
            'legal_regime' => LegalRegime::CLT,
            'daily_workload_minutes' => 480,
            'weekly_workload_minutes' => 2400,
            'admission_date' => '2026-01-01',
        ]);
    }

    protected function registerPunch(Employee $employee, Carbon $time, string $direction): PunchEvent
    {
        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $employee->sector->establishment_id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => rand(1000, 999999),
            'direction' => $direction,
            'occurred_at_utc' => $time->copy()->utc(),
            'occurred_at_local' => $time,
            'fiscal_hash' => hash('sha256', (string) rand()),
            'audit_chain_hash' => hash('sha256', (string) rand()),
            'payload_hash' => hash('sha256', (string) rand()),
            'hash_type_7' => hash('sha256', $direction.'-'.$time->timestamp),
            'integrity_hash' => hash('sha256', $direction.'-'.$time->timestamp),
        ]);
    }

    /**
     * 1. Competência aberta gera prévia provisória identificada com aviso explícito.
     */
    public function test_relatorio_competencia_aberta_exibe_previa_provisoria_com_aviso(): void
    {
        $emp = $this->createEmployee('Servidor Preliminar', $this->sectorSaude, $this->standardSchedule);

        $service = app(MonthlyAttendanceReportService::class);
        $data = $service->getReportData(2026, 10, $this->adminUser);

        $this->assertFalse($data['is_closed']);
        $this->assertTrue($data['is_provisional']);
        $this->assertStringContainsString('PRÉVIA PROVISÓRIA', $data['report_type_title']);
        $this->assertNotNull($data['provisional_notice']);
        $this->assertStringContainsString('Não possui validade como relatório final', $data['provisional_notice']);
        $this->assertCount(1, $data['rows']);
        $this->assertEquals('Servidor Preliminar', $data['rows'][0]['name']);
    }

    /**
     * 2. Competência fechada utiliza exclusivamente snapshots, permanecendo fiel mesmo com alterações posteriores.
     */
    public function test_relatorio_competencia_fechada_utiliza_exclusivamente_snapshots(): void
    {
        $emp = $this->createEmployee('Servidor Concluído', $this->sectorSaude, $this->standardSchedule);

        // Batidas no dia 01/10/2026: 08:00 - 12:00, 13:00 - 17:00 (8h = 480 min)
        $day = Carbon::parse('2026-10-01');
        $this->registerPunch($emp, $day->copy()->setTime(8, 0), 'in');
        $this->registerPunch($emp, $day->copy()->setTime(12, 0), 'out');
        $this->registerPunch($emp, $day->copy()->setTime(13, 0), 'in');
        $this->registerPunch($emp, $day->copy()->setTime(17, 0), 'out');

        // Homologar e fechar mês
        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee($emp, 2026, 10, $this->managerSaude);
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->adminUser);

        $service = app(MonthlyAttendanceReportService::class);
        $reportBefore = $service->getReportData(2026, 10, $this->adminUser);

        $this->assertTrue($reportBefore['is_closed']);
        $this->assertFalse($reportBefore['is_provisional']);
        $this->assertEquals($closedPeriod->id, $reportBefore['closed_period_id']);
        $this->assertEquals($closedPeriod->snapshot_hash, $reportBefore['closed_hash']);
        $this->assertEquals('DEFINITIVO / HOMOLOGADO', $reportBefore['report_badge_label']);

        // Alterar escala e criar batidas posteriores no banco de dados
        $newSchedule = WorkSchedule::create([
            'company_id' => $this->company->id,
            'name' => 'Escala 20h Posterior Alterada',
            'code' => 'ALT-20',
            'modality' => WorkScheduleModality::FixedWeekly,
            'expected_daily_minutes' => 240,
            'expected_weekly_minutes' => 1200,
            'schedule_data' => [],
        ]);
        $emp->update(['work_schedule_id' => $newSchedule->id]);

        // Consulta após alteração no banco DEVE permanecer estritamente idêntica aos dados congelados
        $reportAfter = $service->getReportData(2026, 10, $this->adminUser);
        $this->assertEquals($reportBefore['totals']['scheduled_minutes'], $reportAfter['totals']['scheduled_minutes']);
        $this->assertEquals($reportBefore['totals']['worked_minutes'], $reportAfter['totals']['worked_minutes']);
        $this->assertEquals('Administrativo 40h', $reportAfter['rows'][0]['schedule_name']);
        $this->assertEquals($closedPeriod->snapshot_hash, $reportAfter['closed_hash']);
    }

    /**
     * 3. Totais apurados com horas físicas, diferenças positivas/negativas e noturno.
     */
    public function test_totais_apurados_com_horas_fisicas_diferencas_e_noturno(): void
    {
        $emp = $this->createEmployee('Servidor Horas Extras', $this->sectorSaude, $this->standardSchedule);

        // Dia 1: 08:00 - 12:00, 13:00 - 19:00 (10h = 600 min -> +120 min positivo)
        $day1 = Carbon::parse('2026-10-01');
        $this->registerPunch($emp, $day1->copy()->setTime(8, 0), 'in');
        $this->registerPunch($emp, $day1->copy()->setTime(12, 0), 'out');
        $this->registerPunch($emp, $day1->copy()->setTime(13, 0), 'in');
        $this->registerPunch($emp, $day1->copy()->setTime(19, 0), 'out');

        $service = app(MonthlyAttendanceReportService::class);
        $data = $service->getReportData(2026, 10, $this->adminUser);

        $row = $data['rows'][0];
        $this->assertEquals(600, $row['worked_minutes']);
        $this->assertEquals(120, $row['positive_minutes']);
        $this->assertGreaterThan(0, $data['totals']['worked_minutes']);
    }

    /**
     * 4. Plantões de 12h/24h são identificados no consolidado.
     */
    public function test_plantoes_identificados_no_relatorio(): void
    {
        $empPlantao = $this->createEmployee('Plantoneiro Emergência', $this->sectorSaude, $this->standardSchedule);

        $startLocal = Carbon::parse('2026-10-01 07:00:00');
        $endLocal = Carbon::parse('2026-10-01 19:00:00');

        ShiftAssignment::create([
            'employee_id' => $empPlantao->id,
            'start_at_utc' => $startLocal->copy()->utc(),
            'end_at_utc' => $endLocal->copy()->utc(),
            'start_at_local' => $startLocal,
            'end_at_local' => $endLocal,
            'timezone' => 'America/Sao_Paulo',
            'shift_type' => ShiftType::Regular,
            'origin' => ShiftOrigin::GeneratedCycle,
            'expected_work_minutes' => 720,
            'break_minutes' => 60,
        ]);

        $service = app(MonthlyAttendanceReportService::class);
        $data = $service->getReportData(2026, 10, $this->adminUser);

        $this->assertEquals(1, $data['totals']['shifts_count']);
        $this->assertEquals(1, $data['rows'][0]['shifts_count']);
    }

    /**
     * 5. Gestor acessa apenas servidores dos setores sob sua gerência.
     */
    public function test_permissoes_gestor_restringe_a_setores_gerenciados(): void
    {
        $empSaude = $this->createEmployee('Servidor da Saúde', $this->sectorSaude, $this->standardSchedule);
        $empObras = $this->createEmployee('Servidor de Obras', $this->sectorObras, $this->standardSchedule);

        $service = app(MonthlyAttendanceReportService::class);

        // Gestor da Saúde vê apenas o servidor da Saúde
        $dataGestor = $service->getReportData(2026, 10, $this->managerSaude);
        $this->assertCount(1, $dataGestor['rows']);
        $this->assertEquals('Servidor da Saúde', $dataGestor['rows'][0]['name']);

        // Admin vê ambos os servidores
        $dataAdmin = $service->getReportData(2026, 10, $this->adminUser);
        $this->assertCount(2, $dataAdmin['rows']);
    }

    /**
     * 6. Filtros por estabelecimento, setor e colaborador funcionam e recalculam totais.
     */
    public function test_filtros_por_estabelecimento_setor_e_colaborador(): void
    {
        $emp1 = $this->createEmployee('Servidor Um', $this->sectorSaude, $this->standardSchedule);
        $emp2 = $this->createEmployee('Servidor Dois', $this->sectorObras, $this->standardSchedule);

        $service = app(MonthlyAttendanceReportService::class);

        // Filtrar por Setor Obras
        $dataObras = $service->getReportData(2026, 10, $this->adminUser, ['sector_id' => $this->sectorObras->id]);
        $this->assertCount(1, $dataObras['rows']);
        $this->assertEquals('Servidor Dois', $dataObras['rows'][0]['name']);

        // Filtrar por Estabelecimento B (Saúde)
        $dataEstB = $service->getReportData(2026, 10, $this->adminUser, ['establishment_id' => $this->establishmentB->id]);
        $this->assertCount(1, $dataEstB['rows']);
        $this->assertEquals('Servidor Um', $dataEstB['rows'][0]['name']);

        // Filtrar por Colaborador específico
        $dataEmp1 = $service->getReportData(2026, 10, $this->adminUser, ['employee_id' => $emp1->id]);
        $this->assertCount(1, $dataEmp1['rows']);
        $this->assertEquals('Servidor Um', $dataEmp1['rows'][0]['name']);
    }

    /**
     * 7. Exportação de CSV UTF-8 estruturado com cabeçalhos e BOM sem lançamentos monetários.
     */
    public function test_exportacao_csv_formato_utf8_com_cabecalhos_administrativos(): void
    {
        $emp = $this->createEmployee('Servidor Export CSV', $this->sectorSaude, $this->standardSchedule);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.reports.csv', ['year' => 2026, 'month' => 10]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Verifica presença do BOM UTF-8 (\xEF\xBB\xBF)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Verifica cabeçalhos administrativos
        $this->assertStringContainsString('Nome do Servidor', $content);
        $this->assertStringContainsString('Carga Prevista (h)', $content);
        $this->assertStringContainsString('Horas Físicas Trabalhadas (h)', $content);
        $this->assertStringContainsString('Diferença Líquida (h)', $content);
        $this->assertStringContainsString('Servidor Export CSV', $content);

        // Garante ausência total de termos financeiros/salário/moeda
        $this->assertStringNotContainsString('R$', $content);
        $this->assertStringNotContainsString('Salário', $content);
        $this->assertStringNotContainsString('Holerite', $content);

        // Verifica que o log de emissão foi gravado
        $this->assertDatabaseHas('monthly_attendance_report_logs', [
            'year' => 2026,
            'month' => 10,
            'generated_by' => $this->adminUser->id,
            'format' => 'csv',
        ]);
    }

    /**
     * 8. Visualização para impressão/PDF e registro explícito de encaminhamento ao setor de folha.
     */
    public function test_visualizacao_para_impressao_pdf_e_registro_de_despacho(): void
    {
        $emp = $this->createEmployee('Servidor Despacho', $this->sectorSaude, $this->standardSchedule);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.reports.pdf', ['year' => 2026, 'month' => 10]));
        $response->assertStatus(200);
        $response->assertSee('FREQUÊNCIA');
        $response->assertSee('PRÉVIA PROVISÓRIA');
        $response->assertSee('Servidor Despacho');

        $log = MonthlyAttendanceReportLog::where('year', 2026)->where('month', 10)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->dispatched_to_payroll_at);

        // Realizar despacho explícito
        $dispatchResp = $this->post(route('admin.reports.dispatch', ['logId' => $log->id]), [
            'notes' => 'Encaminhado ao setor de folha salarial via memorando 102/2026.',
        ]);
        $dispatchResp->assertStatus(200);

        $log->refresh();
        $this->assertNotNull($log->dispatched_to_payroll_at);
        $this->assertEquals($this->adminUser->id, $log->dispatched_by);
        $this->assertEquals('Encaminhado ao setor de folha salarial via memorando 102/2026.', $log->dispatch_notes);
    }

    /**
     * 9. Componente Livewire /admin/reports renderiza com filtros, totais e histórico.
     */
    public function test_componente_livewire_reports_renderiza_corretamente(): void
    {
        $emp = $this->createEmployee('Servidor Livewire Reports', $this->sectorSaude, $this->standardSchedule);

        $this->actingAs($this->adminUser);

        Livewire::test('admin.reports', ['year' => 2026, 'month' => 10])
            ->assertSee('Relatório Mensal Consolidado de Frequência')
            ->assertSee('Servidor Livewire Reports')
            ->assertSee('Exportar CSV')
            ->assertSee('Imprimir / PDF')
            ->call('openDispatchModal')
            ->assertSet('showDispatchModal', true)
            ->set('dispatchNotes', 'Encaminhado pelo RH em lote.')
            ->call('confirmDispatch')
            ->assertSet('showDispatchModal', false);

        $this->assertDatabaseHas('monthly_attendance_report_logs', [
            'year' => 2026,
            'month' => 10,
            'dispatch_notes' => 'Encaminhado pelo RH em lote.',
        ]);
    }
}
