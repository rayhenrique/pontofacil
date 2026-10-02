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
    <!-- ABA 2: MANUAL DO USUÁRIO ORGANIZADO POR PERFIL  -->
    <!-- ============================================== -->
    @if($activeTab === 'manual')
        <div class="space-y-6" wire:transition x-data="{ selectedProfile: 'colaborador' }">
            <!-- Header do Manual -->
            <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-200/80 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Manual de Instruções do PontoFácil</h2>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Selecione seu perfil de acesso abaixo para ver o passo a passo completo de operação do sistema.</p>
                    </div>

                    <!-- Seletor Rápido de Perfis -->
                    <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100 rounded-2xl border border-slate-200/80">
                        <button type="button" 
                                @click="selectedProfile = 'colaborador'"
                                :class="selectedProfile === 'colaborador' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs transition">
                            Colaborador
                        </button>
                        <button type="button" 
                                @click="selectedProfile = 'gestor'"
                                :class="selectedProfile === 'gestor' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs transition">
                            Gestor
                        </button>
                        <button type="button" 
                                @click="selectedProfile = 'admin'"
                                :class="selectedProfile === 'admin' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs transition">
                            Administrador RH
                        </button>
                        <button type="button" 
                                @click="selectedProfile = 'auditor'"
                                :class="selectedProfile === 'auditor' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs transition">
                            Auditor / Fiscal
                        </button>
                        <button type="button" 
                                @click="selectedProfile = 'permissoes'"
                                :class="selectedProfile === 'permissoes' ? 'bg-white text-rose-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            Câmera & GPS
                        </button>
                    </div>
                </div>
            </div>

            <!-- PERFIL 1: COLABORADOR -->
            <div x-show="selectedProfile === 'colaborador'" class="space-y-6">
                <div class="bg-indigo-50/60 border border-indigo-200/80 rounded-2xl p-5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                        1
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-indigo-950">Perfil do Colaborador (Autoatendimento)</h3>
                        <p class="text-xs text-indigo-800">Rotina diária de registro de jornada, acompanhamento de banco de horas e emissão de comprovantes.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Como Bater Ponto -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">A</span>
                            <h4 class="font-bold text-gray-900 text-sm">Como Bater Ponto Corretamente</h4>
                        </div>
                        <ol class="text-xs text-gray-600 space-y-2 list-decimal pl-4 leading-relaxed">
                            <li>Acesse o menu <strong>"Bater Ponto"</strong> (`/ponto`).</li>
                            <li>Clique no botão azul <strong>"Escanear QR Code"</strong>.</li>
                            <li>Autorize o acesso à <strong>Câmera</strong> e à sua <strong>Localização (GPS)</strong> quando solicitado pelo navegador.</li>
                            <li>Aponte a câmera para o <strong>QR Code oficial da empresa</strong> afixado na entrada ou setor.</li>
                            <li>O sistema valida a leitura óptica, checa o raio de segurança do GPS e grava o ponto instantaneamente com número de registro (NSR) e carimbo de tempo.</li>
                        </ol>
                    </div>

                    <!-- Espelho de Ponto & Saldo de Banco -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">B</span>
                            <h4 class="font-bold text-gray-900 text-sm">Espelho de Ponto & Banco de Horas Diário</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Acesse <strong>"Espelho de Ponto"</strong> (`/timesheet`).</li>
                            <li>Veja os pares de batidas do dia (Entrada, Almoço, Retorno, Saída).</li>
                            <li>Consulte o <strong>Card de Banco de Horas</strong> no topo: saldo anterior acumulado, créditos do mês, débitos e saldo líquido em tempo real.</li>
                            <li>Filtre qualquer mês ou ano para verificar seu histórico de jornada.</li>
                        </ul>
                    </div>

                    <!-- Solicitar Ajustes de Ponto -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs">C</span>
                            <h4 class="font-bold text-gray-900 text-sm">Esqueceu de Bater? Solicite um Ajuste</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>No <strong>"Espelho de Ponto"</strong> (`/timesheet`), clique no botão <strong>"Solicitar Ajuste"</strong>.</li>
                            <li>Escolha o tipo: <em>Batida Esquecida</em>, <em>Marcação Indevida</em> ou <em>Atestado Médico / Justificativa</em>.</li>
                            <li>Informe data, horário, justificativa e anexe o comprovante (se houver).</li>
                            <li>A solicitação será enviada para o seu gestor ou RH aprovar com histórico registrado.</li>
                        </ul>
                    </div>

                    <!-- Comprovantes & Folha de Ponto Oficial -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">D</span>
                            <h4 class="font-bold text-gray-900 text-sm">Comprovantes Trabalhistas & Folha A4</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li><strong>Central de Comprovantes (`/comprovantes`):</strong> Baixe o PDF assinado digitalmente de cada batida com hash SHA-256 e código de validação pública.</li>
                            <li><strong>Folha de Ponto Oficial (`/folha-ponto`):</strong> Visualize sua folha mensal completa com horários, horas trabalhadas e saldo. Clique em <strong>"Imprimir Folha de Ponto"</strong> para gerar a folha limpa em formato A4 para assinatura.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- PERFIL 2: GESTOR DE SETOR -->
            <div x-show="selectedProfile === 'gestor'" class="space-y-6">
                <div class="bg-blue-50/60 border border-blue-200/80 rounded-2xl p-5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                        2
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-blue-950">Perfil do Gestor (Liderança & Supervisão)</h3>
                        <p class="text-xs text-blue-800">Supervisão da equipe dos setores sob sua responsabilidade e aprovação de tratamentos de ponto.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">A</span>
                            <h4 class="font-bold text-gray-900 text-sm">Gestão de Funcionários do Setor</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Em <strong>"Funcionários"</strong> (`/admin/employees`), o gestor visualiza e cadastra colaboradores exclusivamente nos setores onde é responsável.</li>
                            <li>Edite dados funcionais, cargo, vínculo e carga horária (40h, 30h, 20h) para garantir apuração precisa.</li>
                            <li>O formulário conta com máscaras automáticas de CPF, Telefone e validação de e-mail corporativo.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs">B</span>
                            <h4 class="font-bold text-gray-900 text-sm">Aprovação de Tratamento de Ponto (PTRP)</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Acesse <strong>"Tratamento de Ponto"</strong> (`/admin/treatment-requests`).</li>
                            <li>Analise os pedidos de inclusão de batida esquecida, atestados e justificativas da equipe.</li>
                            <li>Ao aprovar ou rejeitar, informe a justificativa formal exigida pela Portaria 671 MTP.</li>
                            <li><strong>Segregação de Funções:</strong> Gestores não podem autoaprovar seus próprios pedidos de ajuste.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">C</span>
                            <h4 class="font-bold text-gray-900 text-sm">Acompanhamento da Jornada da Equipe</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>No <strong>"Espelho de Ponto"</strong> (`/timesheet`), use a barra de busca em tempo real por <strong>Nome ou CPF</strong> para localizar qualquer colaborador do setor.</li>
                            <li>Verifique atrasos, faltas, horas extras e saldos acumulados de banco de horas.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">D</span>
                            <h4 class="font-bold text-gray-900 text-sm">Conferência e Impressão de Folhas da Equipe</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>No menu <strong>"Folha de Ponto"</strong> (`/folha-ponto`), filtre o colaborador do setor e a competência desejada.</li>
                            <li>Clique em <strong>"Imprimir Folha de Ponto"</strong> para gerar o documento A4 pronto para assinatura física dos colaboradores.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- PERFIL 3: ADMINISTRADOR DE RH -->
            <div x-show="selectedProfile === 'admin'" class="space-y-6">
                <div class="bg-slate-50 border border-slate-300 rounded-2xl p-5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                        3
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Perfil do Administrador de RH (Controle Total)</h3>
                        <p class="text-xs text-slate-600">Parametrização institucional da empresa, governança legal da Portaria 671, banco de horas e fechamento.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center font-bold text-xs">A</span>
                            <h4 class="font-bold text-gray-900 text-sm">Dados da Empresa, Logotipo & QR Code</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Em <strong>"Configurações"</strong> (`/admin/settings`): preencha Razão Social, Nome Fantasia, CNPJ, telefone, endereço e envie o <strong>Logotipo Oficial</strong> para sair no cabeçalho das Folhas de Ponto.</li>
                            <li><strong>Cartaz de QR Code:</strong> Clique em <strong>"Imprimir QR Code"</strong> para imprimir exclusivamente o cartaz oficial da empresa para fixar na recepção/entrada.</li>
                            <li>Caso o QR Code vaze, clique em <strong>"Gerar Novo Código"</strong> para rotacionar a chave de segurança instantaneamente.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">B</span>
                            <h4 class="font-bold text-gray-900 text-sm">Cerca Virtual GPS Antifraude</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Em <strong>"Configurações"</strong> (`/admin/settings`), defina a Latitude e Longitude da sede física ou clique em <em>"Capturar Minha Posição Atual (GPS)"</em>.</li>
                            <li>Estipule o <strong>Raio Permitido</strong> em metros (ex: 100m, 200m). Marcações fora do perímetro são rejeitadas em cumprimento às regras trabalhistas.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-xs">C</span>
                            <h4 class="font-bold text-gray-900 text-sm">Banco de Horas, Ledger & Fechamento</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Ative o regime de banco de horas em <strong>"Configurações"</strong> e escolha entre:
                                <br>• <strong>Acumular (CARRY_OVER):</strong> saldo positivo ou negativo passa integralmente para o próximo mês.
                                <br>• <strong>Zerar ao fechar o mês (MONTHLY_RESET):</strong> cria lançamento compensatório no fechamento da competência.
                            </li>
                            <li>Acesse <strong>"Banco de Horas"</strong> (`/admin/time-bank`) para lançar créditos/débitos manuais auditados e executar o <strong>Fechamento Formal de Competência</strong>.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs">D</span>
                            <h4 class="font-bold text-gray-900 text-sm">Fiscalização MTE, Arquivos AEJ & AFD</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Acesse <strong>"Fiscalização MTE"</strong> (`/admin/fiscalizacao`).</li>
                            <li>Gere prévias e emita o arquivo oficial <strong>AEJ (Arquivo Eletrônico de Jornada)</strong> em formato texto padronizado com hash canônico SHA-256 e assinatura digital PAdES/CAdES.</li>
                            <li>Gere o arquivo <strong>AFD (Arquivo Fonte de Dados)</strong> para auditorias fiscais do Ministério do Trabalho.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- PERFIL 4: AUDITOR / FISCAL -->
            <div x-show="selectedProfile === 'auditor'" class="space-y-6">
                <div class="bg-emerald-50/60 border border-emerald-200/80 rounded-2xl p-5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                        4
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-emerald-950">Perfil Auditor / Fiscal do Trabalho</h3>
                        <p class="text-xs text-emerald-800">Ferramentas de auditoria e validação pública de autenticidade sem necessidade de login.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">A</span>
                            <h4 class="font-bold text-gray-900 text-sm">Validação Pública de Comprovantes (Sem Login)</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li>Qualquer auditor fiscal ou colaborador pode acessar a rota pública <strong>`/verificar-comprovante`</strong> (link direto no rodapé e menu da página inicial).</li>
                            <li>Digite o código de verificação no formato `PF-XXXX-XXXX-XXXX` ou o hash SHA-256.</li>
                            <li>O sistema atesta publicamente a autenticidade da batida, data, horário preciso (NTP.br), NSR sequencial e integridade dos dados sem violar a LGPD.</li>
                        </ul>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">B</span>
                            <h4 class="font-bold text-gray-900 text-sm">Conformidade Legal & Integridade Criptográfica</h4>
                        </div>
                        <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4 leading-relaxed">
                            <li><strong>NSR Monotônico Inviolável:</strong> Cada batida possui um Número Sequencial de Registro estritamente crescente e imutável.</li>
                            <li><strong>Trilha de Auditoria:</strong> Toda e qualquer edição ou tratamento fica registrado na tabela `audit_logs` com IP, autor, data e dados anteriores.</li>
                            <li><strong>Layouts MTE 2026:</strong> Compatibilidade integral com as regras e leiautes exigidos pela fiscalização trabalhista federal.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- GUIA ESPECIAL: PERMISSÕES DE CÂMERA & GPS -->
            <div x-show="selectedProfile === 'permissoes'" class="space-y-6">
                <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                        !
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-amber-950">Como Resolver: Câmera ou GPS Bloqueados no Navegador</h3>
                        <p class="text-xs text-amber-800">Se você ou um colaborador clicou em "Não permitir" ou "Bloquear" sem querer, siga este passo a passo para reativar.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Google Chrome (Android / PC) -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold text-xs">G</div>
                            <h4 class="font-bold text-gray-900 text-sm">Google Chrome (Android / PC)</h4>
                        </div>
                        <ol class="text-xs text-gray-600 space-y-2 list-decimal pl-4 leading-relaxed">
                            <li>Na tela de <em>Bater Ponto</em>, olhe para a <strong>barra de endereço (URL)</strong> no topo.</li>
                            <li>Clique no ícone de <strong>Cadeado</strong> ou <strong>Ajustes de Site</strong> (à esquerda de <em>pontofacil...</em>).</li>
                            <li>Localize as opções <strong>Câmera</strong> e <strong>Localização</strong>.</li>
                            <li>Mude ambas para <strong>"Permitir"</strong> (ou toque em <em>"Redefinir permissões"</em>).</li>
                            <li>Recarregue a página ou clique em <strong>"Escanear QR Code"</strong>. O navegador pedirá confirmação novamente.</li>
                        </ol>
                    </div>

                    <!-- Safari (iPhone / iPad iOS) -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">S</div>
                            <h4 class="font-bold text-gray-900 text-sm">Safari (iPhone & iPad)</h4>
                        </div>
                        <ol class="text-xs text-gray-600 space-y-2 list-decimal pl-4 leading-relaxed">
                            <li>Na barra de endereço inferior do Safari, toque no botão <strong>aA</strong> ou no ícone de configurações.</li>
                            <li>Selecione a opção <strong>"Ajustes do Site"</strong>.</li>
                            <li>Altere os campos <strong>Câmera</strong> e <strong>Localização</strong> para <strong>"Permitir"</strong>.</li>
                            <li>Se persistir bloqueado, vá em <em>Ajustes do iPhone &rarr; Safari &rarr; Câmera &rarr; Permitir</em> e <em>Ajustes &rarr; Privacidade &rarr; Serviços de Localização &rarr; Safari &rarr; Permitir Durante o Uso</em>.</li>
                        </ol>
                    </div>

                    <!-- Microsoft Edge & Outros -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-xs">E</div>
                            <h4 class="font-bold text-gray-900 text-sm">Microsoft Edge & Outros</h4>
                        </div>
                        <ol class="text-xs text-gray-600 space-y-2 list-decimal pl-4 leading-relaxed">
                            <li>Clique no ícone de <strong>Cadeado</strong> ao lado da URL na barra superior.</li>
                            <li>Selecione <strong>"Permissões para este site"</strong>.</li>
                            <li>Em <strong>Câmera</strong> e <strong>Localização</strong>, mude de "Bloquear" para <strong>"Permitir"</strong>.</li>
                            <li>Feche e reabra a aba para aplicar as novas permissões.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>