<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_four_punches_calculates_eight_hours_correctly(): void
    {
        $user = User::create([
            'name' => 'Colaborador Jornada Padrão',
            'email' => 'jornada@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $date = Carbon::create(2026, 10, 1, 0, 0, 0, 'America/Maceio');

        // 1ª Entrada: 08:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(8, 0, 0),
            'type' => 'in',
        ]);

        // Saída Almoço: 12:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(12, 0, 0),
            'type' => 'out',
        ]);

        // Volta Almoço: 13:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(13, 0, 0),
            'type' => 'in',
        ]);

        // Saída Fim Expediente: 17:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(17, 0, 0),
            'type' => 'out',
        ]);

        $component = Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10);

        $component->assertSee('08h 00m trabalhadas');
        $component->assertSee('Total Trabalhado');
        $component->assertSee('08h 00m');
    }

    public function test_open_interval_marks_as_in_progress(): void
    {
        $date = Carbon::create(2026, 10, 1, 0, 0, 0, 'America/Maceio');
        Carbon::setTestNow($date->copy()->setTime(14, 0, 0));

        $user = User::create([
            'name' => 'Colaborador Em Andamento',
            'email' => 'andamento@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        // Entrada: 08:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(8, 0, 0),
            'type' => 'in',
        ]);

        // Saída Almoço: 12:00
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(12, 0, 0),
            'type' => 'out',
        ]);

        // Volta Almoço: 13:00 (sem saída final ainda)
        TimeEntry::create([
            'user_id' => $user->id,
            'timestamp' => $date->copy()->setTime(13, 0, 0),
            'type' => 'in',
        ]);

        try {
            $component = Livewire::actingAs($user)
                ->test('timesheet')
                ->set('year', 2026)
                ->set('month', 10);

            $component->assertSee('04h 00m (em andamento)');
        } finally {
            Carbon::setTestNow();
        }
    }
}
