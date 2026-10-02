<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Establishment;
use App\Models\ClosedPeriod;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\FiscalPackage\GenerateFiscalPackageAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Central de Fiscalização Trabalhista')] class extends Component
{
    public $establishmentId;
    public $year;
    public $month;

    // Modal de Reabertura
    public $showReopenModal = false;
    public $reopenReason = '';

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;

        $firstEstablishment = Establishment::first();
        $this->establishmentId = $firstEstablishment ? (string) $firstEstablishment->id : '';
    }

    public function openReopenModal()
    {
        if (! Auth::user()?->isAdmin()) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Acesso Negado',
                'message' => 'Apenas administradores podem reabrir competências.',
                'buttonText' => 'OK'
            ]);
            return;
        }

        $this->reopenReason = '';
        $this->showReopenModal = true;
    }

    public function confirmReopen()
    {
        try {
            app(ReopenMonthlyPeriodAction::class)->execute(
                year: (int) $this->year,
                month: (int) $this->month,
                reopenedBy: Auth::user(),
                reason: $this->reopenReason
            );

            $this->showReopenModal = false;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Competência Reaberta!',
                'message' => sprintf('A competência %02d/%04d foi reaberta para ajustes. Os snapshots anteriores foram preservados para histórico.', $this->month, $this->year),
                'buttonText' => 'OK'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Falha na Reabertura',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar'
            ]);
        }
    }

    public function with()
    {
        $establishments = Establishment::with('company')->get();
        $selectedEstablishment = Establishment::with('company')->find($this->establishmentId) ?? Establishment::first();

        $closedPeriod = ClosedPeriod::with(['closer', 'reopenedBy'])->where('year', (int) $this->year)
            ->where('month', (int) $this->month)
            ->first();

        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';
        $snapshots = $closedPeriod ? $closedPeriod->currentSnapshots()->with('employee')->get() : collect();

        return [
            'establishments' => $establishments,
            'selectedEstablishment' => $selectedEstablishment,
            'closedPeriod' => $closedPeriod,
            'isClosed' => $isClosed,
            'snapshots' => $snapshots,
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-700">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Central de Fiscalização Trabalhista</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Emissão do Pacote Fiscal MTE, AFD (REP-P), AEJ (PTRP) e Auditoria Forense (Portaria 671/2021)</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                Leiaute MTE 31/07/2026
            </span>
        </div>
    </div>

    <!-- Filtros de Seleção -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Estabelecimento / Filial</label>
                <select wire:model.live="establishmentId" class="w-full px-3 py-2 text-sm font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    @foreach($establishments as $est)
                        <option value="{{ $est->id }}">{{ $est->name }} (CNPJ: {{ $est->identifier_number }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Mês de Competência</label>
                <select wire:model.live="month" class="w-full px-3 py-2 text-sm font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}">{{ sprintf('%02d - %s', $m, \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F')) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Ano</label>
                <select wire:model.live="year" class="w-full px-3 py-2 text-sm font-medium border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500">
                    @foreach([2025, 2026, 2027] as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Status da Competência & Integridade Forense -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                @if($isClosed)
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        COMPETÊNCIA FECHADA E CONGELADA (OFICIAL)
                    </span>
                    <span class="text-xs text-gray-500 font-medium">Versão do Snapshot: <strong>v{{ $closedPeriod->snapshot_version }}</strong></span>
                @elseif($closedPeriod && $closedPeriod->status === 'reopened')
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 gap-1.5">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                        COMPETÊNCIA REABERTA PARA AJUSTES
                    </span>
                @else
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        COMPETÊNCIA EM ABERTO (PRÉVIA)
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($isClosed && Auth::user()?->isAdmin())
                    <button wire:click="openReopenModal" class="inline-flex items-center px-3 py-1.5 text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded-lg transition gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        Reabrir Competência
                    </button>
                @endif

                @if(! $isClosed)
                    <a href="{{ route('admin.time-bank') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 rounded-lg transition gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        Ir Fechar Competência
                    </a>
                @endif
            </div>
        </div>

        @if($isClosed)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div>
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Hash SHA-256 do Snapshot</span>
                    <span class="font-mono text-xs text-indigo-900 break-all select-all font-semibold" title="{{ $closedPeriod->snapshot_hash }}">
                        {{ substr($closedPeriod->snapshot_hash, 0, 16) }}...{{ substr($closedPeriod->snapshot_hash, -12) }}
                    </span>
                </div>
                <div>
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Fechado por</span>
                    <span class="text-xs text-gray-800 font-semibold">{{ $closedPeriod->closer?->name ?? 'Admin' }}</span>
                    <span class="block text-[10px] text-gray-500">{{ $closedPeriod->closed_at?->format('d/m/Y H:i') }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Empregados Congelados</span>
                    <span class="text-base text-gray-900 font-bold">{{ $closedPeriod->employees_count }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Total de Batidas / Tratamentos</span>
                    <span class="text-base text-gray-900 font-bold">{{ $closedPeriod->punches_count }} / {{ $closedPeriod->treatments_count }}</span>
                </div>
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <div>
                    <strong class="font-bold">Aviso de Modo Prévia:</strong>
                    A competência {{ sprintf('%02d/%04d', $month, $year) }} ainda não foi fechada formalmente.
                    Os arquivos gerados agora serão identificados como <strong>PRÉVIA</strong> e não constituem entrega fiscal definitiva perante o Auditor-Fiscal do Trabalho.
                </div>
            </div>
        @endif
    </div>

    <!-- Cards de Download dos Artefatos Fiscais -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- 1. Pacote Fiscal Completo ZIP -->
        <div class="bg-gradient-to-br from-indigo-900 to-indigo-950 text-white rounded-2xl shadow-md p-6 flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 rounded-lg bg-indigo-800/80 text-indigo-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-300 bg-indigo-800/50 px-2.5 py-1 rounded-full border border-indigo-700">Recomendado</span>
                </div>
                <h3 class="text-lg font-bold mt-4">Pacote Fiscal Completo (ZIP)</h3>
                <p class="text-xs text-indigo-200 mt-1">Compactado ZIP com AFD, AEJ, Resumo do Espelho, Extrato do Banco de Horas, Manifesto de Hashes SHA-256 e LEIA-ME.</p>
            </div>

            <div class="pt-4 border-t border-indigo-800/80">
                <a href="{{ route('admin.fiscalizacao.package', ['establishmentId' => $selectedEstablishment->id, 'year' => $year, 'month' => $month]) }}"
                   class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-xs transition shadow-sm gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.5V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Baixar Pacote Fiscal (.ZIP)
                </a>
            </div>
        </div>

        <!-- 2. AEJ (PTRP) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-6 flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-700">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">PTRP</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mt-4">Arquivo Eletrônico de Jornada (AEJ)</h3>
                <p class="text-xs text-gray-500 mt-1">Gerado a partir do snapshot congelado com horários, escalas, apuração analítica de horas e extrato de banco.</p>
            </div>

            <div class="pt-4 border-t border-gray-100 flex flex-col gap-2">
                <a href="{{ route('admin.fiscalizacao.aej', ['establishmentId' => $selectedEstablishment->id, 'year' => $year, 'month' => $month, 'previa' => $isClosed ? 0 : 1]) }}"
                   class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.5V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    {{ $isClosed ? 'Baixar AEJ Oficial (.TXT)' : 'Baixar Prévia do AEJ (.TXT)' }}
                </a>
            </div>
        </div>

        <!-- 3. AFD (REP-P) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-6 flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="p-2 rounded-lg bg-blue-50 text-blue-700">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /></svg>
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">REP-P</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mt-4">Arquivo Fonte de Dados (AFD)</h3>
                <p class="text-xs text-gray-500 mt-1">Extraído diretamente da tabela imutável de batidas brutas (REP-P) com NSR sequencial e hashes SHA-256 encadeados.</p>
            </div>

            <div class="pt-4 border-t border-gray-100">
                <a href="{{ route('admin.fiscalizacao.afd', ['establishmentId' => $selectedEstablishment->id, 'year' => $year, 'month' => $month]) }}"
                   class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition shadow-sm gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.5V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Baixar AFD Bruto (.TXT)
                </a>
            </div>
        </div>
    </div>

    <!-- Lista de Snapshots por Trabalhador (Quando Fechada) -->
    @if($isClosed && $snapshots->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Demonstrativo de Snapshots Congelados por Empregado</h3>
                    <p class="text-xs text-gray-500">Cada trabalhador possui integridade criptográfica individual com hash SHA-256 auditável</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">
                    {{ $snapshots->count() }} colaboradores
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-6 py-3 text-left">Colaborador</th>
                            <th class="px-4 py-3 text-left">CPF</th>
                            <th class="px-4 py-3 text-left">Matrícula</th>
                            <th class="px-4 py-3 text-left">Escala / Jornada</th>
                            <th class="px-4 py-3 text-left">Saldo Banco</th>
                            <th class="px-6 py-3 text-left">Hash do Snapshot (SHA-256)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white font-medium text-gray-700">
                        @foreach($snapshots as $snap)
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-6 py-3.5 text-gray-900 font-bold">
                                    {{ $snap->employee_snapshot['name'] ?? 'Colaborador' }}
                                    <span class="block text-[11px] font-normal text-gray-500">{{ $snap->employee_snapshot['job_title'] ?? '' }}</span>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-gray-600">{{ $snap->employee_snapshot['cpf'] ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ $snap->employee_snapshot['registration_number'] ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ $snap->schedule_snapshot['name'] ?? 'Padrão' }}</td>
                                <td class="px-4 py-3.5">
                                    @php
                                        $finalBal = $snap->time_bank_snapshot['final_balance'] ?? 0;
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $finalBal >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                        {{ sprintf('%+d min', $finalBal) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 font-mono text-[11px] text-gray-500 select-all" title="{{ $snap->snapshot_hash }}">
                                    {{ substr($snap->snapshot_hash, 0, 16) }}...{{ substr($snap->snapshot_hash, -8) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Nota Regulamentar e Orientação de Auditoria -->
    <div class="p-5 rounded-2xl bg-indigo-50/60 border border-indigo-100 text-xs text-indigo-900 space-y-2">
        <div class="flex items-center gap-2 font-bold text-indigo-950">
            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            Orientações Oficiais da Fiscalização do Trabalho (Portaria 671/2021 MTP)
        </div>
        <p class="leading-relaxed text-indigo-800/90">
            O PontoFácil 2.0 atua simultaneamente como <strong>REP-P</strong> (gerador de AFD e comprovantes do trabalhador) e como <strong>PTRP</strong> (motor de tratamento, escalas e gerador do AEJ).
            O empregador pode disponibilizar o pacote compactado (.ZIP) ou fornecer acesso read-only ao Auditor-Fiscal do Trabalho através do perfil <strong>Auditor</strong>, permitindo conferência autônoma dos arquivos fiscais e verificação de hashes sem necessidade de credenciais externas.
        </p>
    </div>

    <!-- Modal de Reabertura de Competência -->
    @if($showReopenModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-gray-100">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">Reabrir Competência {{ sprintf('%02d/%04d', $month, $year) }}</h3>
                    <button wire:click="$set('showReopenModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 leading-relaxed">
                    <strong>Atenção:</strong> A reabertura permite que novos ajustes manuais ou tratamentos sejam aprovados para esta competência.
                    O snapshot anterior será mantido permanentemente no histórico de auditoria e, ao fechar novamente, será gerada uma nova versão (ex: v{{ ($closedPeriod->snapshot_version ?? 1) + 1 }}).
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Justificativa Formal Obrigatória *</label>
                    <textarea wire:model="reopenReason" rows="3" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500" placeholder="Informe o motivo formal da reabertura (mínimo 10 caracteres)..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showReopenModal', false)" class="px-4 py-2 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmReopen" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition shadow-sm">
                        Confirmar Reabertura
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
