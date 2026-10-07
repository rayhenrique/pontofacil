<?php

namespace Tests\Feature;

use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ArpLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Establishment $establishment;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'legal_name' => 'Empresa Teste ARP LTDA',
            'trade_name' => 'Teste ARP',
            'cnpj' => '11222333000199',
            'rep_p_software_name' => 'PontoFácil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'MATRIZ',
            'name' => 'Sede Central',
            'identifier_type' => 'cnpj',
            'identifier_number' => '11222333000199',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Maria Analista',
            'email' => 'maria@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'cpf' => '987.654.321-99',
            'job_title' => 'Analista de Compliance',
            'admission_date' => '2026-01-01',
        ]);
    }

    public function test_arp_generates_single_monotonic_nsr_sequence_across_diverse_event_types(): void
    {
        $arpAction = app(RecordArpEventAction::class);
        $punchAction = app(RecordPunchEventAction::class);

        // Evento 1: Mutação de Empregador / Estabelecimento (NSR 1)
        $event1 = $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::EmployerEstablishmentMutation,
            payload: ['action' => 'company_registered', 'cnpj' => $this->company->cnpj]
        );
        $this->assertSame(1, $event1->nsr);

        // Evento 2: Mutação de Trabalhador (NSR 2)
        $event2 = $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::WorkerMutation,
            user: $this->user,
            employee: $this->employee,
            payload: ['action' => 'worker_admitted', 'cpf' => $this->employee->cpf]
        );
        $this->assertSame(2, $event2->nsr);

        // Evento 3: Marcação de Ponto (NSR 3)
        $punch = $punchAction->execute(
            user: $this->user,
            direction: 'in',
            latitude: -9.665800,
            longitude: -35.735000,
            establishment: $this->establishment
        );
        $this->assertSame(3, $punch->nsr);

        // Evento 4: Sincronização de Relógio (NSR 4)
        $event4 = $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::TimeSync,
            payload: ['source' => 'ntp.br', 'offset_ms' => 12]
        );
        $this->assertSame(4, $event4->nsr);

        // Evento 5: Outra Marcação de Ponto (NSR 5)
        $punch2 = $punchAction->execute(
            user: $this->user,
            direction: 'out',
            establishment: $this->establishment
        );
        $this->assertSame(5, $punch2->nsr);

        // Verifica que o contador no estabelecimento avançou para 6
        $this->establishment->refresh();
        $this->assertSame(6, (int) $this->establishment->nsr_next);

        // Verifica que todos os 5 eventos foram registrados na tabela central arp_events
        $this->assertEquals(5, ArpEvent::where('establishment_id', $this->establishment->id)->count());

        $nsrs = ArpEvent::where('establishment_id', $this->establishment->id)
            ->orderBy('nsr', 'asc')
            ->pluck('nsr')
            ->toArray();
        $this->assertSame([1, 2, 3, 4, 5], $nsrs);

        // Verifica que a batida no punch_events compartilha o mesmo NSR da ARP (não inicia sequência própria)
        $this->assertSame(3, $punch->nsr);
        $this->assertSame(5, $punch2->nsr);
    }

    public function test_independent_establishments_maintain_isolated_monotonic_nsr_sequences(): void
    {
        $estFilial = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'FILIAL_1',
            'name' => 'Filial Arapiraca',
            'identifier_type' => 'cnpj',
            'identifier_number' => '11222333000270',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $arpAction = app(RecordArpEventAction::class);

        // Evento na Matriz: NSR 1
        $eMatriz1 = $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::EmployerEstablishmentMutation,
            payload: ['code' => 'MATRIZ']
        );
        $this->assertSame(1, $eMatriz1->nsr);

        // Evento na Filial: NSR 1
        $eFilial1 = $arpAction->execute(
            establishment: $estFilial,
            eventType: ArpEventType::EmployerEstablishmentMutation,
            payload: ['code' => 'FILIAL']
        );
        $this->assertSame(1, $eFilial1->nsr);

        // Evento 2 na Filial: NSR 2
        $eFilial2 = $arpAction->execute(
            establishment: $estFilial,
            eventType: ArpEventType::TimeSync,
            payload: ['source' => 'NTP']
        );
        $this->assertSame(2, $eFilial2->nsr);

        // Matriz ainda está no NSR 1 (próximo é 2)
        $this->establishment->refresh();
        $estFilial->refresh();
        $this->assertSame(2, (int) $this->establishment->nsr_next);
        $this->assertSame(3, (int) $estFilial->nsr_next);
    }

    public function test_arp_events_are_strictly_immutable_preventing_update_and_delete(): void
    {
        $arpAction = app(RecordArpEventAction::class);
        $event = $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::TimeSync,
            payload: ['status' => 'synced']
        );

        // Tentativa de update deve falhar com LogicException
        try {
            $event->event_type = ArpEventType::RepSensitiveEvent;
            $event->save();
            $this->fail('Deveria ter lançado LogicException ao tentar atualizar ArpEvent');
        } catch (LogicException $e) {
            $this->assertStringContainsString('imutáveis', $e->getMessage());
        }

        // Tentativa de delete deve falhar com LogicException
        try {
            $event->delete();
            $this->fail('Deveria ter lançado LogicException ao tentar deletar ArpEvent');
        } catch (LogicException $e) {
            $this->assertStringContainsString('não podem ser excluídos', $e->getMessage());
        }
    }

    public function test_establishment_cannot_be_deleted_if_arp_events_exist(): void
    {
        $arpAction = app(RecordArpEventAction::class);
        $arpAction->execute(
            establishment: $this->establishment,
            eventType: ArpEventType::EmployerEstablishmentMutation,
            payload: ['test' => true]
        );

        $this->expectException(QueryException::class);
        $this->establishment->delete();
    }
}
