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
        'logo_path',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'postal_code',
        'header_state',
        'header_entity',
        'header_sub_entity',
        'rep_p_software_name',
        'rep_p_software_version',
    ];

    /**
     * Retorna a URL pública do logotipo ou null se não configurado.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        return asset('storage/'.ltrim($this->logo_path, '/'));
    }

    /**
     * Retorna o CNPJ formatado para exibição amigável.
     */
    public function getFormattedCnpjAttribute(): string
    {
        $cnpj = preg_replace('/\D/', '', (string) $this->cnpj);
        if (strlen($cnpj) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
        }

        return (string) $this->cnpj;
    }

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
