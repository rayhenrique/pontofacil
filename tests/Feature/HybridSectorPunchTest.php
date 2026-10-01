<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HybridSectorPunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'COMPANY_GLOBAL_QR_12345']);
        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => '-9.665800']);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => '-35.735000']);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => '100']);
    }

    public function test_employee_punches_successfully_with_company_fallback_when_sector_has_no_custom_settings(): void
    {
        $sector = Sector::create([
            'name' => 'Recursos Humanos',
            'qr_code_hash' => null,
            'latitude' => null,
            'longitude' => null,
            'allowed_radius_meters' => null,
        ]);

        $user = User::create([
            'name' => 'João RH',
            'email' => 'joao@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'cpf' => '111.222.333-44',
            'job_title' => 'Analista RH',
            'admission_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'COMPANY_GLOBAL_QR_12345', -9.665800, -35.735000)
            ->assertSet('status', 'success');

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'type' => 'in',
        ]);
    }

    public function test_employee_in_custom_qr_sector_cannot_use_company_global_qr_code(): void
    {
        $customSector = Sector::create([
            'name' => 'Tecnologia da Informação',
            'qr_code_hash' => 'TI_EXCLUSIVE_QR_ABCDEF',
            'latitude' => null,
            'longitude' => null,
            'allowed_radius_meters' => null,
        ]);

        $user = User::create([
            'name' => 'Dev TI',
            'email' => 'dev@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'sector_id' => $customSector->id,
            'cpf' => '555.666.777-88',
            'job_title' => 'Desenvolvedor',
            'admission_date' => now()->toDateString(),
        ]);

        // Tentativa usando o QR Code global da empresa deve falhar
        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'COMPANY_GLOBAL_QR_12345', -9.665800, -35.735000)
            ->assertSet('status', 'error')
            ->assertSee('QR Code inválido ou não autorizado para o seu setor');

        $this->assertEquals(0, TimeEntry::where('user_id', $user->id)->count());

        // Usando o QR Code exclusivo do setor deve ter sucesso
        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'TI_EXCLUSIVE_QR_ABCDEF', -9.665800, -35.735000)
            ->assertSet('status', 'success');

        $this->assertEquals(1, TimeEntry::where('user_id', $user->id)->count());
    }

    public function test_employee_in_custom_location_sector_is_validated_against_sector_gps(): void
    {
        // Filial com coordenadas próprias distantes da Matriz
        $filialSector = Sector::create([
            'name' => 'Filial Ponta Verde',
            'qr_code_hash' => null, // Fallback para QR global
            'latitude' => -9.645000,
            'longitude' => -35.705000,
            'allowed_radius_meters' => 50,
        ]);

        $user = User::create([
            'name' => 'Atendente Filial',
            'email' => 'atendente@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'sector_id' => $filialSector->id,
            'cpf' => '999.888.777-66',
            'job_title' => 'Atendente',
            'admission_date' => now()->toDateString(),
        ]);

        // Tentativa de bater o ponto na Matriz (-9.665800, -35.735000) estando alocado na Filial Ponta Verde
        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'COMPANY_GLOBAL_QR_12345', -9.665800, -35.735000)
            ->assertSet('status', 'error')
            ->assertSee('Você está fora do raio permitido para bater o ponto do seu setor (Filial Ponta Verde)');

        $this->assertEquals(0, TimeEntry::where('user_id', $user->id)->count());

        // Batendo o ponto no local correto da filial
        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'COMPANY_GLOBAL_QR_12345', -9.645000, -35.705000)
            ->assertSet('status', 'success');

        $this->assertEquals(1, TimeEntry::where('user_id', $user->id)->count());
    }

    public function test_admin_can_manage_hybrid_sector_settings_in_admin_component(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_sectors@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        Livewire::actingAs($admin)
            ->test('admin.sectors')
            ->call('openCreateModal')
            ->set('name', 'Almoxarifado Norte')
            ->set('latitude', '-9.610000')
            ->set('longitude', '-35.720000')
            ->set('allowed_radius_meters', '150')
            ->call('generateSectorQrCode')
            ->call('save')
            ->assertHasNoErrors();

        $sector = Sector::where('name', 'Almoxarifado Norte')->first();
        $this->assertNotNull($sector);
        $this->assertEquals(-9.610000, (float) $sector->latitude);
        $this->assertEquals(-35.720000, (float) $sector->longitude);
        $this->assertEquals(150, $sector->allowed_radius_meters);
        $this->assertNotEmpty($sector->qr_code_hash);
        $this->assertTrue($sector->hasCustomLocation());
        $this->assertTrue($sector->hasCustomQrCode());
    }
}
