<?php

namespace Database\Seeders;

use App\Domain\Company\Services\CurrentCompany;
use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $isProduction = app()->environment('production');

        if ($isProduction) {
            // Em produção, contas administrativas NUNCA recebem credenciais padrão/fixas.
            // Permite provisionamento inicial seguro se credenciais explícitas forem fornecidas via ENV.
            $initialEmail = env('INITIAL_ADMIN_EMAIL');
            $initialPassword = env('INITIAL_ADMIN_PASSWORD');

            if ($initialEmail && $initialPassword) {
                if (strlen($initialPassword) < 12) {
                    throw new \RuntimeException('INITIAL_ADMIN_PASSWORD em produção deve conter no mínimo 12 caracteres.');
                }

                User::firstOrCreate(
                    ['email' => $initialEmail],
                    [
                        'name' => env('INITIAL_ADMIN_NAME', 'Administrador do Sistema'),
                        'password' => Hash::make($initialPassword),
                        'role' => UserRole::Admin,
                    ]
                );
            }
        } else {
            // Ambiente de desenvolvimento e testes: preserva fixtures sem quebrar a suíte existente
            User::firstOrCreate(
                ['email' => 'admin@pontofacil.local'],
                [
                    'name' => 'Admin Ponto Fácil',
                    'password' => Hash::make('admin123'),
                    'role' => UserRole::Admin,
                ]
            );
        }

        $settings = [
            ['key' => 'qr_code_hash', 'value' => Str::random(40)],
            ['key' => 'company_latitude', 'value' => '-23.550520'],
            ['key' => 'company_longitude', 'value' => '-46.633308'],
            ['key' => 'allowed_radius_meters', 'value' => '100'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        CurrentCompany::get();

        $this->call(LaborRuleProfileSeeder::class);
    }
}
