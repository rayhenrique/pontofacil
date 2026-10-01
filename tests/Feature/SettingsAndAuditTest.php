<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_admin_relationship_works(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_rh@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employeeUser = User::create([
            'name' => 'Funcionario Teste',
            'email' => 'func@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $timeEntry = TimeEntry::create([
            'user_id' => $employeeUser->id,
            'timestamp' => now(),
            'type' => 'in',
            'is_manual' => true,
        ]);

        $adjustment = TimeAdjustment::create([
            'time_entry_id' => $timeEntry->id,
            'adjusted_by' => $admin->id,
            'new_timestamp' => now(),
            'justification' => 'Ajuste de teste',
        ]);

        $loaded = TimeAdjustment::with(['timeEntry.user', 'admin'])->find($adjustment->id);

        $this->assertNotNull($loaded->admin);
        $this->assertEquals('Admin RH', $loaded->admin->name);
        $this->assertEquals('Funcionario Teste', $loaded->timeEntry->user->name);
    }

    public function test_only_admin_can_access_admin_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_settings@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager_settings@test.com',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $employee = User::create([
            'name' => 'Employee User',
            'email' => 'emp_settings@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->actingAs($employee)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
    }
}
