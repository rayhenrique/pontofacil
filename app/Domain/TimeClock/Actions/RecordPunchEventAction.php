<?php

namespace App\Domain\TimeClock\Actions;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\Compliance\Fiscal\Services\AuditChainHashService;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\Compliance\Receipt\GeneratePunchReceiptAction;
use App\Models\ArpEvent;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPunchEventAction
{
    public function __construct(
        protected FiscalHashService $fiscalHashService,
        protected AuditChainHashService $auditChainHashService
    ) {}

    /**
     * Registra o fato bruto da batida de ponto no ledger fiscal oficial da ARP (arp_events)
     * e no repositório de domínio (punch_events), projetando atomicamente para time_entries.
     *
     * @param  User  $user  Colaborador autenticado
     * @param  string  $direction  'in' ou 'out'
     * @param  float|null  $latitude  Coordenada capturada
     * @param  float|null  $longitude  Coordenada capturada
     * @param  float|null  $accuracy  Precisão em metros
     * @param  bool|null  $qrLocationValid  Validação do QR Code
     * @param  bool|null  $locationValid  Validação do raio Geofence (evidência antifraude)
     * @param  string|null  $source  Canal de coleta ('web_pwa', 'mobile_app', etc.)
     * @param  Establishment|null  $establishment  Estabelecimento de lotação (se null, usa Matriz padrão)
     * @param  float|null  $locationDistanceMeters  Distância calculada em metros do perímetro autorizado
     */
    public function execute(
        User $user,
        string $direction,
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $accuracy = null,
        ?bool $qrLocationValid = null,
        ?bool $locationValid = null,
        ?string $source = 'web_pwa',
        ?Establishment $establishment = null,
        ?float $locationDistanceMeters = null
    ): PunchEvent {
        return DB::transaction(function () use (
            $user,
            $direction,
            $latitude,
            $longitude,
            $accuracy,
            $qrLocationValid,
            $locationValid,
            $source,
            $establishment,
            $locationDistanceMeters
        ) {
            $employee = Employee::where('user_id', $user->id)->first();

            if (! $establishment) {
                $establishment = $employee?->sector?->establishment ?? CurrentCompany::defaultEstablishment();
            }

            // 1. Reserva atômica do NSR monotônico com lock pessimista no estabelecimento
            $est = Establishment::where('id', $establishment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nsr = (int) $est->nsr_next;
            $est->nsr_next = $nsr + 1;
            $est->save();

            // 2. Busca o hash anterior para o encadeamento interno da auditoria
            $previousArp = ArpEvent::where('establishment_id', $est->id)
                ->orderBy('nsr', 'desc')
                ->first();

            $previousAuditHash = $previousArp?->audit_chain_hash;
            if ($previousAuditHash === null) {
                $previousPunch = PunchEvent::where('establishment_id', $est->id)
                    ->orderBy('nsr', 'desc')
                    ->first();
                $previousAuditHash = $previousPunch?->audit_chain_hash ?? $previousPunch?->payload_hash;
            }

            // 3. Timestamps e fuso horário oficial do estabelecimento
            $tz = $est->timezone ?: 'America/Maceio';
            $nowUtc = Carbon::now('UTC');
            $nowLocal = Carbon::now($tz);
            $utcOffset = $nowLocal->format('P'); // ex: "-03:00"

            // 4. Cálculo do Hash Fiscal Oficial MTE (Portaria 671/2021)
            $fiscalHash = $this->fiscalHashService->calculatePunchFiscalHash(
                nsr: $nsr,
                occurredAtLocal: $nowLocal,
                utcOffset: $utcOffset,
                cpf: $employee?->cpf
            );

            // 5. Cálculo do Hash Interno de Auditoria Encadeado (PontoFácil)
            $auditChainHash = $this->auditChainHashService->calculateAuditHash(
                establishmentId: $est->id,
                userId: $user->id,
                nsr: $nsr,
                occurredAtUtc: $nowUtc,
                eventTypeOrDirection: $direction,
                previousAuditHash: $previousAuditHash
            );

            $punchId = (string) Str::ulid();

            // 6. Registro no Ledger Fiscal Central da ARP (Armazenamento de Registro de Ponto)
            ArpEvent::create([
                'id' => (string) Str::ulid(),
                'establishment_id' => $est->id,
                'nsr' => $nsr,
                'event_type' => ArpEventType::Punch,
                'occurred_at_utc' => $nowUtc,
                'occurred_at_local' => $nowLocal,
                'timezone' => $tz,
                'utc_offset' => $utcOffset,
                'employee_id' => $employee?->id,
                'user_id' => $user->id,
                'fiscal_hash' => $fiscalHash,
                'audit_chain_hash' => $auditChainHash,
                'previous_audit_hash' => $previousAuditHash,
                'payload' => [
                    'direction' => $direction,
                    'source' => $source ?? 'web_pwa',
                    'collector_type' => 'browser',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_accuracy' => $accuracy,
                    'location_distance_meters' => $locationDistanceMeters,
                    'qr_location_valid' => $qrLocationValid,
                    'location_valid' => $locationValid,
                ],
                'reference_type' => 'punch_events',
                'reference_id' => $punchId,
                'created_at' => $nowUtc,
            ]);

            // 7. Registro no Repositório Oficial de Domínio (punch_events)
            $punchEvent = PunchEvent::create([
                'id' => $punchId,
                'establishment_id' => $est->id,
                'employee_id' => $employee?->id,
                'user_id' => $user->id,
                'nsr' => $nsr,
                'occurred_at_utc' => $nowUtc,
                'occurred_at_local' => $nowLocal,
                'timezone' => $tz,
                'utc_offset' => $utcOffset,
                'direction' => $direction,
                'source' => $source ?? 'web_pwa',
                'collector_type' => 'browser',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_accuracy' => $accuracy,
                'location_distance_meters' => $locationDistanceMeters,
                'qr_location_valid' => $qrLocationValid,
                'location_valid' => $locationValid,
                'fiscal_hash' => $fiscalHash,
                'audit_chain_hash' => $auditChainHash,
                'previous_audit_hash' => $previousAuditHash,
                'payload_hash' => $auditChainHash, // Compatibilidade com encadeamento legado
                'previous_event_hash' => $previousAuditHash, // Compatibilidade com leituras legadas
                'created_at' => $nowUtc,
            ]);

            // 8. Projeção derivada para a tabela legada time_entries (não possui escrita independente)
            TimeEntry::create([
                'user_id' => $user->id,
                'timestamp' => $nowLocal,
                'type' => $direction,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy !== null ? (int) round($accuracy) : null,
                'is_manual' => false,
            ]);

            // 9. Emissão imediata do comprovante do trabalhador (Portaria 671)
            try {
                $receipt = app(GeneratePunchReceiptAction::class)->execute($punchEvent);
                $punchEvent->setRelation('receipt', $receipt);
            } catch (\Throwable $e) {
                report($e);
            }

            return $punchEvent;
        });
    }
}
