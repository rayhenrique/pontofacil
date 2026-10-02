<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Registro de Ponto • {{ $receipt->verification_code }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .receipt-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 font-sans p-4 sm:p-8 min-h-screen flex flex-col items-center justify-center">

    <!-- Ações superiores na tela -->
    <div class="no-print w-full max-w-xl mb-4 flex items-center justify-between">
        <a href="{{ url()->previous() ?: route('receipts.center') }}" class="text-sm font-medium text-slate-600 hover:text-indigo-600 flex items-center gap-1.5 transition">
            ← Voltar
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('receipts.pdf', $receipt->verification_code) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition shadow-xs">
                Baixar PDF
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-xs">
                Imprimir
            </button>
        </div>
    </div>

    <!-- Cartão do Comprovante -->
    <div class="receipt-card w-full max-w-xl bg-white border border-slate-300 rounded-2xl p-6 sm:p-8 shadow-md">
        
        <!-- Cabeçalho -->
        <div class="text-center pb-5 border-b border-slate-200">
            <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-900 mb-2">
                Ambiente de Testes / Não Assinado Digitalmente
            </span>
            <h1 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight">
                Comprovante de Registro de Ponto
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Conformidade com a Portaria MTP nº 671/2021 (REP-P)
            </p>
        </div>

        @php
            $event = $receipt->punchEvent;
            $est = $event->establishment;
            $comp = $est->company;
            $user = $event->user;
            $emp = $event->employee;
            $nsrFormatado = str_pad((string)$event->nsr, 9, '0', STR_PAD_LEFT);
        @endphp

        <!-- 1. Empregador -->
        <div class="py-4 border-b border-slate-100 text-xs space-y-1.5">
            <h2 class="font-bold text-slate-700 uppercase tracking-wider text-[11px] mb-2">1. Empregador & Local de Trabalho</h2>
            <div class="flex justify-between"><span class="text-slate-500">Razão Social:</span> <span class="font-semibold text-slate-800">{{ $comp->legal_name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">CNPJ do Empregador:</span> <span class="font-mono text-slate-800">{{ $comp->cnpj }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Estabelecimento:</span> <span class="font-medium text-slate-800">{{ $est->name }} ({{ $est->code }})</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Município/UF:</span> <span class="text-slate-800">{{ $est->city }}/{{ $est->state }}</span></div>
        </div>

        <!-- 2. Trabalhador -->
        <div class="py-4 border-b border-slate-100 text-xs space-y-1.5">
            <h2 class="font-bold text-slate-700 uppercase tracking-wider text-[11px] mb-2">2. Dados do Trabalhador</h2>
            <div class="flex justify-between"><span class="text-slate-500">Nome:</span> <span class="font-semibold text-slate-800">{{ $user->name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">CPF:</span> <span class="font-mono text-slate-800">{{ $emp?->cpf ?? 'Não cadastrado' }}</span></div>
            @if($emp?->job_title)
                <div class="flex justify-between"><span class="text-slate-500">Cargo:</span> <span class="text-slate-800">{{ $emp->job_title }}</span></div>
            @endif
        </div>

        <!-- 3. Registro de Ponto -->
        <div class="py-4 border-b border-slate-100 text-xs space-y-2 bg-slate-50/70 -mx-6 px-6 sm:-mx-8 sm:px-8">
            <h2 class="font-bold text-slate-700 uppercase tracking-wider text-[11px] mb-2">3. Registro Efetivado</h2>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">NSR (Número Sequencial):</span>
                <span class="font-mono font-black text-sm text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md">
                    #{{ $nsrFormatado }}
                </span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Operação / Sentido:</span>
                <span class="font-bold px-2 py-0.5 rounded text-[11px] {{ $event->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $event->direction === 'in' ? 'ENTRADA' : 'SAÍDA' }}
                </span>
            </div>
            <div class="flex justify-between"><span class="text-slate-500">Data e Hora (Local):</span> <span class="font-semibold text-slate-900">{{ $event->occurred_at_local->format('d/m/Y H:i:s') }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Fuso Horário Oficial:</span> <span class="text-slate-800">{{ $event->timezone }} ({{ $event->utc_offset }})</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Carimbo UTC:</span> <span class="font-mono text-slate-600 text-[11px]">{{ $event->occurred_at_utc->format('Y-m-d\TH:i:s\Z') }}</span></div>
        </div>

        <!-- 4. Autenticidade e Hash -->
        <div class="pt-4 text-[11px] space-y-2">
            <h2 class="font-bold text-slate-700 uppercase tracking-wider text-[11px] mb-2">4. Integridade do Registro</h2>
            <div>
                <span class="text-slate-500 block text-[10px]">Código de Autenticidade:</span>
                <span class="font-mono font-bold text-slate-900 select-all">{{ $receipt->verification_code }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px]">Hash SHA-256 do Registro:</span>
                <span class="font-mono text-[10px] text-slate-700 break-all select-all">{{ $event->payload_hash }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px]">Hash do Comprovante:</span>
                <span class="font-mono text-[10px] text-slate-700 break-all select-all">{{ $receipt->receipt_hash }}</span>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 text-center">
                <a href="{{ $receipt->verificationUrl() }}" target="_blank" class="text-indigo-600 font-semibold text-[11px] hover:underline">
                    {{ $receipt->verificationUrl() }}
                </a>
                <p class="text-[10px] text-slate-400 mt-0.5">Consulte este link público para auditar o registro no ledger.</p>
            </div>
        </div>

    </div>

</body>
</html>
