<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsAndLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
        Storage::fake('public');
    }

    public function test_admin_settings_page_renders_with_configuracoes_title_and_company_card(): void
    {
        $admin = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin_config@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings'));
        $response->assertOk();
        $response->assertSee('Configurações');
        $response->assertSee('Dados da Empresa');
        $response->assertSee('Logotipo Oficial');
        $response->assertSee('Logotipo da Organização');
        $response->assertSee('QR Code Oficial da Empresa');
        $response->assertSee('Geolocalização');
        $response->assertSee('Banco de Horas');
    }

    public function test_admin_can_update_company_data_and_upload_logo(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_rh_logo@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $logoFile = UploadedFile::fake()->image('empresa_logo.png', 300, 120);

        Livewire::actingAs($admin)
            ->test('admin.settings')
            ->set('company_legal_name', 'Prefeitura de Teste LTDA')
            ->set('company_trade_name', 'Prefeitura de Teste')
            ->set('company_cnpj', '12.345.678/0001-99')
            ->set('company_phone', '(82) 99999-0000')
            ->set('company_email', 'contato@teste.gov.br')
            ->set('company_address', 'Praça Central, 50')
            ->set('company_city', 'Maceió')
            ->set('company_state', 'AL')
            ->set('company_postal_code', '57000-001')
            ->set('company_header_entity', 'PREFEITURA MUNICIPAL DE TESTE')
            ->set('company_header_sub_entity', 'SECRETARIA DE ADMINISTRAÇÃO')
            ->set('company_header_state', 'ESTADO DE ALAGOAS')
            ->set('logo', $logoFile)
            ->call('saveCompany')
            ->assertDispatched('app-modal-alert');

        $company = CurrentCompany::get();
        $this->assertEquals('Prefeitura de Teste LTDA', $company->legal_name);
        $this->assertEquals('Prefeitura de Teste', $company->trade_name);
        $this->assertEquals('12345678000199', $company->cnpj);
        $this->assertEquals('PREFEITURA MUNICIPAL DE TESTE', $company->header_entity);
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_folha_ponto_renders_custom_company_logo_when_configured(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_folha_logo@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $company = CurrentCompany::get();
        $company->update([
            'legal_name' => 'Hospital Municipal LTDA',
            'header_entity' => 'HOSPITAL MUNICIPAL DE TESTE',
            'header_sub_entity' => 'DIRETORIA CLÍNICA',
            'logo_path' => 'company/custom_logo.png',
        ]);
        Storage::disk('public')->put('company/custom_logo.png', 'fake_image_content');

        CurrentCompany::clear();

        $response = $this->actingAs($admin)->get(route('folha-ponto'));
        $response->assertOk();
        $response->assertSee('company/custom_logo.png');
        $response->assertSee('HOSPITAL MUNICIPAL DE TESTE');
        $response->assertSee('DIRETORIA CLÍNICA');
    }

    public function test_admin_can_remove_custom_logo(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_remove_logo@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $company = CurrentCompany::get();
        $company->update([
            'logo_path' => 'company/to_delete.png',
        ]);
        Storage::disk('public')->put('company/to_delete.png', 'content');

        CurrentCompany::clear();

        Livewire::actingAs($admin)
            ->test('admin.settings')
            ->call('removeLogo')
            ->assertDispatched('app-modal-alert');

        $company->refresh();
        $this->assertNull($company->logo_path);
        Storage::disk('public')->assertMissing('company/to_delete.png');
    }

    public function test_logo_url_returns_null_when_file_does_not_exist_on_disk(): void
    {
        $company = CurrentCompany::get();
        $company->update(['logo_path' => 'company/ghost_file.png']);

        // O arquivo NÃO existe no disco public fake
        $this->assertNull($company->logo_url, 'Deve retornar null se o arquivo físico não existir no storage');
    }

    public function test_company_logo_endpoint_serves_logo(): void
    {
        $company = CurrentCompany::get();
        $company->update(['logo_path' => 'company/test_asset.png']);
        Storage::disk('public')->put('company/test_asset.png', 'test_image_bytes');

        $response = $this->get(route('company.logo'));
        $response->assertOk();
    }
}
