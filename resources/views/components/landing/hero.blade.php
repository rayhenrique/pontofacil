<section id="hero" class="relative pt-6 pb-12 sm:pt-10 sm:pb-16 md:pt-16 md:pb-24 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-12 gap-8 lg:gap-8 items-center">
            
            <!-- Coluna Principal: Texto & Chamadas para Ação -->
            <div class="lg:col-span-7 space-y-4 sm:space-y-6 text-center lg:text-left">
                
                <!-- Badge Normativo / Categoria -->
                <div class="gsap-hero-element inline-flex items-center gap-2 px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full bg-white/90 backdrop-blur-md border border-indigo-100 shadow-2xs">
                    <span class="flex h-2 w-2 relative shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[11px] sm:text-xs font-bold tracking-tight sm:tracking-wider text-indigo-950 uppercase">
                        Controle de jornada digital • REP-P + PTRP
                    </span>
                </div>

                <!-- Título Objetivo (3-4 linhas no mobile) -->
                <h1 class="gsap-hero-element text-2xl min-[360px]:text-[26px] sm:text-3xl md:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.2] sm:leading-[1.15]">
                    Ponto eletrônico simples para o colaborador. <br class="hidden sm:inline" />
                    <span class="text-indigo-600">
                        Gestão completa para a empresa.
                    </span>
                </h1>

                <!-- Subtítulo Funcional e Direto -->
                <p class="gsap-hero-element text-sm sm:text-base lg:text-lg text-slate-600 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                    Registre jornadas pelo celular e acompanhe comprovantes, espelhos, ajustes, banco de horas e fechamento em um único sistema.
                </p>

                <!-- CTAs (Largura total no mobile, flex-row em telas maiores) -->
                <div class="gsap-hero-element flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3 pt-1">
                    <a href="{{ Auth::check() ? route('home') : route('login') }}" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 sm:px-7 sm:py-4 rounded-xl sm:rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-sm sm:text-base shadow-sm hover:shadow-md transition-all duration-200 transform active:scale-[0.99] sm:hover:-translate-y-0.5">
                        <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no PontoFácil' }}</span>
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a href="#recursos" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3.5 sm:px-6 sm:py-4 rounded-xl sm:rounded-2xl bg-white hover:bg-slate-50 text-slate-700 hover:text-indigo-600 font-semibold text-sm sm:text-base border border-slate-200 shadow-2xs hover:shadow-xs transition-all duration-200">
                        <span>Conhecer os recursos</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </a>
                </div>

                <!-- Micro-evidências Sutis -->
                <div class="gsap-hero-element pt-1 flex flex-wrap items-center justify-center lg:justify-start gap-x-5 gap-y-2 text-[11px] sm:text-xs font-semibold text-slate-500">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Sem relógios físicos</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>QR Code e localização como apoio à validação</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Portaria 671 / MTP</span>
                    </div>
                </div>

            </div>

            <!-- Coluna Secundária: Mockup Imediato do Relógio / Registro -->
            <div class="lg:col-span-5 flex justify-center w-full">
                <div class="gsap-hero-mockup w-full max-w-sm sm:max-w-md relative">
                    
                    <!-- Mockup de Tela Real do PontoFácil -->
                    <div x-data="{
                            time: '',
                            date: '',
                            registered: false,
                            lastPunch: '18:02 • Entrada',
                            journeyStatus: 'Você ainda não registrou a saída',
                            feedbackReceipt: false,
                            updateClock() {
                                const now = new Date();
                                this.time = now.toLocaleTimeString('pt-BR', { hour12: false });
                                const options = { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' };
                                this.date = now.toLocaleDateString('pt-BR', options);
                            },
                            simulatePunch() {
                                if (this.registered) return;
                                this.registered = true;
                                const punchTime = this.time || '22:41:08';
                                this.lastPunch = punchTime + ' • Saída';
                                this.journeyStatus = 'Saída registrada com sucesso';
                                this.feedbackReceipt = true;
                                setTimeout(() => {
                                    this.registered = false;
                                    this.lastPunch = '18:02 • Entrada';
                                    this.journeyStatus = 'Você ainda não registrou a saída';
                                    this.feedbackReceipt = false;
                                }, 4000);
                            }
                         }"
                         x-init="updateClock(); setInterval(() => updateClock(), 1000)"
                         class="relative bg-white border border-slate-200/90 p-4 min-[360px]:p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm sm:shadow-md space-y-3.5 sm:space-y-4">
                        
                        <!-- Barra de Status do Sistema / App -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-black text-xs shadow-xs">
                                    P
                                </div>
                                <span class="text-xs font-bold text-slate-800">PontoFácil</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                <span>Horário Oficial • GMT-3</span>
                            </div>
                        </div>

                        <!-- Saudação do Colaborador -->
                        <div class="flex items-center justify-between pt-0.5">
                            <div>
                                <h3 class="text-base min-[360px]:text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                                    Olá, Carlos
                                </h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Jornada de Trabalho • CLT</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100/80">
                                Em jornada
                            </span>
                        </div>

                        <!-- Display do Relógio Digital Atual -->
                        <div class="text-center py-2.5 sm:py-3 px-3 bg-slate-50/80 rounded-xl sm:rounded-2xl border border-slate-100">
                            <div class="text-4xl min-[360px]:text-5xl sm:text-6xl font-black text-slate-900 tracking-tight font-mono-numbers py-0.5"
                                 x-text="time || '22:41:08'">
                                22:41:08
                            </div>
                            <div class="text-[11px] sm:text-xs font-semibold text-slate-500 capitalize" 
                                 x-text="date || 'segunda-feira, 01 de outubro de 2026'">
                                segunda-feira, 01 de outubro de 2026
                            </div>
                        </div>

                        <!-- Situação da Jornada -->
                        <div class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl border text-xs sm:text-sm font-semibold transition-colors duration-200"
                             :class="registered ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50/90 border-amber-200/80 text-amber-900'">
                            <svg x-show="!registered" class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <svg x-show="registered" x-cloak class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span x-text="journeyStatus">Você ainda não registrou a saída</span>
                        </div>

                        <!-- Botão Grande: Registrar ponto -->
                        <div class="pt-0.5">
                            <button @click="simulatePunch()" 
                                    :disabled="registered"
                                    type="button" 
                                    class="w-full py-3.5 sm:py-4 px-5 rounded-xl sm:rounded-2xl font-bold text-sm sm:text-base text-white shadow-sm transition-all duration-200 flex items-center justify-center gap-2.5 cursor-pointer touch-manipulation"
                                    :class="registered ? 'bg-emerald-600' : 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 active:scale-98'">
                                <svg x-show="!registered" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <svg x-show="registered" x-cloak class="w-5 h-5 shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span x-text="registered ? 'Ponto Confirmado!' : 'Registrar ponto'"></span>
                            </button>
                        </div>

                        <!-- Rodapé com Evidências e Informações Reais -->
                        <div class="pt-2.5 border-t border-slate-100 space-y-2 text-xs">
                            <!-- Último Registro -->
                            <div class="flex items-center justify-between text-slate-600">
                                <span class="font-medium text-slate-500">Último registro:</span>
                                <span class="font-bold text-slate-900" x-text="lastPunch">18:02 • Entrada</span>
                            </div>

                            <!-- Localização como Evidência -->
                            <div class="flex items-center gap-2 text-slate-600 text-[11px] sm:text-xs">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                <span class="font-medium">Localização registrada</span>
                            </div>

                            <!-- Comprovante Disponível -->
                            <div class="flex items-center gap-2 text-slate-500 text-[11px] sm:text-xs">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Comprovante disponível após a marcação</span>
                            </div>

                            <!-- Toast / Confirmação da Demonstração -->
                            <div x-show="feedbackReceipt" 
                                 x-cloak
                                 x-transition
                                 class="mt-2 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] sm:text-xs text-center font-medium">
                                ✓ Comprovante gerado com sucesso • Demonstração interativa
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
