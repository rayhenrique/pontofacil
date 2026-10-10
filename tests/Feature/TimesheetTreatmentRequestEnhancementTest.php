<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
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

class TimesheetTreatmentRequestEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Establishment $establishment;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        CurrentCompany::clear();
        $this->company = Company::create([
            'legal_name' => 'Empresa PontoFácil S/A',
            'trade_name' => 'PontoFácil Corporativo',
            'cnpj' => '12345678000199',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Sede Central',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000199',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);
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
            'cpf' => sprintf('222333%05d', $user->id),
            'job_title' => 'Analista de Operações',
        ]);

        return [$user, $employee];
    }

    protected function createPunch(Employee $employee, Carbon $time, string $direction = 'in', int $nsr = 1): PunchEvent
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
            'latitude' => -9.6658,
            'longitude' => -35.7351,
            'fiscal_hash' => hash('sha256', (string) $nsr),
            'audit_chain_hash' => hash('sha256', 'chain'.$nsr),
            'payload_hash' => hash('sha256', 'payload'.$nsr),
        ]);
    }

    /**
     * 1. Redesenhar funcionalmente o modal de solicitações e campos contextuais.
     */
    public function test_dynamic_form_modal_opening_and_contextual_fields(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Ana Lima', 'ana.lima@test.com');
        $punch = $this->createPunch($employee, Carbon::create(2026, 10, 7, 12, 11));

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->call('openTreatmentModal', '2026-10-07', 'manual_punch_added')
            ->assertSet('showTreatmentModal', true)
            ->assertSet('reqType', TreatmentEventType::ManualPunchAdded->value)
            ->assertSet('reqDate', '2026-10-07')
            // Abrir para marcação específica via botão contextual
            ->call('openTreatmentModalForPunch', $punch->id)
            ->assertSet('showTreatmentModal', true)
            ->assertSet('reqType', TreatmentEventType::PunchDisregarded->value)
            ->assertSet('reqDate', '2026-10-07')
            ->assertSet('reqReferencePunchId', $punch->id)
            ->assertCount('availablePunchesForDate', 1);
    }

    /**
     * 2. Inclusão de marcação esquecida (solicita data, horário, justificativa, anexo opcional).
     */
    public function test_manual_punch_request_creation_with_optional_attachment(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->createEmployeeUser('Bruno Silva', 'bruno.silva@test.com');

        $attachment = UploadedFile::fake()->create('comprovante_reuniao.pdf', 300, 'application/pdf');

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::ManualPunchAdded->value)
            ->set('reqDate', '2026-10-08')
            ->set('reqTime', '08:00')
            ->set('reqReason', 'Esquecimento de registro no retorno do almoço com cliente')
            ->set('reqAttachment', $attachment)
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors()
            ->assertSet('showTreatmentModal', false)
            ->assertDispatched('app-modal-alert');

        $treatment = TreatmentEvent::where('employee_id', $employee->id)->first();
        $this->assertNotNull($treatment);
        $this->assertEquals(TreatmentEventType::ManualPunchAdded, $treatment->type);
        $this->assertEquals(TreatmentEventStatus::Pending, $treatment->status);
        $this->assertEquals('2026-10-08', $treatment->effective_at->format('Y-m-d'));
        $this->assertEquals('08:00', $treatment->payload['suggested_time'] ?? null);
        $this->assertNotNull($treatment->attachment_path);
        Storage::disk('local')->assertExists($treatment->attachment_path);
    }

    /**
     * 3. Justificativa de ausência não exige horário de batida para justificar o dia.
     */
    public function test_absence_justification_does_not_require_punch_time(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Clara Nunes', 'clara.nunes@test.com');

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::AbsenceJustified->value)
            ->set('reqDate', '2026-10-09')
            ->set('reqTime', '') // Não preenche horário de batida
            ->set('reqReasonCategory', 'court_summons')
            ->set('reqReason', 'Convocação da Justiça Eleitoral como mesária')
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors()
            ->assertSet('showTreatmentModal', false);

        $treatment = TreatmentEvent::where('employee_id', $employee->id)->first();
        $this->assertNotNull($treatment);
        $this->assertEquals(TreatmentEventType::AbsenceJustified, $treatment->type);
        $this->assertEquals('court_summons', $treatment->reason_code);
        $this->assertEquals(TreatmentEventStatus::Pending, $treatment->status);
    }

    /**
     * 4. Desconsideração de marcação vincula reference_punch_id e NUNCA exclui a marcação original.
     */
    public function test_disregard_punch_links_reference_punch_id_and_does_not_delete_original_raw_punch(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Daniel Rocha', 'daniel.rocha@test.com');
        $rawPunch = $this->createPunch($employee, Carbon::create(2026, 10, 10, 14, 30), 'in', 42);

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->call('openTreatmentModalForPunch', $rawPunch->id)
            ->set('reqReason', 'Registro duplicado por reflexo no leitor biométrico')
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors()
            ->assertSet('showTreatmentModal', false);

        $treatment = TreatmentEvent::where('reference_punch_id', $rawPunch->id)->first();
        $this->assertNotNull($treatment);
        $this->assertEquals(TreatmentEventType::PunchDisregarded, $treatment->type);
        $this->assertEquals($rawPunch->id, $treatment->reference_punch_id);

        // A marcação original NUNCA pode ser excluída do REP-P (fato bruto imutável)
        $this->assertDatabaseHas('punch_events', ['id' => $rawPunch->id]);

        // Mesmo após aprovação pelo PTRP, o PunchEvent permanece intacto no ledger
        $admin = User::create([
            'name' => 'Admin Fiscal',
            'email' => 'admin.fiscal@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        app(ApproveTreatmentEventAction::class)->execute($treatment, $admin, 'Aprovada desconsideração por duplicidade');

        $this->assertDatabaseHas('punch_events', ['id' => $rawPunch->id]);
        $this->assertEquals(TreatmentEventStatus::Approved, $treatment->fresh()->status);
    }

    /**
     * 5. Solicitação em período fechado é rejeitada com mensagem amigável.
     */
    public function test_treatment_request_blocked_in_closed_period(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Eduardo Reis', 'eduardo.reis@test.com');

        // Cria período fechado para o mês 09/2026
        ClosedPeriod::create([
            'company_id' => $this->company->id,
            'establishment_id' => $this->establishment->id,
            'year' => 2026,
            'month' => 9,
            'closed_at' => now(),
            'closed_by' => $user->id,
            'snapshot_version' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 9, 'year' => 2026])
            ->set('reqType', TreatmentEventType::ManualPunchAdded->value)
            ->set('reqDate', '2026-09-15')
            ->set('reqTime', '09:00')
            ->set('reqReason', 'Tentativa de ajuste em competência fechada')
            ->call('submitTreatmentRequest')
            ->assertHasErrors(['reqDate'])
            ->assertDispatched('app-modal-alert');

        $this->assertDatabaseMissing('treatment_events', [
            'employee_id' => $employee->id,
            'effective_at' => '2026-09-15 09:00:00',
        ]);
    }

    /**
     * 6. Anexos obrigatórios para atestado médico e opcionais para outros motivos.
     */
    public function test_attachment_validation_rules_aligned_with_policy(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->createEmployeeUser('Fernanda Lima', 'fernanda.lima@test.com');

        // Tentativa de submeter atestado médico sem anexo: deve falhar na validação
        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::AbsenceJustified->value)
            ->set('reqDate', '2026-10-12')
            ->set('reqReasonCategory', 'medical_certificate')
            ->set('reqReason', 'Atestado de consulta ortopédica')
            ->set('reqAttachment', null)
            ->call('submitTreatmentRequest')
            ->assertHasErrors(['reqAttachment']);

        // Submetendo com anexo válido: deve ter sucesso
        $pdf = UploadedFile::fake()->create('atestado_medico.pdf', 500, 'application/pdf');

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::AbsenceJustified->value)
            ->set('reqDate', '2026-10-12')
            ->set('reqReasonCategory', 'medical_certificate')
            ->set('reqReason', 'Atestado de consulta ortopédica com comprovante')
            ->set('reqAttachment', $pdf)
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors()
            ->assertSet('showTreatmentModal', false);

        // Outro motivo (ex: falecimento) NÃO exige anexo obrigatoriamente
        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::AbsenceJustified->value)
            ->set('reqDate', '2026-10-13')
            ->set('reqReasonCategory', 'bereavement')
            ->set('reqReason', 'Licença nojo / luto de parente de primeiro grau')
            ->set('reqAttachment', null)
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors()
            ->assertSet('showTreatmentModal', false);
    }

    /**
     * 7. Prevenção de submissão duplicada para mesma batida ou mesma solicitação pendente.
     */
    public function test_duplicate_pending_treatment_submission_is_prevented(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Gabriel Costa', 'gabriel.costa@test.com');
        $punch = $this->createPunch($employee, Carbon::create(2026, 10, 14, 8, 5), 'in', 88);

        $component = Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->call('openTreatmentModalForPunch', $punch->id)
            ->set('reqReason', 'Primeira solicitação de desconsideração')
            ->call('submitTreatmentRequest')
            ->assertHasNoErrors();

        $this->assertEquals(1, TreatmentEvent::where('reference_punch_id', $punch->id)->count());

        // Segunda tentativa para a mesma batida enquanto a primeira estiver pendente
        $component->call('openTreatmentModalForPunch', $punch->id)
            ->set('reqReason', 'Segunda tentativa duplicada')
            ->call('submitTreatmentRequest')
            ->assertSee('Já existe uma solicitação pendente');

        $this->assertEquals(1, TreatmentEvent::where('reference_punch_id', $punch->id)->count());
    }

    /**
     * 8. A solicitação permanece como Pending e não aplica aprovação automática.
     */
    public function test_treatment_request_remains_pending_without_auto_approval(): void
    {
        [$user, $employee] = $this->createEmployeeUser('Helena Castro', 'helena.castro@test.com');

        Livewire::actingAs($user)
            ->test('timesheet', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->set('reqType', TreatmentEventType::ManualPunchAdded->value)
            ->set('reqDate', '2026-10-15')
            ->set('reqTime', '17:30')
            ->set('reqReason', 'Esqueci de registrar saída no retorno externo')
            ->call('submitTreatmentRequest');

        $treatment = TreatmentEvent::where('employee_id', $employee->id)->first();
        $this->assertNotNull($treatment);
        $this->assertEquals(TreatmentEventStatus::Pending, $treatment->status);
        $this->assertNull($treatment->approved_by);
        $this->assertNull($treatment->decided_at);
        $this->assertEquals($user->id, $treatment->requested_by);
    }

    /**
     * 9. Dados institucionais ausentes na folha de ponto exibem aviso sem inventar dados fiscais.
     */
    public function test_folha_ponto_displays_incomplete_configuration_notice_when_company_data_missing(): void
    {
        // Limpa CNPJ e dados cadastrais da empresa e estabelecimento
        $this->company->update([
            'cnpj' => '',
            'trade_name' => '',
            'legal_name' => '',
        ]);
        $this->establishment->update([
            'identifier_number' => '',
            'address' => '',
        ]);
        CurrentCompany::clear();

        [$user, $employee] = $this->createEmployeeUser('Igor Santos', 'igor.santos@test.com');

        Livewire::actingAs($user)
            ->test('folha-ponto', ['userId' => $user->id, 'month' => 10, 'year' => 2026])
            ->assertSee('Configuração cadastral da empresa incompleta')
            ->assertSee('CNPJ da Empresa')
            ->assertDontSee('PREFEITURA MUNICIPAL DE TEOTÔNIO VILELA');
    }

    /**
     * 10. Acesso à folha por URL manipulada com userId de terceiro é bloqueado com 403.
     */
    public function test_folha_ponto_blocks_unauthorized_user_id_url_manipulation(): void
    {
        [$userA, $empA] = $this->createEmployeeUser('Julia Silva', 'julia.silva@test.com');
        [$userB, $empB] = $this->createEmployeeUser('Lucas Souza', 'lucas.souza@test.com');

        // Tentativa de acesso via GET com query parameter manipulado
        $response = $this->actingAs($userA)->get(route('folha-ponto', ['userId' => $userB->id, 'month' => 10, 'year' => 2026]));
        $response->assertForbidden();

        // Tentativa de alteração via Livewire público
        Livewire::actingAs($userA)
            ->test('folha-ponto', ['userId' => $userA->id, 'month' => 10, 'year' => 2026])
            ->set('userId', $userB->id)
            ->assertForbidden();
    }

    /**
     * 11. Preservação do período e colaborador ao navegar entre o Espelho e a Folha de Ponto.
     */
    public function test_period_and_employee_parameters_preserved_across_navigation(): void
    {
        $admin = User::create([
            'name' => 'Coordenador RH',
            'email' => 'rh.coord@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        [$colab, $emp] = $this->createEmployeeUser('Marcos Santos', 'marcos.santos@test.com');

        // No espelho de ponto, o link de navegação para a folha de ponto preserva userId, month e year
        $timesheet = Livewire::actingAs($admin)
            ->test('timesheet', ['userId' => $colab->id, 'month' => 8, 'year' => 2026])
            ->assertSee('Imprimir folha de ponto')
            ->assertSee(e(route('folha-ponto', ['userId' => $colab->id, 'month' => 8, 'year' => 2026])), false);

        // Na folha de ponto, o botão de voltar preserva userId, month e year
        $folha = Livewire::actingAs($admin)
            ->test('folha-ponto', ['userId' => $colab->id, 'month' => 8, 'year' => 2026])
            ->assertSee(e(route('timesheet', ['userId' => $colab->id, 'month' => 8, 'year' => 2026])), false);
    }
}
