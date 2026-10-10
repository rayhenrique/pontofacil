<section id="portaria-671" class="py-12 sm:py-14 md:py-16 lg:py-20 relative bg-slate-50/60 border-y border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho Sóbrio da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-12 space-y-2.5 sm:space-y-3">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">05 — Portaria 671 / MTP</p>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">
                Estrutura preparada para a Portaria 671
            </h2>
            <p class="text-sm sm:text-base md:text-lg text-slate-600 font-normal leading-relaxed">
                Arquitetura técnica baseada na separação funcional entre coleta bruta e tratamento da jornada, priorizando rastreabilidade, transparência e integridade das informações.
            </p>
        </div>

        <!-- Grid de 6 Pilares Técnicos e Sóbrios -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6 max-w-6xl mx-auto">
            
            <!-- 1. REP-P: Eventos Brutos -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-xs font-bold w-fit">
                            REP-P
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Coleta Fiel</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        REP-P registra os eventos brutos
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        O Registrador Eletrônico de Ponto por Programa coleta cada batida exatamente no instante em que ocorre, armazenando o evento original no ledger imutável da ARP.
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Dado bruto capturado sem interferências</span>
                </div>
            </div>

            <!-- 2. PTRP: Tratamento de Jornada -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-xs font-bold w-fit">
                            PTRP
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Apuração</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        PTRP trata jornadas, ocorrências e fechamento
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        O Programa de Tratamento de Registro de Ponto processa horários contratuais, banco de horas, tolerâncias, abonos e consolidação do espelho mensal de ponto.
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Cálculos de jornada e espelho formal</span>
                </div>
            </div>

            <!-- 3. Registros de ponto não são alterados pelo tratamento -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-xs font-bold w-fit">
                            Integridade
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Desacoplamento</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Registros de ponto não são alterados pelo tratamento
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        A rotina de tratamento de ponto nunca sobrescreve ou apaga a batida original. Justificativas e correções geram eventos auditados complementares, mantendo o histórico bruto preservado.
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Preservação perpétua da marcação original</span>
                </div>
            </div>

            <!-- 4. Comprovantes após a marcação -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 text-xs font-bold w-fit">
                            Recibo
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Transparência</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Comprovantes são gerados após a marcação
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Ao concluir o registro, o trabalhador recebe imediatamente o comprovante digital com carimbo de tempo, dados do empregador e identificador para verificação pública.
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    <span>Disponível instantaneamente ao colaborador</span>
                </div>
            </div>

            <!-- 5. Trilhas de Auditoria -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 text-xs font-bold w-fit">
                            Auditoria
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Rastreabilidade</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Trilhas de auditoria são preservadas
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Manutenção contínua de sequência temporal com Número Sequencial de Registro (NSR) por estabelecimento, viabilizando exportações fiscais estruturadas (AFD e AEJ).
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                    <span>NSR contínuo e histórico cronológico</span>
                </div>
            </div>

            <!-- 6. INPI e ICP-Brasil como etapas complementares -->
            <div class="gsap-compliance-card bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 text-xs font-bold w-fit">
                            Regulatório
                        </span>
                        <span class="text-[11px] font-medium text-slate-500">Transparência</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        INPI e ICP-Brasil como etapas complementares
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        O registro de programa no INPI e a utilização de certificados digitais padrão ICP-Brasil (CAdES/PAdES) são etapas regulatórias complementares previstas na norma técnica quando aplicáveis.
                    </p>
                </div>
                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                    <span>Etapas normativas tratadas com clareza</span>
                </div>
            </div>

        </div>

        <!-- Nota Técnica de Sobriedade e Transparência Regulatória -->
        <div class="mt-8 sm:mt-12 max-w-4xl mx-auto">
            <div class="bg-slate-100/90 border border-slate-200 rounded-xl p-4 sm:p-6 text-slate-600 text-xs sm:text-sm leading-relaxed">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-slate-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
