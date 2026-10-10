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
        $response->assertSee('Arquitetura preparada para a Portaria 671');
        $response->assertSee('https://kltecnologia.com');
    }

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('PontoFácil');
        $response->assertSee('Arquitetura preparada para a Portaria 671');
        $response->assertSee('https://kltecnologia.com');
    }

    public function test_landing_page_contains_portaria_671_badge_and_key_sections(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();

        // 1. Hero Section & Mockup da Tela Real do PontoFácil
        $response->assertSee('Ponto eletrônico simples para o colaborador');
        $response->assertSee('Gestão completa para a empresa');
        $response->assertSee('Controle de jornada digital • REP-P + PTRP');
        $response->assertSee('Olá, Carlos');
        $response->assertSee('Você ainda não registrou a saída');
        $response->assertSee('Registrar ponto');
        $response->assertSee('Último registro:');
        $response->assertSee('Localização registrada');
        $response->assertSee('Comprovante disponível após a marcação');
        $response->assertDontSee('ponto bloqueado por GPS');
        $response->assertDontSee('somente dentro do raio');
        $response->assertDontSee('GPS obrigatório');

        // 2. Como Funciona (Fluxo em 4 etapas)
        $response->assertSee('Como funciona o PontoFácil');
        $response->assertSee('Colaborador registra o ponto');
        $response->assertSee('O sistema gera o registro e o comprovante');
        $response->assertSee('Gestores acompanham jornadas e ocorrências');
        $response->assertSee('RH trata ajustes, banco de horas e fechamento');

        // 3. Colaborador x Empresa (Para quem registra vs Para quem gerencia)
        $response->assertSee('Colaborador x Empresa');
        $response->assertSee('Para quem registra');
        $response->assertSee('Para quem gerencia');
        // Itens do Colaborador
        $response->assertSee('Registrar ponto:');
        $response->assertSee('Visualizar histórico:');
        $response->assertSee('Receber comprovantes:');
        $response->assertSee('Consultar espelho:');
        $response->assertSee('Solicitar ajustes:');
        // Itens da Empresa / Gestão
        $response->assertSee('Acompanhar jornadas:');
        $response->assertSee('Tratar ocorrências:');
        $response->assertSee('Banco de horas:');
        $response->assertSee('Fechamento:');
        $response->assertSee('Relatórios:');
        $response->assertSee('Auditoria:');

        // 4. Principais Recursos
        $response->assertSee('Tudo o que sua gestão precisa em um único sistema');
        $response->assertSee('Espelho Automatizado em Tempo Real');
        $response->assertSee('Banco de Horas e Fechamento Ágil');
        $response->assertSee('QR Code Dinâmico por Setor');
        $response->assertSee('Relatórios e Integração com Folha');

        // 5. Segurança e Rastreabilidade (Linguagem técnica e confiável)
        $response->assertSee('Rastreabilidade desde o registro e trilha de auditoria');
        $response->assertSee('Registros imutáveis da ARP e NSR sequencial');
        $response->assertSee('Integridade com Hash SHA-256');
        $response->assertSee('Registro bruto separado do tratamento');
        $response->assertSee('Comprovantes e histórico de marcações');
        $response->assertSee('Validar comprovante');

        // Ausência de termos exagerados e promessas absolutas
        $response->assertDontSee('Arquitetura Blindada');
        $response->assertDontSee('Máxima Segurança Jurídica');
        $response->assertDontSee('Proteção jurídica total');
        $response->assertDontSee('Inviolável');
        $response->assertDontSee('segurança jurídica irrefutável');

        // Garantir que a localização nunca é apresentada como motivo para bloquear a marcação
        $response->assertDontSee('o registro só é aceito quando');
        $response->assertDontSee('fim das fraudes de localização');
        $response->assertDontSee('cerca virtual obrigatória');
        $response->assertDontSee('ponto bloqueado fora do raio');
        $response->assertDontSee('Bloqueia registros fora da filial');

        // 6. Portaria 671 (Estrutura clara, sóbria e sem promessas regulatórias indevidas)
        $response->assertSee('Estrutura preparada para a Portaria 671');
        $response->assertSee('REP-P registra os eventos brutos');
        $response->assertSee('PTRP trata jornadas, ocorrências e fechamento');
        $response->assertSee('Registros de ponto não são alterados pelo tratamento');
        $response->assertSee('Comprovantes são gerados após a marcação');
        $response->assertSee('Trilhas de auditoria são preservadas');
        $response->assertSee('INPI e ICP-Brasil como etapas complementares');
        $response->assertSee('Portaria 671 / MTP');

        // Ausência de declarações regulatórias indevidas
        $response->assertDontSee('homologado pelo MTE');
        $response->assertDontSee('homologação MTE');
        $response->assertDontSee('INPI concluído');
        $response->assertDontSee('assinatura ICP-Brasil ativa');
        $response->assertDontSee('conformidade definitiva');
        $response->assertDontSee('tranquilidade jurídica completa');

        // 7. Demonstração Interativa
        $response->assertSee('Simplicidade na Ponta do Dedo');
        $response->assertSee('Visão do Colaborador');
        $response->assertSee('Visão do Gestor / RH');
        $response->assertSee('gsap-scanner-laser');

        // 8. CTA Final & Footer
        $response->assertSee('Sua gestão de ponto pronta para a nova era');
        $response->assertSee('Comece agora mesmo');
        $response->assertSee('KL Tecnologia');
        $response->assertDontSee('shadow-2xl');

        // Garantir que a timeline técnica de versões não está presente na landing page comercial
        $response->assertDontSee('id="evolucao"', false);
        $response->assertDontSee('#evolucao');
        $response->assertDontSee('gsap-timeline-item');
        $response->assertDontSee('Estrutura Híbrida Inteligente de Setores');
        $response->assertDontSee('Evolução & Versões');

        // Validação da ordem estrita de renderização das 8 seções
        $content = $response->getContent();
        $posHero = strpos($content, 'id="hero"');
        $posHowItWorks = strpos($content, 'id="como-funciona"');
        $posComparison = strpos($content, 'id="para-empresas"');
        $posFeatures = strpos($content, 'id="recursos"');
        $posSecurity = strpos($content, 'id="seguranca"');
        $posCompliance = strpos($content, 'id="portaria-671"');
        $posPreview = strpos($content, 'id="demonstracao"');
        $posCta = strpos($content, 'id="contato"');

        $this->assertNotFalse($posHero, 'Seção Hero não encontrada');
        $this->assertNotFalse($posHowItWorks, 'Seção Como Funciona não encontrada');
        $this->assertNotFalse($posComparison, 'Seção Para Colaborador/Empresa não encontrada');
        $this->assertNotFalse($posFeatures, 'Seção Recursos não encontrada');
        $this->assertNotFalse($posSecurity, 'Seção Segurança não encontrada');
        $this->assertNotFalse($posCompliance, 'Seção Portaria 671 não encontrada');
        $this->assertNotFalse($posPreview, 'Seção Demonstração não encontrada');
        $this->assertNotFalse($posCta, 'Seção CTA Final não encontrada');

        $this->assertTrue($posHero < $posHowItWorks, 'Hero deve vir antes de Como Funciona');
        $this->assertTrue($posHowItWorks < $posComparison, 'Como Funciona deve vir antes de Para Empresas');
        $this->assertTrue($posComparison < $posFeatures, 'Para Empresas deve vir antes de Recursos');
        $this->assertTrue($posFeatures < $posSecurity, 'Recursos deve vir antes de Segurança');
        $this->assertTrue($posSecurity < $posCompliance, 'Segurança deve vir antes de Portaria 671');
        $this->assertTrue($posCompliance < $posPreview, 'Portaria 671 deve vir antes de Demonstração');
        $this->assertTrue($posPreview < $posCta, 'Demonstração deve vir antes de CTA Final');
    }

    public function test_landing_page_cta_buttons_link_to_login_route(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee(route('login'));
    }

    public function test_landing_page_navbar_has_mobile_first_links_and_no_saas_rep_a(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        // Links desktop e mobile
        $response->assertSee('PontoFácil');
        $response->assertSee('REP-P + PTRP');
        $response->assertSee('Recursos');
        $response->assertSee('Para empresas');
        $response->assertSee('Segurança');
        $response->assertSee('Portaria 671');
        $response->assertSee('Verificar comprovante');
        $response->assertSee('Entrar');
        $response->assertSee('Entrar no sistema');

        // Ausência de termos obsoletos e seções removidas
        $response->assertDontSee('SaaS REP-A');
        $response->assertDontSee('Evolução');
    }

    public function test_login_page_contains_link_to_landing(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('landing'));
    }
}
