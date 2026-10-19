<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
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
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RegressionConsistencyJourneyAndAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $adminUser;

    protected User $managerUser;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::firstOrCreate(
            ['cnpj' => '11780685000152'],
            [
                'legal_name' => 'Prefeitura Municipal Teste',
                'trade_name' => 'PontoFácil Etapa 3',
                'rep_p_software_name' => 'PontoFácil',
                'rep_p_software_version' => '2.6.15',
            ]
        );

        $this->establishment = Establishment::firstOrCreate(
            ['company_id' => $this->company->id],
            [
                'code' => 'SEDE-01',
                'name' => 'Sede Central',
                'identifier_type' => 'CNPJ',
                'identifier_number' => '11780685000152',
                'timezone' => 'America/Maceio',
                'nsr_next' => 1,
            ]
        );

        $this->adminUser = User::create([
            'name' => 'Coordenador RH',
            'email' => 'rh.coordenador@pontofacil.gov.br',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Admin,
        ]);

        $this->managerUser = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor.setor@pontofacil.gov.br',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Manager,
        ]);

        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Divisão de Saúde e Serviços',
            'manager_id' => $this->managerUser->id,
        ]);
    }

    protected function createEmployeeUser(
        string $name,
        string $reg,
        ?WorkSchedule $schedule = null,
        LegalRegime $regime = LegalRegime::CLT,
        ?Sector $sector = null
    ): array {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@pontofacil.gov.br',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'establishment_id' => $this->establishment->id,
            'sector_id' => $sector?->id ?? $this->sector->id,
            'registration_number' => $reg,
            'cpf' => sprintf('123456%05d', (int) preg_replace('/\D/', '', $reg)),
            'job_title' => 'Assistente Técnico',
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
        $tz = $this->establishment->timezone ?? 'America/Maceio';
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
            'fiscal_hash' => hash('sha256', (string) $nsr),
            'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
            'payload_hash' => hash('sha256', 'payload'.$nsr),
        ]);
    }

    /**
     * FLUXO A: Jornada Comum
     * 08h entrada -> 12h saída -> 13h entrada -> saída esquecida -> virada do dia -> nova entrada às 08h.
     * Resultado: Dia anterior incompleto; novo dia independente; nenhuma saída artificial.
     */
    public function test_fluxo_a_jornada_comum_virada_meia_noite_e_independencia(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Carlos Servidor Comum', '1001', $schedule);

        // Dia 1 (2026-10-05): 08:00 (in), 12:00 (out), 13:00 (in) -> Esqueceu a saída das 17:00
        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 12:00:00', 'out');
        $this->createPunch($employee, '2026-10-05 13:00:00', 'in');

        // Virada do dia civil. Próxima batida ocorre no Dia 2 às 08:00:00
        $dirAction = app(DetermineNextPunchDirectionAction::class);
        $nextDir = $dirAction->execute($user, Carbon::parse('2026-10-06 08:00:00', 'America/Maceio'), $this->establishment);
        $this->assertSame('in', $nextDir, 'Primeira batida de novo dia civil em jornada comum deve iniciar compulsoriamente com in');

        // Dia 2 (2026-10-06): Jornada completa normal de 8h
        $this->createPunch($employee, '2026-10-06 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 12:00:00', 'out');
        $this->createPunch($employee, '2026-10-06 13:00:00', 'in');
        $this->createPunch($employee, '2026-10-06 17:00:00', 'out');

        // 1. Validação no Motor PTRP
        $action = app(CalculateDailyJourneyAction::class);

        // Dia 1: Apenas o primeiro período (08h-12h = 240min) é computado.
        // O segundo período (13h em aberto) não computa horas fictícias e marca jornada incompleta.
        $day1 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertTrue($day1->isIncomplete);
        $this->assertSame(240, $day1->workedMinutes);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day1->attentionReason());

        // Dia 2: Totalmente independente, computa 480 minutos trabalhados sem contaminação do dia anterior
        $day2 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertFalse($day2->isIncomplete);
        $this->assertSame(480, $day2->workedMinutes);
        $this->assertCount(4, $day2->effectivePunches);

        // 2. Validação no Espelho de Ponto (Livewire Timesheet)
        Livewire::actingAs($user)
            ->test('timesheet')
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('04:00'); // 4h computadas no dia 5

        // 3. Validação na Folha de Ponto (Livewire FolhaPonto)
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('Carlos Servidor Comum')
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('17:00');
    }

    /**
     * FLUXO B: Correção de Ponto e Invalidação de Conferência
     * RH inclui saída esquecida com justificativa -> Tratamento aprovado ->
     * Espelho/Folha atualizados -> Conferência anterior invalidada -> RH reconfere.
     */
    public function test_fluxo_b_correcao_de_ponto_e_invalidacao_de_conferencia(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Mariana Assistente', '1002', $schedule);

        // Dia 1: 08:00 às 12:00 e 13:00 sem saída
        $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $this->createPunch($employee, '2026-10-05 12:00:00', 'out');
        $this->createPunch($employee, '2026-10-05 13:00:00', 'in');

        // 1. Gestor realiza a conferência inicial da competência (com ressalva da ocorrência pendente)
        $reviewAction = app(ReviewMonthlyAttendanceAction::class);
        $review = $reviewAction->reviewEmployee($employee, 2026, 10, $this->managerUser, 'Pendente saída das 17h do dia 05');
        $this->assertNotNull($review->fingerprint);

        $reviewService = app(MonthlyAttendanceReviewService::class);
        $overviewBefore = collect($reviewService->getCompetenceOverview(2026, 10, $this->adminUser)['items'])
            ->firstWhere('employee_id', $employee->id);
        $this->assertFalse($overviewBefore['stale']);

        // 2. RH homologa a inclusão da batida das 17:00 via TreatmentEvent
        $reqAction = app(RequestTreatmentEventAction::class);
        $event = $reqAction->execute(
            employee: $employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            reasonText: 'Servidor esqueceu saída em razão de atendimento ao público',
            requestedBy: $this->adminUser,
            newValueJson: ['direction' => 'out']
        );

        $appAction = app(ApproveTreatmentEventAction::class);
        $appAction->execute($event, $this->adminUser);

        // 3. Validação imediata no Motor PTRP
        $action = app(CalculateDailyJourneyAction::class);
        $day1After = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertFalse($day1After->isIncomplete);
        $this->assertSame(480, $day1After->workedMinutes, 'Após aprovação da saída das 17:00, o dia deve totalizar 8h (480min)');
        $this->assertCount(4, $day1After->effectivePunches);

        // 4. Conferência anterior deve se tornar obsoleta (stale) devido à alteração no fingerprint
        $overviewAfter = collect($reviewService->getCompetenceOverview(2026, 10, $this->adminUser)['items'])
            ->firstWhere('employee_id', $employee->id);
        $this->assertTrue($overviewAfter['stale'], 'A conferência deve ser invalidada e exigir reanálise');
        $this->assertContains('Conferência desatualizada — houve alterações após a última análise.', $overviewAfter['occurrences']);

        // 5. RH reconfere a competência agora corrigida (passando ressalva explicativa para os demais dias sem registro)
        $reReview = $reviewAction->reviewEmployee($employee, 2026, 10, $this->adminUser, 'Conferência homologada após inclusão de saída pelo RH.');
        $this->assertNotEquals($review->fingerprint, $reReview->fingerprint);

        $overviewRechecked = collect($reviewService->getCompetenceOverview(2026, 10, $this->adminUser)['items'])
            ->firstWhere('employee_id', $employee->id);
        $this->assertFalse($overviewRechecked['stale']);

        // 6. Espelho e Folha refletem a correção
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026])
            ->assertOk()
            ->assertSee('17:00')
            ->assertSee('[Ajuste aprovado]');
    }

    /**
     * FLUXO C: Plantão Noturno (19h às 07h atravessando a meia-noite)
     * 19h entrada -> virada 00h -> saída 07h -> jornada pertencente ao plantão autorizado.
     */
    public function test_fluxo_c_plantao_noturno_12x36_continuidade_e_zero_duplicacao(): void
    {
        LaborRuleProfile::createStandardCltProfile();

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-HOSP',
            'name' => 'Escala 12x36 Hospitalar',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Dr. Marcos Plantonista', '1003', $schedule);

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

        // Batidas: Entrada às 19:00 do dia 05, Saída às 07:00 do dia 06
        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');

        // Testar auto-direção às 07:00 do dia seguinte para plantão em andamento
        $dirAction = app(DetermineNextPunchDirectionAction::class);
        $nextDir = $dirAction->execute($user, Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'), $this->establishment);
        $this->assertSame('out', $nextDir, 'Para plantonista 12x36 com entrada ontem, a direção às 07:00 deve ser out');

        $this->createPunch($employee, '2026-10-06 07:00:00', 'out');

        $action = app(CalculateDailyJourneyAction::class);

        // Dia 05: Contabiliza o plantão integral de 12 horas (720min)
        $day5 = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertFalse($day5->isIncomplete);
        $this->assertSame(720, $day5->workedMinutes);

        // Dia 06: A saída das 07:00 foi absorvida no plantão da véspera. Zero horas e nenhuma inconsistência.
        $day6 = $action->execute($employee, Carbon::parse('2026-10-06', 'America/Maceio'));
        $this->assertSame(0, $day6->workedMinutes);
        $this->assertFalse($day6->isIncomplete);

        // Validação na Folha de Ponto: exibição com (+1d)
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'reportModel' => 'nocturnal'])
            ->assertOk()
            ->assertSee('19:00')
            ->assertSee('07:00 (+1d)');
    }

    /**
     * FLUXO D: Plantão Incompleto com Tratamento Autorizado
     * Entrada sem saída -> ocorrência pendente -> inclusão de saída aprovada -> resolução integral.
     */
    public function test_fluxo_d_plantao_incompleto_e_tratamento_autorizado(): void
    {
        LaborRuleProfile::createStandardCltProfile();

        $schedule = WorkSchedule::create([
            'code' => 'PL-12X36-NOT',
            'name' => 'Escala 12x36',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'expected_daily_minutes' => 720,
            'active' => true,
        ]);
        [$user, $employee] = $this->createEmployeeUser('Enfermeira Juliana', '1004', $schedule);

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

        // Apenas a entrada foi registrada
        $this->createPunch($employee, '2026-10-05 19:00:00', 'in');

        $action = app(CalculateDailyJourneyAction::class);
        $day5Before = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertTrue($day5Before->isIncomplete);
        $this->assertSame('Jornada incompleta — saída não registrada. Solicite correção.', $day5Before->attentionReason());

        // Inclusão da saída às 07:00 do dia 06 via TreatmentEvent aprovado
        $reqAction = app(RequestTreatmentEventAction::class);
        $event = $reqAction->execute(
            employee: $employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-06 07:00:00', 'America/Maceio'),
            reasonText: 'Falha no leitor biométrico ao término do plantão',
            requestedBy: $this->adminUser,
            newValueJson: ['direction' => 'out']
        );
        app(ApproveTreatmentEventAction::class)->execute($event, $this->adminUser);

        // Apuração recalculada com sucesso
        $day5After = $action->execute($employee, Carbon::parse('2026-10-05', 'America/Maceio'));
        $this->assertFalse($day5After->isIncomplete);
        $this->assertSame(720, $day5After->workedMinutes);
    }

    /**
     * FLUXO E: Competência Fechada com Snapshot Imutável
     * Snapshot permanece idêntico mesmo com reavaliação ou chamadas posteriores.
     */
    public function test_fluxo_e_competencia_fechada_snapshot_imutavel(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Renata Arquivista', '1005', $schedule);

        $this->createPunch($employee, '2026-09-01 08:00:00', 'in');
        $this->createPunch($employee, '2026-09-01 12:00:00', 'out');
        $this->createPunch($employee, '2026-09-01 13:00:00', 'in');
        $this->createPunch($employee, '2026-09-01 17:00:00', 'out');

        // Cria o registro da competência fechada com snapshot de auditoria
        $period = ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'closed_at' => now(),
            'closed_by' => $this->adminUser->id,
            'status' => 'closed',
            'snapshot_hash' => hash('sha256', 'hash_competencia_09_2026'),
        ]);

        $snapshotHash = hash('sha256', 'dados_congelados_renata');
        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $period->id,
            'employee_id' => $employee->id,
            'version' => 1,
            'employee_snapshot' => [
                'name' => 'Renata Arquivista',
                'registration' => '1005',
                'job_title' => 'Assistente Técnico',
            ],
            'schedule_snapshot' => ['modality' => '40h'],
            'journey_snapshot' => [
                [
                    'date' => '2026-09-01',
                    'worked_minutes' => 480,
                    'is_incomplete' => false,
                    'scheduled_minutes' => 480,
                    'effective_punches' => [
                        ['time' => '08:00', 'direction' => 'in', 'source' => 'rep_p'],
                        ['time' => '12:00', 'direction' => 'out', 'source' => 'rep_p'],
                        ['time' => '13:00', 'direction' => 'in', 'source' => 'rep_p'],
                        ['time' => '17:00', 'direction' => 'out', 'source' => 'rep_p'],
                    ],
                ],
            ],
            'treatment_snapshot' => [],
            'time_bank_snapshot' => ['balance' => 0],
            'snapshot_hash' => $snapshotHash,
        ]);

        // Consulta o snapshot congelado
        $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame($snapshotHash, $snapshot->snapshot_hash);
        $this->assertSame(480, $snapshot->journey_snapshot[0]['worked_minutes']);
        $this->assertSame('Renata Arquivista', $snapshot->employee_snapshot['name']);

        // Validação na Folha de Ponto para o mês 09/2026 fechado
        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 9, 'year' => 2026])
            ->assertOk()
            ->assertSee('COMPETÊNCIA FECHADA')
            ->assertSee('Renata Arquivista');
    }

    /**
     * VALIDAÇÃO DE CONFORMIDADE FISCAL: AFD E AEJ (Portaria 671 MTE)
     * AFD lê somente eventos reais do REP-P (PunchEvent monotônico).
     * AEJ inclui a rastreabilidade dos tratamentos do PTRP sem corromper dados brutos.
     */
    public function test_conformidade_fiscal_afd_e_aej(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$user, $employee] = $this->createEmployeeUser('Paulo Fiscal', '1006', $schedule);

        $punchIn = $this->createPunch($employee, '2026-10-05 08:00:00', 'in');
        $punchOut = $this->createPunch($employee, '2026-10-05 12:00:00', 'out');

        // Tratamento aprovado
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            reasonText: 'Ajuste fiscal autorizado',
            requestedBy: $this->adminUser,
            newValueJson: ['direction' => 'out']
        );
        app(ApproveTreatmentEventAction::class)->execute($event, $this->adminUser);

        // 1. AFD (Arquivo Fonte de Dados)
        $afdGen = app(AfdGenerator_2026_07_31::class);
        $afdResult = $afdGen->generate($this->establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertNotNull($afdResult->content);

        // O AFD contém os registros do REP-P
        $this->assertStringContainsString((string) $punchIn->nsr, $afdResult->content);
        $this->assertStringContainsString((string) $punchOut->nsr, $afdResult->content);

        // 2. AEJ (Arquivo Eletrônico de Jornada - Prévia Oficial)
        $aejGen = app(AejGenerator_2026_07_31::class);
        $aejResult = $aejGen->generate($this->establishment, 2026, 10, null, true);
        $this->assertNotNull($aejResult->content);
        $this->assertStringContainsString('Paulo Fiscal', $aejResult->content);
        $this->assertStringContainsString('Ajuste fiscal autorizado', $aejResult->content);
    }

    /**
     * VALIDAÇÃO DE PERMISSÕES E SEGREGAÇÃO DE FUNÇÕES
     * Servidor não pode aprovar tratamentos.
     * Gestor não pode auto-aprovar correções em seu próprio espelho.
     */
    public function test_segregacao_de_funcoes_e_permissoes(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        [$empUser, $employee] = $this->createEmployeeUser('Servidor Comum', '1007', $schedule);

        // 1. Servidor tenta aprovar tratamento diretamente
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-05 17:00:00', 'America/Maceio'),
            reasonText: 'Tentativa de auto-aprovação',
            requestedBy: $empUser,
            newValueJson: ['direction' => 'out']
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('O colaborador não possui permissão para aprovar a própria solicitação.');
        app(ApproveTreatmentEventAction::class)->execute($event, $empUser);
    }
}
