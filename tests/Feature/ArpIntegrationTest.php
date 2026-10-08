<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Testes de Integração da ARP (Armazenamento de Registro de Ponto)
 * aos fluxos reais do sistema (Portaria 671/2021 MTP).
 */
class ArpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
    }

    /**
     * Valida o ciclo completo e integrado da ARP com a sequência monotônica esperada:
     * NSR 1 → cadastro empregador
     * NSR 2 → cadastro trabalhador
     * NSR 3 → batida (in)
     * NSR 4 → batida (out)
     * NSR 5 → alteração trabalhador
     * NSR 6 → evento sensível do REP-P
     * NSR 7 → batida (in)
     * NSR 8 → sincronização do relógio
     * NSR 9 → inativação do trabalhador
     */
    public function test_arp_integrated_with_real_system_events_produces_monotonic_sequence_1_to_9(): void
    {
        $company = Company::create([
            'legal_name' => 'ORGANIZACOES COMPLIANCE S.A.',
            'trade_name' => 'Compliance SA',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        // 1. Criação do estabelecimento → Gera automaticamente NSR 1 na ARP
        $establishment = Establishment::create([
            'company_id' => $company->id,
            'code' => 'SEDE',
            'name' => 'Sede Maceio',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $sector = Sector::create([
            'name' => 'Tecnologia',
            'establishment_id' => $establishment->id,
        ]);

        $user = User::create([
            'name' => 'Carlos Engenheiro',
            'email' => 'carlos@compliance.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        // 2. Inclusão de trabalhador → Gera automaticamente NSR 2 na ARP
        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'cpf' => '123.456.789-00',
            'registration_number' => 'ENG-001',
            'job_title' => 'Engenheiro de Software',
        ]);

        $punchAction = app(RecordPunchEventAction::class);
        $arpAction = app(RecordArpEventAction::class);

        // 3. Batida de entrada → Consome NSR 3
        $punch1 = $punchAction->execute(
            user: $user,
            direction: 'in',
            establishment: $establishment
        );
        $this->assertSame(3, $punch1->nsr);

        // 4. Batida de saída → Consome NSR 4
        $punch2 = $punchAction->execute(
            user: $user,
            direction: 'out',
            establishment: $establishment
        );
        $this->assertSame(4, $punch2->nsr);

        // 5. Alteração cadastral do trabalhador → Gera automaticamente NSR 5 na ARP
        $employee->update(['job_title' => 'Engenheiro Chefe']);

        // 6. Evento sensível do REP-P (disponibilidade) → Consome NSR 6
        $event6 = $arpAction->recordRepSensitiveEvent(
            establishment: $establishment,
            eventDescription: 'Verificacao de disponibilidade e integridade do REP-P',
            metadata: ['event_code' => '07']
        );
        $this->assertSame(6, $event6->nsr);

        // 7. Batida de entrada → Consome NSR 7
        $punch3 = $punchAction->execute(
            user: $user,
            direction: 'in',
            establishment: $establishment
        );
        $this->assertSame(7, $punch3->nsr);

        // 8. Evento de sincronização de relógio (NTP) → Consome NSR 8
        $event8 = $arpAction->recordTimeSync(
            establishment: $establishment,
            syncDetails: ['source' => 'ntp.br', 'offset_ms' => 5]
        );
        $this->assertSame(8, $event8->nsr);

        // 9. Inativação / desligamento fiscal do trabalhador → Consome NSR 9
        $employee->inactivate('Desligamento a pedido');

        // Verificações gerais do ledger fiscal
        $events = ArpEvent::where('establishment_id', $establishment->id)->orderBy('nsr', 'asc')->get();
        $this->assertCount(9, $events);

        // Confere tipos exatos e sequência estritamente contígua
        $this->assertSame(1, $events[0]->nsr);
        $this->assertSame(ArpEventType::EmployerEstablishmentMutation, $events[0]->event_type);

        $this->assertSame(2, $events[1]->nsr);
        $this->assertSame(ArpEventType::WorkerMutation, $events[1]->event_type);
        $this->assertSame('I', $events[1]->payload['mutation_type']);

        $this->assertSame(3, $events[2]->nsr);
        $this->assertSame(ArpEventType::Punch, $events[2]->event_type);

        $this->assertSame(4, $events[3]->nsr);
        $this->assertSame(ArpEventType::Punch, $events[3]->event_type);

        $this->assertSame(5, $events[4]->nsr);
        $this->assertSame(ArpEventType::WorkerMutation, $events[4]->event_type);
        $this->assertSame('A', $events[4]->payload['mutation_type']);

        $this->assertSame(6, $events[5]->nsr);
        $this->assertSame(ArpEventType::RepSensitiveEvent, $events[5]->event_type);

        $this->assertSame(7, $events[6]->nsr);
        $this->assertSame(ArpEventType::Punch, $events[6]->event_type);

        $this->assertSame(8, $events[7]->nsr);
        $this->assertSame(ArpEventType::TimeSync, $events[7]->event_type);

        $this->assertSame(9, $events[8]->nsr);
        $this->assertSame(ArpEventType::WorkerMutation, $events[8]->event_type);
        $this->assertSame('E', $events[8]->payload['mutation_type']);

        // Próximo NSR deve ser 10
        $establishment->refresh();
        $this->assertSame(10, (int) $establishment->nsr_next);

        // Imutabilidade estrita: update e delete bloqueados
        $sampleEvent = $events->first();
        try {
            $sampleEvent->delete();
            $this->fail('Deveria ter impedido delete de ArpEvent');
        } catch (LogicException $e) {
            $this->assertStringContainsString('não podem ser excluídos', $e->getMessage());
        }
    }
}
