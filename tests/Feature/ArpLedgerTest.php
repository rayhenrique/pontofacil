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
        // 1. Establishment::create já gerou NSR 1 (cadastro empregador)
        // 2. Employee::create já gerou NSR 2 (cadastro trabalhador)
        $arpEvents = ArpEvent::where('establishment_id', $this->establishment->id)->orderBy('nsr')->get();
        $this->assertCount(2, $arpEvents);
        $this->assertSame(1, $arpEvents[0]->nsr);
        $this->assertSame(ArpEventType::EmployerEstablishmentMutation, $arpEvents[0]->event_type);
        $this->assertSame(2, $arpEvents[1]->nsr);
        $this->assertSame(ArpEventType::WorkerMutation, $arpEvents[1]->event_type);

        $punchAction = app(RecordPunchEventAction::class);
        $arpAction = app(RecordArpEventAction::class);

        // NSR 3 → batida (in)
        $punch1 = $punchAction->execute(
            user: $this->user,
            direction: 'in',
            latitude: -9.665800,
            longitude: -35.735000,
            establishment: $this->establishment
        );
        $this->assertSame(3, $punch1->nsr);

        // NSR 4 → batida (out)
        $punch2 = $punchAction->execute(
            user: $this->user,
            direction: 'out',
            establishment: $this->establishment
        );
        $this->assertSame(4, $punch2->nsr);

        // NSR 5 → alteração trabalhador (mutação cadastral)
        $this->employee->update(['job_title' => 'Especialista em Compliance']);
        $event5 = ArpEvent::where('establishment_id', $this->establishment->id)->where('nsr', 5)->first();
        $this->assertNotNull($event5);
        $this->assertSame(ArpEventType::WorkerMutation, $event5->event_type);
        $this->assertSame('A', $event5->payload['mutation_type']);

        // NSR 6 → evento sensível do REP-P (disponibilidade / verificação de integridade)
        $event6 = $arpAction->recordRepSensitiveEvent(
            establishment: $this->establishment,
            eventDescription: 'Verificação de integridade do ledger fiscal REP-P',
            metadata: ['event_code' => '01']
        );
        $this->assertSame(6, $event6->nsr);
        $this->assertSame(ArpEventType::RepSensitiveEvent, $event6->event_type);

        // NSR 7 → batida (in)
        $punch3 = $punchAction->execute(
            user: $this->user,
            direction: 'in',
            establishment: $this->establishment
        );
        $this->assertSame(7, $punch3->nsr);

        // Verifica que o contador no estabelecimento avançou para 8
        $this->establishment->refresh();
        $this->assertSame(8, (int) $this->establishment->nsr_next);

        // Verifica a sequência estritamente contígua e monotônica de 1 a 7
        $nsrs = ArpEvent::where('establishment_id', $this->establishment->id)
            ->orderBy('nsr', 'asc')
            ->pluck('nsr')
            ->toArray();
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $nsrs);
    }

    public function test_independent_establishments_maintain_isolated_monotonic_nsr_sequences(): void
    {
        // Matriz já possui NSR 1 (establishment) e NSR 2 (employee) de setUp
        $this->assertEquals(2, ArpEvent::where('establishment_id', $this->establishment->id)->count());

        $estFilial = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'FILIAL_1',
            'name' => 'Filial Arapiraca',
            'identifier_type' => 'cnpj',
            'identifier_number' => '11222333000270',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        // Criação da Filial gerou automaticamente seu próprio NSR 1 isolado
        $eFilial1 = ArpEvent::where('establishment_id', $estFilial->id)->first();
        $this->assertNotNull($eFilial1);
        $this->assertSame(1, $eFilial1->nsr);

        $arpAction = app(RecordArpEventAction::class);

        // Evento na Matriz: NSR 3
        $eMatriz1 = $arpAction->recordTimeSync(
            establishment: $this->establishment,
            syncDetails: ['source' => 'NTP']
        );
        $this->assertSame(3, $eMatriz1->nsr);

        // Evento 2 na Filial: NSR 2
        $eFilial2 = $arpAction->recordTimeSync(
            establishment: $estFilial,
            syncDetails: ['source' => 'NTP']
        );
        $this->assertSame(2, $eFilial2->nsr);

        // Contadores avançaram isoladamente
        $this->establishment->refresh();
        $estFilial->refresh();
        $this->assertSame(4, (int) $this->establishment->nsr_next);
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
