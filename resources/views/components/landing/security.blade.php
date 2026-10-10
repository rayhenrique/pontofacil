<section id="seguranca" class="py-10 sm:py-14 lg:py-16 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-10 lg:mb-12 space-y-2">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">04 — Segurança e rastreabilidade</p>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">
                Rastreabilidade desde o registro e trilha de auditoria
            </h2>
            <p class="text-sm sm:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Arquitetura técnica com separação entre a coleta original do ponto e o tratamento da jornada, assegurando integridade e transparência em todas as etapas.
            </p>
        </div>

        <!-- Narrativa de Fluxo Real de Segurança e Auditoria (Sem Cards Repetidos) -->
        <div class="max-w-4xl mx-auto bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 md:p-10 shadow-xs">
            
            <!-- Barra de Identificação do Evento Real / Trilha -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 rounded bg-slate-900 text-white font-mono text-xs font-bold tracking-wider">
                        08:01:32
                    </span>
                    <div>
                        <span class="text-xs font-bold text-slate-900">Entrada registrada</span>
                        <span class="text-slate-400 font-mono text-xs ml-2">NSR 000004281</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Cadeia fiscal ativa</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-[11px] text-slate-400">SHA-256</span>
                </div>
            </div>

            <!-- Fluxo Narrativo Sequencial dos 4 Pilares -->
            <div class="relative pt-6 sm:pt-8 space-y-6 sm:space-y-8">
                
                <!-- Linha Conectora Vertical Contínua -->
                <div class="absolute top-10 bottom-6 left-5 sm:left-6 w-px bg-slate-200 -z-0" aria-hidden="true"></div>

                <!-- 1. Registros imutáveis da ARP & NSR Sequencial -->
                <div class="gsap-security-card relative flex items-start gap-4 sm:gap-5 group">
                    <div class="relative z-10 w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-white border border-slate-200 text-indigo-600 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                    </div>

                    <div class="space-y-1 pt-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                                Registros imutáveis da ARP e NSR sequencial
                            </h3>
                            <span class="hidden sm:inline-block text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                NSR contínuo por estabelecimento
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                            Cada batida é gravada na ARP com Número Sequencial de Registro (NSR) contínuo e irrepetível. O registro original bruto permanece preservado sem possibilidade de exclusão.
                        </p>
                    </div>
                </div>

                <!-- 2. Hash SHA-256 encadeado -->
                <div class="gsap-security-card relative flex items-start gap-4 sm:gap-5 group">
                    <div class="relative z-10 w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-white border border-slate-200 text-indigo-600 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>

                    <div class="space-y-1 pt-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                                Integridade com Hash SHA-256
                            </h3>
                            <span class="hidden sm:inline-block text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                Cadeia fiscal rastreável
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                            Cada marcação gera um hash fiscal SHA-256 calculado em cadeia a partir do evento anterior, permitindo verificar e auditar a consistência de toda a sequência histórica.
                        </p>
                    </div>
                </div>

                <!-- 3. Separação de Registro Bruto vs Tratamento de Jornada -->
                <div class="gsap-security-card relative flex items-start gap-4 sm:gap-5 group">
                    <div class="relative z-10 w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-white border border-slate-200 text-indigo-600 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>

                    <div class="space-y-1 pt-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                                Registro bruto separado do tratamento
                            </h3>
                            <span class="hidden sm:inline-block text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                Preservação do dado original
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                            O REP-P registra o dado bruto original, enquanto o PTRP realiza o tratamento da jornada (cálculos e ajustes) de forma independente, sem sobrescrever o evento coletado.
                        </p>
                    </div>
                </div>

                <!-- 4. Comprovantes e Histórico de Marcações com Validação Pública -->
                <div class="gsap-security-card relative flex items-start gap-4 sm:gap-5 group">
                    <div class="relative z-10 w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-white border border-slate-200 text-emerald-600 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>

                    <div class="space-y-1.5 pt-1 w-full">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                                Comprovantes e histórico de marcações
                            </h3>
                            <a href="{{ route('receipts.verify') }}" 
                               class="hover:underline text-xs font-semibold text-emerald-700 flex items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 rounded"
                               aria-label="Validar comprovante de ponto no portal público">
                                <span>Validar comprovante</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                            Cada registro gera um comprovante digital com identificador único e carimbo de tempo. Qualquer trabalhador ou auditor pode validar sua autenticidade no portal público.
                        </p>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>
