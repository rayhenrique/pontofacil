<?php

use Livewire\Component;
use App\Models\PunchReceipt;

new class extends Component
{
    public string $code = '';
    public ?PunchReceipt $receipt = null;
    public bool $searched = false;
    public bool $hashValid = false;

    public function mount()
    {
        $codigoParam = request()->query('codigo');
        if ($codigoParam) {
            $this->code = trim($codigoParam);
            $this->verify();
        }
    }

    public function verify()
    {
        $this->searched = true;
        $cleanCode = trim($this->code);

        if (empty($cleanCode)) {
            $this->receipt = null;
            return;
        }

        $this->receipt = PunchReceipt::with([
            'punchEvent.establishment.company',
            'punchEvent.user',
            'punchEvent.employee',
        ])->where('verification_code', $cleanCode)->first();

        if ($this->receipt && $this->receipt->punchEvent) {
            $event = $this->receipt->punchEvent;
            $fiscalHash = $event->fiscal_hash ?? $event->payload_hash;
            $canonicalData = sprintf(
                '%s|%d|%d|%s|%s|%s',
                $event->id,
                $event->establishment_id,
                $event->nsr,
                $event->user_id,
                $event->occurred_at_utc->toIso8601String(),
                $fiscalHash
            );
            $expectedHash = hash('sha256', $canonicalData);
            $this->hashValid = hash_equals($expectedHash, $this->receipt->receipt_hash);
        } else {
            $this->hashValid = false;
        }
    }
}; ?>

<div class="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center items-center">
    <div class="max-w-2xl w-full space-y-8">
        
        <!-- Topo da Consulta -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                Auditoria e Validação Pública
            </div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                Verificação de Comprovante de Ponto
            </h1>
            <p class="text-sm text-slate-600">
                Consulte a integridade de qualquer comprovante do trabalhador no ledger imutável do PontoFácil.
            </p>
        </div>

        <!-- Formulário de Consulta -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
            <form wire:submit="verify" class="space-y-4">
                <div>
                    <label for="code" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Código de Autenticidade do Comprovante
                    </label>
                    <div class="flex gap-2">
                        <input 
                            wire:model="code"
                            type="text" 
                            id="code" 
                            placeholder="Ex: PF-XXXX-XXXX-XXXX"
                            class="flex-1 font-mono uppercase px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none text-slate-900"
                        />
                        <button 
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl transition shadow-sm flex items-center gap-2"
                        >
                            <span wire:loading.remove>Verificar</span>
                            <span wire:loading>Consultando...</span>
                        </button>
                    </div>
                </div>
            </form>

            @if($searched)
                <div class="mt-8 pt-8 border-t border-slate-200">
                    @if($receipt)
                        @php
                            $event = $receipt->punchEvent;
                            $est = $event->establishment;
                            $comp = $est->company;
                            $user = $event->user;
                            $emp = $event->employee;
                            $nsrStr = str_pad((string)$event->nsr, 9, '0', STR_PAD_LEFT);
                        @endphp

                        <!-- Resultado Positivo -->
                        <div class="rounded-2xl p-6 border {{ $hashValid ? 'bg-emerald-50/70 border-emerald-200' : 'bg-amber-50/70 border-amber-200' }} space-y-6">
                            
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full {{ $hashValid ? 'bg-emerald-500' : 'bg-amber-500' }} text-white flex items-center justify-center font-bold text-lg">
                                    {{ $hashValid ? '✓' : '!' }}
                                </div>
                                <div>
                                    <h3 class="text-base font-bold {{ $hashValid ? 'text-emerald-950' : 'text-amber-950' }}">
                                        {{ $hashValid ? 'Registro Autêntico e Íntegro no Ledger' : 'Aviso: Divergência Detectada no Hash' }}
                                    </h3>
                                    <p class="text-xs {{ $hashValid ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $hashValid ? 'Os dados conferem com o registro inviolável persistido no banco de dados da empresa.' : 'O hash da carga útil não correspondeu ao esperado.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Ficha de Dados Auditados -->
                            <div class="bg-white rounded-xl p-5 border border-slate-200 text-xs space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pb-3 border-b border-slate-100">
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Empregador:</span>
                                        <span class="font-bold text-slate-800">{{ $comp->legal_name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">CNPJ do Estabelecimento:</span>
                                        <span class="font-mono text-slate-800">{{ $est->identifier_number }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Colaborador:</span>
                                        <span class="font-bold text-slate-800">{{ $user->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">CPF:</span>
                                        <span class="font-mono text-slate-800">{{ $emp?->cpf ?? 'Não cadastrado' }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pb-3 border-b border-slate-100">
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">NSR Monotônico:</span>
                                        <span class="font-mono font-black text-indigo-700 text-sm">#{{ $nsrStr }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Operação:</span>
                                        <span class="font-bold {{ $event->direction === 'in' ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ $event->direction === 'in' ? 'ENTRADA' : 'SAÍDA' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[11px]">Horário Local:</span>
                                        <span class="font-semibold text-slate-900">{{ $event->occurred_at_local->format('d/m/Y H:i:s') }}</span>
                                    </div>
                                </div>

                                <div class="space-y-1.5 pt-1 text-[11px]">
                                    <div>
                                        <span class="text-slate-500 block text-[10px]">Hash SHA-256 Fiscal (Portaria 671):</span>
                                        <span class="font-mono text-[10px] text-slate-700 break-all select-all">{{ $event->fiscal_hash ?? $event->payload_hash }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[10px]">Registro no INPI:</span>
                                        <span class="text-slate-700 text-[10px]">{{ $event->establishment?->company?->isRegisteredInpi() ? $event->establishment->company->inpi_registration_number : 'Pendente de Registro Oficial' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[10px]">Hash SHA-256 do Comprovante:</span>
                                        <span class="font-mono text-[10px] text-slate-700 break-all select-all">{{ $receipt->receipt_hash }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[10px]">Status da Assinatura:</span>
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                            Modo Desenvolvimento (ICP-Brasil Pendente de Instalação)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Botões de Ação -->
                            <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
                                <a 
                                    href="{{ route('receipts.print', $receipt->verification_code) }}" 
                                    target="_blank"
                                    class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl font-bold text-xs transition shadow-xs flex items-center gap-1.5"
                                >
                                    Imprimir Comprovante
                                </a>
                                <a 
                                    href="{{ route('receipts.pdf', $receipt->verification_code) }}" 
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs transition shadow-xs flex items-center gap-1.5"
                                >
                                    Baixar PDF
                                </a>
                            </div>

                        </div>
                    @else
                        <!-- Código Não Encontrado -->
                        <div class="rounded-2xl p-6 bg-red-50 border border-red-200 text-center space-y-2">
                            <div class="h-10 w-10 mx-auto rounded-full bg-red-500 text-white flex items-center justify-center font-bold text-lg">
                                ✕
                            </div>
                            <h3 class="text-base font-bold text-red-950">
                                Comprovante Não Localizado
                            </h3>
                            <p class="text-xs text-red-700 max-w-md mx-auto">
                                Nenhum registro de ponto foi encontrado com o código fornecido. Verifique se o código foi digitado corretamente.
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="text-center text-xs text-slate-400">
            <a href="{{ route('home') }}" class="text-indigo-600 hover:underline">Voltar ao Sistema PontoFácil</a>
        </div>

    </div>
</div>
