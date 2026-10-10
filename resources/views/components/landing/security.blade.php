<section id="seguranca" class="py-10 sm:py-14 md:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-12 space-y-2.5 sm:space-y-3">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span>Segurança e Rastreabilidade</span>
            </div>
            <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                Rastreabilidade desde o registro e trilha de auditoria
            </h2>
            <p class="text-sm sm:text-base md:text-lg text-slate-600 font-normal leading-relaxed">
                Arquitetura técnica com separação entre a coleta original do ponto e o tratamento da jornada, assegurando integridade e transparência em todas as etapas.
            </p>
        </div>

        <!-- 4 Pilares Técnicos de Segurança -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6 max-w-7xl mx-auto">
            
            <!-- Pilar 1: Registros imutáveis da ARP & NSR Sequencial -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                    </div>

                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Registros imutáveis da ARP e NSR sequencial
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Cada batida é gravada na ARP com Número Sequencial de Registro (NSR) contínuo e irrepetível. O registro original bruto permanece preservado sem possibilidade de exclusão.
                    </p>
                </div>

                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold text-indigo-600">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>NSR contínuo por estabelecimento</span>
                </div>
            </div>

            <!-- Pilar 2: Hash SHA-256 encadeado -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>

                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Integridade com Hash SHA-256
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Cada marcação gera um hash fiscal SHA-256 calculado em cadeia a partir do evento anterior, permitindo verificar e auditar a consistência de toda a sequência histórica.
                    </p>
                </div>

                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold text-indigo-600">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Cadeia fiscal rastreável</span>
                </div>
            </div>

            <!-- Pilar 3: Separação de Registro Bruto vs Tratamento de Jornada -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>

                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Registro bruto separado do tratamento
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        O REP-P registra o dado bruto original, enquanto o PTRP realiza o tratamento da jornada (cálculos e ajustes) de forma independente, sem sobrescrever o evento coletado.
                    </p>
                </div>

                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold text-purple-700">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-600"></span>
                    <span>Preservação do dado original</span>
                </div>
            </div>

            <!-- Pilar 4: Comprovantes e Histórico de Marcações com Validação Pública -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>

                    <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                        Comprovantes e histórico de marcações
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Cada registro gera um comprovante digital com identificador único e carimbo de tempo. Qualquer trabalhador ou auditor pode validar sua autenticidade no portal público.
                    </p>
                </div>

                <div class="pt-3 mt-4 border-t border-slate-100 flex items-center justify-between text-[11px] sm:text-xs font-semibold text-emerald-700">
                    <a href="{{ route('receipts.verify') }}" class="hover:underline flex items-center gap-1">
                        <span>Validar comprovante</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>

        </div>

    </div>
</section>
