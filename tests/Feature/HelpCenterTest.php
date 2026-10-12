<?php

namespace Tests\Feature;

use App\Domain\Help\Services\HelpCenterService;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Servidor de Teste',
            'email' => 'servidor@teste.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);
    }

    public function test_help_center_loads_with_search_and_quick_access_cards(): void
    {
        $response = $this->actingAs($this->user)->get(route('help'));

        $response->assertOk();
        $response->assertSee('Central de Ajuda');
        $response->assertSee('Encontre instruções para utilizar o PontoFácil.');
        $response->assertSee('Buscar ajuda: ponto, escala, QR Code...');
        $response->assertSee('Como registrar meu ponto');
        $response->assertSee('Configurar empresa e setores');
        $response->assertSee('Criar horários e plantões');
        $response->assertSee('Conferir frequência mensal');
        $response->assertSee('Gerar relatório consolidado');
        $response->assertSee('Instalar o PontoFácil no celular');
        $response->assertSee('Perguntas frequentes');
    }

    public function test_deep_linking_with_article_query_parameter_renders_article(): void
    {
        $response = $this->actingAs($this->user)->get(route('help', ['artigo' => 'como-registrar-ponto']));

        $response->assertOk();
        $response->assertSee('Como Registrar meu Ponto');
        $response->assertSee('Bater Ponto');
        $response->assertSee('Fechar Guia');
    }

    public function test_deep_linking_with_tab_query_parameters(): void
    {
        // FAQ
        $resFaq = $this->actingAs($this->user)->get(route('help', ['tab' => 'faq']));
        $resFaq->assertOk();
        $resFaq->assertSee('Perguntas Frequentes (FAQ)');
        $resFaq->assertSee('O PontoFácil calcula salário');

        // Glossário
        $resGlossary = $this->actingAs($this->user)->get(route('help', ['tab' => 'glossary']));
        $resGlossary->assertOk();
        $resGlossary->assertSee('Glossário Normativo');
        $resGlossary->assertSee('REP-P');
        $resGlossary->assertSee('PTRP');
        $resGlossary->assertSee('NSR');
    }

    public function test_livewire_search_filters_articles_and_faqs(): void
    {
        Livewire::actingAs($this->user)
            ->test('help')
            ->set('search', 'QR Code')
            ->assertSee('Resultados para')
            ->assertSee('Como Registrar meu Ponto')
            ->assertSee('O leitor diz que não consegue acessar a câmera');
    }

    public function test_livewire_selecting_and_clearing_article(): void
    {
        Livewire::actingAs($this->user)
            ->test('help')
            ->call('selectArticle', 'conferencia-mensal-e-fechamento')
            ->assertSet('selectedArticleId', 'conferencia-mensal-e-fechamento')
            ->assertSee('Conferência Mensal de Frequência e Fechamento')
            ->call('selectArticle', null)
            ->assertSet('selectedArticleId', null)
            ->assertSee('Acesso Rápido — Guias mais procurados');
    }

    public function test_help_center_service_provides_all_required_articles_and_faqs(): void
    {
        $service = app(HelpCenterService::class);

        $articles = $service->getArticles();
        $this->assertGreaterThanOrEqual(11, count($articles));

        $faqs = $service->getFaq();
        $this->assertGreaterThanOrEqual(40, count($faqs));

        $glossary = $service->getGlossary();
        $this->assertGreaterThanOrEqual(10, count($glossary));

        // Test specific article integrity
        $pontoArt = $service->getArticle('como-registrar-ponto');
        $this->assertNotNull($pontoArt);
        $this->assertStringContainsString('Como Registrar', $pontoArt['title']);
        $this->assertNotEmpty($pontoArt['html']);

        // Test search
        $results = $service->search('PWA');
        $this->assertGreaterThan(0, $results['total']);
    }
}
