<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_role_enum_and_helpers(): void
    {
        $this->assertEquals('manager', UserRole::Manager->value);
        $this->assertEquals('Gestor', UserRole::Manager->label());

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isAdmin());
        $this->assertFalse($manager->isEmployee());
    }

    public function test_manager_can_access_employees_page_but_not_other_admin_pages(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get('/admin/employees')->assertOk();
        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($manager)->get('/admin/sectors')->assertForbidden();
        $this->actingAs($manager)->get('/admin/adjustment')->assertForbidden();
        $this->actingAs($manager)->get('/admin/audit')->assertForbidden();
        $this->actingAs($manager)->get('/admin/reports')->assertForbidden();
    }

    public function test_employee_cannot_access_employees_page(): void
    {
        $employeeUser = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($employeeUser)->get('/admin/employees')->assertForbidden();
    }

    public function test_manager_can_only_register_employees_in_managed_sector(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);

        $mySector = Sector::create([
            'name' => 'Setor Gestão Própria',
            'manager_id' => $manager->id,
        ]);

        $otherSector = Sector::create([
            'name' => 'Setor de Outro Gestor',
            'manager_id' => $otherManager->id,
        ]);

        // Attempt to register in other sector should fail validation
        Livewire::actingAs($manager)
            ->test('admin.employees')
            ->set('name', 'Novo Colaborador')
            ->set('email', 'colab1@empresa.local')
            ->set('cpf', '11122233344')
            ->set('phone', '11999999999')
            ->set('sector_id', $otherSector->id)
            ->call('save')
            ->assertHasErrors(['sector_id']);

        $this->assertDatabaseMissing('users', ['email' => 'colab1@empresa.local']);

        // Register in managed sector should succeed
        Livewire::actingAs($manager)
            ->test('admin.employees')
            ->set('name', 'Novo Colaborador')
            ->set('email', 'colab1@empresa.local')
            ->set('cpf', '11122233344')
            ->set('phone', '11999999999')
            ->set('sector_id', $mySector->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'colab1@empresa.local',
            'role' => UserRole::Employee->value,
        ]);
        $this->assertDatabaseHas('employees', [
            'cpf' => '11122233344',
            'sector_id' => $mySector->id,
        ]);
    }

    public function test_manager_cannot_delete_employee_from_another_sector(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);

        $mySector = Sector::create([
            'name' => 'Meu Setor',
            'manager_id' => $manager->id,
        ]);

        $otherSector = Sector::create([
            'name' => 'Outro Setor',
            'manager_id' => $otherManager->id,
        ]);

        $otherUser = User::factory()->create(['role' => UserRole::Employee]);
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'sector_id' => $otherSector->id,
            'cpf' => '99988877766',
        ]);

        // Attempting to delete employee from other sector results in 403
        Livewire::actingAs($manager)
            ->test('admin.employees')
            ->call('delete', $otherEmployee->id)
            ->assertForbidden();

        $this->assertDatabaseHas('employees', ['id' => $otherEmployee->id]);
    }
}
