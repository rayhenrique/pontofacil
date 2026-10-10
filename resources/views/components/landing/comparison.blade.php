<section id="para-empresas" class="py-12 sm:py-14 md:py-16 lg:py-20 relative">
    <div id="beneficios" class="absolute -top-24 left-0 pointer-events-none"></div>
    <div id="comparativo" class="absolute -top-24 left-0 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-12 space-y-2.5 sm:space-y-3">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span>Colaborador x Empresa</span>
            </div>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-black text-slate-900 tracking-tight">
                Para quem registra. <br class="hidden sm:inline" />
                <span class="text-indigo-600">
                    Para quem gerencia.
                </span>
            </h2>
            <p class="text-sm sm:text-base md:text-lg text-slate-600 font-normal leading-relaxed">
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
            
            <!-- Seletor de Tabs em Telas Menores (< 1024px) com área de toque mínima confortável -->
            <div class="lg:hidden flex justify-center mb-6">
                <div class="inline-flex p-1.5 rounded-2xl bg-slate-200/80 border border-slate-300/60 shadow-inner w-full max-w-md"
                     role="tablist"
                     aria-label="Público atendido pelo PontoFácil">
                    <button @click="tab = 'registra'" 
                            id="tab-registra"
                            role="tab"
                            :aria-selected="tab === 'registra'"
                            aria-controls="panel-registra"
                            type="button" 
                            :class="tab === 'registra' ? 'bg-white text-indigo-700 shadow-sm font-bold' : 'text-slate-600 font-semibold'"
                            class="flex-1 py-3 px-3.5 rounded-xl text-xs min-[380px]:text-sm transition-all duration-200 text-center min-h-[48px] flex items-center justify-center gap-1.5 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">
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
                            :class="tab === 'gerencia' ? 'bg-white text-purple-700 shadow-sm font-bold' : 'text-slate-600 font-semibold'"
                            class="flex-1 py-3 px-3.5 rounded-xl text-xs min-[380px]:text-sm transition-all duration-200 text-center min-h-[48px] flex items-center justify-center gap-1.5 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-600">
                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                        <span>Para quem gerencia</span>
                    </button>
                </div>
            </div>

            <!-- Grid de Cards: Empilhados / Alternados no Mobile, 2 Colunas no Desktop -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 items-stretch">
                
                <!-- Card 1: Para quem registra (Colaborador) -->
                <div :class="(!isMobile || tab === 'registra') ? 'flex' : 'hidden lg:flex'"
                     id="panel-registra"
                     role="tabpanel"
                     aria-labelledby="tab-registra"
                     class="gsap-comparison-card bg-white border border-slate-200/90 rounded-2xl sm:rounded-3xl p-5 sm:p-6 md:p-8 shadow-xs hover:border-slate-300 transition-all duration-200 flex-col justify-between">
                    <div class="space-y-5">
                        
                        <!-- Cabeçalho do Card -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 text-xs sm:text-sm font-bold tracking-tight">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>Para quem registra</span>
                            </div>
                            <span class="text-[11px] sm:text-xs font-semibold text-slate-500">Colaborador</span>
                        </div>

                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mb-1.5">
                                Autonomia e transparência na palma da mão
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Tudo o que o trabalhador precisa para registrar seu ponto e acompanhar seus dados sem burocracia.
                            </p>
                        </div>

                        <!-- 5 Itens Principais do Colaborador -->
                        <ul class="space-y-3 sm:space-y-3.5 text-xs sm:text-sm text-slate-700">
                            
                            <!-- 1. Registrar ponto -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center font-bold mt-0.5">
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
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Visualizar histórico:</strong>
                                    <span class="text-slate-600">Consulta completa das batidas realizadas e acompanhamento de registros anteriores.</span>
                                </div>
                            </li>

                            <!-- 3. Receber comprovantes -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Receber comprovantes:</strong>
                                    <span class="text-slate-600">Recibo eletrônico imediato após a marcação, com verificação pública de autenticidade.</span>
                                </div>
                            </li>

                            <!-- 4. Consultar espelho -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Consultar espelho:</strong>
                                    <span class="text-slate-600">Acesso transparente às horas normais, adicionais e saldo do banco de horas em tempo real.</span>
                                </div>
                            </li>

                            <!-- 5. Solicitar ajustes -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Solicitar ajustes:</strong>
                                    <span class="text-slate-600">Envio simples de justificativas ou atestados para análise do gestor em caso de esquecimento.</span>
                                </div>
                            </li>

                        </ul>
                    </div>

                    <div class="pt-4 mt-6 border-t border-slate-100 text-xs text-indigo-600 font-semibold flex items-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                        <span>Sem necessidade de aplicativo na loja</span>
                    </div>
                </div>

                <!-- Card 2: Para quem gerencia (Empresa, Gestores e RH) -->
                <div :class="(!isMobile || tab === 'gerencia') ? 'flex' : 'hidden lg:flex'"
                     id="panel-gerencia"
                     role="tabpanel"
                     aria-labelledby="tab-gerencia"
                     class="gsap-comparison-card bg-white border border-slate-200/90 rounded-2xl sm:rounded-3xl p-5 sm:p-6 md:p-8 shadow-xs hover:border-slate-300 transition-all duration-200 flex-col justify-between">
                    <div class="space-y-5">
                        
                        <!-- Cabeçalho do Card -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-purple-50 text-purple-700 text-xs sm:text-sm font-bold tracking-tight">
                                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                <span>Para quem gerencia</span>
                            </div>
                            <span class="text-[11px] sm:text-xs font-semibold text-slate-500">Empresa & RH</span>
                        </div>

                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mb-1.5">
                                Previsibilidade e controle sem retrabalho
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Ferramentas completas para acompanhamento de equipes e fechamento de folha sem complicação.
                            </p>
                        </div>

                        <!-- 6 Itens Principais da Empresa / Gestão -->
                        <ul class="space-y-3 sm:space-y-3.5 text-xs sm:text-sm text-slate-700">
                            
                            <!-- 1. Acompanhar jornadas -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Acompanhar jornadas:</strong>
                                    <span class="text-slate-600">Painel com visão ao vivo de entradas, pausas, saídas e colaboradores ativos por setor.</span>
                                </div>
                            </li>

                            <!-- 2. Tratar ocorrências -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Tratar ocorrências:</strong>
                                    <span class="text-slate-600">Aprovação ou recusa ágil de solicitações de ajuste e atestados em fluxo centralizado.</span>
                                </div>
                            </li>

                            <!-- 3. Banco de horas -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Banco de horas:</strong>
                                    <span class="text-slate-600">Compensações automáticas e controle de saldo positivo ou negativo por período.</span>
                                </div>
                            </li>

                            <!-- 4. Fechamento -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Fechamento:</strong>
                                    <span class="text-slate-600">Encerramento mensal seguro por competência, evitando cálculos manuais e erros na folha.</span>
                                </div>
                            </li>

                            <!-- 5. Relatórios -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Relatórios:</strong>
                                    <span class="text-slate-600">Exportação rápida de espelhos de ponto assináveis e dados para contabilidade.</span>
                                </div>
                            </li>

                            <!-- 6. Auditoria -->
                            <li class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-lg bg-purple-100/80 text-purple-700 flex items-center justify-center font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <strong class="text-slate-900 font-bold block sm:inline">Auditoria:</strong>
                                    <span class="text-slate-600">Trilha de eventos protegida e arquivos fiscais prontos para conformidade e fiscalização.</span>
                                </div>
                            </li>

                        </ul>
                    </div>

                    <div class="pt-4 mt-6 border-t border-slate-100 text-xs text-purple-700 font-semibold flex items-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-600"></span>
                        <span>Processamento automatizado sem planilhas manuais</span>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>
