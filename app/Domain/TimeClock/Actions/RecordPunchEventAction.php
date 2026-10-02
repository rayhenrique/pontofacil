<?php

namespace App\Domain\TimeClock\Actions;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\Receipt\GeneratePunchReceiptAction;
use App\Domain\TimeClock\Services\NsrGeneratorService;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RecordPunchEventAction
{
    public function __construct(
        protected NsrGeneratorService $nsrGenerator
    ) {}

    /**
     * Registra o fato bruto da batida de ponto no ledger imutável (punch_events).
     *
     * @param  User  $user  Colaborador autenticado
     * @param  string  $direction  'in' ou 'out'
     * @param  float|null  $latitude  Coordenada capturada
     * @param  float|null  $longitude  Coordenada capturada
     * @param  float|null  $accuracy  Precisão em metros
     * @param  bool|null  $qrLocationValid  Validação do QR Code
     * @param  bool|null  $locationValid  Validação do raio Geofence
     * @param  string|null  $source  Canal de coleta ('web_pwa', 'mobile_app', etc.)
     * @param  Establishment|null  $establishment  Estabelecimento de lotação (se null, usa Matriz padrão)
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
        ?Establishment $establishment = null
    ): PunchEvent {
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $establishment) {
            $establishment = $employee?->sector?->establishment ?? CurrentCompany::defaultEstablishment();
        }

        // Obtém a última marcação deste estabelecimento para encadeamento criptográfico de hash
        $previousEvent = PunchEvent::where('establishment_id', $establishment->id)
            ->orderBy('nsr', 'desc')
            ->first();

        $previousHash = $previousEvent?->payload_hash;

        // Reserva atômica do próximo NSR monotônico
        $nsr = $this->nsrGenerator->reserveNextNsr($establishment->id);

        $tz = $establishment->timezone ?: 'America/Maceio';
        $nowUtc = Carbon::now('UTC');
        $nowLocal = Carbon::now($tz);
        $utcOffset = $nowLocal->format('P'); // ex: "-03:00"

        // Geração do Hash SHA-256 da carga útil encadeada
        $payloadRaw = sprintf(
            '%d|%d|%d|%s|%s|%s',
            $establishment->id,
            $user->id,
            $nsr,
            $nowUtc->toIso8601String(),
            $direction,
            $previousHash ?? 'GENESIS_PONTOFACIL_2.0'
        );

        $payloadHash = hash('sha256', $payloadRaw);

        $event = PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $establishment->id,
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
            'qr_location_valid' => $qrLocationValid,
            'location_valid' => $locationValid,
            'payload_hash' => $payloadHash,
            'previous_event_hash' => $previousHash,
            'created_at' => $nowUtc,
        ]);

        try {
            $receipt = app(GeneratePunchReceiptAction::class)->execute($event);
            $event->setRelation('receipt', $receipt);
        } catch (\Throwable $e) {
            report($e);
        }

        return $event;
    }
}
