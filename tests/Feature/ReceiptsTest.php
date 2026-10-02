<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\User;
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
            ->assertSee('Registro Autêntico e Íntegro no Ledger')
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
        $this->assertStringContainsString('9999999999', $response->getContent());
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
}
