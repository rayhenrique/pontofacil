<section class="py-10 sm:py-14 bg-slate-50/70 border-y border-slate-200/80 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-10 space-y-2">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-indigo-50 border border-indigo-100/80 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                <span>Ciclo do Registro • REP-P & PTRP</span>
            </div>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">
                O que acontece quando alguém registra o ponto?
            </h2>
            <p class="text-sm sm:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Entenda o fluxo técnico de um registro de jornada desde a captura no celular até a consolidação no espelho de ponto.
            </p>
        </div>

        <!-- Fluxo Visual Proprietário: 4 Marcos Conectados -->
        <div class="max-w-5xl mx-auto">
            
            <!-- Grid de Etapas com Conectores -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 relative">
                
                <!-- Marco 1: O Registro na Origem -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-3 relative flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Passo 01 • Coleta</span>
                            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200/60">Ao vivo</span>
                        </div>
                        
                        <!-- Dados Reais da Batida (Horário, Operação, NSR) -->
                        <div class="bg-slate-900 text-white rounded-xl p-3 space-y-1 font-mono">
                            <div class="text-base font-extrabold tracking-tight text-white flex items-center justify-between">
                                <span>08:01:32</span>
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            </div>
                            <div class="text-xs font-semibold text-emerald-400">
                                Entrada registrada
                            </div>
                            <div class="text-[11px] text-slate-400">
                                NSR 000004281
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed pt-1">
                            Dispositivo captura data, horário oficial e contexto no momento exato da leitura do QR Code.
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Localização registrada</span>
                    </div>
                </div>

                <!-- Marco 2: Registro Original Preservado -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-3 relative flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Passo 02 • Ledger ARP</span>
                            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200/60">Imutável</span>
                        </div>

                        <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3 space-y-1">
                            <div class="flex items-center gap-2 text-indigo-900 font-bold text-xs">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                                <span>Registro original preservado</span>
                            </div>
                            <p class="text-[11px] text-indigo-700/90 font-mono">
                                Hash SHA-256 em cadeia
                            </p>
                            <p class="text-[10px] text-slate-500 font-mono truncate">
                                4f8a9e...c72b1d
                            </p>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed pt-1">
                            A batida é persistida na ARP como dado bruto intocado, garantindo fidelidade histórica sem exclusão.
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>NSR sequencial único</span>
                    </div>
                </div>

                <!-- Marco 3: Comprovante Disponível -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-3 relative flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Passo 03 • Recibo</span>
                            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200/60">Trabalhador</span>
                        </div>

                        <div class="bg-emerald-50/70 border border-emerald-100 rounded-xl p-3 space-y-1">
                            <div class="flex items-center gap-2 text-emerald-950 font-bold text-xs">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Comprovante disponível</span>
                            </div>
                            <p class="text-[11px] text-emerald-800 font-mono font-semibold">
                                PF-2026-8942-01
                            </p>
                            <p class="text-[10px] text-slate-500">
                                Validação pública habilitada
                            </p>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed pt-1">
                            O colaborador recebe na hora o comprovante eletrônico assinado para consulta ou download a qualquer tempo.
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Comprovante auditável</span>
                    </div>
                </div>

                <!-- Marco 4: Jornada Atualizada para Acompanhamento -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-3 relative flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Passo 04 • Gestão PTRP</span>
                            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200/60">Espelho</span>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 space-y-1">
                            <div class="flex items-center gap-2 text-slate-900 font-bold text-xs">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span>Jornada atualizada para acompanhamento</span>
                            </div>
                            <p class="text-[11px] text-slate-700 font-mono">
                                08:00h normais • Saldo +09m
                            </p>
                            <p class="text-[10px] text-slate-500">
                                Cálculo em tempo real
                            </p>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed pt-1">
                            O espelho de ponto e os cálculos de banco de horas são recalculados automaticamente para o gestor e RH.
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Tratamento segregado</span>
                    </div>
                </div>

            </div>

            <!-- Banner Explicativo de Segregação (Texto Complementar) -->
            <div class="mt-6 sm:mt-8 p-4 sm:p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">
                            O registro original permanece separado dos tratamentos e ajustes posteriores.
                        </h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            O REP-P preserva a integridade do fato cronológico, enquanto o PTRP processa ocorrências, justificativas e regras de banco de horas sem sobrescrever o evento original.
                        </p>
                    </div>
                </div>

                <div class="shrink-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">
                        Demonstração visual ilustrativa
                    </span>
                </div>
            </div>

        </div>

    </div>
</section>
