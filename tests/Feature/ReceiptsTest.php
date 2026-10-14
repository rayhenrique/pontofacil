<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
    }

    public function test_record_punch_action_automatically_creates_punch_receipt_linked_one_to_one(): void
    {
        $user = User::create([
            'name' => 'Felipe Ramos',
            'email' => 'felipe@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event = $action->execute(user: $user, direction: 'in');

        $this->assertDatabaseHas('punch_receipts', [
            'punch_event_id' => $event->id,
            'signature_status' => 'unsigned',
        ]);

        $receipt = $event->receipt;
        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('PF-', $receipt->verification_code);
        $this->assertNotEmpty($receipt->receipt_hash);
        $this->assertEquals(64, strlen($receipt->receipt_hash));
    }

    public function test_employee_can_access_receipts_center_and_sees_only_own_receipts(): void
    {
        $user1 = User::create([
            'name' => 'Maria Silva',
            'email' => 'maria@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $user2 = User::create([
            'name' => 'Joao Santos',
            'email' => 'joao@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event1 = $action->execute(user: $user1, direction: 'in');
        $event2 = $action->execute(user: $user2, direction: 'in');

        $this->actingAs($user1)
            ->get(route('receipts.center'))
            ->assertOk()
            ->assertSee($event1->receipt->verification_code)
            ->assertDontSee($event2->receipt->verification_code);
    }

    public function test_admin_can_access_receipts_center_and_sees_all_receipts(): void
    {
        $admin = User::create([
            'name' => 'Gestor RH',
            'email' => 'gestor@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $employee = User::create([
            'name' => 'Marcos Teste',
            'email' => 'marcos@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event = $action->execute(user: $employee, direction: 'in');

        $this->actingAs($admin)
            ->get(route('receipts.center'))
            ->assertOk()
            ->assertSee($event->receipt->verification_code)
            ->assertSee('Marcos Teste');
    }

    public function test_employee_can_download_own_receipt_pdf(): void
    {
        $user = User::create([
            'name' => 'Tiago Pereira',
            'email' => 'tiago@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event = $action->execute(user: $user, direction: 'in');
        $receipt = $event->receipt;

        $response = $this->actingAs($user)
            ->get(route('receipts.pdf', $receipt->verification_code));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_employee_cannot_download_other_employee_receipt_pdf(): void
    {
        $user1 = User::create([
            'name' => 'Usuario Um',
            'email' => 'user1@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $user2 = User::create([
            'name' => 'Usuario Dois',
            'email' => 'user2@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event2 = $action->execute(user: $user2, direction: 'in');

        $this->actingAs($user1)
            ->get(route('receipts.pdf', $event2->receipt->verification_code))
            ->assertForbidden();
    }

    public function test_public_verification_page_verifies_valid_receipt_and_validates_hash(): void
    {
        $user = User::create([
            'name' => 'Lucas Auditado',
            'email' => 'lucas@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event = $action->execute(user: $user, direction: 'in');
        $code = $event->receipt->verification_code;

        Livewire::test('receipt-verification')
            ->set('code', $code)
            ->call('verify')
            ->assertSet('searched', true)
            ->assertSet('hashValid', true)
            ->assertSee('Registro Autêntico e Íntegro no PontoFácil')
            ->assertSee('Lucas Auditado')
            ->assertSee($code);
    }

    public function test_public_verification_page_shows_not_found_for_invalid_code(): void
    {
        Livewire::test('receipt-verification')
            ->set('code', 'PF-FAKE-0000-0000')
            ->call('verify')
            ->assertSet('searched', true)
            ->assertSet('hashValid', false)
            ->assertSee('Comprovante Não Localizado');
    }

    public function test_admin_can_export_afd_file_via_route(): void
    {
        $admin = User::create([
            'name' => 'Admin Export',
            'email' => 'admin_export@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.export-afd'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('0000000001', $response->getContent());
        $this->assertStringContainsString('999999999', $response->getContent());
    }

    public function test_non_admin_cannot_export_afd_file_via_route(): void
    {
        $employee = User::create([
            'name' => 'Employee Normal',
            'email' => 'employee_normal@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->actingAs($employee)
            ->get(route('admin.export-afd'))
            ->assertForbidden();
    }

    public function test_search_filters_by_code_nsr_name_and_email(): void
    {
        $admin = User::create([
            'name' => 'Admin Geral',
            'email' => 'admin@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $user1 = User::create([
            'name' => 'Ana Beatriz',
            'email' => 'ana.beatriz@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $user2 = User::create([
            'name' => 'Carlos Eduardo',
            'email' => 'carlos.eduardo@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event1 = $action->execute(user: $user1, direction: 'in');
        $event2 = $action->execute(user: $user2, direction: 'in');

        $code1 = $event1->receipt->verification_code;
        $code2 = $event2->receipt->verification_code;

        // 1. Busca por código
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('search', $code1)
            ->assertSee($code1)
            ->assertDontSee($code2);

        // 2. Busca por nome
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('search', 'Carlos')
            ->assertSee($code2)
            ->assertDontSee($code1);

        // 3. Busca por e-mail
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('search', 'ana.beatriz@')
            ->assertSee($code1)
            ->assertDontSee($code2);

        // 4. Busca por NSR numérico
        $nsr1 = (string) $event1->nsr;
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('search', '#'.str_pad($nsr1, 9, '0', STR_PAD_LEFT))
            ->assertSee($code1)
            ->assertDontSee($code2);
    }

    public function test_filters_by_sector_and_establishment(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_rh@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $est1 = Establishment::first() ?? CurrentCompany::defaultEstablishment();
        $est2 = Establishment::create([
            'company_id' => $est1->company_id,
            'code' => 'EST-02',
            'name' => 'Filial Norte',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000200',
            'address' => 'Av Norte 100',
            'city' => 'Maceio',
            'state' => 'AL',
            'postal_code' => '57000000',
        ]);

        $sec1 = Sector::create([
            'establishment_id' => $est1->id,
            'name' => 'Tecnologia da Informacao',
        ]);
        $sec2 = Sector::create([
            'establishment_id' => $est2->id,
            'name' => 'Operacoes Industriais',
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $user1 = User::create([
            'name' => 'Dev TI',
            'email' => 'ti@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sec1->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'TI-01',
            'cpf' => '11122233344',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $user2 = User::create([
            'name' => 'Operador Fabril',
            'email' => 'fabril@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $user2->id,
            'sector_id' => $sec2->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'OP-02',
            'cpf' => '22233344455',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event1 = $action->execute(user: $user1, direction: 'in', establishment: $est1);
        $event2 = $action->execute(user: $user2, direction: 'in', establishment: $est2);

        $code1 = $event1->receipt->verification_code;
        $code2 = $event2->receipt->verification_code;

        // Filtro por setor
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedSectorId', $sec1->id)
            ->assertSee($code1)
            ->assertDontSee($code2);

        // Filtro por estabelecimento
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedEstablishmentId', $est2->id)
            ->assertSee($code2)
            ->assertDontSee($code1);
    }

    public function test_filters_by_direction_in_and_out(): void
    {
        $admin = User::create([
            'name' => 'Admin Direction',
            'email' => 'direction@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $user = User::create([
            'name' => 'Colaborador Sentido',
            'email' => 'sentido@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $eventIn = $action->execute(user: $user, direction: 'in');
        $eventOut = $action->execute(user: $user, direction: 'out');

        $codeIn = $eventIn->receipt->verification_code;
        $codeOut = $eventOut->receipt->verification_code;

        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedDirection', 'in')
            ->assertSee($codeIn)
            ->assertDontSee($codeOut);

        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedDirection', 'out')
            ->assertSee($codeOut)
            ->assertDontSee($codeIn);
    }

    public function test_filters_by_indexable_date_range(): void
    {
        $admin = User::create([
            'name' => 'Admin Datas',
            'email' => 'datas@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $user = User::create([
            'name' => 'Colaborador Datas',
            'email' => 'colab_datas@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);

        Carbon::setTestNow(Carbon::parse('2026-08-15 08:00:00'));
        $eventAug = $action->execute(user: $user, direction: 'in');

        Carbon::setTestNow(Carbon::parse('2026-09-20 08:00:00'));
        $eventSep = $action->execute(user: $user, direction: 'in');

        Carbon::setTestNow(Carbon::parse('2026-10-10 08:00:00'));
        $eventOct = $action->execute(user: $user, direction: 'in');

        Carbon::setTestNow();

        $codeAug = $eventAug->receipt->verification_code;
        $codeSep = $eventSep->receipt->verification_code;
        $codeOct = $eventOct->receipt->verification_code;

        // Setembro 2026
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedYear', '2026')
            ->set('selectedMonth', '09')
            ->assertSee($codeSep)
            ->assertDontSee($codeAug)
            ->assertDontSee($codeOct);

        // Outubro 2026
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedYear', '2026')
            ->set('selectedMonth', '10')
            ->assertSee($codeOct)
            ->assertDontSee($codeAug)
            ->assertDontSee($codeSep);
    }

    public function test_combined_filters_with_and_logic(): void
    {
        $admin = User::create([
            'name' => 'Admin Combined',
            'email' => 'combined@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $sec = Sector::create(['establishment_id' => $est->id, 'name' => 'Financeiro']);
        $schedule = WorkSchedule::createDefault40h();

        $user1 = User::create([
            'name' => 'Bruno Fin',
            'email' => 'bruno@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sec->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'FIN-01',
            'cpf' => '33344455566',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(RecordPunchEventAction::class);

        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $eventIn = $action->execute(user: $user1, direction: 'in', establishment: $est);

        Carbon::setTestNow(Carbon::parse('2026-10-05 17:00:00'));
        $eventOut = $action->execute(user: $user1, direction: 'out', establishment: $est);

        Carbon::setTestNow();

        $codeIn = $eventIn->receipt->verification_code;
        $codeOut = $eventOut->receipt->verification_code;

        // Combina: Setor Financeiro + Sentido IN + Outubro 2026 + busca "Bruno"
        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('selectedSectorId', $sec->id)
            ->set('selectedDirection', 'in')
            ->set('selectedYear', '2026')
            ->set('selectedMonth', '10')
            ->set('search', 'Bruno')
            ->assertSee($codeIn)
            ->assertDontSee($codeOut);
    }

    public function test_pagination_resets_when_any_filter_changes(): void
    {
        $admin = User::create([
            'name' => 'Admin Paginator',
            'email' => 'paginator@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $component = Livewire::actingAs($admin)->test('receipts-center');

        $component->call('gotoPage', 3);
        $component->set('search', 'qualquer');
        $this->assertEquals(1, $component->get('paginators')['page'] ?? 1);

        $component->call('gotoPage', 2);
        $component->set('selectedMonth', '05');
        $this->assertEquals(1, $component->get('paginators')['page'] ?? 1);

        $component->call('gotoPage', 2);
        $component->set('selectedDirection', 'in');
        $this->assertEquals(1, $component->get('paginators')['page'] ?? 1);
    }

    public function test_manager_profile_strictly_restricted_to_managed_sectors(): void
    {
        $manager = User::create([
            'name' => 'Gestora Lúcia',
            'email' => 'lucia.gestora@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $otherManager = User::create([
            'name' => 'Outro Gestor',
            'email' => 'outro.gestor@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $secManaged = Sector::create(['establishment_id' => $est->id, 'name' => 'Setor Sob Gestao', 'manager_id' => $manager->id]);
        $secOther = Sector::create(['establishment_id' => $est->id, 'name' => 'Setor Alheio', 'manager_id' => $otherManager->id]);
        $schedule = WorkSchedule::createDefault40h();

        $subordinate = User::create([
            'name' => 'Subordinado Meu',
            'email' => 'meu.sub@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $subordinate->id,
            'sector_id' => $secManaged->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'SUB-01',
            'cpf' => '44455566677',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $outsider = User::create([
            'name' => 'Funcionario Alheio',
            'email' => 'alheio@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $outsider->id,
            'sector_id' => $secOther->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'ALH-02',
            'cpf' => '55566677788',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(RecordPunchEventAction::class);
        $eventSub = $action->execute(user: $subordinate, direction: 'in', establishment: $est);
        $eventOutsider = $action->execute(user: $outsider, direction: 'in', establishment: $est);

        $codeSub = $eventSub->receipt->verification_code;
        $codeOutsider = $eventOutsider->receipt->verification_code;

        // Gestora vê seu subordinado e NÃO vê funcionário de setor alheio
        Livewire::actingAs($manager)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->assertSee($codeSub)
            ->assertDontSee($codeOutsider);
    }

    public function test_manager_cannot_view_unauthorized_receipts_via_forged_ids(): void
    {
        $manager = User::create([
            'name' => 'Gestor Roberto',
            'email' => 'roberto@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $secManaged = Sector::create(['establishment_id' => $est->id, 'name' => 'Setor Gestor Roberto', 'manager_id' => $manager->id]);
        $secOther = Sector::create(['establishment_id' => $est->id, 'name' => 'Setor Outro']);
        $schedule = WorkSchedule::createDefault40h();

        $outsider = User::create([
            'name' => 'Secretário Alheio',
            'email' => 'sec.alheio@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $outsider->id,
            'sector_id' => $secOther->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'SEC-09',
            'cpf' => '66677788899',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(RecordPunchEventAction::class);
        $eventOutsider = $action->execute(user: $outsider, direction: 'in', establishment: $est);
        $codeOutsider = $eventOutsider->receipt->verification_code;

        // 1. Forjando selectedUserId com o ID do funcionário alheio
        Livewire::actingAs($manager)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedUserId', $outsider->id)
            ->assertDontSee($codeOutsider);

        // 2. Forjando selectedSectorId com o ID do setor alheio
        Livewire::actingAs($manager)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedSectorId', $secOther->id)
            ->assertDontSee($codeOutsider);
    }

    public function test_employee_profile_strictly_restricted_to_own_receipts_even_with_tampered_properties(): void
    {
        $employee1 = User::create([
            'name' => 'Colaborador Um',
            'email' => 'colab1@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $employee2 = User::create([
            'name' => 'Colaborador Dois',
            'email' => 'colab2@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event1 = $action->execute(user: $employee1, direction: 'in');
        $event2 = $action->execute(user: $employee2, direction: 'in');

        $code1 = $event1->receipt->verification_code;
        $code2 = $event2->receipt->verification_code;

        // Forjando selectedUserId para apontar para employee2
        Livewire::actingAs($employee1)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->set('selectedUserId', $employee2->id)
            ->assertSee($code1)
            ->assertDontSee($code2);
    }

    public function test_auditor_profile_has_read_only_access_across_installation(): void
    {
        $auditor = User::create([
            'name' => 'Auditor Fiscal MTE',
            'email' => 'auditor@mte.gov.br',
            'password' => 'secret123',
            'role' => UserRole::Auditor,
        ]);

        $user1 = User::create([
            'name' => 'Trabalhador Alpha',
            'email' => 'alpha@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $user2 = User::create([
            'name' => 'Trabalhador Beta',
            'email' => 'beta@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $event1 = $action->execute(user: $user1, direction: 'in');
        $event2 = $action->execute(user: $user2, direction: 'in');

        $code1 = $event1->receipt->verification_code;
        $code2 = $event2->receipt->verification_code;

        // Auditor acessa comprovantes e pode filtrar normalmente
        Livewire::actingAs($auditor)
            ->test('receipts-center')
            ->set('selectedMonth', '')
            ->set('selectedYear', '')
            ->assertSee($code1)
            ->assertSee($code2)
            ->assertDontSee('Exportar AFD MTE'); // Apenas leitura/auditoria, sem ação administrativa de exportar AFD
    }

    public function test_manager_cannot_download_other_sector_receipt_pdf_or_print(): void
    {
        $manager = User::create([
            'name' => 'Gestor Setorial',
            'email' => 'gestor.setorial@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Manager,
        ]);

        $est = CurrentCompany::defaultEstablishment();
        $secManaged = Sector::create(['establishment_id' => $est->id, 'name' => 'Meu Setor', 'manager_id' => $manager->id]);
        $secOther = Sector::create(['establishment_id' => $est->id, 'name' => 'Outro Setor']);
        $schedule = WorkSchedule::createDefault40h();

        $myEmployee = User::create([
            'name' => 'Meu Liderado',
            'email' => 'liderado@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $myEmployee->id,
            'sector_id' => $secManaged->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MY-01',
            'cpf' => '77788899900',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $otherEmployee = User::create([
            'name' => 'Outro Liderado',
            'email' => 'outro.liderado@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
        Employee::create([
            'user_id' => $otherEmployee->id,
            'sector_id' => $secOther->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'OT-02',
            'cpf' => '88899900011',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $action = app(RecordPunchEventAction::class);
        $eventMy = $action->execute(user: $myEmployee, direction: 'in', establishment: $est);
        $eventOther = $action->execute(user: $otherEmployee, direction: 'in', establishment: $est);

        $codeMy = $eventMy->receipt->verification_code;
        $codeOther = $eventOther->receipt->verification_code;

        // Gestor pode baixar PDF do seu liderado
        $this->actingAs($manager)
            ->get(route('receipts.pdf', $codeMy))
            ->assertOk();

        // Gestor NÃO pode baixar PDF de liderado de outro setor (403)
        $this->actingAs($manager)
            ->get(route('receipts.pdf', $codeOther))
            ->assertForbidden();

        // Gestor NÃO pode imprimir HTML de liderado de outro setor (403)
        $this->actingAs($manager)
            ->get(route('receipts.print', $codeOther))
            ->assertForbidden();
    }

    public function test_clear_filters_button_resets_all_criteria(): void
    {
        $admin = User::create([
            'name' => 'Admin Reset',
            'email' => 'reset@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        Livewire::actingAs($admin)
            ->test('receipts-center')
            ->set('search', 'termo')
            ->set('selectedMonth', '03')
            ->set('selectedYear', '2025')
            ->set('selectedDirection', 'out')
            ->set('selectedSectorId', 10)
            ->set('selectedEstablishmentId', 5)
            ->set('selectedUserId', 20)
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('selectedMonth', '')
            ->assertSet('selectedYear', '')
            ->assertSet('selectedDirection', '')
            ->assertSet('selectedSectorId', null)
            ->assertSet('selectedEstablishmentId', null)
            ->assertSet('selectedUserId', null);
    }
}
