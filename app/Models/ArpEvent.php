<?php

namespace App\Models;

use App\Domain\Compliance\ARP\Enums\ArpEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArpEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'establishment_id',
        'nsr',
        'event_type',
        'occurred_at_utc',
        'occurred_at_local',
        'timezone',
        'utc_offset',
        'employee_id',
        'user_id',
        'fiscal_hash',
        'audit_chain_hash',
        'previous_audit_hash',
        'payload',
        'reference_type',
        'reference_id',
        'created_at',
    ];

    protected $casts = [
        'nsr' => 'integer',
        'event_type' => ArpEventType::class,
        'occurred_at_utc' => 'datetime',
        'occurred_at_local' => 'datetime',
        'created_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Trava de Imutabilidade Estrita da ARP (Portaria 671/2021 MTP):
     * Os registros fiscais da ARP são perpétuos e inalteráveis.
     * É estritamente proibido atualizar (UPDATE) ou apagar (DELETE) qualquer evento na ARP.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Os registros fiscais na ARP são estritamente imutáveis sob a Portaria 671/2021 MTP.');
        });

        static::deleting(function () {
            throw new \LogicException('Os registros fiscais na ARP não podem ser excluídos sob a Portaria 671/2021 MTP.');
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
