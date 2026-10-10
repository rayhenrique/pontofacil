<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\Compliance\Receipt\GeneratePunchReceiptAction;
use App\Domain\Compliance\Signing\SigningServiceInterface;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\PunchReceipt;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Tests\TestCase;

class TimePunchHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private Sector $sector;

    private Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'TEST_QR_SECURITY_123']);
        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => '-9.665800']);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => '-35.735000']);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => '100']);

        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->sector = Sector::create([
            'name' => 'Operações Noturnas',
            'establishment_id' => $this->establishment->id,
            'latitude' => -9.665800,
            'longitude' => -35.735000,
            'allowed_radius_meters' => 100,
        ]);

        $this->user = User::create([
            'name' => 'Roberto Plantão',
            'email' => 'roberto.plantao@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '123.456.789-99',
            'job_title' => 'Vigilante',
            'admission_date' => now()->toDateString(),
        ]);
    }

    /**
     * 1. Duas solicitações concorrentes ou duplo clique com a mesma chave de idempotência.
     */
    public function test_concurrent_or_double_click_with_same_idempotency_key_returns_existing_record_without_duplication(): void
    {
        $idempotencyKey = 'attempt_key_uuid_1111';

        // Primeira chamada
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, $idempotencyKey)
            ->assertSet('status', 'success')
            ->assertSee('Ponto registrado com sucesso!');

        $initialPunchCount = PunchEvent::count();
        $initialArpCount = ArpEvent::where('event_type', ArpEventType::Punch)->count();
        $this->assertEquals(1, $initialPunchCount);
        $this->assertEquals(1, $initialArpCount);

        $punch1 = PunchEvent::first();
        $initialNsr = $punch1->nsr;

        // Segunda chamada (simulando retentativa, duplo clique ou concorrência com mesma chave)
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, $idempotencyKey)
            ->assertSet('status', 'success');

        // Garante que nenhum novo registro e nenhum novo NSR foi consumido
        $this->assertEquals(1, PunchEvent::count());
        $this->assertEquals(1, ArpEvent::where('event_type', ArpEventType::Punch)->count());
        $this->assertEquals(1, TimeEntry::count());

        $punch2 = PunchEvent::first();
        $this->assertEquals($punch1->id, $punch2->id);
        $this->assertEquals($initialNsr, $punch2->nsr);
        $this->establishment->refresh();
        $this->assertEquals($initialNsr + 1, $this->establishment->nsr_next);
    }

    /**
     * 2. Retentativa direta no backend com a mesma chave de idempotência via RecordPunchEventAction.
     */
    public function test_backend_action_idempotency_protection(): void
    {
        $action = app(RecordPunchEventAction::class);
        $key = 'backend_idempotency_unique_test';

        $first = $action->execute(
            user: $this->user,
            direction: 'in',
            idempotencyKey: $key
        );

        $second = $action->execute(
            user: $this->user,
            direction: 'in',
            idempotencyKey: $key
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->nsr, $second->nsr);
        $this->assertEquals(1, PunchEvent::where('idempotency_key', $key)->count());
    }

    /**
     * 3. Reconciliação por identificador da tentativa após timeout de rede (checkPunchStatus).
     */
    public function test_network_failure_reconciliation_using_check_punch_status(): void
    {
        $idempotencyKey = 'attempt_key_uuid_reconcile';

        // Registra o ponto
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, $idempotencyKey)
            ->assertSet('status', 'success');

        // Reconcilia com a chave existente
        $component = Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('checkPunchStatus', $idempotencyKey);

        $component->assertSet('status', 'success')
            ->assertDispatched('app-modal-alert');

        // Reconcilia com chave inexistente
        $result = $component->instance()->checkPunchStatus('chave_inexistente_999');
        $this->assertNull($result);
    }

    /**
     * 4. Duas marcações legítimas diferentes consecutivas não são bloqueadas arbitrariamente.
     */
    public function test_two_legitimate_consecutive_punches_are_not_blocked(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'legit_punch_1')
            ->assertSet('status', 'success');

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'legit_punch_2')
            ->assertSet('status', 'success');

        $this->assertEquals(2, PunchEvent::where('user_id', $this->user->id)->count());

        $punches = PunchEvent::where('user_id', $this->user->id)
            ->orderBy('occurred_at_utc', 'asc')
            ->get();

        $this->assertEquals('in', $punches[0]->direction);
        $this->assertEquals('out', $punches[1]->direction);
        $this->assertEquals($punches[0]->nsr + 1, $punches[1]->nsr);
    }

    /**
     * 5. Jornada noturna que atravessa meia-noite (Entrada às 22h, Saída às 06h do dia seguinte).
     */
    public function test_night_shift_crossing_midnight_determines_in_and_out_correctly(): void
    {
        Carbon::setTestNow('2026-10-10 22:00:00');

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'night_punch_in')
            ->assertSet('status', 'success');

        $firstPunch = PunchEvent::where('user_id', $this->user->id)->latest('occurred_at_utc')->first();
        $this->assertEquals('in', $firstPunch->direction);

        // Saída às 06h do dia seguinte (outro dia civil!)
        Carbon::setTestNow('2026-10-11 06:00:00');

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'night_punch_out')
            ->assertSet('status', 'success');

        $secondPunch = PunchEvent::where('user_id', $this->user->id)->latest('occurred_at_utc')->first();
        $this->assertEquals('out', $secondPunch->direction, 'A marcação das 06h deve ser Saída, e não nova Entrada!');

        // Nova entrada às 22h do segundo dia
        Carbon::setTestNow('2026-10-11 22:00:00');

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'night_punch_in_day2')
            ->assertSet('status', 'success');

        $thirdPunch = PunchEvent::where('user_id', $this->user->id)->latest('occurred_at_utc')->first();
        $this->assertEquals('in', $thirdPunch->direction);

        Carbon::setTestNow();
    }

    /**
     * 6. Retorno de intervalo atravessando meia-noite (20h in -> 23h30 out -> 00h30 in -> 05h out).
     */
    public function test_interval_crossing_midnight_sequence(): void
    {
        Carbon::setTestNow('2026-10-10 20:00:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'seq_1');

        Carbon::setTestNow('2026-10-10 23:30:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'seq_2');

        Carbon::setTestNow('2026-10-11 00:30:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'seq_3');

        Carbon::setTestNow('2026-10-11 05:00:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'seq_4');

        $directions = PunchEvent::where('user_id', $this->user->id)
            ->orderBy('occurred_at_utc', 'asc')
            ->pluck('direction')
            ->all();

        $this->assertSame(['in', 'out', 'in', 'out'], $directions);

        Carbon::setTestNow();
    }

    /**
     * 7. Virada de mês e de ano (31/12 22h -> 01/01 06h).
     */
    public function test_year_turnover_crossing_midnight(): void
    {
        Carbon::setTestNow('2026-12-31 22:00:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'year_end_in');

        Carbon::setTestNow('2027-01-01 06:00:00');
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -9.665800, -35.735000, 10.0, 'year_end_out');

        $directions = PunchEvent::where('user_id', $this->user->id)
            ->orderBy('occurred_at_utc', 'asc')
            ->pluck('direction')
            ->all();

        $this->assertSame(['in', 'out'], $directions);

        Carbon::setTestNow();
    }

    /**
     * 8. GPS negado ou indisponível (null) não impede a marcação.
     */
    public function test_punch_with_gps_denied_or_null_succeeds_without_blocking(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', null, null, null, 'no_gps_punch')
            ->assertSet('status', 'success');

        $punch = PunchEvent::where('idempotency_key', 'no_gps_punch')->first();
        $this->assertNotNull($punch);
        $this->assertNull($punch->latitude);
        $this->assertNull($punch->longitude);
        $this->assertNull($punch->location_valid);
        $this->assertNull($punch->location_distance_meters);
    }

    /**
     * 9. GPS fora do raio não bloqueia a marcação e registra evidência de auditoria.
     */
    public function test_punch_outside_radius_is_not_refused(): void
    {
        // Posição a ~20km de distância
        $farLat = -9.450000;
        $farLon = -35.850000;

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', $farLat, $farLon, 15.0, 'outside_radius_punch')
            ->assertSet('status', 'warning')
            ->assertSee('Fora do raio permitido');

        $punch = PunchEvent::where('idempotency_key', 'outside_radius_punch')->first();
        $this->assertNotNull($punch);
        $this->assertFalse((bool) $punch->location_valid);
        $this->assertEquals($farLat, (float) $punch->latitude);
        $this->assertEquals($farLon, (float) $punch->longitude);
        $this->assertGreaterThan(100.0, (float) $punch->location_distance_meters);
    }

    /**
     * 10. Coordenadas de referência ausentes (sem perímetro configurado).
     */
    public function test_missing_reference_coordinates_does_not_calculate_against_invented_location(): void
    {
        // Limpa configurações de coordenadas da empresa
        SystemSetting::whereIn('key', ['company_latitude', 'company_longitude'])->delete();

        // Cria setor sem coordenadas
        $sectorNoGps = Sector::create([
            'name' => 'Setor Sem GPS',
            'establishment_id' => $this->establishment->id,
            'latitude' => null,
            'longitude' => null,
            'allowed_radius_meters' => null,
        ]);
        $this->employee->update(['sector_id' => $sectorNoGps->id]);

        // Colaborador em São Paulo (-23.5505, -46.6333)
        $spLat = -23.550500;
        $spLon = -46.633300;

        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', $spLat, $spLon, 12.0, 'sp_coords_punch')
            ->assertSet('status', 'success'); // NÃO deve dar warning de raio contra Maceió!

        $punch = PunchEvent::where('idempotency_key', 'sp_coords_punch')->first();
        $this->assertNotNull($punch);
        $this->assertEquals($spLat, (float) $punch->latitude);
        $this->assertEquals($spLon, (float) $punch->longitude);
        $this->assertNull($punch->location_valid, 'Perímetro não deve ser avaliado se não há referência');
        $this->assertNull($punch->location_distance_meters, 'Distância não deve ser calculada contra local inventado');
    }

    /**
     * 11. Timezone configurado no estabelecimento diferente de America/Maceio (ex: America/Manaus GMT-4).
     */
    public function test_establishment_timezone_other_than_maceio_is_respected(): void
    {
        $company = CurrentCompany::get();
        $manausEst = Establishment::create([
            'company_id' => $company->id,
            'code' => 'FILIAL_MANAUS',
            'name' => 'Filial Manaus',
            'identifier_type' => 'cnpj',
            'identifier_number' => '00000000000353',
            'city' => 'Manaus',
            'state' => 'AM',
            'timezone' => 'America/Manaus',
            'nsr_next' => 1,
        ]);

        $sectorManaus = Sector::create([
            'name' => 'Almoxarifado Manaus',
            'establishment_id' => $manausEst->id,
            'latitude' => -3.119000,
            'longitude' => -60.021700,
            'allowed_radius_meters' => 100,
        ]);

        $userManaus = User::create([
            'name' => 'José Manaus',
            'email' => 'jose.manaus@pontofacil.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $userManaus->id,
            'sector_id' => $sectorManaus->id,
            'cpf' => '888.777.666-55',
            'job_title' => 'Conferente',
            'admission_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($userManaus)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_SECURITY_123', -3.119000, -60.021700, 8.0, 'manaus_punch_key')
            ->assertSet('status', 'success');

        $punch = PunchEvent::where('idempotency_key', 'manaus_punch_key')->first();
        $this->assertNotNull($punch);
        $this->assertEquals('America/Manaus', $punch->timezone);
        $this->assertEquals('-04:00', $punch->utc_offset);
        $this->assertEquals($manausEst->id, $punch->establishment_id);

        // Verifica consistência entre occurred_at_utc e occurred_at_local
        $this->assertEquals(
            $punch->occurred_at_utc->copy()->setTimezone('America/Manaus')->format('Y-m-d H:i:s'),
            $punch->occurred_at_local->format('Y-m-d H:i:s'),
            'occurred_at_utc e occurred_at_local devem representar o exato mesmo instante físico'
        );
    }

    /**
     * 12. Falha na emissão imediata do comprovante não perde a marcação e permite recuperação posterior.
     */
    public function test_receipt_emission_failure_does_not_lose_punch_and_allows_subsequent_generation(): void
    {
        Exceptions::fake();

        $action = app(RecordPunchEventAction::class);

        // Simula falha temporária no container para a emissão do comprovante
        $this->app->bind(GeneratePunchReceiptAction::class, function () {
            return new class extends GeneratePunchReceiptAction
            {
                public function __construct() {}

                public function execute(PunchEvent $punchEvent): PunchReceipt
                {
                    throw new \RuntimeException('Falha simulada no serviço de comprovante!');
                }
            };
        });

        // A marcação deve ser registrada normalmente apesar da falha na emissão imediata
        $punch = $action->execute(
            user: $this->user,
            direction: 'in',
            idempotencyKey: 'resilient_punch_key'
        );

        $this->assertInstanceOf(PunchEvent::class, $punch);
        $this->assertDatabaseHas('punch_events', ['id' => $punch->id]);
        $this->assertNull(PunchReceipt::where('punch_event_id', $punch->id)->first());

        // Restaura a Action original no container
        $this->app->bind(GeneratePunchReceiptAction::class, function ($app) {
            return new GeneratePunchReceiptAction($app->make(SigningServiceInterface::class));
        });

        // Recupera o comprovante posteriormente
        $receipt = app(GeneratePunchReceiptAction::class)->execute($punch);
        $this->assertInstanceOf(PunchReceipt::class, $receipt);
        $this->assertEquals($punch->id, $receipt->punch_event_id);
        $this->assertDatabaseHas('punch_receipts', ['punch_event_id' => $punch->id]);
    }

    /**
     * 13. NSR monotônico e integridade dos hashes fiscal e de auditoria encadeados.
     */
    public function test_nsr_monotonicity_and_hash_chaining_integrity(): void
    {
        $action = app(RecordPunchEventAction::class);

        $punch1 = $action->execute(user: $this->user, direction: 'in', idempotencyKey: 'hash_test_1');
        $punch2 = $action->execute(user: $this->user, direction: 'out', idempotencyKey: 'hash_test_2');

        $this->assertEquals($punch1->nsr + 1, $punch2->nsr);
        $this->assertNotEmpty($punch1->fiscal_hash);
        $this->assertNotEmpty($punch2->fiscal_hash);
        $this->assertNotEmpty($punch1->audit_chain_hash);
        $this->assertNotEmpty($punch2->audit_chain_hash);

        $this->assertEquals($punch1->audit_chain_hash, $punch2->previous_audit_hash);
    }
}
