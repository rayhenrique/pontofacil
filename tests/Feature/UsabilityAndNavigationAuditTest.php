<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsabilityAndNavigationAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Sector $sectorAlpha;

    private Sector $sectorBeta;

    private Employee $emp1;

    private Employee $emp2;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $company = Company::create([
            'legal_name' => 'EMPRESA FISCAL MODELO LTDA',
            'trade_name' => 'Fiscal Modelo',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $establishment = Establishment::create([
            'company_id' => $company->id,
            'name' => 'Sede Central',
            'code' => '001',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'address' => 'Av. Principal, 100',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Diretoria',
            'email' => 'admin@audit.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Admin,
        ]);

        $this->manager = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor@audit.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Manager,
        ]);

        $this->sectorAlpha = Sector::create([
            'name' => 'Operações TI',
            'description' => 'Infraestrutura e Sistemas',
            'manager_id' => $this->manager->id,
            'qr_code_hash' => 'sec-qr-hash-ti',
        ]);

        $this->sectorBeta = Sector::create([
            'name' => 'Recursos Humanos',
            'description' => 'Gestão de Pessoas',
            'manager_id' => null,
        ]);

        $userEmp1 = User::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos.silva@audit.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $this->emp1 = Employee::create([
            'user_id' => $userEmp1->id,
            'sector_id' => $this->sectorAlpha->id,
            'cpf' => '123.456.789-01',
            'registration_number' => 'MAT-001',
            'job_title' => 'Analista Desenvolvedor',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $userEmp2 = User::create([
            'name' => 'Mariana Souza',
            'email' => 'mariana.souza@audit.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $this->emp2 = Employee::create([
            'user_id' => $userEmp2->id,
            'sector_id' => $this->sectorBeta->id,
            'cpf' => '987.654.321-09',
            'registration_number' => 'MAT-002',
            'job_title' => 'Especialista RH',
            'legal_regime' => LegalRegime::FederalStatutory,
        ]);
    }

    public function test_employees_component_filters_by_name_cpf_sector_and_resets(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->assertSee('Carlos Silva')
            ->assertSee('Mariana Souza')
            ->set('search', 'Carlos')
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza')
            // Test search by unformatted CPF
            ->set('search', '98765432109')
            ->assertSee('Mariana Souza')
            ->assertDontSee('Carlos Silva')
            // Test search by job title
            ->set('search', 'Desenvolvedor')
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza')
            // Test sector filter
            ->set('search', '')
            ->set('sectorFilter', (string) $this->sectorAlpha->id)
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza')
            // Test regime filter
            ->set('sectorFilter', '')
            ->set('regimeFilter', LegalRegime::FederalStatutory->value)
            ->assertSee('Mariana Souza')
            ->assertDontSee('Carlos Silva')
            // Test clearFilters
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('sectorFilter', '')
            ->assertSet('regimeFilter', '')
            ->assertSee('Carlos Silva')
            ->assertSee('Mariana Souza');
    }

    public function test_employees_component_manager_cannot_filter_outside_scope(): void
    {
        Livewire::actingAs($this->manager)
            ->test('admin.employees')
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza')
            // Attempt to filter by sectorBeta which is outside manager's scope
            ->set('sectorFilter', (string) $this->sectorBeta->id)
            // Sector filter gets reset to empty because manager does not manage sectorBeta
            ->assertSet('sectorFilter', '')
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza');
    }

    public function test_treatment_requests_component_sober_terminology_and_filter_clearing(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test('admin.treatment-requests')
            ->set('statusFilter', 'approved')
            ->set('typeFilter', 'absence')
            ->call('clearFilters')
            ->assertSet('statusFilter', 'pending')
            ->assertSet('typeFilter', 'all')
            ->assertSet('employeeId', '')
            ->assertSet('sectorId', '');

        $html = $component->html();
        // DESIGN.md §19.1: Forbidden "Poder Total", must be "Coordenação de RH (Gestão Global)"
        $this->assertStringNotContainsString('Poder Total', $html);
        $this->assertStringContainsString('Coordenação de RH (Gestão Global)', $html);
        // DESIGN.md §28.3: No animate-pulse on status counters
        $this->assertStringNotContainsString('animate-pulse', $html);
        // DESIGN.md §3.3: No purple badge decoration
        $this->assertStringNotContainsString('bg-purple-100', $html);
    }

    public function test_receipts_center_dynamic_year_range_and_manager_scoping(): void
    {
        $currentYear = now()->year;
        $oldYear = $currentYear - 5;

        // Admin sees dynamic year range back at least 8 years
        Livewire::actingAs($this->admin)
            ->test('receipts-center')
            ->assertSee((string) $currentYear)
            ->assertSee((string) $oldYear)
            ->set('search', 'Carlos')
            ->call('clearSearch')
            ->assertSet('search', '');

        // Manager only sees subordinates in their managed sector
        Livewire::actingAs($this->manager)
            ->test('receipts-center')
            ->assertSee('Carlos Silva')
            ->assertDontSee('Mariana Souza');
    }

    public function test_folha_ponto_renders_extended_historical_years(): void
    {
        $currentYear = now()->year;
        $pastYear = $currentYear - 6;

        Livewire::actingAs($this->admin)
            ->test('folha-ponto')
            ->assertSee((string) $currentYear)
            ->assertSee((string) $pastYear);
    }

    public function test_monthly_review_filter_clearing(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.monthly-review')
            ->set('search', 'Carlos')
            ->set('statusFilter', 'approved')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('statusFilter', 'all');
    }

    public function test_reports_filter_clearing(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.reports')
            ->set('sectorId', (string) $this->sectorAlpha->id)
            ->set('employeeId', (string) $this->emp1->id)
            ->call('clearFilters')
            ->assertSet('establishmentId', '')
            ->assertSet('sectorId', '')
            ->assertSet('employeeId', '')
            ->assertSet('page', 1);
    }

    public function test_users_search_and_design_system_indigo_badges(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test('admin.users')
            ->assertSee('Admin Diretoria')
            ->assertSee('Gestor Operacional')
            ->set('search', 'Gestor')
            ->assertSee('Gestor Operacional')
            ->assertDontSee('Admin Diretoria')
            ->call('clearSearch')
            ->assertSet('search', '')
            ->assertSee('Admin Diretoria');

        $html = $component->html();
        // Admin badge should use institutional indigo tokens, not purple
        $this->assertStringNotContainsString('bg-purple-100 text-purple-800', $html);
        $this->assertStringContainsString('bg-indigo-50 text-indigo-700', $html);
    }

    public function test_sectors_search_and_indigo_qr_tokens(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test('admin.sectors')
            ->assertSee('Operações TI')
            ->assertSee('Recursos Humanos')
            ->set('search', 'Infraestrutura')
            ->assertSee('Operações TI')
            ->assertDontSee('Recursos Humanos')
            ->call('clearSearch')
            ->assertSet('search', '')
            ->assertSee('Recursos Humanos');

        $html = $component->html();
        // QR button should use institutional indigo tokens, not purple
        $this->assertStringNotContainsString('bg-purple-50 text-purple-700', $html);
        $this->assertStringContainsString('bg-indigo-50 text-indigo-700', $html);
    }
}
