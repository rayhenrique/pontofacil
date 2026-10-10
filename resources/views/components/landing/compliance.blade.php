<section id="portaria-671" class="py-10 sm:py-14 lg:py-16 relative bg-slate-50/60 border-y border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho Sóbrio da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-10 lg:mb-12 space-y-2">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">05 — Portaria 671 / MTP</p>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">
                Estrutura preparada para a Portaria 671
            </h2>
            <p class="text-sm sm:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Arquitetura técnica baseada na separação funcional entre coleta bruta e tratamento da jornada, priorizando rastreabilidade, transparência e integridade das informações.
            </p>
        </div>

        <div class="max-w-5xl mx-auto space-y-8">
            
            <!-- 1. Fluxo Arquitetural Visual: REP-P -> ARP -> PTRP -> Espelho/AEJ -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-xs">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-5">
                    Fluxo Arquitetural da Portaria 671
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 relative">
                    
                    <!-- Etapa 1: REP-P -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-2 relative">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-bold text-xs">REP-P</span>
                            <span class="text-[11px] text-slate-400">Coleta Fiel</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            REP-P registra os eventos brutos
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Captura cada batida exatamente no instante em que ocorre, sem filtros ou bloqueios.
                        </p>
                    </div>

                    <!-- Etapa 2: ARP -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-2 relative">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-bold text-xs">ARP</span>
                            <span class="text-[11px] text-slate-400">Ledger Imutável</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Trilha de eventos e recibos
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Comprovantes são gerados após a marcação com carimbo de tempo e identificador de validação.
                        </p>
                    </div>

                    <!-- Etapa 3: PTRP -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-2 relative">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-bold text-xs">PTRP</span>
                            <span class="text-[11px] text-slate-400">Apuração</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            PTRP trata jornadas, ocorrências e fechamento
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Processa tolerâncias contratuais, banco de horas e justificativas sem alterar o dado bruto.
                        </p>
                    </div>

                    <!-- Etapa 4: Espelho / AEJ -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-2 relative">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-bold text-xs">Espelho / AEJ</span>
                            <span class="text-[11px] text-slate-400">Exportação</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Consolidação fiscal formal
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Espelho de ponto mensal assinado e arquivos fiscais prontos para auditoria e contabilidade.
                        </p>
                    </div>

                </div>
            </div>

            <!-- 2. Três Princípios Arquiteturais Essenciais -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                
                <!-- Princípio 1: Registros de ponto não são alterados pelo tratamento -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-5 space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Princípio 01</span>
                    </div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Registros de ponto não são alterados pelo tratamento
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        A rotina de tratamento de ponto nunca sobrescreve ou apaga a batida original. Justificativas e correções geram eventos auditados complementares, mantendo o histórico bruto preservado.
                    </p>
                </div>

                <!-- Princípio 2: Trilhas de auditoria são preservadas -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-5 space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Princípio 02</span>
                    </div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Trilhas de auditoria são preservadas
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Manutenção contínua de sequência temporal com Número Sequencial de Registro (NSR) por estabelecimento, viabilizando exportações fiscais estruturadas (AFD e AEJ).
                    </p>
                </div>

                <!-- Princípio 3: INPI e ICP-Brasil como etapas complementares -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-5 space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Princípio 03</span>
                    </div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        INPI e ICP-Brasil como etapas complementares
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        O registro de programa no INPI e a utilização de certificados digitais padrão ICP-Brasil (CAdES/PAdES) são etapas regulatórias complementares previstas na norma técnica quando aplicáveis.
                    </p>
                </div>

            </div>

            <!-- Nota Técnica de Sobriedade e Transparência Regulatória -->
            <div class="bg-slate-100/90 border border-slate-200 rounded-xl p-4 sm:p-5 text-slate-600 text-xs leading-relaxed">
                <div class="flex items-start gap-3">
                    <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <div class="space-y-1">
                        <p class="font-bold text-slate-800">Nota sobre posicionamento normativo:</p>
                        <p>
                            O PontoFácil desenvolve suas rotinas e leiautes em consonância com as especificações da Portaria 671/2021 MTP (REP-P e PTRP). O sistema opera de forma autônoma para registro e gestão de ponto, sem promessas comerciais de certificação governamental prévia ou atestados em trâmite.
                        </p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>
