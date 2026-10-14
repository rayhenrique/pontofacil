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
use Tests\TestCase;

class SettingsSubmenuPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $auditor;

    private User $employeeUser;

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
            'name' => 'Administrador Geral',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Admin,
        ]);

        $this->manager = User::create([
            'name' => 'Gestor do Setor',
            'email' => 'gestor@test.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Manager,
        ]);

        $this->auditor = User::create([
            'name' => 'Auditor Fiscal MTE',
            'email' => 'auditor@test.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Auditor,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Servidor Operacional',
            'email' => 'servidor@test.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        $sector = Sector::create([
            'name' => 'Setor Geral',
            'description' => 'Geral',
            'manager_id' => $this->manager->id,
        ]);

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'sector_id' => $sector->id,
            'cpf' => '111.222.333-44',
            'registration_number' => 'MAT-100',
            'job_title' => 'Assistente',
            'legal_regime' => LegalRegime::CLT,
        ]);
    }

    public function test_admin_opening_each_child_route_directly_initializes_submenu_open_and_active(): void
    {
        $childRoutes = [
            'admin.sectors',
            'admin.calendar',
            'admin.time-bank',
            'admin.settlement-policies',
            'admin.adjustment',
            'admin.fiscalizacao',
            'admin.audit',
            'admin.users',
            'admin.settings',
        ];

        foreach ($childRoutes as $routeName) {
            $response = $this->actingAs($this->admin)->get(route($routeName));

            $response->assertOk();
            $content = $response->getContent();

            // 1. Precedence: Submenu is initialized open in Alpine x-data
            $this->assertStringContainsString('advancedOpen: true', $content, "Route {$routeName} should initialize advancedOpen as true");

            // 2. Accessibility: aria-expanded is server-rendered as true
            $this->assertStringContainsString('id="advanced-menu-desktop-btn"', $content);
            $this->assertStringContainsString('id="advanced-menu-mobile-btn"', $content);
            $this->assertStringContainsString('aria-controls="advanced-menu-desktop"', $content);
            $this->assertStringContainsString('aria-controls="advanced-menu-mobile"', $content);
            $this->assertStringContainsString('aria-expanded="true"', $content);

            // 3. Accessibility: containers have ids matching aria-controls and x-cloak
            $this->assertStringContainsString('id="advanced-menu-desktop"', $content);
            $this->assertStringContainsString('id="advanced-menu-mobile"', $content);

            // 4. Visual indicator: subtle active indicator dot rendered for the group
            $this->assertStringContainsString('bg-indigo-400 shrink-0', $content);
            $this->assertStringContainsString('bg-indigo-950/40', $content);

            // 5. Active item highlighting: the target route link has active classes
            $expectedTargetRoute = $routeName === 'admin.settlement-policies' ? 'admin.time-bank' : $routeName;
            $expectedHref = route($expectedTargetRoute);
            $this->assertMatchesRegularExpression(
                '/<a\s+href="'.preg_quote($expectedHref, '/').'"[^>]*class="[^"]*bg-indigo-800 text-white font-semibold[^"]*"/',
                $content,
                "Route {$routeName} must have its link styled with active classes"
            );
        }
    }

    public function test_admin_on_non_advanced_route_uses_persisted_fallback_with_collapsed_default(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.employees'));

        $response->assertOk();
        $content = $response->getContent();

        // On non-advanced routes, state falls back to localStorage preference
        $this->assertStringContainsString("advancedOpen: localStorage.getItem('pf_advanced_menu_open') === 'true'", $content);

        // aria-expanded initializes to false by default on server
        $this->assertStringContainsString('id="advanced-menu-desktop-btn"', $content);
        $this->assertStringContainsString('aria-expanded="false"', $content);

        // Active indicator dot should NOT be present when outside the group
        $this->assertStringNotContainsString('bg-indigo-400 shrink-0', $content);
    }

    public function test_manager_cannot_see_settings_and_support_submenu(): void
    {
        $response = $this->actingAs($this->manager)->get(route('admin.employees'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('Configurações & Apoio', $content);
        $this->assertStringNotContainsString('id="advanced-menu-desktop"', $content);
        $this->assertStringNotContainsString('id="advanced-menu-mobile"', $content);
    }

    public function test_auditor_sees_dedicated_fiscalizacao_section_without_settings_submenu(): void
    {
        $response = $this->actingAs($this->auditor)->get(route('admin.fiscalizacao'));

        $response->assertOk();
        $content = $response->getContent();

        // Auditor has their own top-level Fiscalização section
        $this->assertStringContainsString('Fiscalização Trabalhista', $content);
        // Auditor must NOT see the Admin Configurações & Apoio submenu
        $this->assertStringNotContainsString('Configurações & Apoio', $content);
        $this->assertStringNotContainsString('id="advanced-menu-desktop"', $content);
    }

    public function test_regular_employee_cannot_see_settings_submenu(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('home'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('Configurações & Apoio', $content);
        $this->assertStringNotContainsString('id="advanced-menu-desktop"', $content);
    }

    public function test_app_layout_contains_x_cloak_style_prevention(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.employees'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('[x-cloak]', $content);
        $this->assertStringContainsString('display: none !important;', $content);
    }
}
