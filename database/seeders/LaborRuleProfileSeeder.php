<?php

namespace Database\Seeders;

use App\Models\LaborRuleProfile;
use Illuminate\Database\Seeder;

class LaborRuleProfileSeeder extends Seeder
{
    /**
     * Seed standard approved labor rule profiles.
     */
    public function run(): void
    {
        LaborRuleProfile::createStandardCltProfile();
        LaborRuleProfile::createFederalStatutoryProfile();
    }
}
