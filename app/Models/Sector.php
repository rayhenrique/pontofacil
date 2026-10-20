<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = [
        'establishment_id',
        'name',
        'description',
        'manager_id',
        'qr_code_hash',
        'latitude',
        'longitude',
        'allowed_radius_meters',
        'gps_enforcement_enabled',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'allowed_radius_meters' => 'integer',
            'gps_enforcement_enabled' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function hasCustomLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasCustomQrCode(): bool
    {
        return ! empty($this->qr_code_hash);
    }
}
