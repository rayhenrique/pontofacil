<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LaborRuleProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $adminUser;

    protected User $managerUser;

    protected Sector $sector;

    protected WorkSchedule $schedule12x36;

    protected WorkSchedule $scheduleAdmin40h;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->adminUser = User::create([
            'name' => 'Gestor de RH / Admin',
            'email' => 'admin.rh@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->managerUser = User::create([
            'name' => 'Gestor de Setor',
            'email' => 'gestor.setor@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $this->sector = Sector::create([
            'name' => 'Pronto Atendimento Municipal',
            'establishment_id' => $this->establishment->id,
            'manager_id' => $this->managerUser->id,
        ]);

        $this->scheduleAdmin40h = WorkSchedule::createDefault40h();

        $this->schedule12x36 = WorkSchedule::create([
            'name' => 'Plantão 12x36 Diurno Emergência',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => ['clt', 'municipal_statutory', 'state_statutory', 'federal_statutory'],
            'schedule_data' => $this->scheduleAdmin40h->schedule_data,
            'cycle_data' => [
                'start_time' => '07:00',
                'end_time' => '19:00',
                'duration_hours' => 12,
                'rest_hours' => 36,
                'break_minutes' => 60,
                'is_night_shift' => false,
                'anchor_date' => '2026-10-01',
            ],
            'active' => true,
        ]);
    }

    public function test_clt_employee_uses_standard_clt_profile_for_12x36(): void
    {
        $cltUser = User::create([
            'name' => 'Enfermeiro CLT',
            'email' => 'enf.clt@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $cltUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '11111111111',
            'job_title' => 'Enfermeiro',
            'contract_type' => 'CLT',
            'legal_regime' => LegalRegime::CLT,
            'workload' => '12x36',
        ]);

        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $employee,
            schedule: $this->schedule12x36,
            effectiveFrom: '2026-10-01',
            reason: 'Escala 12x36 CLT padrão',
            assignedBy: $this->adminUser,
        );

        $this->assertNotNull($assignment);
        $this->assertEquals($this->schedule12x36->id, $assignment->work_schedule_id);

        $resolvedProfile = $employee->getLaborRuleProfileForDate(Carbon::parse('2026-10-01'));
        $this->assertNotNull($resolvedProfile);
        $this->assertEquals('clt_urban_standard', $resolvedProfile->code);
    }

    public function test_municipal_statutory_employee_without_profile_is_denied_12x36(): void
    {
        $statUser = User::create([
            'name' => 'Médico Municipal',
            'email' => 'medico.mun@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '22222222222',
            'job_title' => 'Médico Plantonista',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'workload' => '12x36',
        ]);

        $this->expectException(ValidationException::class);

        app(AssignWorkScheduleAction::class)->execute(
            employee: $employee,
            schedule: $this->schedule12x36,
            effectiveFrom: '2026-10-01',
            reason: 'Tentativa sem perfil normativo',
            assignedBy: $this->adminUser,
        );
    }

    public function test_municipal_statutory_with_approved_profile_is_authorized_for_12x36(): void
    {
        $profile = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Saúde Municipal Plantão 12x36',
            code: 'saude-mun-12x36',
            legalReference: 'Lei Municipal nº 5.678/2022, art. 12',
            config: [
                'allowed_modalities' => ['standard', '12x36'],
            ],
            attributes: [
                'entity_name' => 'Prefeitura de Teste',
                'effective_from' => '2020-01-01',
            ]
        );

        $statUser = User::create([
            'name' => 'Médico Autorizado',
            'email' => 'medico.aut@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '33333333333',
            'job_title' => 'Médico',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $profile->id,
            'workload' => '12x36',
        ]);

        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $employee,
            schedule: $this->schedule12x36,
            effectiveFrom: '2026-10-01',
            reason: 'Atribuição autorizada por lei municipal',
            assignedBy: $this->adminUser,
        );

        $this->assertEquals($this->schedule12x36->id, $assignment->work_schedule_id);
    }

    public function test_municipal_statutory_with_profile_not_allowing_12x36_is_denied(): void
    {
        $profile = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Estatuto Geral Administrativo',
            code: 'estatuto-geral-admin',
            legalReference: 'Lei Municipal nº 1.000/2010',
            config: [
                'allowed_modalities' => ['standard'], // NÃO autoriza 12x36
            ],
            attributes: [
                'entity_name' => 'Prefeitura de Teste',
                'effective_from' => '2020-01-01',
            ]
        );

        $statUser = User::create([
            'name' => 'Servidor Administrativo',
            'email' => 'serv.admin@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '44444444444',
            'job_title' => 'Agente Administrativo',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $profile->id,
            'workload' => '12x36',
        ]);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $employee,
                schedule: $this->schedule12x36,
                effectiveFrom: '2026-10-01',
                reason: 'Atribuição indevida 12x36',
                assignedBy: $this->adminUser,
            );
            $this->fail('Deveria ter lançado ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('não está autorizada no perfil normativo', $e->getMessage());
        }
    }

    public function test_draft_profile_does_not_authorize_special_schedule(): void
    {
        $draftProfile = LaborRuleProfile::create([
            'name' => 'Perfil Rascunho 12x36',
            'code' => 'draft-12x36',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'jurisdiction' => 'municipal',
            'entity_name' => 'Prefeitura de Teste',
            'legal_reference' => 'Projeto de Lei',
            'effective_from' => '2026-01-01',
            'approval_status' => LaborRuleApprovalStatus::Draft,
            'version' => 1,
            'configuration' => [
                'allowed_modalities' => ['standard', '12x36'],
            ],
        ]);

        $statUser = User::create([
            'name' => 'Servidor Teste Draft',
            'email' => 'draft.user@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '55555555555',
            'job_title' => 'Técnico',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $draftProfile->id,
        ]);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $employee,
                schedule: $this->schedule12x36,
                effectiveFrom: '2026-10-01',
                reason: 'Atribuição com perfil em rascunho',
                assignedBy: $this->adminUser,
            );
            $this->fail('Deveria ter lançado ValidationException para perfil em rascunho');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('está em rascunho', $e->getMessage());
        }
    }

    public function test_revoked_profile_does_not_authorize_new_schedule_assignments(): void
    {
        $revokedProfile = LaborRuleProfile::create([
            'name' => 'Perfil Revogado Lei Antiga',
            'code' => 'revoked-12x36',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'jurisdiction' => 'municipal',
            'entity_name' => 'Prefeitura de Teste',
            'legal_reference' => 'Lei Revogada 999',
            'effective_from' => '2020-01-01',
            'approval_status' => LaborRuleApprovalStatus::Revoked,
            'revoked_by' => $this->adminUser->id,
            'revoked_at' => Carbon::now(),
            'revocation_reason' => 'Lei revogada por decisão judicial transitada em julgado',
            'version' => 1,
            'configuration' => [
                'allowed_modalities' => ['standard', '12x36'],
            ],
        ]);

        $statUser = User::create([
            'name' => 'Servidor Teste Revoked',
            'email' => 'revoked.user@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '66666666666',
            'job_title' => 'Técnico',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $revokedProfile->id,
        ]);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $employee,
                schedule: $this->schedule12x36,
                effectiveFrom: '2026-10-01',
                reason: 'Atribuição com perfil revogado',
                assignedBy: $this->adminUser,
            );
            $this->fail('Deveria ter lançado ValidationException para perfil revogado');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('foi revogado', $e->getMessage());
        }
    }

    public function test_expired_or_future_profile_is_denied(): void
    {
        $futureProfile = LaborRuleProfile::create([
            'name' => 'Perfil Vigência Futura',
            'code' => 'future-12x36',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'jurisdiction' => 'municipal',
            'entity_name' => 'Prefeitura de Teste',
            'legal_reference' => 'Lei com Vacatio Legis',
            'effective_from' => '2027-01-01', // Futuro
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'version' => 1,
            'configuration' => [
                'allowed_modalities' => ['standard', '12x36'],
            ],
        ]);

        $statUser = User::create([
            'name' => 'Servidor Teste Futuro',
            'email' => 'future.user@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $statUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '77777777777',
            'job_title' => 'Técnico',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $futureProfile->id,
        ]);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $employee,
                schedule: $this->schedule12x36,
                effectiveFrom: '2026-10-01',
                reason: 'Atribuição antes da vigência',
                assignedBy: $this->adminUser,
            );
            $this->fail('Deveria ter lançado ValidationException para perfil fora da vigência');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('não é válido para a data selecionada', $e->getMessage());
        }
    }

    public function test_profile_of_incompatible_regime_is_denied(): void
    {
        $stateProfile = LaborRuleProfile::create([
            'name' => 'Perfil Polícia Civil Estadual',
            'code' => 'pc-estadual-12x36',
            'legal_regime' => LegalRegime::StateStatutory,
            'jurisdiction' => 'state',
            'entity_name' => 'Estado de Alagoas',
            'legal_reference' => 'Lei Estadual nº 7.000',
            'effective_from' => '2020-01-01',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'version' => 1,
            'configuration' => [
                'allowed_modalities' => ['standard', '12x36'],
            ],
        ]);

        $munUser = User::create([
            'name' => 'Servidor Municipal Incompatível',
            'email' => 'incompativel@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $munUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '88888888888',
            'job_title' => 'Guarda',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory, // Municipal!
            'normative_entity' => 'Prefeitura de Teste',
            'labor_rule_profile_id' => $stateProfile->id, // Perfil é estadual!
        ]);

        try {
            app(AssignWorkScheduleAction::class)->execute(
                employee: $employee,
                schedule: $this->schedule12x36,
                effectiveFrom: '2026-10-01',
                reason: 'Atribuição com regime incompatível',
                assignedBy: $this->adminUser,
            );
            $this->fail('Deveria ter lançado ValidationException para regime incompatível');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('incompatível com o colaborador', $e->getMessage());
        }
    }

    public function test_distinct_entities_profiles_are_not_confused(): void
    {
        $profileMaceio = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Saúde Maceió 12x36',
            code: 'saude-maceio-12x36',
            legalReference: 'Lei Municipal Maceió nº 1.111',
            config: ['allowed_modalities' => ['standard', '12x36']],
            attributes: ['entity_name' => 'Prefeitura de Maceió']
        );

        $profileArapiraca = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Saúde Arapiraca 12x36',
            code: 'saude-arapiraca-12x36',
            legalReference: 'Lei Municipal Arapiraca nº 2.222',
            config: ['allowed_modalities' => ['standard', '12x36']],
            attributes: ['entity_name' => 'Prefeitura de Arapiraca']
        );

        $employeeUser = User::create([
            'name' => 'Servidor Maceió',
            'email' => 'maceio@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employeeMaceio = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '99999999991',
            'job_title' => 'Médico',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Maceió',
        ]);

        // Sem atribuição explícita mas com ente 'Prefeitura de Maceió', deve resolver o perfil de Maceió
        $resolved = $employeeMaceio->getLaborRuleProfileForDate(Carbon::parse('2026-10-01'));
        $this->assertNotNull($resolved);
        $this->assertEquals($profileMaceio->id, $resolved->id);
        $this->assertNotEquals($profileArapiraca->id, $resolved->id);
    }

    public function test_employee_profile_historical_versioning(): void
    {
        $profileV1 = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Plano Antigo 12x36',
            code: 'plano-antigo-12x36',
            legalReference: 'Lei Antiga nº 1.000',
            config: ['allowed_modalities' => ['standard', '12x36']],
            attributes: ['entity_name' => 'Prefeitura de Teste', 'effective_from' => '2020-01-01', 'effective_until' => '2026-05-31']
        );

        $profileV2 = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Novo Estatuto 12x36',
            code: 'novo-estatuto-12x36',
            legalReference: 'Lei Nova nº 2.000',
            config: ['allowed_modalities' => ['standard', '12x36']],
            attributes: ['entity_name' => 'Prefeitura de Teste', 'effective_from' => '2026-06-01']
        );

        $empUser = User::create([
            'name' => 'Servidor Histórico',
            'email' => 'historico@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $empUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '99999999992',
            'job_title' => 'Enfermeiro',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
        ]);

        // Atribuição 1 (período passado)
        $employee->assignLaborRuleProfile(
            profile: $profileV1,
            effectiveFrom: '2026-01-01',
            effectiveUntil: '2026-05-31',
            reason: 'Vigência do plano antigo',
            assignedBy: $this->adminUser,
        );

        // Atribuição 2 (período novo)
        $employee->assignLaborRuleProfile(
            profile: $profileV2,
            effectiveFrom: '2026-06-01',
            effectiveUntil: null,
            reason: 'Migração para o novo estatuto',
            assignedBy: $this->adminUser,
        );

        $this->assertEquals(2, $employee->laborRuleProfileAssignments()->count());

        // Apuração em março de 2026: deve resolver Plano Antigo
        $pastProfile = $employee->getLaborRuleProfileForDate(Carbon::parse('2026-03-15'));
        $this->assertNotNull($pastProfile);
        $this->assertEquals($profileV1->id, $pastProfile->id);

        // Apuração em julho de 2026: deve resolver Novo Estatuto
        $currentProfile = $employee->getLaborRuleProfileForDate(Carbon::parse('2026-07-01'));
        $this->assertNotNull($currentProfile);
        $this->assertEquals($profileV2->id, $currentProfile->id);
    }

    public function test_sector_manager_cannot_manage_labor_rule_profiles(): void
    {
        $this->actingAs($this->managerUser);

        // Acesso à rota de gerenciamento de perfis deve ser 403 Forbidden para gestores
        $response = $this->get(route('admin.labor-rule-profiles'));
        $response->assertStatus(403);
    }

    public function test_rh_can_create_approve_and_revoke_profile_via_livewire(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test('admin.labor-rule-profiles')
            ->call('openCreateModal')
            ->set('name', 'Estatuto dos Médicos Emergencistas')
            ->set('code', 'estatuto-med-emerg')
            ->set('legal_regime', 'municipal_statutory')
            ->set('jurisdiction', 'municipal')
            ->set('entity_name', 'Prefeitura de Teste')
            ->set('legal_reference', 'Lei Municipal nº 3.456/2026')
            ->set('effective_from', '2026-01-01')
            ->set('additional_percentage', 20)
            ->set('allowed_modalities', ['standard', '12x36'])
            ->call('save')
            ->assertHasNoErrors();

        $created = LaborRuleProfile::where('code', 'estatuto-med-emerg')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->isDraft());

        // Aprovação pelo RH
        Livewire::actingAs($this->adminUser)
            ->test('admin.labor-rule-profiles')
            ->call('openApproveModal', $created->id)
            ->call('confirmApproval')
            ->assertHasNoErrors();

        $created->refresh();
        $this->assertTrue($created->isApproved());
        $this->assertEquals($this->adminUser->id, $created->approved_by);

        // Revogação pelo RH
        Livewire::actingAs($this->adminUser)
            ->test('admin.labor-rule-profiles')
            ->call('openRevokeModal', $created->id)
            ->set('revocation_reason', 'Revogado após julgamento de ADI municipal')
            ->call('confirmRevocation')
            ->assertHasNoErrors();

        $created->refresh();
        $this->assertTrue($created->isRevoked());
        $this->assertEquals('Revogado após julgamento de ADI municipal', $created->revocation_reason);
    }

    public function test_approved_profile_immutability_requires_new_version(): void
    {
        $approvedProfile = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Estatuto Imutável v1',
            code: 'estatuto-imutavel',
            legalReference: 'Lei nº 1.234',
            config: ['allowed_modalities' => ['standard', '12x36']]
        );

        // Tentativa de alterar configuração diretamente dispara erro de imutabilidade
        try {
            $approvedProfile->update([
                'configuration' => ['allowed_modalities' => ['standard']],
            ]);
            $this->fail('Deveria ter impedido alteração direta de perfil aprovado');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('não podem ser alterados retroativamente', $e->getMessage());
        }

        // Criar próxima versão funciona corretamente
        $nextVersion = $approvedProfile->createNextVersion([
            'name' => 'Estatuto Imutável v2',
            'configuration' => ['allowed_modalities' => ['standard', '12x36', '24x72']],
        ], $this->adminUser, 'Inclusão da modalidade 24x72');

        $this->assertEquals(2, $nextVersion->version);
        $this->assertTrue($nextVersion->isDraft());
        $this->assertEquals($approvedProfile->id, $nextVersion->parent_profile_id);
    }

    public function test_administrative_schedules_continue_working_without_special_profile(): void
    {
        $employeeUser = User::create([
            'name' => 'Servidor Administrativo Comum',
            'email' => 'admin.comum@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'cpf' => '99999999993',
            'job_title' => 'Assistente Administrativo',
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'normative_entity' => 'Prefeitura de Teste',
            'workload' => '40h',
        ]);

        // Atribuir jornada administrativa 40h regular não exige perfil especial de plantão
        $assignment = app(AssignWorkScheduleAction::class)->execute(
            employee: $employee,
            schedule: $this->scheduleAdmin40h,
            effectiveFrom: '2026-10-01',
            reason: 'Jornada administrativa comum',
            assignedBy: $this->adminUser,
        );

        $this->assertNotNull($assignment);
        $this->assertEquals($this->scheduleAdmin40h->id, $assignment->work_schedule_id);
    }
}
