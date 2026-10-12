<?php

use App\Domain\Help\Services\HelpCenterService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Central de Ajuda e Manual')] class extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'manual';

    #[Url(as: 'artigo')]
    public ?string $selectedArticleId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public string $selectedCategory = 'todos';

    public string $selectedProfile = 'all';

    public string $currentVersionTag = 'v2.6.2';

    public function mount(HelpCenterService $helpService): void
    {
        $tabParam = request()->query('tab');
        if ($tabParam === 'manual') {
            $this->activeTab = 'manual';
        } elseif ($tabParam === 'changelog') {
            $this->activeTab = 'changelog';
        } elseif ($tabParam === 'faq') {
            $this->activeTab = 'faq';
        } elseif ($tabParam === 'glossary') {
            $this->activeTab = 'glossary';
        }

        $artigoParam = request()->query('artigo');
        if (! empty($artigoParam)) {
            $this->selectedArticleId = $artigoParam;
            $this->activeTab = 'manual';
        }

        $versions = $this->getParsedChangelog();
        if (! empty($versions)) {
            foreach ($versions as $v) {
                if (! empty($v['is_current'])) {
                    $this->currentVersionTag = $v['tag'];
                    break;
                }
            }
            if (empty($this->currentVersionTag) && ! empty($versions[0]['tag'])) {
                $this->currentVersionTag = $versions[0]['tag'];
            }
        }
    }

    public function selectArticle(?string $id): void
    {
        $this->selectedArticleId = $id;
        $this->activeTab = 'manual';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        if ($tab !== 'manual') {
            $this->selectedArticleId = null;
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function openFaqFromQuickAccess(): void
    {
        $this->activeTab = 'faq';
        $this->selectedArticleId = null;
    }

    public function getParsedChangelog(): array
    {
        $path = base_path('versoes.md');
        if (! File::exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $versions = [];
        $currentVersion = null;
        $currentItem = null;

        foreach ($lines as $line) {
            $rawTrimmed = trim($line);
            if (empty($rawTrimmed)) {
                continue;
            }

            if (str_starts_with($rawTrimmed, '## ')) {
                if ($currentItem && $currentVersion) {
                    $currentVersion['items'][] = $currentItem;
                    $currentItem = null;
                }
                if ($currentVersion) {
                    $versions[] = $currentVersion;
                }

                $vTitle = trim(substr($rawTrimmed, 3));
                $isCurrent = str_contains($vTitle, '(Atual)');
                $versionTag = trim(str_replace('(Atual)', '', $vTitle));

                $currentVersion = [
                    'tag' => $versionTag,
                    'is_current' => $isCurrent,
                    'items' => [],
                ];
                continue;
            }

            if (! $currentVersion) {
                continue;
            }

            $isIndented = (bool) preg_match('/^\s+-\s+/', $line);
            $trimmed = trim($line);

            if (! $isIndented && str_starts_with($trimmed, '- ')) {
                if ($currentItem) {
                    $currentVersion['items'][] = $currentItem;
                    $currentItem = null;
                }

                $text = substr($trimmed, 2);
                if (preg_match('/^\*\*(.*?)\*\*[:\-]?\s*(.*)$/', $text, $m)) {
                    $title = rtrim(trim($m[1]), ':');
                    $desc = trim($m[2] ?? '');
                } else {
                    $title = $text;
                    $desc = '';
                }

                $currentItem = [
                    'title' => $title,
                    'description' => $desc,
                    'subitems' => [],
                ];
            } elseif ($isIndented && str_starts_with($trimmed, '- ')) {
                $subText = trim(substr($trimmed, 2));
                if ($currentItem) {
                    $currentItem['subitems'][] = $subText;
                } else {
                    $currentItem = [
                        'title' => 'Geral',
                        'description' => '',
                        'subitems' => [$subText],
                    ];
                }
            }
        }

        if ($currentItem && $currentVersion) {
            $currentVersion['items'][] = $currentItem;
        }

        if ($currentVersion) {
            $versions[] = $currentVersion;
        }

        return $versions;
    }

    public function with(): array
    {
        $helpService = app(HelpCenterService::class);

        $hasSearch = mb_strlen(trim($this->search)) >= 2;
        $searchResults = $hasSearch
            ? $helpService->search($this->search, $this->selectedCategory, $this->selectedProfile)
            : ['articles' => [], 'faqs' => [], 'total' => 0];

        $currentArticle = $this->selectedArticleId ? $helpService->getArticle($this->selectedArticleId) : null;

        return [
            'allArticles' => $helpService->getArticles(),
            'featuredArticles' => $helpService->getFeaturedArticles(),
            'allFaqs' => $helpService->getFaq(),
            'glossaryList' => $helpService->getGlossary(),
            'categories' => $helpService->getCategories(),
            'profiles' => $helpService->getProfiles(),
            'hasSearch' => $hasSearch,
            'searchResults' => $searchResults,
            'currentArticle' => $currentArticle,
            'changelogVersions' => $this->getParsedChangelog(),
        ];
    }
};
?>

<div class="max-w-5xl mx-auto py-2 sm:py-6 px-3 sm:px-6 lg:px-8 space-y-6" x-data="{ expandedFaqs: {} }">
    <!-- Top Header: Central de Ajuda Banner -->
    <div class="no-print bg-gradient-to-r from-indigo-900 via-indigo-800 to-indigo-950 rounded-3xl p-5 sm:p-7 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-indigo-500/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-24 -bottom-16 w-48 h-48 rounded-full bg-purple-500/10 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-indigo-700/80 border border-indigo-400/30 flex items-center justify-center text-white shrink-0 shadow-inner">
                    <svg class="w-6 h-6 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                        Central de Ajuda
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-200 mt-0.5">
                        Encontre instruções para utilizar o PontoFácil.
                    </p>
                </div>
            </div>

            <!-- Chips de Status do Sistema -->
            <div class="flex flex-wrap sm:flex-nowrap gap-2 shrink-0">
                <div class="bg-white/10 backdrop-blur-xs rounded-xl px-3 py-1.5 border border-white/10 text-xs">
                    <span class="text-indigo-200 block text-[10px] uppercase font-semibold">Fuso Oficial</span>
                    <span class="font-bold text-white">Maceió (GMT-3)</span>
                </div>
                <div class="bg-white/10 backdrop-blur-xs rounded-xl px-3 py-1.5 border border-white/10 text-xs">
                    <span class="text-indigo-200 block text-[10px] uppercase font-semibold">Conformidade</span>
                    <span class="font-bold text-emerald-300">Portaria 671 (REP-P / PTRP)</span>
                </div>
            </div>
        </div>

        <!-- Seletor de Abas Estilizado (Pills) -->
        <div class="mt-6 flex flex-wrap gap-2 border-t border-indigo-700/50 pt-4">
            <button wire:click="setTab('manual')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-xs {{ $activeTab === 'manual' ? 'bg-white text-indigo-950 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                Manual do Usuário
            </button>

            <button wire:click="setTab('faq')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-xs {{ $activeTab === 'faq' ? 'bg-white text-indigo-950 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>
                Perguntas Frequentes
                <span class="px-1.5 py-0.5 text-[10px] font-black rounded-full bg-amber-400 text-amber-950">{{ count($allFaqs) }}</span>
            </button>

            <button wire:click="setTab('glossary')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-xs {{ $activeTab === 'glossary' ? 'bg-white text-indigo-950 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                Glossário Normativo
            </button>

            <button wire:click="setTab('changelog')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-xs {{ $activeTab === 'changelog' ? 'bg-white text-indigo-950 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                </svg>
                Novidades e Versões
                <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-full bg-emerald-500 text-white">{{ $currentVersionTag }}</span>
            </button>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- BARRA DE BUSCA GLOBAL (PROMINENTE & INSTANTÂNEA) -->
    <!-- ============================================== -->
    <div class="no-print space-y-3">
        <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input type="text" 
                   wire:model.live.debounce.250ms="search"
                   placeholder="Buscar ajuda: ponto, escala, QR Code..."
                   class="w-full pl-12 pr-10 py-3.5 bg-white border border-gray-200 rounded-2xl shadow-xs text-sm sm:text-base text-gray-900 placeholder-gray-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            
            @if(!empty($search))
                <button wire:click="clearSearch" 
                        type="button" 
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Filtros Rápidos de Perfil e Categoria (quando buscando ou explorando) -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs text-gray-500">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="font-bold text-gray-700 mr-1">Filtrar perfil:</span>
                @foreach($profiles as $profKey => $profLabel)
                    <button wire:click="$set('selectedProfile', '{{ $profKey }}')"
                            type="button"
                            class="px-2.5 py-1 rounded-lg font-semibold transition border {{ $selectedProfile === $profKey ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                        {{ $profLabel }}
                    </button>
                @endforeach
            </div>

            @if($hasSearch)
                <div class="text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg">
                    {{ $searchResults['total'] }} resultado(s) encontrado(s)
                </div>
            @endif
        </div>
    </div>

    <!-- ============================================== -->
    <!-- RESULTADOS DA BUSCA (SE DIGITOU) -->
    <!-- ============================================== -->
    @if($hasSearch)
        <div class="no-print space-y-6">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span>Resultados para</span>
                    <span class="text-indigo-600 font-extrabold">"{{ $search }}"</span>
                </h2>
                <button wire:click="clearSearch" type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-800 underline">
                    Limpar busca
                </button>
            </div>

            <!-- Artigos correspondentes -->
            @if(count($searchResults['articles']) > 0)
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">
                        Manuais e Guias ({{ count($searchResults['articles']) }})
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($searchResults['articles'] as $art)
                            <div wire:click="selectArticle('{{ $art['id'] }}')" 
                                 class="cursor-pointer bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700">
                                        {{ $art['category'] }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-medium">{{ $art['reading_time'] ?? '3 min' }}</span>
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 mt-2 transition">
                                    {{ $art['title'] }}
                                </h4>
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                    {{ $art['summary'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- FAQs correspondentes -->
            @if(count($searchResults['faqs']) > 0)
                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">
                        Perguntas Frequentes ({{ count($searchResults['faqs']) }})
                    </h3>
                    <div class="space-y-2">
                        @foreach($searchResults['faqs'] as $faq)
                            <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                                <button @click="expandedFaqs['search_{{ $faq['id'] }}'] = !expandedFaqs['search_{{ $faq['id'] }}']" 
                                        type="button" 
                                        class="w-full text-left p-4 flex items-center justify-between gap-3 font-bold text-sm text-gray-900 hover:text-indigo-600 transition">
                                    <span class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-xs shrink-0 font-black">?</span>
                                        {{ $faq['question'] }}
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400 transform transition-transform" 
                                         :class="expandedFaqs['search_{{ $faq['id'] }}'] ? 'rotate-180' : ''" 
                                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <div x-show="expandedFaqs['search_{{ $faq['id'] }}']" x-collapse style="display: none;" class="px-4 pb-4 pt-1 text-xs text-gray-600 border-t border-gray-100 bg-gray-50/50 space-y-2">
                                    <p class="leading-relaxed">{{ $faq['answer'] }}</p>
                                    @if(!empty($faq['article_id']))
                                        <div class="pt-1">
                                            <button wire:click="selectArticle('{{ $faq['article_id'] }}')" type="button" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-1">
                                                Ler guia completo sobre este tema &rarr;
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($searchResults['articles']) === 0 && count($searchResults['faqs']) === 0)
                <div class="bg-white p-8 rounded-2xl border border-gray-200 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800">Nenhum resultado encontrado</h3>
                    <p class="text-xs text-gray-500 max-w-md mx-auto">
                        Tente pesquisar por outros termos como "escala", "QR Code", "comprovante", "tolerância" ou navegue pelas abas abaixo.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <!-- ============================================== -->
    <!-- DETALHE DO ARTIGO (SE SELECIONADO) -->
    <!-- ============================================== -->
    @if($currentArticle && ! $hasSearch)
        <div class="space-y-6">
            <!-- Breadcrumbs e Ações -->
            <div class="no-print flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-2xl border border-gray-200 shadow-xs">
                <nav class="flex items-center gap-2 text-xs text-gray-500">
                    <button wire:click="selectArticle(null)" type="button" class="hover:text-indigo-600 font-semibold flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Central de Ajuda
                    </button>
                    <span>/</span>
                    <span class="text-gray-700 font-bold">{{ $currentArticle['category'] }}</span>
                </nav>

                <div class="flex items-center gap-2">
                    <button onclick="window.print()" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition">
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" />
                        </svg>
                        Imprimir Guia
                    </button>
                    <button wire:click="selectArticle(null)" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition">
                        Fechar Guia &times;
                    </button>
                </div>
            </div>

            <!-- Corpo Principal do Artigo -->
            <article class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200/90 shadow-sm space-y-6">
                <!-- Cabeçalho do Artigo -->
                <header class="border-b border-gray-100 pb-6 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-indigo-100 text-indigo-800">
                            {{ $currentArticle['category'] }}
                        </span>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700">
                            Perfil: {{ $currentArticle['profile'] ?? 'Geral' }}
                        </span>
                        <span class="text-xs text-gray-400 font-semibold ml-auto flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Leitura: {{ $currentArticle['reading_time'] ?? '4 min' }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight leading-tight">
                        {{ $currentArticle['title'] }}
                    </h1>

                    @if(!empty($currentArticle['summary']))
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed font-medium">
                            {{ $currentArticle['summary'] }}
                        </p>
                    @endif
                </header>

                <!-- Conteúdo Renderizado (HTML CommonMark) -->
                <div class="help-article-content prose prose-indigo max-w-none text-sm sm:text-base leading-relaxed text-gray-700">
                    {!! $currentArticle['html'] !!}
                </div>

                <!-- Rodapé do Artigo com Botão de Voltar -->
                <footer class="no-print pt-8 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4">
                    <button wire:click="selectArticle(null)" 
                            type="button" 
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 text-gray-800 font-bold text-xs hover:bg-gray-200 transition">
                        &larr; Voltar para a Central de Ajuda
                    </button>

                    <button wire:click="openFaqFromQuickAccess" 
                            type="button" 
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 font-bold text-xs hover:bg-amber-100 transition">
                        Dúvidas? Consulte as Perguntas Frequentes (FAQ) &rarr;
                    </button>
                </footer>
            </article>
        </div>
    @endif

    <!-- ============================================== -->
    <!-- ABA 1: MANUAL DO USUÁRIO & ACESSO RÁPIDO -->
    <!-- ============================================== -->
    @if($activeTab === 'manual' && ! $currentArticle && ! $hasSearch)
        <div class="space-y-8" wire:transition>
            <!-- CARDS DE ACESSO RÁPIDO (EXATO DESIGN DOS MOCKUPS) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500">
                        Acesso Rápido — Guias mais procurados
                    </h2>
                    <span class="text-[11px] text-gray-400 font-semibold">Clique para abrir o passo a passo</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    <!-- 1. Como registrar meu ponto -->
                    <button wire:click="selectArticle('como-registrar-ponto')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0 group-hover:bg-indigo-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition truncate">
                                    Como registrar meu ponto
                                </h3>
                                <p class="text-xs text-gray-500">Servidor</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-indigo-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 2. Configurar empresa e setores -->
                    <button wire:click="selectArticle('configuracao-inicial-rh')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 group-hover:bg-blue-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5V21" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition truncate">
                                    Configurar empresa e setores
                                </h3>
                                <p class="text-xs text-gray-500">Administrador</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-blue-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 3. Criar horários e plantões -->
                    <button wire:click="selectArticle('horarios-jornadas-e-plantoes')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0 group-hover:bg-purple-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253 3.75m-18 0h24m-24 0v13.5A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25V7.5M8.25 10.5h.008v.008H8.25v-.008Zm4.5 0h.008v.008h-.008v-.008Zm4.5 0h.008v.008h-.008v-.008Zm-9 4.5h.008v.008H8.25v-.008Zm4.5 0h.008v.008h-.008v-.008Zm4.5 0h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-purple-600 transition truncate">
                                    Criar horários e plantões
                                </h3>
                                <p class="text-xs text-gray-500">RH e Gestor</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-purple-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 4. Conferir frequência mensal -->
                    <button wire:click="selectArticle('conferencia-mensal-e-fechamento')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-emerald-600 transition truncate">
                                    Conferir frequência mensal
                                </h3>
                                <p class="text-xs text-gray-500">RH e Gestor</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-emerald-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 5. Gerar relatório consolidado -->
                    <button wire:click="selectArticle('relatorios-consolidados')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0 group-hover:bg-teal-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-teal-600 transition truncate">
                                    Gerar relatório consolidado
                                </h3>
                                <p class="text-xs text-gray-500">Administrador</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-teal-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 6. Instalar o PontoFácil no celular -->
                    <button wire:click="selectArticle('instalacao-no-celular-e-pwa')" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0 group-hover:bg-orange-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-orange-600 transition truncate">
                                    Instalar o PontoFácil no celular
                                </h3>
                                <p class="text-xs text-gray-500">PWA / Atalho</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-orange-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>

                    <!-- 7. Perguntas frequentes -->
                    <button wire:click="openFaqFromQuickAccess" 
                            type="button" 
                            class="text-left bg-white p-4 rounded-2xl border border-gray-200 shadow-xs hover:border-amber-500 hover:shadow-md hover:scale-[1.01] transition flex items-center justify-between gap-3 group sm:col-span-2 lg:col-span-3">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 group-hover:bg-amber-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-amber-600 transition truncate">
                                    Perguntas frequentes
                                </h3>
                                <p class="text-xs text-gray-500">49 respostas organizadas por assunto (Ponto, Escala, Justificativas, etc.)</p>
                            </div>
                        </div>
                        <span class="text-gray-300 group-hover:text-amber-600 transition text-base font-black shrink-0">&gt;</span>
                    </button>
                </div>
            </div>

            <!-- DESTAQUES NORMATIVOS & FUNCIONALIDADES CHAVE (GARANTE RETROCOMPATIBILIDADE DE TESTES) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gradient-to-br from-indigo-50/80 to-white p-5 rounded-2xl border border-indigo-100 shadow-xs space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-xs">P</span>
                        <h3 class="text-sm font-bold text-gray-900">PTRP — Tratamento de Ponto</h3>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Classificação jurídica de batidas, abonos legais, atestados e registro imutável com preservação dos dados originais.
                    </p>
                    <button wire:click="selectArticle('espelho-comprovantes-e-correcoes')" type="button" class="text-xs font-bold text-indigo-700 hover:underline">
                        Ver guia do espelho &rarr;
                    </button>
                </div>

                <div class="bg-gradient-to-br from-emerald-50/80 to-white p-5 rounded-2xl border border-emerald-100 shadow-xs space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">B</span>
                        <h3 class="text-sm font-bold text-gray-900">Banco de Horas &amp; Tolerância Legal</h3>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Compensação de saldo e aplicação da tolerância legal de 5 a 10 minutos (Art. 58 CLT e Portaria 671 MTP).
                    </p>
                    <button wire:click="selectArticle('conferencia-mensal-e-fechamento')" type="button" class="text-xs font-bold text-emerald-700 hover:underline">
                        Ver regras de apuração &rarr;
                    </button>
                </div>

                <div class="bg-gradient-to-br from-blue-50/80 to-white p-5 rounded-2xl border border-blue-100 shadow-xs space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">C</span>
                        <h3 class="text-sm font-bold text-gray-900">Central de Comprovantes &amp; Folha de Ponto Oficial A4</h3>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Validação de autenticidade criptográfica de comprovantes e geração de folha de frequência mensal em PDF A4.
                    </p>
                    <button wire:click="selectArticle('relatorios-consolidados')" type="button" class="text-xs font-bold text-blue-700 hover:underline">
                        Ver relatórios oficiais &rarr;
                    </button>
                </div>
            </div>

            <!-- TODOS OS MANUAIS DISPONÍVEIS (CATÁLOGO COMPLETO) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-700">
                        Índice Completo de Manuais &amp; Tutoriais ({{ count($allArticles) }})
                    </h2>
                    <span class="text-xs text-gray-400">Totalmente atualizado para a Portaria 671</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($allArticles as $art)
                        <div wire:click="selectArticle('{{ $art['id'] }}')" 
                             class="cursor-pointer bg-white p-5 rounded-2xl border border-gray-200 shadow-xs hover:border-indigo-500 hover:shadow-md transition group flex flex-col justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        {{ $art['category'] }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-semibold">{{ $art['reading_time'] ?? '4 min' }}</span>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 group-hover:text-indigo-600 transition leading-snug">
                                    {{ $art['title'] }}
                                </h3>
                                <p class="text-xs text-gray-500 leading-relaxed line-clamp-2">
                                    {{ $art['summary'] }}
                                </p>
                            </div>
                            <div class="pt-4 mt-3 border-t border-gray-50 flex items-center justify-between text-xs font-bold text-indigo-600">
                                <span>Ler manual completo</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- ============================================== -->
    <!-- ABA 2: PERGUNTAS FREQUENTES (FAQ) -->
    <!-- ============================================== -->
    @if($activeTab === 'faq' && ! $hasSearch)
        <div class="space-y-6" wire:transition>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-lg font-black text-gray-900">Perguntas Frequentes (FAQ)</h2>
                    <p class="text-xs text-gray-500">Respostas diretas e esclarecimentos para as 49 principais dúvidas operacionais e legais.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="expandedFaqs = Object.fromEntries(Array.from({length: 60}, (_, i) => [i + 1, true]))" 
                            type="button" 
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                        Expandir todas
                    </button>
                    <button @click="expandedFaqs = {}" 
                            type="button" 
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                        Recolher todas
                    </button>
                </div>
            </div>

            <!-- Agrupamento por Categoria do FAQ -->
            @php
                $groupedFaqs = [];
                foreach ($allFaqs as $fq) {
                    $cat = $fq['category'] ?? 'Geral';
                    $groupedFaqs[$cat][] = $fq;
                }
            @endphp

            <div class="space-y-8">
                @foreach($groupedFaqs as $catTitle => $catItems)
                    <div class="space-y-3">
                        <h3 class="text-xs font-black uppercase tracking-wider text-indigo-900 bg-indigo-50/70 border border-indigo-100 px-3.5 py-1.5 rounded-xl inline-block">
                            {{ $catTitle }} ({{ count($catItems) }})
                        </h3>

                        <div class="space-y-2.5">
                            @foreach($catItems as $fItem)
                                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                                    <button @click="expandedFaqs[{{ $fItem['id'] }}] = !expandedFaqs[{{ $fItem['id'] }}]" 
                                            type="button" 
                                            class="w-full text-left p-4 sm:p-4.5 flex items-center justify-between gap-4 font-bold text-sm text-gray-900 hover:text-indigo-600 transition">
                                        <span class="flex items-center gap-3">
                                            <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-800 flex items-center justify-center text-xs shrink-0 font-black">
                                                {{ $fItem['id'] }}
                                            </span>
                                            <span>{{ $fItem['question'] }}</span>
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 transform transition-transform shrink-0" 
                                             :class="expandedFaqs[{{ $fItem['id'] }}] ? 'rotate-180' : ''" 
                                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div x-show="expandedFaqs[{{ $fItem['id'] }}]" x-collapse style="display: none;" class="px-5 pb-5 pt-2 text-xs sm:text-sm text-gray-600 border-t border-gray-100 bg-gray-50/40 space-y-3">
                                        <p class="leading-relaxed whitespace-pre-line">{{ $fItem['answer'] }}</p>
                                        @if(!empty($fItem['article_id']))
                                            <div class="pt-1">
                                                <button wire:click="selectArticle('{{ $fItem['article_id'] }}')" type="button" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-1 text-xs">
                                                    Abrir guia passo a passo sobre este assunto &rarr;
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ============================================== -->
    <!-- ABA 3: GLOSSÁRIO NORMATIVO -->
    <!-- ============================================== -->
    @if($activeTab === 'glossary' && ! $hasSearch)
        <div class="space-y-6" wire:transition>
            <div class="border-b border-gray-200 pb-3">
                <h2 class="text-lg font-black text-gray-900">Glossário Normativo</h2>
                <p class="text-xs text-gray-500">Significado prático dos termos técnicos, siglas e exigências legais da Portaria 671/2021 MTP.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($glossaryList as $gItem)
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-base font-extrabold text-indigo-950 font-mono">
                                {{ $gItem['term'] }}
                            </h3>
                            @if(!empty($gItem['acronym']))
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ $gItem['acronym'] }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs sm:text-sm text-gray-700 leading-relaxed">
                            {{ $gItem['definition'] ?? ($gItem['meaning'] ?? '') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ============================================== -->
    <!-- ABA 4: NOVIDADES E VERSÕES (TIMELINE COMPLETA) -->
    <!-- ============================================== -->
    <div class="no-print space-y-6" x-show="$wire.activeTab === 'changelog'" style="{{ $activeTab === 'changelog' ? '' : 'display: none;' }}">
        <!-- Timeline Container -->
        <div class="relative border-l-2 border-indigo-200 ml-4 sm:ml-7 pl-6 sm:pl-9 space-y-10">
            @forelse($changelogVersions as $ver)
                <div class="relative">
                    <!-- Marcador da Linha do Tempo -->
                    @if($ver['is_current'])
                        <div class="absolute -left-[35px] sm:-left-[47px] top-1">
                            <span class="relative flex h-6 w-6">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-6 w-6 bg-emerald-600 items-center justify-center text-white text-xs font-black shadow-md">
                                    ★
                                </span>
                            </span>
                        </div>
                    @else
                        <div class="absolute -left-[31px] sm:-left-[43px] top-1.5">
                            <span class="inline-flex rounded-full h-4 w-4 bg-indigo-300 border-2 border-white shadow-xs"></span>
                        </div>
                    @endif

                    <!-- Card da Versão -->
                    <div class="rounded-2xl transition {{ $ver['is_current'] ? 'bg-white p-6 sm:p-7 shadow-lg border-2 border-indigo-500/40 ring-4 ring-indigo-50/60' : 'bg-white p-5 sm:p-6 shadow-xs border border-gray-200/90' }}">
                        <!-- Cabeçalho do Card -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4 mb-5">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-base sm:text-lg font-black px-3 py-1 rounded-xl shadow-xs {{ $ver['is_current'] ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $ver['tag'] }}
                                </span>
                                
                                @if($ver['is_current'])
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Versão Atual
                                    </span>
                                @endif
                            </div>

                            <span class="text-xs font-semibold text-gray-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Fuso Oficial Maceió (GMT-3)
                            </span>
                        </div>

                        <!-- Lista de Funcionalidades / Melhorias -->
                        <div class="space-y-4">
                            @foreach($ver['items'] as $item)
                                <div class="p-4 rounded-xl border {{ $ver['is_current'] ? 'bg-indigo-50/30 border-indigo-100' : 'bg-gray-50/50 border-gray-100' }}">
                                    <div class="flex items-start gap-3">
                                        <div class="p-1 rounded-lg mt-0.5 shrink-0 {{ $ver['is_current'] ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600' }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-bold text-gray-900 leading-snug">
                                                {{ $item['title'] }}
                                            </h4>
                                            
                                            @if(!empty($item['description']))
                                                <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                                                    {{ $item['description'] }}
                                                </p>
                                            @endif

                                            @if(!empty($item['subitems']))
                                                <ul class="mt-2.5 space-y-1.5 border-t border-gray-200/60 pt-2.5">
                                                    @foreach($item['subitems'] as $sub)
                                                        <li class="text-xs text-gray-600 flex items-start gap-2">
                                                            <span class="text-indigo-400 mt-1">•</span>
                                                            <span class="leading-relaxed">{{ $sub }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white p-6 rounded-2xl border border-gray-200 text-center text-sm text-gray-500">
                    Nenhuma versão catalogada encontrada em versoes.md.
                </div>
            @endforelse
        </div>
    </div>
</div>

<style>
    /* Estilos de tipografia para o conteúdo dos Manuais */
    .help-article-content h1 { font-size: 1.5rem; font-weight: 800; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #1e1b4b; }
    .help-article-content h2 { font-size: 1.25rem; font-weight: 800; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #1e1b4b; border-bottom: 1px solid #e0e7ff; padding-bottom: 0.35rem; }
    .help-article-content h3 { font-size: 1.05rem; font-weight: 700; margin-top: 1.25rem; margin-bottom: 0.35rem; color: #312e81; }
    .help-article-content p { margin-bottom: 0.85rem; line-height: 1.65; color: #374151; }
    .help-article-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
    .help-article-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
    .help-article-content li { margin-bottom: 0.4rem; color: #374151; }
    .help-article-content blockquote { border-left: 4px solid #6366f1; background-color: #f5f3ff; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-style: normal; }
    .help-article-content table { width: 100%; border-collapse: collapse; margin-top: 1rem; margin-bottom: 1.25rem; font-size: 0.825rem; }
    .help-article-content th { background-color: #eef2ff; color: #312e81; font-weight: 700; text-align: left; padding: 0.6rem 0.75rem; border: 1px solid #e0e7ff; }
    .help-article-content td { padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; color: #4b5563; }
    .help-article-content code { background-color: #f3f4f6; color: #4338ca; padding: 0.15rem 0.35rem; border-radius: 0.25rem; font-size: 0.85em; font-family: monospace; }
    .help-article-content hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }

    @media print {
        .no-print { display: none !important; }
        body { background: #ffffff !important; }
        .help-article-content { font-size: 11pt !important; color: #000000 !important; }
        .help-article-content table { font-size: 9pt !important; }
    }
</style>