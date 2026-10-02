<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_app_locale_is_configured_to_pt_br(): void
    {
        $this->assertEquals('pt_BR', App::getLocale());
    }

    public function test_validation_messages_are_translated_to_pt_br(): void
    {
        $validator = Validator::make([], [
            'name' => 'required',
            'email' => 'required',
        ]);

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->all();

        $this->assertContains('O campo nome é obrigatório.', $errors);
        $this->assertContains('O campo e-mail é obrigatório.', $errors);
    }

    public function test_carbon_dates_format_in_portuguese(): void
    {
        $date = Carbon::create(2026, 10, 1, 12, 0, 0);
        $this->assertEquals('outubro', $date->translatedFormat('F'));
        $this->assertEquals('quinta-feira', $date->translatedFormat('l'));
    }

    public function test_app_timezone_is_configured_to_america_maceio(): void
    {
        $this->assertEquals('America/Maceio', config('app.timezone'));
        $this->assertEquals('-03:00', Carbon::now()->format('P'));
    }
}
