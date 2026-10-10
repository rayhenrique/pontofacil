<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimePunchUxRefactorTest extends TestCase
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

        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => 'TEST_QR_VALID_123']);
        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => '-9.665800']);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => '-35.735000']);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => '100']);

        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->sector = Sector::create([
            'name' => 'Setor de Produção',
            'establishment_id' => $this->establishment->id,
            'latitude' => -9.665800,
            'longitude' => -35.735000,
            'allowed_radius_meters' => 100,
        ]);

        $this->user = User::create([
            'name' => 'Juliana Silva',
            'email' => 'juliana.silva@pontofacil.local',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Employee,
            'company_id' => $this->establishment->company_id,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-2026-UX',
            'cpf' => '987.654.321-00',
            'job_title' => 'Analista de Operações',
            'admission_date' => now()->toDateString(),
        ]);
    }

    public function test_ux_displays_operational_layout_and_excludes_misleading_terms(): void
    {
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->assertSee('Registrar ponto')
            ->assertSee('Sua jornada hoje')
            ->assertSee('Leitura não iniciada')
            ->assertSee('Iniciar leitura do QR Code')
            ->assertSee('Juliana')
            ->assertDontSee('Câmera pronta')
            ->assertDontSee('Registro inviolável');
    }

    public function test_ux_invalid_qr_code_returns_safe_error_feedback(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'QR_INVALIDO_INEXISTENTE');

        $component->assertSet('status', 'error')
            ->assertSee('QR Code inválido ou não autorizado');

        $this->assertSame(0, PunchEvent::where('user_id', $this->user->id)->count());
    }

    public function test_ux_punch_without_gps_proceeds_smoothly_without_blocking(): void
    {
        $idempotencyKey = 'attempt_no_gps_1';

        $result = Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_VALID_123', null, null, null, $idempotencyKey)
            ->get('status');

        $this->assertSame('success', $result);

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);
        $this->assertNull($punch->latitude);
        $this->assertNull($punch->longitude);
        $this->assertNull($punch->location_valid);
    }

    public function test_ux_punch_outside_radius_confirms_record_with_auditing_warning(): void
    {
        $idempotencyKey = 'attempt_outside_radius_1';

        // Coordenadas a 500m de distância
        $testComponent = Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_VALID_123', -9.670000, -35.735000, 10.0, $idempotencyKey);

        $testComponent->assertSet('status', 'warning');

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch, 'Ponto deve ser confirmado e gravado mesmo com GPS fora do raio');
        $this->assertFalse((bool) $punch->location_valid);
        $this->assertGreaterThan(100, (float) $punch->location_distance_meters);
    }

    public function test_ux_confirmation_payload_provides_server_persisted_attributes(): void
    {
        $idempotencyKey = 'attempt_confirmation_payload_1';

        $component = Livewire::actingAs($this->user)->test('time-punch');
        $component->call('registerPunch', 'TEST_QR_VALID_123', -9.665800, -35.735000, 5.0, $idempotencyKey);

        $punch = PunchEvent::where('user_id', $this->user->id)->first();
        $this->assertNotNull($punch);

        $payload = $component->get('confirmationData');
        $this->assertIsArray($payload);
        $this->assertSame($punch->id, $payload['id']);
        $this->assertSame('Entrada registrada', $payload['direction_title']);
        $this->assertSame($punch->nsr, $payload['nsr']);
        $this->assertSame(str_pad((string) $punch->nsr, 9, '0', STR_PAD_LEFT), $payload['nsr_formatted']);
        $this->assertSame($punch->occurred_at_local->format('H:i:s'), $payload['time']);
    }

    public function test_ux_today_punches_shows_only_authenticated_user_records(): void
    {
        // Outro colaborador registrando ponto
        $otherUser = User::create([
            'name' => 'Marcos Outro',
            'email' => 'marcos.outro@pontofacil.local',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Employee,
            'company_id' => $this->establishment->company_id,
        ]);
        Employee::create([
            'user_id' => $otherUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-OTHER',
            'cpf' => '111.222.333-44',
            'job_title' => 'Assistente',
            'admission_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($otherUser)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_VALID_123', -9.665800, -35.735000, 5.0);

        // Juliana consulta o componente dela
        $component = Livewire::actingAs($this->user)->test('time-punch');

        // Juliana ainda não tem batidas hoje
        $punches = $component->get('todayPunches');
        $this->assertCount(0, $punches);
        $component->assertSee('Nenhuma marcação registrada hoje');
    }

    public function test_ux_unknown_status_reconciliation_verifies_punch_without_duplicate(): void
    {
        $idempotencyKey = 'attempt_reconciliation_network_fail_1';

        // Registra via backend simulando uma requisição que alcançou o servidor
        Livewire::actingAs($this->user)
            ->test('time-punch')
            ->call('registerPunch', 'TEST_QR_VALID_123', -9.665800, -35.735000, 5.0, $idempotencyKey);

        $countInitial = PunchEvent::where('user_id', $this->user->id)->count();
        $this->assertSame(1, $countInitial);

        // Agora simula o frontend acionando checkPunchStatus após queda de rede
        $component = Livewire::actingAs($this->user)->test('time-punch');
        $reconciled = $component->instance()->checkPunchStatus($idempotencyKey);

        $this->assertNotNull($reconciled);
        $this->assertSame($idempotencyKey, PunchEvent::where('id', $reconciled['id'])->value('idempotency_key'));
        $this->assertSame(1, PunchEvent::where('user_id', $this->user->id)->count(), 'Reconciliação não deve duplicar o registro');
    }
}
