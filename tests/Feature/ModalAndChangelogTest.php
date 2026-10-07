<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModalAndChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_renders_with_styled_changelog_and_current_version(): void
    {
        $user = User::create([
            'name' => 'Colaborador Teste',
            'email' => 'colab@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $response = $this->actingAs($user)->get(route('help'));
        $response->assertOk();
        $response->assertSee('Novidades e Versões');
        $response->assertSee('v2.0.0');
        $response->assertSee('Versão Atual');
        $response->assertSee('Manual do Usuário');
    }

    public function test_version_notifier_detects_new_version_modal(): void
    {
        $user = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_notifier@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
            'last_seen_version' => 'v2.3.0',
        ]);

        $test = Livewire::actingAs($user)
            ->test('version-notifier')
            ->assertSet('showModal', true)
            ->assertSet('currentVersion', 'v2.4.0')
            ->assertSee('PontoFácil v2.4.0')
            ->assertSee('Compliance Portaria MTP 671/2021')
            ->call('close')
            ->assertSet('showModal', false);

        $user->refresh();
        $this->assertEquals('v2.4.0', $user->last_seen_version);
    }

    public function test_version_notifier_does_not_show_modal_if_user_already_viewed_current_version(): void
    {
        $user = User::create([
            'name' => 'Colaborador Atualizado',
            'email' => 'colab_updated@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
            'last_seen_version' => 'v2.4.0',
        ]);

        Livewire::actingAs($user)
            ->test('version-notifier')
            ->assertSet('showModal', false)
            ->assertSet('currentVersion', 'v2.4.0');
    }

    public function test_help_page_changelog_items_are_properly_parsed_without_empty_melhoria_titles(): void
    {
        $user = User::create([
            'name' => 'Colaborador Teste',
            'email' => 'colab_parse@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $response = $this->actingAs($user)->get(route('help'));
        $response->assertOk();
        $response->assertSee('Motor de Tratamento PTRP, Jornadas, Tolerância Legal &amp; Banco de Horas', false);
        $response->assertSee('treatment_events');
        $response->assertSee('work_schedules');
        $response->assertSee('CalculateDailyJourneyAction');
        $response->assertDontSee('>Melhoria<', false);
    }

    public function test_help_page_manual_tab_renders_updated_features(): void
    {
        $user = User::create([
            'name' => 'Colaborador Teste',
            'email' => 'colab_manual@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $response = $this->actingAs($user)->get(route('help', ['tab' => 'manual']));
        $response->assertOk();
        $response->assertSee('Central de Comprovantes');
        $response->assertSee('PTRP — Tratamento de Ponto');
        $response->assertSee('Banco de Horas em Ledger');
        $response->assertSee('Folha de Ponto Oficial A4');
        $response->assertSee('Tolerância Legal');
    }

    public function test_login_page_renders_with_kl_tecnologia_link(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('KL Tecnologia');
        $response->assertSee('https://kltecnologia.com');
        $response->assertSee('PontoFácil');
    }
}
