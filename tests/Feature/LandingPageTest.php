<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_renders_successfully_at_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('PontoFácil');
        $response->assertSee('Adequado à Portaria 671 / MTP');
        $response->assertSee('https://kltecnologia.com');
    }

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('PontoFácil');
        $response->assertSee('Adequado à Portaria 671 / MTP');
        $response->assertSee('https://kltecnologia.com');
    }

    public function test_landing_page_contains_portaria_671_badge_and_key_sections(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        // Hero Section
        $response->assertSee('Do Caos da Folha à');
        $response->assertSee('Precisão em 1 Segundo');
        $response->assertSee('REP-A Oficial');

        // Comparativo
        $response->assertSee('O Jeito Antigo');
        $response->assertSee('Com o PontoFácil');

        // Recursos (Tríade)
        $response->assertSee('Validação Cruzada Inviolável');
        $response->assertSee('Espelho Automatizado em Tempo Real');
        $response->assertSee('Auditoria Imutável (Portaria 671)');

        // Demonstração Interativa
        $response->assertSee('Visão do Colaborador');
        $response->assertSee('Visão do Gestor / RH');
        $response->assertSee('gsap-scanner-laser');

        // Timeline
        $response->assertSee('v1.5.0');
        $response->assertSee('Estrutura Híbrida Inteligente de Setores');

        // Footer & Copyright
        $response->assertSee('KL Tecnologia');
    }

    public function test_landing_page_cta_buttons_link_to_login_route(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee(route('login'));
    }

    public function test_login_page_contains_link_to_landing(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('landing'));
    }
}
