<?php

namespace App\Domain\Compliance\ARP\Actions;

use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\Compliance\Fiscal\Services\AuditChainHashService;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Models\ArpEvent;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordArpEventAction
{
    public function __construct(
        protected FiscalHashService $fiscalHashService,
        protected AuditChainHashService $auditChainHashService
    ) {}

    /**
     * Registra um evento fiscal arbitrário na ARP (Armazenamento de Registro de Ponto),
     * garantindo monotonicidade atômica do NSR por estabelecimento e encadeamento de integridade.
     */
    public function execute(
        Establishment $establishment,
        ArpEventType $eventType,
        ?User $user = null,
        ?Employee $employee = null,
        array $payload = [],
        ?string $fiscalHash = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?Carbon $customOccurredAtUtc = null
    ): ArpEvent {
        return DB::transaction(function () use (
            $establishment,
            $eventType,
            $user,
            $employee,
            $payload,
            $fiscalHash,
            $referenceType,
            $referenceId,
            $customOccurredAtUtc
        ) {
            // 1. Lock pessimista no estabelecimento para reserva atômica de NSR monotônico
            $est = Establishment::where('id', $establishment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nsr = (int) $est->nsr_next;
            $est->nsr_next = $nsr + 1;
            $est->save();

            // 2. Busca último evento na ARP para obtenção do previous_audit_hash
            $previousArpEvent = ArpEvent::where('establishment_id', $est->id)
                ->orderBy('nsr', 'desc')
                ->first();

            $previousAuditHash = $previousArpEvent?->audit_chain_hash;

            // 3. Timestamps padronizados
            $tz = $est->timezone ?: 'America/Maceio';
            $nowUtc = $customOccurredAtUtc ?: Carbon::now('UTC');
            $nowLocal = $nowUtc->copy()->setTimezone($tz);
            $utcOffset = $nowLocal->format('P');

            // 4. Determinação dos hashes
            if ($fiscalHash === null) {
                if ($eventType === ArpEventType::Punch) {
                    $previousPunchArp = ArpEvent::where('establishment_id', $est->id)
                        ->where('event_type', ArpEventType::Punch)
                        ->orderBy('nsr', 'desc')
                        ->first();
                    $previousTipo7Hash = $previousPunchArp?->fiscal_hash;

                    $collectorType = match ($payload['collector_type'] ?? $payload['source'] ?? 'browser') {
                        'mobile_app', 'mobile' => '01',
                        'desktop' => '03',
                        'device', 'hardware' => '04',
                        default => '02',
                    };

                    $punchType = ($payload['is_offline'] ?? false) ? '1' : '0';

                    $fiscalHash = $this->fiscalHashService->calculateTipo7FiscalHash(
                        nsr: $nsr,
                        occurredAtLocal: $nowLocal,
                        recordedAtLocal: $nowLocal,
                        cpf: $employee?->cpf,
                        collectorType: $collectorType,
                        punchType: $punchType,
                        previousTipo7FiscalHash: $previousTipo7Hash
                    );
                } else {
                    $fiscalHash = $this->fiscalHashService->calculateGenericArpFiscalHash(
                        $nsr,
                        $eventType->value,
                        $nowLocal,
                        $utcOffset,
                        (string) ($employee?->cpf ?? $user?->id ?? $est->identifier_number)
                    );
                }
            }

            $auditChainHash = $this->auditChainHashService->calculateAuditHash(
                establishmentId: $est->id,
                userId: $user?->id,
                nsr: $nsr,
                occurredAtUtc: $nowUtc,
                eventTypeOrDirection: $payload['direction'] ?? $eventType->value,
                previousAuditHash: $previousAuditHash
            );

            // 5. Gravação imutável no ledger fiscal da ARP
            return ArpEvent::create([
                'id' => (string) Str::ulid(),
                'establishment_id' => $est->id,
                'nsr' => $nsr,
                'event_type' => $eventType,
                'occurred_at_utc' => $nowUtc,
                'occurred_at_local' => $nowLocal,
                'timezone' => $tz,
                'utc_offset' => $utcOffset,
                'employee_id' => $employee?->id,
                'user_id' => $user?->id,
                'fiscal_hash' => $fiscalHash,
                'audit_chain_hash' => $auditChainHash,
                'previous_audit_hash' => $previousAuditHash,
                'payload' => $payload,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => $nowUtc,
            ]);
        });
    }

    /**
     * Registra evento fiscal de mutação do empregador ou do estabelecimento.
     */
    public function recordEmployerMutation(
        Establishment $establishment,
        array $details,
        ?User $actor = null
    ): ArpEvent {
        return $this->execute(
            establishment: $establishment,
            eventType: ArpEventType::EmployerEstablishmentMutation,
            user: $actor,
            payload: $details,
            referenceType: 'establishments',
            referenceId: (string) $establishment->id
        );
    }

    /**
     * Registra evento fiscal de mutação cadastral do trabalhador (inclusão, alteração, inativação).
     */
    public function recordWorkerMutation(
        Establishment $establishment,
        Employee $employee,
        string $mutationType,
        array $details,
        ?User $actor = null
    ): ArpEvent {
        return $this->execute(
            establishment: $establishment,
            eventType: ArpEventType::WorkerMutation,
            user: $actor ?? $employee->user,
            employee: $employee,
            payload: array_merge(['mutation_type' => $mutationType], $details),
            referenceType: 'employees',
            referenceId: (string) $employee->id
        );
    }

    /**
     * Registra evento de sincronização ou ajuste do relógio do sistema (NTP/tempo oficial).
     */
    public function recordTimeSync(
        Establishment $establishment,
        array $syncDetails,
        ?User $actor = null
    ): ArpEvent {
        return $this->execute(
            establishment: $establishment,
            eventType: ArpEventType::TimeSync,
            user: $actor,
            payload: $syncDetails
        );
    }

    /**
     * Registra evento sensível de integridade / operação do REP-P.
     */
    public function recordRepSensitiveEvent(
        Establishment $establishment,
        string $eventDescription,
        array $metadata = [],
        ?User $actor = null
    ): ArpEvent {
        return $this->execute(
            establishment: $establishment,
            eventType: ArpEventType::RepSensitiveEvent,
            user: $actor,
            payload: array_merge(['description' => $eventDescription], $metadata)
        );
    }
}
