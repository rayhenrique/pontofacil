<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisioningSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_in_local_or_testing_creates_default_fixture_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@pontofacil.local',
            'role' => UserRole::Admin->value,
        ]);

        $admin = User::where('email', 'admin@pontofacil.local')->firstOrFail();
        $this->assertTrue(Hash::check('admin123', $admin->password));

        // Re-execução do seeder é estritamente idempotente (não duplica nem altera senha)
        $this->seed(DatabaseSeeder::class);
        $this->assertEquals(1, User::where('email', 'admin@pontofacil.local')->count());
    }

    public function test_seeder_in_production_does_not_create_insecure_default_admin(): void
    {
        // Simula ambiente de produção sem credenciais explícitas via ENV
        $this->app->detectEnvironment(fn () => 'production');
        putenv('INITIAL_ADMIN_EMAIL=');
        putenv('INITIAL_ADMIN_PASSWORD=');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        // Garante que o administrador padrão com senha conhecida NÃO é criado em produção
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@pontofacil.local',
        ]);
    }

    public function test_seeder_in_production_accepts_secure_env_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        putenv('INITIAL_ADMIN_EMAIL=prod.admin@empresa.com.br');
        putenv('INITIAL_ADMIN_PASSWORD=SenhaSegura@2026!Complexa');
        putenv('INITIAL_ADMIN_NAME=Admin Corporativo');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'prod.admin@empresa.com.br',
            'name' => 'Admin Corporativo',
            'role' => UserRole::Admin->value,
        ]);

        $admin = User::where('email', 'prod.admin@empresa.com.br')->firstOrFail();
        $this->assertTrue(Hash::check('SenhaSegura@2026!Complexa', $admin->password));

        // Limpa ENV
        putenv('INITIAL_ADMIN_EMAIL=');
        putenv('INITIAL_ADMIN_PASSWORD=');
        putenv('INITIAL_ADMIN_NAME=');
    }

    public function test_seeder_in_production_rejects_weak_initial_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        putenv('INITIAL_ADMIN_EMAIL=prod.admin@empresa.com.br');
        putenv('INITIAL_ADMIN_PASSWORD=curta');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('deve conter no mínimo 12 caracteres');

        try {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        } finally {
            putenv('INITIAL_ADMIN_EMAIL=');
            putenv('INITIAL_ADMIN_PASSWORD=');
        }
    }

    public function test_create_admin_command_creates_admin_safely(): void
    {
        $this->artisan('ponto:admin:create', [
            '--email' => 'rh.diretoria@empresa.gov.br',
            '--name' => 'Diretoria de Recursos Humanos',
            '--password' => 'ForteSegura#2026',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'rh.diretoria@empresa.gov.br',
            'name' => 'Diretoria de Recursos Humanos',
            'role' => UserRole::Admin->value,
        ]);

        $admin = User::where('email', 'rh.diretoria@empresa.gov.br')->firstOrFail();
        $this->assertTrue(Hash::check('ForteSegura#2026', $admin->password));
    }

    public function test_create_admin_command_refuses_duplicate_email(): void
    {
        User::create([
            'name' => 'Admin Existente',
            'email' => 'existente@empresa.gov.br',
            'password' => 'secret12345',
            'role' => UserRole::Admin,
        ]);

        $this->artisan('ponto:admin:create', [
            '--email' => 'existente@empresa.gov.br',
            '--name' => 'Tentativa Duplicada',
            '--password' => 'OutraSenha12345',
        ])->assertFailed();

        $this->assertEquals(1, User::where('email', 'existente@empresa.gov.br')->count());
    }

    public function test_system_settings_are_seeded_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $countInitial = SystemSetting::count();

        $this->seed(DatabaseSeeder::class);
        $this->assertEquals($countInitial, SystemSetting::count());
    }
}
