<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'legal_name',
        'trade_name',
        'cnpj',
        'rep_p_software_name',
        'rep_p_software_version',
    ];

    /**
     * Estabelecimentos pertencentes à empresa (Matriz e Filiais).
     */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    /**
     * Retorna o estabelecimento principal (Matriz).
     */
    public function defaultEstablishment(): ?Establishment
    {
        return $this->establishments()->where('code', 'MATRIZ')->first()
            ?? $this->establishments()->first();
    }
}
