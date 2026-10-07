<?php

namespace App\Domain\Company\Services;

use App\Models\Company;
use App\Models\Establishment;

class CurrentCompany
{
    protected static ?Company $instance = null;

    /**
     * Retorna a Empresa Única da instalação (Single-Tenant).
     * Se ainda não existir no banco, inicializa a empresa e o estabelecimento matriz padrão.
     */
    public static function get(): Company
    {
        if (static::$instance !== null && (! static::$instance->exists || ! Company::where('id', static::$instance->id)->exists())) {
            static::$instance = null;
        }

        if (static::$instance === null) {
            $company = Company::with('establishments')->first();

            if (! $company) {
                $company = Company::create([
                    'legal_name' => 'Empresa Matriz PontoFácil LTDA',
                    'trade_name' => 'PontoFácil Corporativo',
                    'cnpj' => '00000000000191',
                    'rep_p_software_name' => 'PontoFácil',
                    'rep_p_software_version' => '2.0.0',
                ]);

                Establishment::create([
                    'company_id' => $company->id,
                    'code' => 'MATRIZ',
                    'name' => 'Sede Principal Maceió',
                    'identifier_type' => 'cnpj',
                    'identifier_number' => '00000000000191',
                    'address' => 'Av. Fernandes Lima, 1000 - Farol',
                    'city' => 'Maceió',
                    'state' => 'AL',
                    'postal_code' => '57055-000',
                    'timezone' => 'America/Maceio',
                    'nsr_next' => 1,
                ]);

                $company->load('establishments');
            }

            static::$instance = $company;
        }

        return static::$instance;
    }

    /**
     * Retorna o estabelecimento Matriz padrão da instalação.
     */
    public static function defaultEstablishment(): Establishment
    {
        $company = static::get();
        $establishment = $company->defaultEstablishment();

        if (! $establishment) {
            $establishment = Establishment::create([
                'company_id' => $company->id,
                'code' => 'MATRIZ',
                'name' => 'Sede Principal Maceió',
                'identifier_type' => 'cnpj',
                'identifier_number' => $company->cnpj,
                'city' => 'Maceió',
                'state' => 'AL',
                'timezone' => 'America/Maceio',
                'nsr_next' => 1,
            ]);
            $company->load('establishments');
        }

        return $establishment;
    }

    /**
     * Limpa o cache estático (útil em testes automatizados).
     */
    public static function clear(): void
    {
        static::$instance = null;
    }
}
