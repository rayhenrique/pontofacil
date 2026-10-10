<section id="para-empresas" class="py-10 sm:py-14 lg:py-16 relative">
    <div id="beneficios" class="absolute -top-24 left-0 pointer-events-none"></div>
    <div id="comparativo" class="absolute -top-24 left-0 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-10 lg:mb-12 space-y-2">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">
                02 — Colaborador x Empresa
            </p>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">
                Para quem registra. <br class="hidden sm:inline" />
                <span class="text-indigo-600">
                    Para quem gerencia.
                </span>
            </h2>
            <p class="text-sm sm:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Desenvolvido para oferecer simplicidade no dia a dia do colaborador e controle completo para a gestão.
            </p>
        </div>

        <!-- Container com Alpine.js para Abordagem Mobile-First -->
        <div x-data="{ 
            tab: 'registra', 
            isMobile: window.innerWidth < 1024 
        }" 
        x-init="window.addEventListener('resize', () => { isMobile = window.innerWidth < 1024 })"
        class="max-w-6xl mx-auto">
            
            <!-- Seletor de Tabs em Telas Menores (< 1024px) -->
            <div class="lg:hidden flex justify-center mb-6">
                <div class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 w-full max-w-md"
                     role="tablist"
                     aria-label="Público atendido pelo PontoFácil">
                    <button @click="tab = 'registra'" 
                            id="tab-registra"
                            role="tab"
                            :aria-selected="tab === 'registra'"
                            aria-controls="panel-registra"
                            type="button" 
                            :class="tab === 'registra' ? 'bg-white text-indigo-700 shadow-xs font-bold' : 'text-slate-600 font-semibold'"
                            class="flex-1 py-2.5 px-3 rounded-lg text-xs min-[380px]:text-sm transition-all duration-200 text-center min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span>Para quem registra</span>
                    </button>
                    <button @click="tab = 'gerencia'" 
                            id="tab-gerencia"
                            role="tab"
                            :aria-selected="tab === 'gerencia'"
                            aria-controls="panel-gerencia"
                            type="button" 
                            :class="tab === 'gerencia' ? 'bg-white text-indigo-700 shadow-xs font-bold' : 'text-slate-600 font-semibold'"
                            class="flex-1 py-2.5 px-3 rounded-lg text-xs min-[380px]:text-sm transition-all duration-200 text-center min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                        <span>Para quem gerencia</span>
                    </button>
                </div>
            </div>

            <!-- Composição Assimétrica no Desktop: Colaborador (Painel compacto com interface) x Gestão (Matriz de capacidades) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
                
                <!-- Lado Esquerdo (Desktop: 5 colunas): Para quem registra (Colaborador) -->
                <div :class="(!isMobile || tab === 'registra') ? 'flex' : 'hidden lg:flex'"
                     id="panel-registra"
                     role="tabpanel"
                     aria-labelledby="tab-registra"
                     class="gsap-comparison-card lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 md:p-7 shadow-xs hover:border-slate-300 transition-all duration-200 flex-col justify-between space-y-5">
                    
                    <div class="space-y-4">
                        <!-- Cabeçalho do Colaborador -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs sm:text-sm font-bold tracking-tight">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>Para quem registra</span>
                            </div>
                            <span class="text-[11px] sm:text-xs font-semibold text-slate-500">Colaborador</span>
                        </div>

                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mb-1">
                                Autonomia e transparência na palma da mão
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Tudo o que o trabalhador precisa para registrar seu ponto e acompanhar seus dados sem burocracia.
                            </p>
                        </div>

                        <!-- Mini Representação Visual da Interface do Colaborador com Dados Reais -->
                        <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-3.5 space-y-2 font-mono">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-900 flex items-center gap-1.5 font-sans">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Entrada Confirmada
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-900">08:02:14</span>
                                    <span class="text-[10px] text-indigo-600 font-bold bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200/60">#000004281</span>
                                </div>
                            </div>
                            <div class="text-[11px] text-slate-600 flex items-center justify-between font-sans">
                                <span class="flex items-center gap-1 text-[11px]">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    Localização registrada (4m)
                                </span>
                                <span class="font-mono text-[10px] font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60">PF-2026-8942</span>
                            </div>
                        </div>

                        <!-- 5 Itens Principais do Colaborador -->
                        <ul class="space-y-3 text-xs sm:text-sm text-slate-700 pt-1">
                            
                            <!-- 1. Registrar ponto -->
                            <li class="flex items-start gap-2.5">
                                <div class="shrink-0 w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Registrar ponto:</strong>
                                    <span class="text-slate-600">Confirmação rápida pelo navegador do celular ou leitura de QR Code em segundos.</span>
                                </div>
                            </li>

                            <!-- 2. Visualizar histórico -->
                            <li class="flex items-start gap-2.5">
                                <div class="shrink-0 w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Visualizar histórico:</strong>
                                    <span class="text-slate-600">Consulta imediata aos horários batidos, saldo diário e conferência transparente.</span>
                                </div>
                            </li>

                            <!-- 3. Receber comprovantes -->
                            <li class="flex items-start gap-2.5">
                                <div class="shrink-0 w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Receber comprovantes:</strong>
                                    <span class="text-slate-600">Emissão instantânea de recibo digital com identificador e carimbo de tempo oficial.</span>
                                </div>
                            </li>

                            <!-- 4. Consultar espelho -->
                            <li class="flex items-start gap-2.5">
                                <div class="shrink-0 w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Consultar espelho:</strong>
                                    <span class="text-slate-600">Acompanhamento mensal de horas normais, extras acumuladas e banco de horas.</span>
                                </div>
                            </li>

                            <!-- 5. Solicitar ajustes -->
                            <li class="flex items-start gap-2.5">
                                <div class="shrink-0 w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Solicitar ajustes:</strong>
                                    <span class="text-slate-600">Envio direto de atestados médicos ou justificativas de esquecimento ao gestor.</span>
                                </div>
                            </li>

                        </ul>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-xs text-indigo-700 font-semibold flex items-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                        <span>Sem dependência de biometria física ou catracas</span>
                    </div>
                </div>

                <!-- Lado Direito (Desktop: 7 colunas): Para quem gerencia (Empresa & RH) -->
                <div :class="(!isMobile || tab === 'gerencia') ? 'flex' : 'hidden lg:flex'"
                     id="panel-gerencia"
                     role="tabpanel"
                     aria-labelledby="tab-gerencia"
                     class="gsap-comparison-card lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 md:p-7 shadow-xs hover:border-slate-300 transition-all duration-200 flex-col justify-between space-y-5">
                    
                    <div class="space-y-4">
                        <!-- Cabeçalho da Gestão -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs sm:text-sm font-bold tracking-tight">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                <span>Para quem gerencia</span>
                            </div>
                            <span class="text-[11px] sm:text-xs font-semibold text-slate-500">Empresa & RH</span>
                        </div>

                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mb-1">
                                Previsibilidade e controle sem retrabalho
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Ferramentas completas para acompanhamento de equipes e fechamento de folha sem complicação.
                            </p>
                        </div>

                        <!-- Mini Representação Visual da Gestão / PTRP com Dados Reais -->
                        <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-3.5 space-y-2 font-mono">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-900 flex items-center gap-1.5 font-sans">
                                    <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                    Painel Gerencial • Apuração PTRP
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-900 font-sans">48 em jornada</span>
                                    <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200/60">Folha 99.4%</span>
                                </div>
                            </div>
                            <div class="text-[11px] text-slate-600 flex items-center justify-between font-sans">
                                <span class="text-[11px]">Banco de horas consolidado sem planilhas</span>
                                <span class="font-mono text-[10px] font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200/60">+01h15m saldo</span>
                            </div>
                        </div>

                        <!-- Matriz de Capacidades Estruturada (2 Colunas no Desktop) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs sm:text-sm text-slate-700 pt-1">
                            
                            <!-- 1. Acompanhar jornadas -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Acompanhar jornadas:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Painel com visão ao vivo de entradas, pausas, saídas e colaboradores ativos por setor.
                                </p>
                            </div>

                            <!-- 2. Tratar ocorrências -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Tratar ocorrências:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Aprovação ou recusa ágil de solicitações de ajuste e atestados em fluxo centralizado.
                                </p>
                            </div>

                            <!-- 3. Banco de horas -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Banco de horas:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Compensações automáticas e controle de saldo positivo ou negativo por período.
                                </p>
                            </div>

                            <!-- 4. Fechamento -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Fechamento:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Encerramento mensal seguro por competência, evitando cálculos manuais e erros na folha.
                                </p>
                            </div>

                            <!-- 5. Relatórios -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Relatórios:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Exportação rápida de espelhos de ponto assináveis e dados para contabilidade.
                                </p>
                            </div>

                            <!-- 6. Auditoria -->
                            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="shrink-0 w-5 h-5 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <strong class="text-slate-900 font-bold">Auditoria:</strong>
                                </div>
                                <p class="text-slate-600 text-xs leading-relaxed pl-7">
                                    Trilha de eventos protegida e arquivos fiscais prontos para conformidade e fiscalização.
                                </p>
                            </div>

                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-xs text-indigo-700 font-semibold flex items-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                        <span>Processamento automatizado sem planilhas manuais</span>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>
