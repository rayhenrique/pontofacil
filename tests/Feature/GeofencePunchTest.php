<?php

namespace Tests\Feature;

use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class GeofencePunchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'TEST_QR_CODE_123']);
        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => '-9.665800']);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => '-35.735000']);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => '100']);

        $this->sector = Sector::create([
            'name' => 'Operações',
            'latitude' => -9.665800,
            'longitude' => -35.735000,
            'allowed_radius_meters' => 100,
        ]);

        $this->user = User::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '123.456.789-00',
            'job_title' => 'Operador',
            'admission_date' => now()->toDateString(),
        ]);
    }

    public function test_punch_inside_geofence_records_valid_location(): void
    {
        // Ponto dentro do raio de 100m da empresa (mesmas coordenadas)
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_CODE_123', -9.665800, -35.735000, 10.0)
            ->assertSet('status', 'success')
            ->assertSee('Ponto registrado com sucesso!');

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);
        $this->assertTrue((bool) $punch->location_valid);
        $this->assertEquals(-9.665800, (float) $punch->latitude);
        $this->assertEquals(-35.735000, (float) $punch->longitude);
        $this->assertEquals(10.0, (float) $punch->location_accuracy);
        $this->assertEquals(0.0, (float) $punch->location_distance_meters);

        // Verifica que o TimeEntry foi projetado
        $entry = TimeEntry::where('user_id', $this->user->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('in', $entry->type);

        // Verifica que foi registrado no ledger central ARP
        $arp = ArpEvent::where('user_id', $this->user->id)
            ->where('event_type', ArpEventType::Punch)
            ->first();
        $this->assertNotNull($arp);
        $this->assertSame($punch->nsr, $arp->nsr);
    }

    public function test_punch_outside_geofence_is_no_t_blocked_and_records_location_invalid_with_distance(): void
    {
        // Coordenadas distantes (~5km de distância)
        $farLatitude = -9.620000;
        $farLongitude = -35.710000;

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_CODE_123', $farLatitude, $farLongitude, 15.5)
            ->assertSet('status', 'warning')
            ->assertSee('Fora do raio permitido');

        // Ponto DEVE ser registrado normalmente (não pode ser bloqueado)
        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch, 'A batida deve ser registrada mesmo fora da cerca eletrônica');
        $this->assertFalse((bool) $punch->location_valid);
        $this->assertEquals($farLatitude, (float) $punch->latitude);
        $this->assertEquals($farLongitude, (float) $punch->longitude);
        $this->assertEquals(15.5, (float) $punch->location_accuracy);
        $this->assertGreaterThan(100.0, (float) $punch->location_distance_meters);

        // Projeção TimeEntry deve existir
        $this->assertEquals(1, TimeEntry::where('user_id', $this->user->id)->count());

        // Registro fiscal ARP da batida deve existir
        $this->assertEquals(1, ArpEvent::where('user_id', $this->user->id)
            ->where('event_type', ArpEventType::Punch)
            ->count());
    }

    public function test_punch_without_gps_is_recorded_successfully_as_evidence_fallback(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_CODE_123', null, null, null)
            ->assertSet('status', 'success');

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);
        $this->assertNull($punch->latitude);
        $this->assertNull($punch->longitude);
        $this->assertNull($punch->location_distance_meters);
        $this->assertNull($punch->location_valid);
    }

    public function test_punch_event_is_strictly_immutable(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_CODE_123', -9.665800, -35.735000);

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);

        // Tentativa de update deve disparar LogicException
        $this->expectException(LogicException::class);
        $punch->direction = 'out';
        $punch->save();
    }

    public function test_punch_event_cannot_be_deleted(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_CODE_123', -9.665800, -35.735000);

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);

        // Tentativa de delete deve disparar LogicException
        $this->expectException(LogicException::class);
        $punch->delete();
    }
}
