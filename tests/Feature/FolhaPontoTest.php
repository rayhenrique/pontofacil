<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FolhaPontoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_employee_can_access_folha_ponto(): void
    {
        $user = User::create([
            'name' => 'Maria Enfermagem',
            'email' => 'maria@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $sector = Sector::create(['name' => 'Posto Central']);
        Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'cpf' => '123.456.789-00',
        ]);

        $response = $this->actingAs($user)->get(route('folha-ponto'));
        $response->assertOk();
        $response->assertSee('FOLHA DE PONTO DE FUNCIONÁRIO');
        $response->assertSee('PREFEITURA MUNICIPAL DE TEOTÔNIO VILELA');
        $response->assertSee('SECRETARIA MUNICIPAL DE SAÚDE');
        $response->assertSee('MARIA ENFERMAGEM');
    }

    public function test_folha_ponto_renders_official_prefeitura_header_and_signatures(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_folha@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        Livewire::actingAs($admin)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026])
            ->assertSee('Competência/OUTUBRO- Ano:')
            ->assertSee('Horário Matutino')
            ->assertSee('Horário Vespertino')
            ->assertSee('SERVIDOR (a)')
            ->assertSee('Coordenador (a)')
            ->assertSee('RESPONSÁVEL PELO SETOR')
            ->assertSee('Setor de Recursos Humanos')
            ->assertSee('SÁBADO')
            ->assertSee('DOMINGO');
    }

    public function test_folha_ponto_blank_mode_displays_manual_time_placeholders(): void
    {
        $user = User::create([
            'name' => 'Carlos Servidor',
            'email' => 'carlos_servidor@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Livewire::actingAs($user)
            ->test('folha-ponto', ['month' => 10, 'year' => 2026, 'fillMode' => 'blank'])
            ->assertSet('fillMode', 'blank')
            ->assertSee(': ');
    }
}
