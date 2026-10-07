<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Domain\TimeClock\Services\NsrGeneratorService;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RepPFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'TEST_QR_GLOBAL_123']);
        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => '-9.665800']);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => '-35.735000']);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => '100']);
    }

    public function test_current_company_singleton_returns_installation_company_and_default_matriz_establishment(): void
    {
        $company = CurrentCompany::get();
        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals('00000000000191', $company->cnpj);

        $establishment = CurrentCompany::defaultEstablishment();
        $this->assertInstanceOf(Establishment::class, $establishment);
        $this->assertEquals('MATRIZ', $establishment->code);
        // O evento fiscal de criação do estabelecimento consome o NSR 1 na ARP:
        $this->assertEquals(2, $establishment->nsr_next);
    }

    public function test_nsr_generator_generates_strictly_consecutive_monotonic_numbers(): void
    {
        $establishment = CurrentCompany::defaultEstablishment();
        $service = app(NsrGeneratorService::class);

        $nsr1 = $service->reserveNextNsr($establishment->id);
        $nsr2 = $service->reserveNextNsr($establishment->id);
        $nsr3 = $service->reserveNextNsr($establishment->id);

        $this->assertEquals(2, $nsr1);
        $this->assertEquals(3, $nsr2);
        $this->assertEquals(4, $nsr3);

        $establishment->refresh();
        $this->assertEquals(5, $establishment->nsr_next);
    }

    public function test_multiple_establishments_maintain_independent_nsr_counters(): void
    {
        $company = CurrentCompany::get();
        $matriz = CurrentCompany::defaultEstablishment();

        $filial = Establishment::create([
            'company_id' => $company->id,
            'code' => 'FILIAL_01',
            'name' => 'Filial Arapiraca',
            'identifier_type' => 'cnpj',
            'identifier_number' => '00000000000272',
            'city' => 'Arapiraca',
            'state' => 'AL',
            'nsr_next' => 1,
        ]);

        $service = app(NsrGeneratorService::class);

        $matrizNsr1 = $service->reserveNextNsr($matriz->id);
        $filialNsr1 = $service->reserveNextNsr($filial->id);
        $filialNsr2 = $service->reserveNextNsr($filial->id);
        $matrizNsr2 = $service->reserveNextNsr($matriz->id);

        $this->assertEquals(2, $matrizNsr1);
        $this->assertEquals(3, $matrizNsr2);

        $this->assertEquals(2, $filialNsr1);
        $this->assertEquals(3, $filialNsr2);
    }

    public function test_record_punch_event_action_creates_immutable_event_with_chained_sha256_hash(): void
    {
        $user = User::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);

        $punch1 = $action->execute(
            user: $user,
            direction: 'in',
            latitude: -9.665800,
            longitude: -35.735000,
            accuracy: 10.0,
            qrLocationValid: true,
            locationValid: true
        );

        $this->assertInstanceOf(PunchEvent::class, $punch1);
        $this->assertEquals(2, $punch1->nsr);
        $this->assertEquals('in', $punch1->direction);
        // O hash anterior encadeia com o evento fiscal de criação de estabelecimento (NSR 1):
        $this->assertNotNull($punch1->previous_event_hash);
        $this->assertNotEmpty($punch1->payload_hash);
        $this->assertEquals(64, strlen($punch1->payload_hash));

        $punch2 = $action->execute(
            user: $user,
            direction: 'out',
            latitude: -9.665800,
            longitude: -35.735000,
            accuracy: 12.0,
            qrLocationValid: true,
            locationValid: true
        );

        $this->assertEquals(3, $punch2->nsr);
        $this->assertEquals('out', $punch2->direction);
        $this->assertEquals($punch1->payload_hash, $punch2->previous_event_hash);
    }

    public function test_punch_event_throws_logic_exception_when_attempting_to_update_or_delete(): void
    {
        $user = User::create([
            'name' => 'Maria Souza',
            'email' => 'maria@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $punch = $action->execute(user: $user, direction: 'in');

        $this->expectException(\LogicException::class);
        $punch->update(['direction' => 'out']);
    }

    public function test_punch_event_throws_logic_exception_when_attempting_to_delete(): void
    {
        $user = User::create([
            'name' => 'Roberto Santos',
            'email' => 'roberto@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $punch = $action->execute(user: $user, direction: 'in');

        $this->expectException(\LogicException::class);
        $punch->delete();
    }

    public function test_time_punch_component_creates_both_legacy_entry_and_rep_p_punch_event(): void
    {
        $sector = Sector::create([
            'name' => 'Operações',
            'qr_code_hash' => null,
            'latitude' => null,
            'longitude' => null,
            'allowed_radius_meters' => null,
        ]);

        $user = User::create([
            'name' => 'Ana Paula',
            'email' => 'anapaula@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'cpf' => '999.888.777-66',
            'job_title' => 'Técnica de Operações',
        ]);

        Livewire::actingAs($user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_GLOBAL_123', -9.665800, -35.735000)
            ->assertSet('status', 'success');

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'type' => 'in',
        ]);

        $this->assertDatabaseHas('punch_events', [
            'user_id' => $user->id,
            'direction' => 'in',
            'nsr' => 3,
        ]);
    }
}
