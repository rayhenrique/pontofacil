<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'qr_location_valid',
        'location_valid',
        'payload_hash',
        'previous_event_hash',
        'created_at',
    ];

    protected $casts = [
        'nsr' => 'integer',
        'occurred_at_utc' => 'datetime',
        'occurred_at_local' => 'datetime',
        'created_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_accuracy' => 'float',
        'qr_location_valid' => 'boolean',
        'location_valid' => 'boolean',
    ];

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
}
