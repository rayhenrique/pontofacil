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
        $response->assertSee('v1.8.0');
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
            'last_seen_version' => 'v1.7.0',
        ]);

        Livewire::actingAs($user)
            ->test('version-notifier')
            ->assertSet('showModal', true)
            ->assertSet('currentVersion', 'v1.8.0')
            ->call('close')
            ->assertSet('showModal', false);

        $user->refresh();
        $this->assertEquals('v1.8.0', $user->last_seen_version);
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
