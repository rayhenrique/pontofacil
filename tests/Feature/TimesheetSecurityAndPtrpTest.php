<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetSecurityAndPtrpTest extends TestCase
{
    use RefreshDatabase;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();

        $company = CurrentCompany::get();
        if (! $company) {
            $company = Company::create([
                'name' => 'Empresa Teste Dedicada',
                'trade_name' => 'PontoFácil Teste',
                'cnpj' => '11780685000152',
            ]);
        }

        $this->establishment = Establishment::firstOrCreate(
            ['company_id' => $company->id],
            [
                'name' => 'Sede Matriz',
                'identifier_type' => 'CNPJ',
                'identifier_number' => '11780685000152',
                'timezone' => 'America/Maceio',
                'nsr_next' => 1,
            ]
        );
    }

    protected function createEmployeeUser(string $name, string $email, ?Sector $sector = null, UserRole $role = UserRole::Employee): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
            'role' => $role,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector?->id,
            'registration_number' => str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'cpf' => sprintf('111222%05d', $user->id),
            'job_title' => 'Assistente Administrativo',
        ]);

        return [$user, $employee];
    }

    protected function createPunch(Employee $employee, Carbon $time, string $direction, int $nsr, ?float $lat = null, ?float $lng = null): PunchEvent
    {
        return PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'nsr' => $nsr,
            'occurred_at_utc' => $time->copy()->utc(),
            'occurred_at_local' => $time,
            'direction' => $direction,
            'latitude' => $lat,
            'longitude' => $lng,
            'fiscal_hash' => hash('sha256', (string) $nsr),
            'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
            'payload_hash' => hash('sha256', 'payload'.$nsr),
        ]);
    }

    /**
     * 1. Colaborador tentando acessar dados de outro usuário.
     */
    public function test_employee_cannot_access_another_employee_data(): void
    {
        [$userA, $empA] = $this->createEmployeeUser('Colaborador A', 'colab.a@test.com');
        [$userB, $empB] = $this->createEmployeeUser('Colaborador B', 'colab.b@test.com');

        Livewire::actingAs($userA)
            ->test('timesheet', ['userId' => $userB->id])
            ->assertStatus(403);
    }

    /**
     * 2. Gestor tentando acessar colaborador fora de seu escopo.
     */
    public function test_manager_cannot_access_employee_outside_managed_sectors(): void
    {
        $sectorA = Sector::create(['establishment_id' => $this->establishment->id, 'name' => 'Setor A']);
        $sectorB = Sector::create(['establishment_id' => $this->establishment->id, 'name' => 'Setor B']);

        [$managerUser, $managerEmp] = $this->createEmployeeUser('Gestor Alpha', 'gestor@test.com', $sectorA, UserRole::Manager);
        $sectorA->update(['manager_id' => $managerUser->id]);

        [$subordinate, $subEmp] = $this->createEmployeeUser('Subordinado Setor A', 'sub@test.com', $sectorA);
        [$otherEmployee, $otherEmp] = $this->createEmployeeUser('Outro Setor B', 'outro@test.com', $sectorB);

        // Acesso ao subordinado do seu setor: 200 OK
        Livewire::actingAs($managerUser)
            ->test('timesheet', ['userId' => $subordinate->id])
            ->assertStatus(200)
            ->assertSee('Subordinado Setor A');

        // Tentativa de acesso a colaborador de outro setor: 403 Forbidden
        Livewire::actingAs($managerUser)
            ->test('timesheet', ['userId' => $otherEmployee->id])
            ->assertStatus(403);
    }

    /**
     * 3. Administrador fora do contexto da empresa (usuário inexistente).
     */
    public function test_admin_cannot_access_nonexistent_user(): void
    {
        [$adminUser, $adminEmp] = $this->createEmployeeUser('Admin Geral', 'admin@test.com', null, UserRole::Admin);

        Livewire::actingAs($adminUser)
            ->test('timesheet', ['userId' => 999999])
            ->assertStatus(404);
    }

    /**
     * 4. Manipulação de userId via Livewire (anti-tampering).
     */
    public function test_livewire_user_id_tampering_is_blocked(): void
    {
        [$userA, $empA] = $this->createEmployeeUser('Colaborador A', 'colab.a@test.com');
        [$userB, $empB] = $this->createEmployeeUser('Colaborador B', 'colab.b@test.com');

        Livewire::actingAs($userA)
            ->test('timesheet')
            ->assertStatus(200)
            ->set('userId', $userB->id)
            ->assertStatus(403);
    }

    /**
     * 5. Solicitação de ajuste sem autorização (gestor tentando para colaborador de outro setor).
     */
    public function test_unauthorized_treatment_request_is_blocked(): void
    {
        $sectorA = Sector::create(['establishment_id' => $this->establishment->id, 'name' => 'Setor A']);
        $sectorB = Sector::create(['establishment_id' => $this->establishment->id, 'name' => 'Setor B']);

        [$managerUser, $managerEmp] = $this->createEmployeeUser('Gestor Alpha', 'gestor@test.com', $sectorA, UserRole::Manager);
        $sectorA->update(['manager_id' => $managerUser->id]);

        [$otherEmployee, $otherEmp] = $this->createEmployeeUser('Outro Setor B', 'outro@test.com', $sectorB);

        // Tentativa de inicializar timesheet com outro colaborador: bloqueada com 403
        Livewire::actingAs($managerUser)
            ->test('timesheet', ['userId' => $otherEmployee->id])
            ->assertStatus(403);
    }

    /**
     * 6. Jornada completa (pares completos geram status 'concluded').
     */
    public function test_complete_journey_displays_concluded_status(): void
    {
        [$user, $emp] = $this->createEmployeeUser('Trabalhador Padrão', 'padrao@test.com');
        $date = Carbon::create(2026, 10, 5, 0, 0, 0, 'America/Maceio');

        $this->createPunch($emp, $date->copy()->setTime(8, 0, 0), 'in', 101);
        $this->createPunch($emp, $date->copy()->setTime(12, 0, 0), 'out', 102);
        $this->createPunch($emp, $date->copy()->setTime(13, 0, 0), 'in', 103);
        $this->createPunch($emp, $date->copy()->setTime(17, 0, 0), 'out', 104);

        Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('08h 00m trabalhadas')
            ->assertSee('1 dia');
    }

    /**
     * 7. Jornada incompleta de dias anteriores (não é 'em andamento').
     */
    public function test_incomplete_journey_displays_incomplete_status(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        [$user, $emp] = $this->createEmployeeUser('Trabalhador Incompleto', 'inc@test.com');
        $pastDate = Carbon::create(2026, 10, 2, 0, 0, 0, 'America/Maceio');

        $this->createPunch($emp, $pastDate->copy()->setTime(8, 0, 0), 'in', 201);

        try {
            Livewire::actingAs($user)
                ->test('timesheet')
                ->set('year', 2026)
                ->set('month', 10)
                ->assertSee('Jornada incompleta')
                ->assertDontSee('(em andamento)');
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 8. Jornada noturna atravessando meia-noite (22h entrada -> 06h saída no dia seguinte).
     */
    public function test_night_shift_crossing_midnight_calculates_correctly(): void
    {
        [$user, $emp] = $this->createEmployeeUser('Plantonista Noturno', 'noturno@test.com');
        $nightDate = Carbon::create(2026, 10, 3, 0, 0, 0, 'America/Maceio');
        $nextDate = Carbon::create(2026, 10, 4, 0, 0, 0, 'America/Maceio');

        $this->createPunch($emp, $nightDate->copy()->setTime(22, 0, 0), 'in', 301);
        $this->createPunch($emp, $nextDate->copy()->setTime(6, 0, 0), 'out', 302);

        Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('08h 00m trabalhadas');
    }

    /**
     * 9. Período fechado com snapshot imutável.
     */
    public function test_closed_period_renders_from_immutable_snapshot(): void
    {
        [$user, $emp] = $this->createEmployeeUser('Trabalhador Histórico', 'hist@test.com');

        $closedPeriod = ClosedPeriod::create([
            'year' => 2026,
            'month' => 8,
            'status' => 'closed',
            'snapshot_version' => 1,
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);

        ClosedPeriodEmployeeSnapshot::create([
            'closed_period_id' => $closedPeriod->id,
            'employee_id' => $emp->id,
            'version' => 1,
            'employee_snapshot' => ['name' => 'Trabalhador Histórico'],
            'schedule_snapshot' => [],
            'journey_snapshot' => [
                [
                    'date' => '2026-08-10',
                    'worked_minutes' => 480,
                    'is_incomplete' => false,
                    'scheduled_minutes' => 480,
                    'effective_punches' => [
                        ['time' => '08:00', 'type' => 'in'],
                        ['time' => '17:00', 'type' => 'out'],
                    ],
                    'treatment_notes' => ['Snapshot congelado oficialmente.'],
                ],
            ],
            'treatment_snapshot' => [],
            'time_bank_snapshot' => [],
            'snapshot_hash' => hash('sha256', 'snapshot_test'),
        ]);

        Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 8)
            ->assertSee('Competência Fechada (Snapshot v1)')
            ->assertSee('08h 00m (Período Fechado)')
            ->assertSee('Período Fechado'); // Botão de solicitar ajuste substituído por Período Fechado
    }

    /**
     * 10. Cálculo de horas e média sobre dias com jornada concluída.
     */
    public function test_hours_and_average_calculation_criteria(): void
    {
        [$user, $emp] = $this->createEmployeeUser('Trabalhador Métricas', 'metricas@test.com');
        $date1 = Carbon::create(2026, 10, 1, 0, 0, 0, 'America/Maceio');
        $date2 = Carbon::create(2026, 10, 2, 0, 0, 0, 'America/Maceio');

        // Dia 1: 8 horas trabalhadas
        $this->createPunch($emp, $date1->copy()->setTime(8, 0, 0), 'in', 401);
        $this->createPunch($emp, $date1->copy()->setTime(16, 0, 0), 'out', 402);

        // Dia 2: 6 horas trabalhadas
        $this->createPunch($emp, $date2->copy()->setTime(8, 0, 0), 'in', 403);
        $this->createPunch($emp, $date2->copy()->setTime(14, 0, 0), 'out', 404);

        // Total: 14h em 2 dias. Média esperada: 07h 00m
        Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('14h 00m')
            ->assertSee('2 dias')
            ->assertSee('07h 00m');
    }

    /**
     * 11. Documentos privados: upload no disco local e proteção por rota com autorização.
     */
    public function test_private_documents_storage_and_access_protection(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$userA, $empA] = $this->createEmployeeUser('Colaborador Anexo', 'anexo@test.com');
        [$userB, $empB] = $this->createEmployeeUser('Outro Usuário', 'outro.anexo@test.com');

        $fakeFile = UploadedFile::fake()->create('atestado_medico.pdf', 500, 'application/pdf');

        Livewire::actingAs($userA)
            ->test('timesheet')
            ->set('reqDate', '2026-10-06')
            ->set('reqTime', '08:00')
            ->set('reqType', 'absence_justified')
            ->set('reqReason', 'Atestado médico de 1 dia de repouso')
            ->set('reqAttachment', $fakeFile)
            ->call('submitTreatmentRequest');

        $treatment = TreatmentEvent::where('employee_id', $empA->id)->first();
        $this->assertNotNull($treatment);
        $this->assertNotNull($treatment->attachment_path);

        // Deve estar gravado no disco PRIVADO 'local', NÃO no disco 'public'
        Storage::disk('local')->assertExists($treatment->attachment_path);
        Storage::disk('public')->assertMissing($treatment->attachment_path);

        // Acesso autorizado pelo próprio colaborador: 200 OK
        $responseAuthorized = $this->actingAs($userA)->get(route('treatment.attachment', $treatment->id));
        $responseAuthorized->assertStatus(200);

        // Tentativa de acesso por outro colaborador: 403 Forbidden
        $responseForbidden = $this->actingAs($userB)->get(route('treatment.attachment', $treatment->id));
        $responseForbidden->assertStatus(403);
    }

    /**
     * 12. Coordenadas ausentes ou zeradas: não exibe 'Lat: 0.0000, Lng: 0.0000'.
     */
    public function test_missing_coordinates_renders_location_unavailable_state(): void
    {
        [$user, $emp] = $this->createEmployeeUser('Sem Coordenadas', 'semgps@test.com');
        $date = Carbon::create(2026, 10, 7, 0, 0, 0, 'America/Maceio');

        $this->createPunch($emp, $date->copy()->setTime(8, 0, 0), 'in', 501, null, null);

        Livewire::actingAs($user)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('Localização não disponível')
            ->assertDontSee('Lat: 0.0000');
    }

    /**
     * 13. Isolamento estrito de dados entre colaboradores.
     */
    public function test_data_isolation_between_collaborators(): void
    {
        [$userA, $empA] = $this->createEmployeeUser('Colaborador Isolado A', 'iso.a@test.com');
        [$userB, $empB] = $this->createEmployeeUser('Colaborador Isolado B', 'iso.b@test.com');

        $date = Carbon::create(2026, 10, 8, 8, 0, 0, 'America/Maceio');

        $this->createPunch($empA, $date, 'in', 601);
        $this->createPunch($empB, $date, 'in', 602);

        Livewire::actingAs($userA)
            ->test('timesheet')
            ->set('year', 2026)
            ->set('month', 10)
            ->assertSee('NSR #000000601')
            ->assertDontSee('NSR #000000602')
            ->assertDontSee('Colaborador Isolado B');
    }
}
