<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Ajuda e Novidades')] class extends Component
{
    public $activeTab = 'changelog'; // Abre direto em novidades se desejado ou manual

    public function mount()
    {
        // Padrão pode ser 'changelog' ou 'manual'
        if (request()->query('tab') === 'manual') {
            $this->activeTab = 'manual';
        }
    }

    public function getParsedChangelog(): array
    {
        $path = base_path('versoes.md');
        if (!File::exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $versions = [];
        $currentVersion = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                if ($currentVersion) {
                    $versions[] = $currentVersion;
                }
                $vTitle = trim(substr($trimmed, 3));
                $isCurrent = str_contains($vTitle, '(Atual)');
                $versionTag = trim(str_replace('(Atual)', '', $vTitle));
                
                $currentVersion = [
                    'tag' => $versionTag,
                    'is_current' => $isCurrent,
                    'items' => [],
                ];
            } elseif ($currentVersion) {
                if (str_starts_with($trimmed, '- **')) {
                    // Item com título em negrito: - **Título:** Descrição
                    preg_match('/^-\s+\*\*(.*?)\*\*:\s*(.*)$/', $trimmed, $matches);
                    $title = $matches[1] ?? 'Melhoria';
                    $desc = $matches[2] ?? '';
                    $currentVersion['items'][] = [
                        'title' => $title,
                        'description' => $desc,
                        'subitems' => []
                    ];
                } elseif (str_starts_with($trimmed, '- ')) {
                    $content = trim(substr($trimmed, 2));
                    if (!empty($currentVersion['items'])) {
                        $lastIdx = count($currentVersion['items']) - 1;
                        $currentVersion['items'][$lastIdx]['subitems'][] = $content;
                    } else {
                        $currentVersion['items'][] = [
                            'title' => 'Geral',
                            'description' => $content,
                            'subitems' => []
                        ];
                    }
                }
            }
        }

        if ($currentVersion) {
            $versions[] = $currentVersion;
        }

        return $versions;
    }
};
?>

<div class="max-w-5xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6">
    <!-- Header Principal -->
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-indigo-950 rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <!-- Detalhe decorativo no fundo -->
        <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-indigo-500/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-24 -bottom-16 w-48 h-48 rounded-full bg-purple-500/10 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Versão Atual Ativa: v1.5.0
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Ajuda, Novidades & Versões</h1>
                <p class="text-sm text-indigo-200 mt-1 max-w-xl">
                    Guia de operação do sistema, conformidade com a Portaria 671 (REP-A) e histórico detalhado de melhorias.
                </p>
            </div>

            <!-- Chips de Status do Sistema -->
            <div class="flex flex-wrap sm:flex-col gap-2 shrink-0">
                <div class="bg-white/10 backdrop-blur-xs rounded-xl px-3.5 py-2 border border-white/10 text-xs">
                    <span class="text-indigo-200 block text-[10px] uppercase font-semibold">Fuso Horário Oficial</span>
                    <span class="font-bold text-white">Maceió / Brasil (GMT-3)</span>
                </div>
                <div class="bg-white/10 backdrop-blur-xs rounded-xl px-3.5 py-2 border border-white/10 text-xs">
                    <span class="text-indigo-200 block text-[10px] uppercase font-semibold">Conformidade Legal</span>
                    <span class="font-bold text-emerald-300">Portaria 671 / REP-A</span>
                </div>
            </div>
        </div>

        <!-- Seletor de Abas Estilizado (Pills Modernas) -->
        <div class="mt-8 flex gap-2 border-t border-indigo-700/50 pt-5">
            <button wire:click="$set('activeTab', 'changelog')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition shadow-xs {{ $activeTab === 'changelog' ? 'bg-white text-indigo-900 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                </svg>
                Novidades e Versões
                <span class="ml-1 px-2 py-0.5 text-[10px] font-extrabold rounded-full bg-emerald-500 text-white">v1.5.0</span>
            </button>

            <button wire:click="$set('activeTab', 'manual')" 
                    type="button"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition shadow-xs {{ $activeTab === 'manual' ? 'bg-white text-indigo-900 shadow-md scale-100' : 'bg-indigo-900/60 text-indigo-200 hover:bg-indigo-800/80 hover:text-white' }}">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                Manual do Usuário
            </button>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- ABA 1: NOVIDADES E VERSÕES (TIMELINE ELEGANTE) -->
    <!-- ============================================== -->
    @if($activeTab === 'changelog')
        <div class="space-y-6" wire:transition>
            <!-- Timeline Container -->
            <div class="relative border-l-2 border-indigo-200 ml-4 sm:ml-7 pl-6 sm:pl-9 space-y-10">
                @php $changelogVersions = $this->getParsedChangelog(); @endphp

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
                                            Versão Atual (Em Produção)
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
                                    <div class="p-3.5 rounded-xl border {{ $ver['is_current'] ? 'bg-indigo-50/30 border-indigo-100' : 'bg-gray-50/50 border-gray-100' }}">
                                        <div class="flex items-start gap-2.5">
                                            <div class="p-1 rounded-md mt-0.5 shrink-0 {{ $ver['is_current'] ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600' }}">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-bold text-gray-900 leading-snug">
                                                    {{ $item['title'] }}
                                                </h4>
                                                
                                                @if(!empty($item['description']))
                                                    <p class="text-xs sm:text-sm text-gray-600 mt-1 leading-relaxed">
                                                        {{ $item['description'] }}
                                                    </p>
                                                @endif

                                                @if(!empty($item['subitems']))
                                                    <ul class="mt-2.5 space-y-1.5 border-t border-gray-200/60 pt-2 text-xs sm:text-sm text-gray-600">
                                                        @foreach($item['subitems'] as $sub)
                                                            <li class="flex items-start gap-2">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span>
                                                                <span>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold text-gray-800">$1</strong>', e($sub)) !!}</span>
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
                    <div class="bg-white rounded-2xl p-8 text-center text-gray-500 border border-gray-200">
                        Nenhum registro de versão encontrado.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- ============================================== -->
    <!-- ABA 2: MANUAL DO USUÁRIO ELEGANTE COM CARDS    -->
    <!-- ============================================== -->
    @if($activeTab === 'manual')
        <div class="space-y-6" wire:transition>
            <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-200/80 space-y-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Guia Prático do PontoFácil</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Orientações passo a passo para colaboradores, gestores e administração</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <!-- Card 1: Bater Ponto -->
                    <div class="p-5 rounded-2xl border border-indigo-100 bg-indigo-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">1</span>
                            <h3 class="font-bold text-gray-900 text-base">Batida de Ponto Diária</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            Acesse a tela inicial <strong>"Bater Ponto"</strong> no seu smartphone ou computador. O sistema acionará a câmera:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Aponte para o QR Code oficial fixado na empresa ou no seu setor.</li>
                            <li>Autorize o acesso ao GPS do navegador para checagem do raio antifraude.</li>
                            <li>O ponto é gravado com carimbo de tempo inviolável no servidor (Maceió GMT-3).</li>
                        </ul>
                    </div>

                    <!-- Card 2: Espelho de Ponto -->
                    <div class="p-5 rounded-2xl border border-blue-100 bg-blue-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-blue-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">2</span>
                            <h3 class="font-bold text-gray-900 text-base">Espelho de Ponto & Saldo</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Espelho de Ponto"</strong>, você acompanha toda a jornada:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Totalização automática de horas trabalhadas por dia.</li>
                            <li>Suporte a múltiplos intervalos (entrada, almoço, retorno, saída).</li>
                            <li>Resumo mensal com dias trabalhados e média diária.</li>
                        </ul>
                    </div>

                    <!-- Card 3: Regra Híbrida de Setores (Novidade v1.5.0) -->
                    <div class="p-5 rounded-2xl border border-purple-100 bg-purple-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-purple-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">3</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">Regras Híbridas de Setor</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-200 text-purple-800">Novo</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            O sistema conta com inteligência de <strong>Fallback Automático</strong>:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Se o setor tiver QR Code ou GPS próprios, valida especificamente para aquela filial.</li>
                            <li>Se deixado em branco, utiliza automaticamente o QR Code e GPS globais da matriz.</li>
                        </ul>
                    </div>

                    <!-- Card 4: Gestão e Administração -->
                    <div class="p-5 rounded-2xl border border-emerald-100 bg-emerald-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">4</span>
                            <h3 class="font-bold text-gray-900 text-base">Perfis de Gestor & RH</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            Controle de acessos com segregação de permissões:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Gestor:</strong> gerencia colaboradores exclusivamente nos setores designados.</li>
                            <li><strong>Administrador:</strong> controle total, ajustes manuais com justificativa e trilha de auditoria inviolável.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>