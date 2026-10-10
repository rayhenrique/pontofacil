<section id="seguranca" class="py-10 sm:py-14 md:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14 space-y-3 sm:space-y-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span>Segurança e Rastreabilidade</span>
            </div>
            <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                Trilha de auditoria contínua e dados protegidos
            </h2>
            <p class="text-sm sm:text-base md:text-lg text-slate-600 font-normal leading-relaxed">
                Cada evento conta com evidências digitais e validação criptográfica, proporcionando segurança jurídica irrefutável.
            </p>
        </div>

        <!-- 3 Pilares de Segurança -->
        <div class="grid md:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
            
            <!-- Pilar 1: Hash SHA-256 -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-6 sm:p-7 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>

                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                        Chave de Integridade SHA-256
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Cada batida de ponto gera uma assinatura digital em cadeia ligada ao evento anterior, impossibilitando qualquer tentativa de exclusão ou edição retroativa.
                    </p>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center gap-2 text-xs font-semibold text-indigo-600">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Cadeia fiscal imutável</span>
                </div>
            </div>

            <!-- Pilar 2: Validação Pública de Comprovante -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-6 sm:p-7 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>

                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                        Validação Pública de Comprovantes
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Qualquer trabalhador ou auditor pode verificar a autenticidade do comprovante a qualquer momento, via QR Code ou código verificador, sem precisar de login.
                    </p>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-emerald-700">
                    <a href="{{ route('receipts.verify') }}" class="hover:underline flex items-center gap-1">
                        <span>Acessar portal público</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Pilar 3: Localização como Evidência -->
            <div class="gsap-security-card bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-6 sm:p-7 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>

                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                        Evidências Adicionais de Localização
                    </h3>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Coordenadas geográficas registradas no instante da marcação como elemento probatório e apoio à gestão, sem impedir a realização do ponto.
                    </p>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center gap-2 text-xs font-semibold text-indigo-600">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                    <span>Análise transparente por cerca virtual</span>
                </div>
            </div>

        </div>

    </div>
</section>
