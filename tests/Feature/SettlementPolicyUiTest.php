<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementPolicyUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $employeeUser;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA TESTE S/A',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Central',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceió',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->sector = Sector::create([
            'name' => 'Administrativo',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Gestor RH Admin',
            'email' => 'admin.rh@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Colaborador Comum',
            'email' => 'colab@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'sector_id' => $this->sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'REG-100',
            'cpf' => '33344455566',
            'job_title' => 'Analista Administrativo',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    public function test_admin_can_access_settlement_policies_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settlement-policies'));
        $response->assertStatus(200);
        $response->assertSee('Política de Jornada e Compensação');
        $response->assertSee('1. Para quem vale a regra?');
        $response->assertSee('2. O que fazer com as diferenças?');
        $response->assertSee('3. Quando começa a valer?');
    }

    public function test_quick_presets_prefill_form_fields_without_saving_immediately(): void
    {
        $component = Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal');

        // Preset 1: CLT sem banco
        $component->call('applyPreset', 'clt_no_bank')
            ->assertSet('legal_regime', 'clt')
            ->assertSet('modality', SettlementModality::NoBank->value)
            ->assertSet('name', 'Empresa CLT - Sem Banco de Horas')
            ->assertSee('Empresa CLT sem banco de horas');

        // Preset 2: CLT compensação mensal
        $component->call('applyPreset', 'clt_monthly')
            ->assertSet('legal_regime', 'clt')
            ->assertSet('modality', SettlementModality::MonthlyCompensation->value)
            ->assertSet('name', 'Empresa CLT - Compensação Mensal')
            ->assertSet('max_compensation_months', 1);

        // Preset 3: CLT com banco
        $component->call('applyPreset', 'clt_bank')
            ->assertSet('legal_regime', 'clt')
            ->assertSet('modality', SettlementModality::CumulativeBank->value)
            ->assertSet('name', 'Empresa CLT - Banco de Horas')
            ->assertSet('max_compensation_months', 6);

        // Preset 4: Estatutário municipal (não inventa número de lei)
        $component->call('applyPreset', 'statutory')
            ->assertSet('legal_regime', LegalRegime::MunicipalStatutory->value)
            ->assertSet('modality', SettlementModality::NoBank->value)
            ->assertSet('name', 'Órgão Público - Regime Estatutário');

        // Nenhum registro no banco de dados deve ter sido criado apenas pelo clique no preset
        $this->assertEquals(0, WorkTimeSettlementPolicy::count());
    }

    public function test_review_step_shows_objective_summary_and_allows_going_back(): void
    {
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Compensação Mensal Matriz')
            ->set('scope_type', 'establishment')
            ->set('establishment_id', $this->establishment->id)
            ->set('legal_regime', 'clt')
            ->set('modality', SettlementModality::MonthlyCompensation->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'CLT, art. 59, § 6º - Acordo individual')
            ->call('goToReview')
            ->assertSet('isReviewing', true)
            ->assertSee('Revisão da Política')
            ->assertSee('Esta política será aplicada aos colaboradores do estabelecimento Sede Central')
            ->assertSee('a partir de 01/11/2026')
            ->assertSee('Tratamento:')
            ->assertSee('Compensação mensal')
            ->assertSee('Voltar e editar')
            ->assertSee('Salvar rascunho')
            ->assertSee('Aprovar e ativar')
            ->call('backToEditing')
            ->assertSet('isReviewing', false)
            ->assertSee('Para quem vale esta regra?');
    }

    public function test_saving_as_draft_records_policy_with_draft_status_and_correct_feedback(): void
    {
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Política Rascunho para Análise')
            ->set('scope_type', 'company')
            ->set('legal_regime', 'clt')
            ->set('modality', SettlementModality::MonthlyCompensation->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'Minuta de acordo individual')
            ->call('goToReview')
            ->call('saveDraft')
            ->assertDispatched('app-modal-alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['title'] ?? null) === 'Política salva.' &&
                    ($payload['type'] ?? null) === 'info' &&
                    str_contains($payload['message'] ?? '', 'rascunho com sucesso');
            });

        $this->assertDatabaseHas('work_time_settlement_policies', [
            'name' => 'Política Rascunho para Análise',
            'approval_status' => SettlementPolicyApprovalStatus::Draft->value,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_saving_and_activating_approves_policy_with_approved_status_and_correct_feedback(): void
    {
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Banco de Horas Ativado')
            ->set('scope_type', 'company')
            ->set('legal_regime', 'clt')
            ->set('modality', SettlementModality::CumulativeBank->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'CLT, art. 59, § 5º - Acordo individual de 6 meses')
            ->set('max_compensation_months', 6)
            ->call('goToReview')
            ->call('saveAndActivate')
            ->assertDispatched('app-modal-alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['title'] ?? null) === 'Política aprovada e ativada.' &&
                    ($payload['type'] ?? null) === 'success' &&
                    str_contains($payload['message'] ?? '', 'está ativa a partir de 01/11/2026');
            });

        $policy = WorkTimeSettlementPolicy::where('name', 'Banco de Horas Ativado')->first();
        $this->assertNotNull($policy);
        $this->assertEquals(SettlementPolicyApprovalStatus::Approved, $policy->approval_status);
        $this->assertEquals($this->admin->id, $policy->approved_by);
        $this->assertNotNull($policy->approved_at);
        $this->assertEquals('active', $policy->status);
    }

    public function test_simple_configuration_does_not_require_bank_limits_or_advanced_fields(): void
    {
        // Ao salvar 'no_bank', não exige 'daily_bank_limit_minutes' nem 'max_compensation_months'
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Regra Simples Sem Banco')
            ->set('scope_type', 'company')
            ->set('legal_regime', 'clt')
            ->set('modality', SettlementModality::NoBank->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'Regulamento interno - sem regime de banco de horas')
            ->call('goToReview')
            ->assertHasNoErrors()
            ->call('saveDraft')
            ->assertDispatched('app-modal-alert');

        $this->assertDatabaseHas('work_time_settlement_policies', [
            'name' => 'Regra Simples Sem Banco',
            'modality' => SettlementModality::NoBank->value,
        ]);
    }

    public function test_hybrid_modality_validates_required_daily_bank_limit(): void
    {
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Modelo Misto Incompleto')
            ->set('scope_type', 'company')
            ->set('legal_regime', 'clt')
            ->set('modality', SettlementModality::Hybrid->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'Acordo coletivo de trabalho híbrido')
            ->set('daily_bank_limit_minutes', 0) // Inválido (min: 1)
            ->call('goToReview')
            ->assertHasErrors(['daily_bank_limit_minutes']);
    }

    public function test_statutory_regime_rejects_pure_clt_framework(): void
    {
        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openCreateModal')
            ->set('name', 'Estatutário com Fundamentação Inválida')
            ->set('scope_type', 'company')
            ->set('legal_regime', LegalRegime::MunicipalStatutory->value)
            ->set('modality', SettlementModality::NoBank->value)
            ->set('effective_from', '2026-11-01')
            ->set('legal_framework', 'CLT, art. 59 § 5º') // Ilegal para servidor estatutário
            ->call('goToReview')
            ->assertHasErrors(['legal_framework']);
    }

    public function test_cannot_destructively_modify_policy_used_in_closed_period(): void
    {
        // Cria política no passado
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Política Histórica de Janeiro',
            'modality' => SettlementModality::CumulativeBank,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo CLT',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        // Fecha formalmente a competência de 01/2026
        $closed = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Fechamento de teste'
        );

        $this->assertTrue(ClosedPeriod::isClosed(2026, 1), 'ClosedPeriod deve estar fechado');

        // Tenta alterar destrutivamente a modalidade da política histórica
        $test = Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('openEditModal', $policy->id)
            ->set('modality', SettlementModality::NoBank->value) // Mudança destrutiva
            ->call('goToReview')
            ->assertHasNoErrors()
            ->call('saveAndActivate');

        $test->assertDispatched('app-modal-alert', function ($event, $params) {
            $payload = $params[0] ?? $params;

            return ($payload['title'] ?? null) === 'Alteração Bloqueada' &&
                ($payload['type'] ?? null) === 'error' &&
                str_contains($payload['message'] ?? '', 'competências formalmente fechadas');
        });

        // Modalidade original permanece inalterada
        $policy->refresh();
        $this->assertEquals(SettlementModality::CumulativeBank, $policy->modality);
    }

    public function test_toggle_policy_status_between_active_and_inactive(): void
    {
        $policy = WorkTimeSettlementPolicy::create([
            'name' => 'Política para Desativar',
            'modality' => SettlementModality::MonthlyCompensation,
            'effective_from' => '2026-01-01',
            'legal_framework' => 'Acordo individual',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
        ]);

        Livewire::actingAs($this->admin)->test('admin.settlement-policies')
            ->call('toggleStatus', $policy->id)
            ->assertDispatched('app-modal-alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return str_contains($payload['message'] ?? '', 'inativa');
            });

        $policy->refresh();
        $this->assertEquals('inactive', $policy->status);
    }
}
