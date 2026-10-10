<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PunchEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'establishment_id',
        'employee_id',
        'user_id',
        'nsr',
        'occurred_at_utc',
        'occurred_at_local',
        'timezone',
        'utc_offset',
        'direction',
        'source',
        'collector_type',
        'latitude',
        'longitude',
        'location_accuracy',
        'location_distance_meters',
        'qr_location_valid',
        'location_valid',
        'fiscal_hash',
        'audit_chain_hash',
        'previous_audit_hash',
        'payload_hash',
        'idempotency_key',
        'previous_event_hash',
        'created_at',
    ];

    protected $casts = [
        'nsr' => 'integer',
        'created_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_accuracy' => 'float',
        'location_distance_meters' => 'float',
        'qr_location_valid' => 'boolean',
        'location_valid' => 'boolean',
    ];

    public function getOccurredAtUtcAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    public function getOccurredAtLocalAttribute($value): ?Carbon
    {
        $tz = $this->timezone ?: config('app.timezone', 'America/Maceio');

        return $value ? Carbon::parse($value, $tz) : null;
    }

    /**
     * Trava de Imutabilidade Estrita sob a Portaria 671/2021 MTP:
     * O fato bruto de registro de ponto nunca pode ser atualizado nem apagado.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('O registro em punch_events é estritamente imutável sob a Portaria 671/2021 MTP.');
        });

        static::deleting(function () {
            throw new \LogicException('O registro em punch_events não pode ser excluído sob a Portaria 671/2021 MTP.');
        });
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PunchReceipt::class, 'punch_event_id', 'id');
    }

    public function arpEvent(): HasOne
    {
        return $this->hasOne(ArpEvent::class, 'reference_id', 'id');
    }
}
