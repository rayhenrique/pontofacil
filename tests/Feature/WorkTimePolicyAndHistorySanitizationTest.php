<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Services\TimeBankStatementService;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkTimePolicyAndHistorySanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Establishment $establishmentA;

    protected Sector $sectorA;

    protected User $userA;

    protected Employee $employeeA;

    protected WorkSchedule $schedule40h;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->companyA = Company::create([
            'legal_name' => 'Empresa Alfa Servicos LTDA',
            'trade_name' => 'Alfa Servicos',
            'cnpj' => '11111111000111',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.5.0',
        ]);

        $this->establishmentA = Establishment::create([
            'company_id' => $this->companyA->id,
            'code' => 'ALFA-01',
            'name' => 'Matriz Alfa',
            'identifier_type' => 'cnpj',
            'identifier_number' => '11111111000111',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sectorA = Sector::create([
            'name' => 'Administrativo',
            'establishment_id' => $this->establishmentA->id,
        ]);

        $this->userA = User::create([
            'name' => 'Carlos Colaborador Alfa',
            'email' => 'carlos.alfa@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule40h = WorkSchedule::createDefault40h();

        $this->employeeA = Employee::create([
            'user_id' => $this->userA->id,
            'sector_id' => $this->sectorA->id,
            'work_schedule_id' => $this->schedule40h->id,
            'registration_number' => 'MAT-ALFA-01',
            'cpf' => '11122233344',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    protected function createPunches(Employee $employee, Carbon $date, array $times): void
    {
        $action = app(RecordPunchEventAction::class);
        $establishment = $employee->sector?->establishment ?? $this->establishmentA;
        $dirs = ['in', 'out', 'in', 'out'];

        foreach ($times as $idx => $timeStr) {
            Carbon::setTestNow(Carbon::parse($date->format('Y-m-d').' '.$timeStr.':00'));
            $action->execute(user: $employee->user, direction: $dirs[$idx % count($dirs)], establishment: $establishment);
        }

        Carbon::setTestNow();
    }

    /**
     * 1. Empresa sem banco de horas (NoBank sem pagamento autorizado):
     * Horas extras não são creditadas no banco nem convertidas automaticamente em pagamento.
     * Diferenças são preservadas e a destinação fica pendente de definição.
     */
    public function test_01_empresa_sem_banco_preserva_diferencas_com_destinacao_pendente(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Sem Banco de Horas - Analise Manual',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Regulamento interno da empresa',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'compensation_terms' => [],
        ]);

        $monday = Carbon::parse('2026-10-05');
        // Trabalhou 08:00 às 12:00 (240m) e 14:00 às 19:00 (300m) = 540m (+60m de horas extras)
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday, $this->schedule40h);

        $this->assertSame(480, $journey->scheduledMinutes);
        $this->assertSame(540, $journey->workedMinutes);
        $this->assertSame(60, $journey->overtimeMinutes);
        $this->assertSame(0, $journey->bankCreditMinutes);
        $this->assertSame(0, $journey->destinedToPayrollMinutes);
        $this->assertStringContainsString('Destinação pendente de definição', implode('; ', $journey->settlementNotes));
    }

    /**
     * 2. Empresa com pagamento autorizado (NoBank com autorização prévia):
     * Excedente é destinado para controle de folha sem contabilizar banco de horas.
     */
    public function test_02_empresa_com_pagamento_autorizado_destina_para_folha(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Sem Banco - Quitação em Folha',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Acordo Coletivo - Pagamento de Horas Extras',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'compensation_terms' => ['authorize_payroll_payment' => true],
        ]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday, $this->schedule40h);

        $this->assertSame(60, $journey->overtimeMinutes);
        $this->assertSame(0, $journey->bankCreditMinutes);
        $this->assertSame(60, $journey->destinedToPayrollMinutes);
    }

    /**
     * 3. Compensação mensal:
     * Positivos e negativos compensam no período mensal sem acumular para períodos posteriores.
     */
    public function test_03_compensacao_mensal_destina_para_compensacao_do_periodo(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Compensação no Mês',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::MonthlyCompensation,
            'legal_framework' => 'Art. 59 § 6º da CLT - Acordo Individual Tácito',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday, $this->schedule40h);

        $this->assertSame(60, $journey->overtimeMinutes);
        $this->assertSame(0, $journey->bankCreditMinutes);
        $this->assertSame(60, $journey->destinedToCompensationMinutes);
    }

    /**
     * 4. Banco acumulativo:
     * Diferenças são creditadas e debitadas no banco de horas.
     */
    public function test_04_banco_acumulativo_lanca_no_banco_de_horas(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Geral',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Acordo Coletivo de Banco de Horas',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday, $this->schedule40h);

        $this->assertSame(60, $journey->overtimeMinutes);
        $this->assertSame(60, $journey->bankCreditMinutes);
        $this->assertSame(0, $journey->destinedToPayrollMinutes);
    }

    /**
     * 5. Política mista (Hybrid):
     * Reconhece a modalidade híbrida sem colidir regras.
     */
    public function test_05_politica_mista_restringe_e_identifica_modalidade_hibrida(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Misto',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::Hybrid,
            'legal_framework' => 'Acordo Coletivo - Modelo Hibrido Banco e Folha',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'hybrid_rules' => ['bank_limit_hours' => 20],
        ]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday, $this->schedule40h);

        $this->assertSame(SettlementModality::Hybrid, $journey->settlementModality);
    }

    /**
     * 6. Política ausente:
     * Comportamento seguro quando não há política configurada: não insere registros no banco e indica estado não configurado.
     */
    public function test_06_politica_ausente_nao_cria_registros_automaticos(): void
    {
        $service = app(WorkTimeSettlementService::class);
        $date = Carbon::parse('2026-10-05');

        $policy = $service->resolvePolicy($this->employeeA, $date);

        $this->assertFalse($policy->exists);
        $this->assertFalse($policy->isConfigured());
        $this->assertSame(SettlementModality::NoBank, $policy->modality);
        $this->assertSame(0, WorkTimeSettlementPolicy::count());
    }

    /**
     * 7. Políticas de empresas diferentes:
     * Uma política da Empresa B nunca é aplicada por engano a um colaborador da Empresa A.
     */
    public function test_07_politica_de_outra_empresa_nao_e_aplicada(): void
    {
        $companyB = Company::create([
            'legal_name' => 'Empresa Beta Tecnologia LTDA',
            'trade_name' => 'Beta Tech',
            'cnpj' => '22222222000122',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.5.0',
        ]);

        WorkTimeSettlementPolicy::create([
            'name' => 'Banco Acumulativo Exclusivo Beta',
            'company_id' => $companyB->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'ACT Beta Tech',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $service = app(WorkTimeSettlementService::class);
        $date = Carbon::parse('2026-10-05');

        // Resolve para colaborador da Empresa A: NÃO deve usar a política da Empresa B
        $policy = $service->resolvePolicy($this->employeeA, $date);

        $this->assertNotSame('Banco Acumulativo Exclusivo Beta', $policy->name);
        $this->assertFalse($policy->isConfigured());
    }

    /**
     * 8. Troca de política por vigência:
     * Edição com nova vigência preserva cálculo histórico e aplica nova regra na data correta.
     */
    public function test_08_troca_de_politica_por_vigencia_preserva_historico(): void
    {
        // Vigência 1: 01/01/2026 até 31/03/2026 -> Sem Banco
        WorkTimeSettlementPolicy::create([
            'name' => 'V1 Sem Banco',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::NoBank,
            'legal_framework' => 'Regulamento 2026-Q1',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-03-31',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        // Vigência 2: a partir de 01/04/2026 -> Banco Acumulativo
        WorkTimeSettlementPolicy::create([
            'name' => 'V2 Banco Acumulativo',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Acordo Coletivo 2026-Q2',
            'effective_from' => '2026-04-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $service = app(WorkTimeSettlementService::class);

        $policyMarco = $service->resolvePolicy($this->employeeA, Carbon::parse('2026-03-15'));
        $policyAbril = $service->resolvePolicy($this->employeeA, Carbon::parse('2026-04-15'));

        $this->assertSame('V1 Sem Banco', $policyMarco->name);
        $this->assertSame(SettlementModality::NoBank, $policyMarco->modality);

        $this->assertSame('V2 Banco Acumulativo', $policyAbril->name);
        $this->assertSame(SettlementModality::CumulativeBank, $policyAbril->modality);
    }

    /**
     * 9. Estatutário sem norma configurada:
     * Servidor estatutário não herda regras da CLT e requer previsão em lei específica.
     */
    public function test_09_estatutario_sem_norma_configurada_rejeita_banco_tacito(): void
    {
        $statutoryUser = User::create([
            'name' => 'Servidora Marina Estatutaria',
            'email' => 'marina.est@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $statutoryEmployee = Employee::create([
            'user_id' => $statutoryUser->id,
            'sector_id' => $this->sectorA->id,
            'work_schedule_id' => $this->schedule40h->id,
            'registration_number' => 'MAT-EST-01',
            'cpf' => '99988877766',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        // Cria política com menção apenas a 'CLT' ou sem lei
        WorkTimeSettlementPolicy::create([
            'name' => 'Regra CLT Inaplicavel',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'CLT Art. 59',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($statutoryEmployee, $monday, ['08:00', '12:00', '14:00', '19:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($statutoryEmployee, $monday, $this->schedule40h);

        $this->assertSame(0, $journey->bankCreditMinutes);
        $this->assertTrue($journey->isPendingSettlement);
        $this->assertStringContainsString('Servidor estatutário', implode('; ', $journey->treatmentNotes));
    }

    /**
     * 10. Competência fechada:
     * Consulta recupera fielmente os dados do snapshot congelado.
     */
    public function test_10_competencia_fechada_retorna_snapshot_congelado(): void
    {
        $closed = ClosedPeriod::create([
            'company_id' => $this->companyA->id,
            'year' => 2026,
            'month' => 8,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'closed',
            'snapshot_version' => 1,
            'closed_at' => now(),
            'closed_by' => $this->userA->id,
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closed->id,
            'employee_id' => $this->employeeA->id,
            'version' => 1,
            'employee_snapshot' => [
                'name' => $this->userA->name,
                'cpf' => $this->employeeA->cpf,
            ],
            'schedule_snapshot' => [],
            'shifts_snapshot' => [],
            'rule_profile_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2026-08-03',
                    'worked_minutes' => 480,
                    'scheduled_minutes' => 480,
                    'is_incomplete' => false,
                    'has_schedule' => true,
                    'effective_punches' => [
                        ['time' => '08:00', 'type' => 'in'],
                        ['time' => '17:00', 'type' => 'out'],
                    ],
                ],
            ],
            'treatment_snapshot' => [],
            'time_bank_snapshot' => [
                'closing_balance' => 0,
            ],
            'calendar_snapshot' => [],
            'snapshot_hash' => hash('sha256', 'test-snap'),
        ]);

        $data = app(TimesheetJourneyService::class)->resolveMonthData($this->userA, 2026, 8);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertSame(480, $data['totalWorkedMinutes']);
        $this->assertTrue($data['differencesSummary']->isDefinitive());
        $this->assertSame('Definitiva (Snapshot imutável v1)', $data['differencesSummary']->statusLabel);
    }

    /**
     * 11. Snapshot ausente em competência fechada:
     * Informa indisponibilidade histórica e não calcula ao vivo nem usa escala atual.
     */
    public function test_11_snapshot_ausente_em_competencia_fechada_indica_historico_indisponivel(): void
    {
        ClosedPeriod::create([
            'company_id' => $this->companyA->id,
            'year' => 2026,
            'month' => 7,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'closed',
            'snapshot_version' => 1,
            'closed_at' => now(),
            'closed_by' => $this->userA->id,
        ]);

        $data = app(TimesheetJourneyService::class)->resolveMonthData($this->userA, 2026, 7);

        $this->assertTrue($data['isClosedPeriod']);
        $this->assertTrue($data['isHistoricalUnavailable']);
        $this->assertSame('Histórico indisponível', $data['differencesSummary']->statusLabel);
        $this->assertSame('N/D', $data['differencesSummary']->formattedNet());
    }

    /**
     * 12. Cadastro funcional ausente:
     * Usuário sem vínculo não gera criação automática de Employee nem CPF fictício.
     */
    public function test_12_usuario_sem_cadastro_funcional_mostra_aviso_apropriado_sem_efeito_colateral(): void
    {
        $orphanUser = User::create([
            'name' => 'Novo Usuario Sem Cadastro',
            'email' => 'novo.orfa@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $initialEmployeeCount = Employee::count();

        $data = app(TimesheetJourneyService::class)->resolveMonthData($orphanUser, 2026, 10);

        $this->assertTrue($data['isRegistrationPending']);
        $this->assertSame($initialEmployeeCount, Employee::count());
        $this->assertNull(Employee::where('user_id', $orphanUser->id)->first());

        // Livewire exibe mensagem orientando a procurar o RH
        Livewire::actingAs($orphanUser)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('Seu cadastro funcional ainda não está completo. Procure o responsável pelo RH.');
    }

    /**
     * 13. Leitura sem inserções no banco:
     * Consultas ao Espelho e TimeBankStatementService não criam contas ou registros involuntários.
     */
    public function test_13_consultas_de_leitura_nao_criam_time_bank_account(): void
    {
        $this->assertSame(0, TimeBankAccount::count());

        // 1. Consulta ao serviço de extrato
        $summary = app(TimeBankStatementService::class)->getMonthlySummary($this->employeeA, 2026, 10);
        $this->assertSame(0, $summary->currentBalanceMinutes);
        $this->assertSame(0, TimeBankAccount::count());

        // 2. Consulta ao espelho
        app(TimesheetJourneyService::class)->resolveMonthData($this->userA, 2026, 10);
        $this->assertSame(0, TimeBankAccount::count());
    }

    /**
     * 14. Jornada incompleta:
     * Marcação ímpar em dia útil classifica competência como Parcial e não exibe 00:00 fictício.
     */
    public function test_14_jornada_incompleta_classifica_como_parcial(): void
    {
        WorkTimeSettlementPolicy::create([
            'name' => 'Banco de Horas Geral',
            'company_id' => $this->companyA->id,
            'modality' => SettlementModality::CumulativeBank,
            'legal_framework' => 'Acordo Coletivo de Banco de Horas',
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
        ]);

        $date = Carbon::parse('2026-10-01');
        // Apenas entrada às 08:00 (sem saída)
        $this->createPunches($this->employeeA, $date, ['08:00']);

        $data = app(TimesheetJourneyService::class)->resolveMonthData($this->userA, 2026, 10);

        $summary = $data['differencesSummary'];
        $this->assertTrue($summary->isPartial());
        $this->assertTrue($data['isPartial']);
    }

    /**
     * 15. Ausência de escala de trabalho:
     * Colaborador sem escala autorizada para o período é classificado como "Pendente de configuração".
     */
    public function test_15_ausencia_de_escala_indica_pendente_de_configuracao(): void
    {
        $this->employeeA->update(['work_schedule_id' => null]);

        $monday = Carbon::parse('2026-10-05');
        $this->createPunches($this->employeeA, $monday, ['08:00', '12:00', '13:00', '17:00']);

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employeeA, $monday);

        $this->assertTrue($journey->isPendingConfiguration);
        $this->assertStringContainsString('Ausência de escala de trabalho', $journey->treatmentNotes[0] ?? '');

        $data = app(TimesheetJourneyService::class)->resolveMonthData($this->userA, 2026, 10);
        $summary = $data['differencesSummary'];
        $this->assertTrue($summary->isPendingConfiguration());
    }

    /**
     * 16. Histórico legado com TimeBankPolicy:
     * Utiliza TimeBankPolicy legada caso configurada, sem modificar retroativamente registros.
     */
    public function test_16_historico_legado_respeita_time_bank_policy_existente(): void
    {
        TimeBankPolicy::create([
            'name' => 'Politica Legada Fechamento Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => '2025-01-01',
            'valid_until' => '2025-12-31',
        ]);

        $service = app(WorkTimeSettlementService::class);
        $policy = $service->resolvePolicy($this->employeeA, Carbon::parse('2025-06-15'));

        $this->assertSame(SettlementModality::LegacyMonthlyReset, $policy->modality);
        $this->assertStringContainsString('Politica Legada', $policy->name);
    }
}
