<?php

namespace App\Domain\Help\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HelpCenterService
{
    /**
     * Cache em memória dos artigos parseados durante a requisição.
     *
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $articles = null;

    /**
     * Retorna todos os artigos disponíveis ordenados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getArticles(): array
    {
        if ($this->articles === null) {
            $this->loadArticles();
        }

        $list = array_values($this->articles);
        usort($list, fn ($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));

        return $list;
    }

    /**
     * Retorna os artigos em destaque para a tela inicial rápida (como na prévia visual).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeaturedArticles(): array
    {
        $all = $this->getArticles();
        $featured = array_filter($all, fn ($art) => ! empty($art['featured']));

        return array_values($featured);
    }

    /**
     * Retorna um artigo específico pelo ID com o corpo em HTML renderizado.
     *
     * @return array<string, mixed>|null
     */
    public function getArticle(string $id): ?array
    {
        if ($this->articles === null) {
            $this->loadArticles();
        }

        return $this->articles[$id] ?? null;
    }

    /**
     * Retorna todos os itens do FAQ.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFaq(): array
    {
        $path = resource_path('help/faq.php');
        if (! File::exists($path)) {
            return [];
        }

        $items = require $path;
        foreach ($items as $idx => &$item) {
            if (! isset($item['id'])) {
                $item['id'] = $idx + 1;
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Alias para getFaq().
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFaqs(): array
    {
        return $this->getFaq();
    }

    /**
     * Retorna o Glossário de termos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGlossary(): array
    {
        $path = resource_path('help/glossary.php');
        if (! File::exists($path)) {
            return [];
        }

        return require $path;
    }

    /**
     * Retorna a lista de categorias disponíveis.
     *
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        return [
            'todos' => 'Todos os Assuntos',
            'registro' => 'Registro de Ponto',
            'configuracao' => 'Configuração Inicial',
            'escalas' => 'Horários e Escalas',
            'conferencia' => 'Conferência Mensal',
            'relatorios' => 'Relatórios de Frequência',
            'espelho' => 'Espelho e Ajustes',
            'servidores' => 'Servidores e Vínculos',
            'celular' => 'PWA e Celular',
            'organizacao' => 'Estrutura e Órgãos',
            'avancado' => 'Governança e Fiscalização',
        ];
    }

    /**
     * Retorna os perfis de acesso para filtragem.
     *
     * @return array<string, string>
     */
    public function getProfiles(): array
    {
        return [
            'all' => 'Todos os Perfis',
            'employee' => 'Servidor / Colaborador',
            'manager' => 'Gestor de Setor',
            'admin' => 'Administrador de RH',
            'auditor' => 'Auditor Fiscal',
        ];
    }

    /**
     * Executa busca inteligente e contextual por termo em artigos e FAQ.
     *
     * @return array{
     *     articles: array<int, array<string, mixed>>,
     *     faqs: array<int, array<string, mixed>>,
     *     total: int
     * }
     */
    public function search(string $query, string $category = 'todos', string $profile = 'all'): array
    {
        $term = mb_strtolower(trim($query));
        $allArticles = $this->getArticles();
        $allFaqs = $this->getFaq();

        // 1. Filtragem e pontuação de artigos
        $matchedArticles = [];
        foreach ($allArticles as $art) {
            // Filtro de perfil
            if ($profile !== 'all' && ! in_array($profile, $art['profiles'] ?? [], true)) {
                continue;
            }

            // Filtro de categoria
            if ($category !== 'todos' && ($art['category'] ?? '') !== $category) {
                continue;
            }

            if ($term === '') {
                $matchedArticles[] = $art;

                continue;
            }

            $score = 0;
            $title = mb_strtolower($art['title'] ?? '');
            $subtitle = mb_strtolower($art['subtitle'] ?? '');
            $summary = mb_strtolower($art['summary'] ?? '');
            $keywords = mb_strtolower($art['keywords'] ?? '');
            $rawContent = mb_strtolower($art['raw_content'] ?? '');

            if (str_contains($title, $term)) {
                $score += 50;
            }
            if (str_contains($keywords, $term)) {
                $score += 30;
            }
            if (str_contains($summary, $term) || str_contains($subtitle, $term)) {
                $score += 20;
            }
            if (str_contains($rawContent, $term)) {
                $score += 10;
            }

            // Busca por palavras individuais se o termo composto não casar diretamente
            if ($score === 0) {
                $words = array_filter(explode(' ', $term), fn ($w) => mb_strlen($w) >= 3);
                foreach ($words as $word) {
                    if (str_contains($title, $word) || str_contains($keywords, $word)) {
                        $score += 15;
                    } elseif (str_contains($summary, $word) || str_contains($rawContent, $word)) {
                        $score += 5;
                    }
                }
            }

            if ($score > 0) {
                $art['search_score'] = $score;
                $matchedArticles[] = $art;
            }
        }

        if ($term !== '') {
            usort($matchedArticles, fn ($a, $b) => ($b['search_score'] ?? 0) <=> ($a['search_score'] ?? 0));
        }

        // 2. Filtragem de FAQs
        $matchedFaqs = [];
        foreach ($allFaqs as $faq) {
            if ($term === '') {
                $matchedFaqs[] = $faq;

                continue;
            }

            $q = mb_strtolower($faq['question'] ?? '');
            $a = mb_strtolower($faq['answer'] ?? '');
            $c = mb_strtolower($faq['category_label'] ?? '');

            if (str_contains($q, $term) || str_contains($a, $term) || str_contains($c, $term)) {
                $matchedFaqs[] = $faq;
            }
        }

        return [
            'articles' => $matchedArticles,
            'faqs' => $matchedFaqs,
            'total' => count($matchedArticles) + count($matchedFaqs),
        ];
    }

    /**
     * Carrega e analisa os arquivos Markdown em resources/help/articles/.
     */
    protected function loadArticles(): void
    {
        $this->articles = [];
        $dir = resource_path('help/articles');

        if (! File::isDirectory($dir)) {
            return;
        }

        $files = File::files($dir);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = File::get($file->getRealPath());
            $parsed = $this->parseMarkdownFile($content);

            if (! empty($parsed['id'])) {
                $this->articles[$parsed['id']] = $parsed;
            }
        }
    }

    /**
     * Faz o parse do cabeçalho YAML-like e do corpo Markdown.
     *
     * @return array<string, mixed>
     */
    protected function parseMarkdownFile(string $content): array
    {
        $meta = [];
        $body = $content;

        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $matches)) {
            $rawMeta = $matches[1];
            $body = $matches[2];

            $lines = explode("\n", $rawMeta);
            $currentListKey = null;

            foreach ($lines as $line) {
                $line = rtrim($line, "\r");
                $trimmed = trim($line);

                if ($trimmed === '') {
                    continue;
                }

                // Subitens de lista YAML (ex: "- employee")
                if (str_starts_with($trimmed, '- ') && $currentListKey !== null) {
                    $itemVal = trim(substr($trimmed, 2));
                    $meta[$currentListKey][] = $itemVal;

                    continue;
                }

                // Chave-valor normal (ex: "title: Como registrar meu ponto")
                if (str_contains($line, ':')) {
                    [$key, $val] = explode(':', $line, 2);
                    $key = trim($key);
                    $val = trim($val);

                    if ($val === '') {
                        // Início de lista
                        $currentListKey = $key;
                        $meta[$key] = [];
                    } else {
                        $currentListKey = null;
                        // Tratamento de booleanos e números
                        if (strtolower($val) === 'true') {
                            $meta[$key] = true;
                        } elseif (strtolower($val) === 'false') {
                            $meta[$key] = false;
                        } elseif (is_numeric($val)) {
                            $meta[$key] = (int) $val;
                        } else {
                            $meta[$key] = trim($val, '"\'');
                        }
                    }
                }
            }
        }

        // Renderização segura do markdown para HTML usando CommonMark nativo do Laravel
        $html = Str::markdown($body);

        $meta['raw_content'] = strip_tags($html);
        $meta['html'] = $html;

        return $meta;
    }
}
