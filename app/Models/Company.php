<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        'inpi_registration_number',
        'inpi_registration_date',
        'inpi_registration_status',
        'inpi_certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'inpi_registration_date' => 'date',
        ];
    }

    /**
     * O preenchimento definitivo do número depende do registro formal junto ao INPI.
     * Enquanto não registrado, o status permanece como 'pending_registration'.
     */
    public function isRegisteredInpi(): bool
    {
        return $this->inpi_registration_status === 'registered' && ! empty($this->inpi_registration_number);
    }

    /**
     * Retorna a identificação oficial para os arquivos fiscais e comprovantes (17 caracteres).
     * Nunca substitui por nome ou versão do software caso o registro esteja pendente.
     */
    public function getInpiFiscalCode(): string
    {
        if ($this->isRegisteredInpi()) {
            return mb_str_pad(mb_substr((string) $this->inpi_registration_number, 0, 17), 17, ' ', STR_PAD_RIGHT);
        }

        return mb_str_pad('PENDENTE REGISTRO', 17, ' ', STR_PAD_RIGHT);
    }

    /**
     * Retorna a URL pública do logotipo ou null se não configurado ou arquivo inexistente.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        $cleanPath = ltrim($this->logo_path, '/');

        // Se o arquivo não existir fisicamente no storage público nem no public_path,
        // retorna null para evitar que o navegador renderize um ícone de imagem quebrada
        if (! Storage::disk('public')->exists($cleanPath) && ! file_exists(public_path('storage/'.$cleanPath))) {
            return null;
        }

        return asset('storage/'.$cleanPath);
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
