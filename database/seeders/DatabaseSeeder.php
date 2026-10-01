<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SystemSetting;
use App\Enums\UserRole;
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
        User::create([
            'name' => 'Admin Ponto Fácil',
            'email' => 'admin@pontofacil.local',
            'password' => Hash::make('admin123'),
            'role' => UserRole::Admin,
        ]);

        $settings = [
            ['key' => 'qr_code_hash', 'value' => Str::random(40)],
            ['key' => 'company_latitude', 'value' => '-23.550520'],
            ['key' => 'company_longitude', 'value' => '-46.633308'],
            ['key' => 'allowed_radius_meters', 'value' => '100'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }
    }
}
