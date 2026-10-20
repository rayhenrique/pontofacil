<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Domain\TimeClock\Exceptions\GpsEnforcementException;
use App\Domain\TimeClock\Services\SectorGpsEnforcementService;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SectorGpsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $adminUser;

    protected User $employeeUserA;

    protected User $employeeUserB;

    protected Employee $employeeA;

    protected Employee $employeeB;

    protected Sector $sectorEnforced;

    protected Sector $sectorUnenforced;

    // Coordenadas de teste: Centro Administrativo Maceió
    protected const SECTOR_LAT = -9.665800;

    protected const SECTOR_LON = -35.735000;

    protected const SECTOR_RADIUS = 100;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste GPS PontoFacil',
            'trade_name' => 'GPS Test Corp',
            'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Maceió',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
            'nsr_next' => 1,
        ]);

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'GLOBAL_QR_HASH_123']);

        $this->adminUser = User::create([
            'name' => 'Admin RH',
            'email' => 'admin.gps@test.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->sectorEnforced = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor A - GPS Obrigatório',
            'latitude' => self::SECTOR_LAT,
            'longitude' => self::SECTOR_LON,
            'allowed_radius_meters' => self::SECTOR_RADIUS,
            'gps_enforcement_enabled' => true,
            'qr_code_hash' => 'SECTOR_A_QR_HASH_456',
        ]);

        $this->sectorUnenforced = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor B - GPS Opcional',
            'latitude' => self::SECTOR_LAT,
            'longitude' => self::SECTOR_LON,
            'allowed_radius_meters' => self::SECTOR_RADIUS,
            'gps_enforcement_enabled' => false,
            'qr_code_hash' => 'SECTOR_B_QR_HASH_789',
        ]);

        $this->employeeUserA = User::create([
            'name' => 'Carlos Setor A',
            'email' => 'carlos.a@test.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employeeA = Employee::create([
            'user_id' => $this->employeeUserA->id,
            'sector_id' => $this->sectorEnforced->id,
            'cpf' => '11122233344',
            'registration_number' => 'MAT-GPS-A',
            'admission_date' => '2026-01-01',
        ]);

        $this->employeeUserB = User::create([
            'name' => 'Bruna Setor B',
            'email' => 'bruna.b@test.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employeeB = Employee::create([
            'user_id' => $this->employeeUserB->id,
            'sector_id' => $this->sectorUnenforced->id,
            'cpf' => '22233344455',
            'registration_number' => 'MAT-GPS-B',
            'admission_date' => '2026-01-01',
        ]);
    }

    public function test_gps_disabled_and_no_coordinates_accepted(): void
    {
        $action = app(RecordPunchEventAction::class);

        // Funcionário do Setor B (trava desativada) bate ponto sem coordenadas
        $punch = $action->execute(
            user: $this->employeeUserB,
            direction: 'in',
            latitude: null,
            longitude: null,
            accuracy: null,
            establishment: $this->establishment
        );

        $this->assertInstanceOf(PunchEvent::class, $punch);
        $this->assertNull($punch->latitude);
        $this->assertNull($punch->longitude);
        $this->assertFalse((bool) $punch->location_valid);
    }

    public function test_gps_disabled_and_outside_radius_accepted_with_evidence(): void
    {
        // 5km de distância (~-9.620000, -35.710000)
        $farLat = -9.620000;
        $farLon = -35.710000;

        Livewire::actingAs($this->employeeUserB)
            ->test('time-punch')
            ->call('registerPunch', 'SECTOR_B_QR_HASH_789', $farLat, $farLon, 15.0)
            ->assertSet('status', 'warning');

        $punch = PunchEvent::where('user_id', $this->employeeUserB->id)->first();
        $this->assertNotNull($punch);
        $this->assertFalse((bool) $punch->location_valid);
        $this->assertGreaterThan(100.0, (float) $punch->location_distance_meters);
    }

    public function test_gps_enabled_and_inside_radius_accepted(): void
    {
        // Coordenadas exatamente no centro do setor com precisão de 10 metros
        $result = Livewire::actingAs($this->employeeUserA)
            ->test('time-punch')
            ->call('registerPunch', 'SECTOR_A_QR_HASH_456', self::SECTOR_LAT, self::SECTOR_LON, 10.0)
            ->assertSet('status', 'success');

        $punch = PunchEvent::where('user_id', $this->employeeUserA->id)->first();
        $this->assertNotNull($punch);
        $this->assertTrue((bool) $punch->location_valid);
        $this->assertEquals(0.0, (float) $punch->location_distance_meters);
        $this->assertEquals(10.0, (float) $punch->location_accuracy);
    }

    public function test_gps_enabled_and_outside_radius_rejected_with_exact_message(): void
    {
        // Posição a ~500m de distância (fora do raio de 100m)
        $outsideLat = -9.670000;
        $outsideLon = -35.735000;

        $punchesBefore = PunchEvent::count();
        $arpBefore = ArpEvent::count();

        Livewire::actingAs($this->employeeUserA)
            ->test('time-punch')
            ->call('registerPunch', 'SECTOR_A_QR_HASH_456', $outsideLat, $outsideLon, 10.0)
            ->assertSet('status', 'error')
            ->assertSet('message', 'Registro não autorizado: você está fora da área permitida para seu setor.')
            ->assertDispatched('app-modal-alert');

        // Nenhum PunchEvent ou ArpEvent foi criado
        $this->assertEquals($punchesBefore, PunchEvent::count());
        $this->assertEquals($arpBefore, ArpEvent::count());
    }

    public function test_gps_enabled_without_location_permission_or_null_coordinates_rejected(): void
    {
        Livewire::actingAs($this->employeeUserA)
            ->test('time-punch')
            ->call('registerPunch', 'SECTOR_A_QR_HASH_456', null, null, null)
            ->assertSet('status', 'error')
            ->assertSet('message', 'Este setor exige localização. Ative a permissão de GPS para tentar novamente.')
            ->assertDispatched('app-modal-alert');

        $this->assertEquals(0, PunchEvent::where('user_id', $this->employeeUserA->id)->count());
    }

    public function test_gps_enabled_with_invalid_coordinates_rejected(): void
    {
        $service = app(SectorGpsEnforcementService::class);

        // Latitude fora dos limites (+95)
        try {
            $service->validatePunch($this->employeeUserA, 95.0, -35.735000, 10.0);
            $this->fail('Deveria ter lançado GpsEnforcementException para latitude inválida.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('Este setor exige localização. Ative a permissão de GPS para tentar novamente.', $e->getMessage());
            $this->assertEquals('invalid_coordinates', $e->getReasonCode());
        }

        // Longitude fora dos limites (-190)
        try {
            $service->validatePunch($this->employeeUserA, -9.665800, -190.0, 10.0);
            $this->fail('Deveria ter lançado GpsEnforcementException para longitude inválida.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('invalid_coordinates', $e->getReasonCode());
        }
    }

    public function test_gps_enabled_with_insufficient_or_invalid_accuracy_rejected(): void
    {
        $service = app(SectorGpsEnforcementService::class);

        // Precisão negativa
        try {
            $service->validatePunch($this->employeeUserA, self::SECTOR_LAT, self::SECTOR_LON, -5.0);
            $this->fail('Deveria ter lançado GpsEnforcementException para precisão negativa.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('Não foi possível confirmar sua localização com precisão suficiente. Tente novamente em um local com melhor sinal.', $e->getMessage());
            $this->assertEquals('insufficient_accuracy', $e->getReasonCode());
        }

        // Precisão zero
        try {
            $service->validatePunch($this->employeeUserA, self::SECTOR_LAT, self::SECTOR_LON, 0.0);
            $this->fail('Deveria ter lançado GpsEnforcementException para precisão zero.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('insufficient_accuracy', $e->getReasonCode());
        }

        // Precisão excessiva (> 150 metros, por exemplo sinal de torre celular de 200m)
        try {
            $service->validatePunch($this->employeeUserA, self::SECTOR_LAT, self::SECTOR_LON, 200.0);
            $this->fail('Deveria ter lançado GpsEnforcementException para precisão > 150m.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('insufficient_accuracy', $e->getReasonCode());
        }
    }

    public function test_gps_enabled_with_inconsistent_sector_config_fails_safely(): void
    {
        // Setor com trava ativa mas sem coordenadas configuradas
        $inconsistentSector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Inconsistente',
            'latitude' => null,
            'longitude' => null,
            'allowed_radius_meters' => null,
            'gps_enforcement_enabled' => true,
        ]);

        $userC = User::create([
            'name' => 'Colaborador C',
            'email' => 'colab.c@test.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $userC->id,
            'sector_id' => $inconsistentSector->id,
            'cpf' => '33344455566',
            'admission_date' => '2026-01-01',
        ]);

        Livewire::actingAs($userC)
            ->test('time-punch')
            ->call('registerPunch', 'GLOBAL_QR_HASH_123', self::SECTOR_LAT, self::SECTOR_LON, 10.0)
            ->assertSet('status', 'error')
            ->assertSet('message', 'Não foi possível validar a área autorizada. Entre em contato com o RH.')
            ->assertDispatched('app-modal-alert');
    }

    public function test_location_exactly_at_radius_boundary_accepted(): void
    {
        $service = app(SectorGpsEnforcementService::class);

        // Setor com raio de 100m. 1 grau de latitude é aprox 111.139 km = 111139 m.
        // 100 metros ao norte = 100 / 111139 ≈ 0.00089976 graus.
        // Vamos usar a função de Haversine para obter uma coordenada a exatamente ~99.9 metros:
        $latOffset = 99.0 / 111139.0;
        $boundaryLat = self::SECTOR_LAT + $latOffset;

        $telemetry = $service->validatePunch(
            user: $this->employeeUserA,
            latitude: $boundaryLat,
            longitude: self::SECTOR_LON,
            accuracy: 10.0
        );

        $this->assertNotNull($telemetry);
        $this->assertTrue($telemetry['valid']);
        $this->assertLessThanOrEqual(100.0, $telemetry['distance']);
    }

    public function test_negative_and_zero_coordinates_handled_correctly(): void
    {
        $service = app(SectorGpsEnforcementService::class);

        // Cria setor nas coordenadas 0.0, 0.0 (Greenwich / Linha do Equador)
        $zeroSector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Zero',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'allowed_radius_meters' => 500,
            'gps_enforcement_enabled' => true,
        ]);

        $userZero = User::create([
            'name' => 'Zero User',
            'email' => 'zero@test.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $userZero->id,
            'sector_id' => $zeroSector->id,
            'cpf' => '44455566677',
            'admission_date' => '2026-01-01',
        ]);

        // Ponto dentro do raio de 500m das coordenadas (0.0, 0.0)
        $result = $service->validatePunch($userZero, 0.0005, 0.0005, 15.0);
        $this->assertNotNull($result);
        $this->assertTrue($result['valid']);
        $this->assertLessThan(500.0, $result['distance']);
    }

    public function test_sector_a_with_enforcement_and_sector_b_without_enforcement(): void
    {
        $outsideLat = -9.670000;
        $outsideLon = -35.735000;

        // Setor A (bloqueio ativado) -> Rejeita
        try {
            app(RecordPunchEventAction::class)->execute(
                user: $this->employeeUserA,
                direction: 'in',
                latitude: $outsideLat,
                longitude: $outsideLon,
                accuracy: 10.0,
                establishment: $this->establishment
            );
            $this->fail('Setor A deveria ter rejeitado o ponto fora da área.');
        } catch (GpsEnforcementException $e) {
            $this->assertEquals('outside_allowed_radius', $e->getReasonCode());
        }

        // Setor B (bloqueio desativado) -> Aceita com aviso
        $punchB = app(RecordPunchEventAction::class)->execute(
            user: $this->employeeUserB,
            direction: 'in',
            latitude: $outsideLat,
            longitude: $outsideLon,
            accuracy: 10.0,
            establishment: $this->establishment
        );
        $this->assertInstanceOf(PunchEvent::class, $punchB);
        $this->assertFalse((bool) $punchB->location_valid);
    }

    public function test_user_with_valid_qr_code_outside_geofence_is_still_rejected(): void
    {
        $outsideLat = -9.670000;
        $outsideLon = -35.735000;

        // Mesmo escaneando o QR Code específico e válido do seu setor, a trava GPS impede a batida se estiver fora da área
        Livewire::actingAs($this->employeeUserA)
            ->test('time-punch')
            ->call('registerPunch', 'SECTOR_A_QR_HASH_456', $outsideLat, $outsideLon, 10.0)
            ->assertSet('status', 'error')
            ->assertSet('message', 'Registro não autorizado: você está fora da área permitida para seu setor.');

        $this->assertEquals(0, PunchEvent::where('user_id', $this->employeeUserA->id)->count());
    }

    public function test_rejection_creates_zero_punch_events_nsr_or_arp_records(): void
    {
        $outsideLat = -9.670000;
        $outsideLon = -35.735000;

        $nsrNextBefore = $this->establishment->fresh()->nsr_next;
        $punchesBefore = PunchEvent::count();
        $arpBefore = ArpEvent::count();
        $timeEntriesBefore = TimeEntry::count();

        try {
            app(RecordPunchEventAction::class)->execute(
                user: $this->employeeUserA,
                direction: 'in',
                latitude: $outsideLat,
                longitude: $outsideLon,
                accuracy: 10.0,
                establishment: $this->establishment
            );
        } catch (GpsEnforcementException) {
            // Esperado
        }

        // Verifica que NENHUM registro foi criado e NENHUM NSR foi consumido
        $this->assertEquals($nsrNextBefore, $this->establishment->fresh()->nsr_next);
        $this->assertEquals($punchesBefore, PunchEvent::count());
        $this->assertEquals($arpBefore, ArpEvent::count());
        $this->assertEquals($timeEntriesBefore, TimeEntry::count());
    }

    public function test_idempotency_returns_existing_record_without_revalidating_gps(): void
    {
        $action = app(RecordPunchEventAction::class);
        $idempotencyKey = 'unique_attempt_abc123';

        // Primeira chamada válida dentro do raio
        $firstPunch = $action->execute(
            user: $this->employeeUserA,
            direction: 'in',
            latitude: self::SECTOR_LAT,
            longitude: self::SECTOR_LON,
            accuracy: 10.0,
            establishment: $this->establishment,
            idempotencyKey: $idempotencyKey
        );

        $this->assertInstanceOf(PunchEvent::class, $firstPunch);
        $initialPunchCount = PunchEvent::count();

        // Segunda tentativa com a MESMA chave de idempotência (mesmo com coordenadas alteradas)
        $secondPunch = $action->execute(
            user: $this->employeeUserA,
            direction: 'in',
            latitude: -9.999999, // Coordenadas fora do raio
            longitude: -35.999999,
            accuracy: 10.0,
            establishment: $this->establishment,
            idempotencyKey: $idempotencyKey
        );

        // Deve retornar o mesmo registro sem duplicar e sem rejeitar
        $this->assertEquals($firstPunch->id, $secondPunch->id);
        $this->assertEquals($initialPunchCount, PunchEvent::count());
    }

    public function test_existing_sectors_default_to_gps_enforcement_disabled(): void
    {
        $newSector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Setor Legado Sem Flag',
        ]);

        $this->assertFalse((bool) $newSector->gps_enforcement_enabled);
        $this->assertDatabaseHas('sectors', [
            'id' => $newSector->id,
            'gps_enforcement_enabled' => false,
        ]);
    }

    public function test_admin_sectors_component_validation_cannot_enable_without_coordinates_and_radius(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test('admin.sectors')
            ->set('name', 'Novo Setor Incompleto')
            ->set('gps_enforcement_enabled', true)
            ->set('latitude', '')
            ->set('longitude', '')
            ->set('allowed_radius_meters', '')
            ->call('save')
            ->assertHasErrors(['latitude', 'longitude', 'allowed_radius_meters']);

        // Agora com dados válidos -> Salva com sucesso
        Livewire::actingAs($this->adminUser)
            ->test('admin.sectors')
            ->set('name', 'Novo Setor Completo')
            ->set('gps_enforcement_enabled', true)
            ->set('latitude', -9.665800)
            ->set('longitude', -35.735000)
            ->set('allowed_radius_meters', 100)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sectors', [
            'name' => 'Novo Setor Completo',
            'gps_enforcement_enabled' => true,
            'allowed_radius_meters' => 100,
        ]);
    }

    public function test_unauthorized_user_cannot_update_sector_gps_policy(): void
    {
        // Funcionário comum tentando acessar / manipular o componente admin.sectors
        $response = $this->actingAs($this->employeeUserA)->get('/admin/sectors');
        $response->assertForbidden();
    }
}
