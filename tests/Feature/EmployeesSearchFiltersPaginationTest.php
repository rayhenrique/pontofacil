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
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeesSearchFiltersPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $unassignedManager;

    private Sector $sectorTech;

    private Sector $sectorHr;

    private WorkSchedule $scheduleStandard;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $company = Company::create([
            'legal_name' => 'EMPRESA TECNOLOGIA E SERVICOS S.A.',
            'trade_name' => 'Tech Services',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $establishment = Establishment::create([
            'company_id' => $company->id,
            'name' => 'Sede Principal',
            'code' => '001',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'address' => 'Rua da Inovacao, 500',
        ]);

        $this->scheduleStandard = WorkSchedule::create([
            'company_id' => $company->id,
            'establishment_id' => $establishment->id,
            'name' => 'Comercial 40h',
            'active' => true,
            'daily_minutes' => 480,
            'type' => 'weekly_standard',
        ]);

        $this->admin = User::create([
            'name' => 'Diretor Administrativo',
            'email' => 'admin.diretoria@empresa.local',
            'password' => bcrypt('password123'),
            'role' => UserRole::Admin,
        ]);

        $this->manager = User::create([
            'name' => 'Gestor Tecnologia',
            'email' => 'gestor.tech@empresa.local',
            'password' => bcrypt('password123'),
            'role' => UserRole::Manager,
        ]);

        $this->unassignedManager = User::create([
            'name' => 'Gestor Sem Setor',
            'email' => 'gestor.semsetor@empresa.local',
            'password' => bcrypt('password123'),
            'role' => UserRole::Manager,
        ]);

        $this->sectorTech = Sector::create([
            'name' => 'Desenvolvimento de Sistemas',
            'description' => 'TI e Software',
            'manager_id' => $this->manager->id,
        ]);

        $this->sectorHr = Sector::create([
            'name' => 'Recursos Humanos',
            'description' => 'Gestao de Talentos',
            'manager_id' => null,
        ]);
    }

    private function createEmployee(string $name, string $email, Sector $sector, string $cpf = '12345678901', string $jobTitle = 'Analista'): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => bcrypt('password123'),
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'cpf' => $cpf,
            'registration_number' => 'MAT-'.fake()->unique()->numerify('#####'),
            'job_title' => $jobTitle,
            'contract_type' => 'Efetivo',
            'legal_regime' => LegalRegime::CLT,
            'work_schedule_id' => $this->scheduleStandard->id,
        ]);
    }

    public function test_admin_can_search_by_partial_name_case_insensitively(): void
    {
        $this->createEmployee('Ana Beatriz Castro', 'ana.castro@empresa.local', $this->sectorTech);
        $this->createEmployee('Bruno Henrique Lima', 'bruno.lima@empresa.local', $this->sectorHr);

        // Lowercase partial search
        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'beatriz')
            ->assertSee('Ana Beatriz Castro')
            ->assertDontSee('Bruno Henrique Lima');

        // Uppercase partial search
        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'BRUNO')
            ->assertSee('Bruno Henrique Lima')
            ->assertDontSee('Ana Beatriz Castro');
    }

    public function test_admin_can_search_by_partial_email_case_insensitively(): void
    {
        $this->createEmployee('Carlos Eduardo', 'carlos.eduardo@empresa.local', $this->sectorTech);
        $this->createEmployee('Daniela Martins', 'daniela.martins@empresa.local', $this->sectorHr);

        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'carlos.eduardo')
            ->assertSee('Carlos Eduardo')
            ->assertDontSee('Daniela Martins')
            ->set('search', 'DANIELA.MARTINS@')
            ->assertSee('Daniela Martins')
            ->assertDontSee('Carlos Eduardo');
    }

    public function test_admin_can_filter_by_sector(): void
    {
        $this->createEmployee('Eduardo Silva', 'eduardo@empresa.local', $this->sectorTech);
        $this->createEmployee('Fernanda Rocha', 'fernanda@empresa.local', $this->sectorHr);

        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('sectorFilter', (string) $this->sectorTech->id)
            ->assertSee('Eduardo Silva')
            ->assertDontSee('Fernanda Rocha')
            ->set('sectorFilter', (string) $this->sectorHr->id)
            ->assertSee('Fernanda Rocha')
            ->assertDontSee('Eduardo Silva');
    }

    public function test_admin_can_combine_search_and_sector_filters(): void
    {
        $this->createEmployee('Gustavo Pereira', 'gustavo.pereira@empresa.local', $this->sectorTech);
        $this->createEmployee('Gustavo Nogueira', 'gustavo.nogueira@empresa.local', $this->sectorHr);

        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'Gustavo')
            ->set('sectorFilter', (string) $this->sectorTech->id)
            ->assertSee('Gustavo Pereira')
            ->assertDontSee('Gustavo Nogueira')
            // Wrong sector for Pereira
            ->set('search', 'Pereira')
            ->set('sectorFilter', (string) $this->sectorHr->id)
            ->assertDontSee('Gustavo Pereira')
            ->assertSee('Nenhum colaborador encontrado com os filtros aplicados.');
    }

    public function test_pagination_splits_employees_into_15_per_page_and_resets_on_search(): void
    {
        // Create 22 employees in sectorTech
        for ($i = 1; $i <= 22; $i++) {
            $num = sprintf('%02d', $i);
            $this->createEmployee("Colaborador {$num}", "colab{$num}@empresa.local", $this->sectorTech, "111222333{$num}");
        }

        $component = Livewire::actingAs($this->admin)
            ->test('admin.employees');

        // Total count should show 22
        $component->assertSee('Total cadastrado: <strong>22</strong> colaborador(es)', false);

        // Page 1 should contain Colaborador 22 down to 08 (ordered by id desc)
        $component->assertSee('Colaborador 22')
            ->assertSee('Colaborador 08')
            ->assertDontSee('Colaborador 07');

        // Move to Page 2
        $component->call('gotoPage', 2);
        $component->assertSee('Colaborador 07')
            ->assertSee('Colaborador 01')
            ->assertDontSee('Colaborador 22');

        // Typing search resets page to 1
        $component->set('search', 'Colaborador 22');
        $this->assertSame(1, $component->get('paginators')['page'] ?? 1);
        $component->assertSee('Colaborador 22')
            ->assertDontSee('Colaborador 01');
    }

    public function test_clear_filters_resets_search_sector_and_page(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $num = sprintf('%02d', $i);
            $this->createEmployee("Pessoa {$num}", "pessoa{$num}@empresa.local", $this->sectorTech, "999888777{$num}");
        }

        $component = Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->call('gotoPage', 2)
            ->set('search', 'Pessoa')
            ->set('sectorFilter', (string) $this->sectorTech->id)
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('sectorFilter', '')
            ->assertSet('regimeFilter', '');

        $this->assertSame(1, $component->get('paginators')['page'] ?? 1);
    }

    public function test_manager_scoping_cannot_see_or_filter_employees_outside_managed_sectors(): void
    {
        $this->createEmployee('Marcos Tech', 'marcos.tech@empresa.local', $this->sectorTech, '12345678901');
        $this->createEmployee('Helena RH', 'helena.rh@empresa.local', $this->sectorHr, '98765432109');

        $component = Livewire::actingAs($this->manager)
            ->test('admin.employees');

        // Manager only sees Marcos Tech
        $component->assertSee('Marcos Tech')
            ->assertDontSee('Helena RH');

        // Attempting to search for Helena RH yields no results for manager
        $component->set('search', 'Helena')
            ->assertDontSee('Helena RH')
            ->assertSee('Nenhum colaborador encontrado com os filtros aplicados.');

        // Attempting to set sectorFilter to sectorHr is rejected and reset
        $component->set('search', '')
            ->set('sectorFilter', (string) $this->sectorHr->id)
            ->assertSet('sectorFilter', '')
            ->assertSee('Marcos Tech')
            ->assertDontSee('Helena RH');
    }

    public function test_manager_without_any_managed_sectors_shows_management_warning(): void
    {
        $this->createEmployee('Funcionario Qualquer', 'qualquer@empresa.local', $this->sectorTech);

        Livewire::actingAs($this->unassignedManager)
            ->test('admin.employees')
            ->assertSee('Aviso de Gestão')
            ->assertSee('você ainda não foi designado como responsável por nenhum setor')
            ->assertDontSee('Funcionario Qualquer');
    }

    public function test_empty_state_when_filters_yield_no_results(): void
    {
        $this->createEmployee('Tatiana Ramos', 'tatiana@empresa.local', $this->sectorTech);

        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'NomeInexistenteXYZ')
            ->assertSee('Nenhum colaborador encontrado com os filtros aplicados.')
            ->assertSee('Limpar filtros');
    }

    public function test_empty_state_when_no_employees_exist(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->assertSee('Nenhum funcionário cadastrado.')
            ->assertSee('Clique aqui para adicionar o primeiro.');
    }

    public function test_cpf_is_discreetly_masked_in_listing_and_complete_in_edit_modal(): void
    {
        $employee = $this->createEmployee('Patricia Alencar', 'patricia@empresa.local', $this->sectorTech, '12345678901');

        $component = Livewire::actingAs($this->admin)
            ->test('admin.employees');

        // Listing masks CPF middle digits (LGPD privacy): 123.***.***-01
        $component->assertSee('123.***.***-01');
        $component->assertDontSee('123.456.789-01');

        // Opening edit modal loads the full unmasked CPF for authorized edition
        $component->call('openEditModal', $employee->id)
            ->assertSet('cpf', '123.456.789-01')
            ->assertSet('name', 'Patricia Alencar')
            ->assertSet('email', 'patricia@empresa.local');
    }

    public function test_crud_operations_preserve_filters_and_update_records(): void
    {
        $employee = $this->createEmployee('Roberto Antunes', 'roberto@empresa.local', $this->sectorTech, '55566677788');

        $component = Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->set('search', 'Roberto')
            ->set('sectorFilter', (string) $this->sectorTech->id);

        // Open edit modal and update
        $component->call('openEditModal', $employee->id)
            ->set('job_title', 'Arquiteto de Software')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        // Verify database updated
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'job_title' => 'Arquiteto de Software',
        ]);

        // Filters were preserved!
        $component->assertSet('search', 'Roberto')
            ->assertSet('sectorFilter', (string) $this->sectorTech->id)
            ->assertSee('Roberto Antunes');
    }

    public function test_work_schedule_relation_loaded_avoids_n_plus_one_queries(): void
    {
        // Create 15 employees
        for ($i = 1; $i <= 15; $i++) {
            $num = sprintf('%02d', $i);
            $this->createEmployee("Funcionario {$num}", "func{$num}@empresa.local", $this->sectorTech, "444555666{$num}");
        }

        // Render the component and count queries
        DB::enableQueryLog();

        Livewire::actingAs($this->admin)
            ->test('admin.employees')
            ->assertSee('Funcionario 15')
            ->assertSee('Comercial 40h');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // When relations are eager loaded, rendering 15 rows with getWorkScheduleForDate
        // must execute only a constant bounded number of queries (pagination count + 1 employee select with relations + sectors + schedules)
        // rather than 15+ individual N+1 schedule queries.
        $this->assertLessThan(15, count($queries), 'Query count must remain small and avoid N+1 per row');
    }
}
