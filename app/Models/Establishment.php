<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'identifier_type',
        'identifier_number',
        'address',
        'city',
        'state',
        'postal_code',
        'timezone',
        'nsr_next',
    ];

    protected $casts = [
        'nsr_next' => 'integer',
    ];

    /**
     * Retorna o fuso horário oficial do estabelecimento ou o fallback configurado na aplicação.
     */
    public function resolvedTimezone(): string
    {
        return ! empty($this->timezone) ? $this->timezone : config('app.timezone', 'America/Maceio');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function punchEvents(): HasMany
    {
        return $this->hasMany(PunchEvent::class);
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }
}
