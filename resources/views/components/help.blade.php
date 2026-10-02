<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\File;

new #[Layout('layouts.app')] #[Title('Ajuda e Novidades')] class extends Component
{
    public $activeTab = 'changelog';
    public string $currentVersionTag = 'v2.0.0';

    public function mount()
    {
        if (request()->query('tab') === 'manual') {
            $this->activeTab = 'manual';
        }

        $versions = $this->getParsedChangelog();
        if (!empty($versions)) {
            foreach ($versions as $v) {
                if (!empty($v['is_current'])) {
                    $this->currentVersionTag = $v['tag'];
                    break;
                }
            }
            if (empty($this->currentVersionTag) && !empty($versions[0]['tag'])) {
                $this->currentVersionTag = $versions[0]['tag'];
            }
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

            if (!$currentVersion) {
                continue;
            }

            // Detecta se a linha é um sub-item indentado (começa com espaços ou tabs seguidos de "- ")
            $isIndented = (bool) preg_match('/^\s+-\s+/', $line);
            $trimmed = trim($line);

            if (!$isIndented && str_starts_with($trimmed, '- ')) {
                // Item principal de nível superior
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
                // Subitem indentado
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
                    Versão Atual Ativa: {{ $currentVersionTag }}
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Ajuda, Novidades & Versões</h1>
                <p class="text-sm text-indigo-200 mt-1 max-w-xl">
                    Guia de operação do sistema, conformidade com a Portaria 671 (REP-P / REP-A) e histórico detalhado de melhorias.
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
                    <span class="font-bold text-emerald-300">Portaria 671 (REP-P / PTRP)</span>
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
                <span class="ml-1 px-2 py-0.5 text-[10px] font-extrabold rounded-full bg-emerald-500 text-white">{{ $currentVersionTag }}</span>
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
                                                    <p class="text-xs sm:text-sm text-gray-600 mt-1 leading-relaxed">
                                                        {!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong class="font-semibold text-gray-800">$1</strong>', '<code class="px-1 py-0.5 bg-gray-100 text-indigo-700 rounded text-[11px] font-mono">$1</code>'], e($item['description'])) !!}
                                                    </p>
                                                @endif

                                                @if(!empty($item['subitems']))
                                                    <ul class="mt-3 space-y-2 border-t border-gray-200/60 pt-2.5 text-xs sm:text-sm text-gray-600">
                                                        @foreach($item['subitems'] as $sub)
                                                            <li class="flex items-start gap-2.5">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span>
                                                                <span class="leading-relaxed leading-snug">
                                                                    {!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong class="font-semibold text-gray-800">$1</strong>', '<code class="px-1 py-0.5 bg-gray-100 text-indigo-700 rounded text-[11px] font-mono">$1</code>'], e($sub)) !!}
                                                                </span>
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
    <!-- ABA 2: MANUAL DO USUÁRIO COMPLETO E ATUALIZADO  -->
    <!-- ============================================== -->
    @if($activeTab === 'manual')
        <div class="space-y-6" wire:transition>
            <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-200/80 space-y-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Guia Prático e Manual de Operação do PontoFácil</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Orientações completas de uso do sistema para colaboradores, gestores de equipe e administração de RH (REP-P / PTRP)</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                    
                    <!-- Card 1: Bater Ponto -->
                    <div class="p-5 rounded-2xl border border-indigo-100 bg-indigo-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">1</span>
                            <h3 class="font-bold text-gray-900 text-base">Batida de Ponto com Validação Dupla</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No smartphone ou computador, acesse a tela <strong>"Bater Ponto"</strong> (`/ponto`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Leitura Ótica (QR Code):</strong> Aponte a câmera para o QR Code físico fixado na entrada da empresa ou do setor.</li>
                            <li><strong>Geolocalização GPS:</strong> O sistema checa se a sua distância física está dentro do raio permitido (Fórmula de Haversine).</li>
                            <li><strong>Carimbo Inviolável:</strong> O horário é cravado pelo servidor no fuso oficial de Maceió (GMT-3), gerando o NSR monotônico.</li>
                        </ul>
                    </div>

                    <!-- Card 2: Central de Comprovantes & Validação Pública -->
                    <div class="p-5 rounded-2xl border border-emerald-100 bg-emerald-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">2</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">Central de Comprovantes & Validação</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-800">Fase 19A</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            Acesso permanente para consulta de comprovantes trabalhistas:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Comprovante em PDF:</strong> Baixe o PDF de qualquer batida com NSR, chave SHA-256 e código de verificação (`PF-XXXX-XXXX-XXXX`).</li>
                            <li><strong>Validação Pública:</strong> Em `/verificar-comprovante`, qualquer auditor ou fiscal confere a autenticidade do ponto sem precisar de login.</li>
                            <li><strong>Exportação AFD (MTE 2026):** O RH pode gerar o Arquivo Fonte de Dados oficial baseado exclusivamente em dados brutos do REP.</li>
                        </ul>
                    </div>

                    <!-- Card 3: Espelho de Ponto & Banco de Horas Diário -->
                    <div class="p-5 rounded-2xl border border-blue-100 bg-blue-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-blue-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">3</span>
                            <h3 class="font-bold text-gray-900 text-base">Espelho de Ponto & Saldo de Banco</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Espelho de Ponto"</strong> (`/timesheet`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Acompanhe pares de batidas diárias (entrada, almoço, volta e saída) e jornada em andamento.</li>
                            <li><strong>Card do Banco de Horas:</strong> Saldo anterior transportado, créditos do mês, débitos, ajustes e saldo atual.</li>
                            <li><strong>Botão "Solicitar Ajuste":</strong> O colaborador abre solicitações de correção de batida esquecida, atestado ou justificativa.</li>
                        </ul>
                    </div>

                    <!-- Card 4: Folha de Ponto A4 Oficial -->
                    <div class="p-5 rounded-2xl border border-amber-100 bg-amber-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-amber-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">4</span>
                            <h3 class="font-bold text-gray-900 text-base">Folha de Ponto Oficial A4 (Impressão)</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Folha de Ponto"</strong> (`/folha-ponto`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Espelho mensal padronizado idêntico ao formulário administrativo do RH com o <strong>Logotipo da Empresa</strong> no cabeçalho.</li>
                            <li>Preenchimento automático do servidor (Cargo, Vínculo, Carga Horária, Zona de lotação e Setor).</li>
                            <li>Divisão em horários matutino/vespertino, folgas em finais de semana e campos para assinatura física formal.</li>
                            <li>Pronta para impressão em 1 página A4 com botão direto (`Ctrl+P`).</li>
                        </ul>
                    </div>

                    <!-- Card 5: PTRP — Tratamento de Ponto & Solicitações -->
                    <div class="p-5 rounded-2xl border border-purple-100 bg-purple-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-purple-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">5</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">PTRP — Tratamento de Ponto</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-200 text-purple-800">Fase 20</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Tratamento de Ponto"</strong> (`/admin/treatment-requests`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Tipos de Tratamento:</strong> Inclusão de batida esquecida, desconsideração de marcação indevida e justificativas de faltas/atestados médicos.</li>
                            <li><strong>Segregação de Funções:</strong> O colaborador não pode autoaprovar solicitações. A chefia ou RH analisa, aprova ou rejeita com motivo registrado.</li>
                            <li><strong>Imutabilidade Legal:</strong> O fato bruto original em `punch_events` jamais é apagado ou adulterado.</li>
                        </ul>
                    </div>

                    <!-- Card 6: Motor de Apuração & Tolerância CLT -->
                    <div class="p-5 rounded-2xl border border-rose-100 bg-rose-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-rose-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">6</span>
                            <h3 class="font-bold text-gray-900 text-base">Apuração & Tolerância Legal (Art. 58 CLT)</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            Motor de cálculo matemático em conformidade trabalhista:
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>Combina batidas originais + tratamentos aprovados + escalas de trabalho (`work_schedules`).</li>
                            <li><strong>Tolerância CLT (Art. 58, § 1º):</strong> Variações de até 5 minutos por batida (com limite de 10 min diários) não geram horas extras nem descontos.</li>
                            <li>A tolerância é aplicada exclusivamente na apuração analítica, mantendo a marcação bruta do relógio 100% inalterada.</li>
                        </ul>
                    </div>

                    <!-- Card 7: Banco de Horas em Ledger & Fechamento Formal -->
                    <div class="p-5 rounded-2xl border border-teal-100 bg-teal-50/30 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-teal-600 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">7</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">Banco de Horas em Ledger & Fechamento</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-200 text-teal-800">Fase 20</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Banco de Horas"</strong> (`/admin/time-bank`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Ledger Auditável:</strong> Saldo apurado estritamente por `SUM(minutes)`, sem campos numéricos sobrescritos.</li>
                            <li><strong>Políticas:</strong> Modo `CARRY_OVER` (saldo transportado para o próximo mês) ou `MONTHLY_RESET` (compensação contábil zerando no fechamento).</li>
                            <li><strong>Fechamento Formal:</strong> O zeramento nunca ocorre por virada de calendário à meia-noite, somente quando o RH executa o Fechamento de Competência.</li>
                            <li><strong>Ajustes Manuais:</strong> Créditos e débitos manuais exigem data, justificativa e responsável.</li>
                        </ul>
                    </div>

                    <!-- Card 8: Configurações da Empresa, Logo e Perfis -->
                    <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-slate-800 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">8</span>
                            <h3 class="font-bold text-gray-900 text-base">Configurações da Empresa & Perfis</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            Gestão administrativa da instalação exclusiva (Single-Tenant):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Dados da Empresa & Logotipo:</strong> Em `/admin/settings`, cadastre Razão Social, CNPJ, Endereço e faça upload do logotipo institucional.</li>
                            <li><strong>QR Code & GPS Antifraude:</strong> Imprima o QR Code oficial e defina as coordenadas GPS da sede com o raio permitido.</li>
                            <li><strong>Perfis de Acesso:</strong> Colaborador (autoatendimento), Gestor (supervisão dos setores atribuídos) e Administrador (controle total de RH).</li>
                        </ul>
                    </div>

                    <!-- Card 9: Central de Fiscalização Trabalhista & AEJ (MTE 2026) -->
                    <div class="p-5 rounded-2xl border border-indigo-200 bg-indigo-50/40 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-indigo-700 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">9</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">Central de Fiscalização & AEJ</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-200 text-indigo-900">Fase 21</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Fiscalização (AEJ)"</strong> (`/admin/fiscalizacao`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Snapshots Imutáveis:</strong> Cada competência fechada congela a escala, jornadas e tratamentos com hash canônico SHA-256.</li>
                            <li><strong>Emissão do AEJ:</strong> Geração do Arquivo Eletrônico de Jornada oficial (Portaria 671/2021 MTP) para a Auditoria Fiscal do Trabalho.</li>
                            <li><strong>Prévia vs. Oficial:</strong> Emita prévias operacionais para validação antes do fechamento formal de competência.</li>
                        </ul>
                    </div>

                    <!-- Card 10: Calendário Laboral — Feriados & Pontos Facultativos -->
                    <div class="p-5 rounded-2xl border border-sky-200 bg-sky-50/40 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-sky-700 text-white font-bold text-sm inline-flex items-center justify-center shadow-xs">10</span>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-900 text-base">Calendário Laboral & Dias Especiais</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-200 text-sky-900">Fase 20.18</span>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            No menu <strong>"Calendário"</strong> (`/admin/calendar`):
                        </p>
                        <ul class="text-xs sm:text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                            <li><strong>Feriados vs. Pontos Facultativos:</strong> Feriados legais (Lei 9.093/1995) e pontos facultativos com regras personalizáveis pelo RH.</li>
                            <li><strong>Escopo Territorial:</strong> Diferenciação estrita entre eventos Nacionais, Estaduais, Municipais e por Estabelecimento.</li>
                            <li><strong>Eventos Parciais & Horas em Feriado:</strong> Suporte a meio período (Quarta de Cinzas) e classificação isolada de `holiday_minutes` sem criar horas extras automáticas.</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    @endif
</div>