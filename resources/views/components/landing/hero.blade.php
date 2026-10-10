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
                <h1 class="gsap-hero-element text-2xl min-[360px]:text-[28px] sm:text-4xl lg:text-5xl xl:text-6xl font-black text-slate-900 tracking-tight leading-[1.2] sm:leading-[1.15]">
                    Ponto eletrônico simples para o colaborador. <br class="hidden sm:inline" />
                    <span class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-600 bg-clip-text text-transparent">
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
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 sm:px-7 sm:py-4 rounded-xl sm:rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-sm sm:text-base shadow-lg shadow-indigo-600/25 hover:shadow-xl hover:shadow-indigo-600/35 transition-all duration-200 transform active:scale-[0.99] sm:hover:-translate-y-0.5">
                        <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no PontoFácil' }}</span>
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a href="#recursos" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3.5 sm:px-6 sm:py-4 rounded-xl sm:rounded-2xl bg-white hover:bg-slate-50 text-slate-700 hover:text-indigo-600 font-semibold text-sm sm:text-base border border-slate-200/90 shadow-2xs hover:shadow-xs transition-all duration-200">
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
                        <span>Validação QR Code & GPS</span>
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
                    
                    <!-- Brilho Traseiro Suave -->
                    <div class="absolute -inset-1 bg-gradient-to-tr from-indigo-500/25 to-purple-500/15 rounded-3xl blur-lg opacity-70"></div>

                    <!-- Card Glassmorphism Compacto -->
                    <div x-data="{
                            time: '',
                            date: '',
                            registered: false,
                            feedbackMessage: '',
                            updateClock() {
                                const now = new Date();
                                this.time = now.toLocaleTimeString('pt-BR', { hour12: false });
                                const options = { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' };
                                this.date = now.toLocaleDateString('pt-BR', options);
                            },
                            simulatePunch() {
                                this.registered = true;
                                this.feedbackMessage = '✓ Ponto registrado às ' + this.time + '! Hash SHA-256 gerado.';
                                setTimeout(() => { this.registered = false; }, 4000);
                            }
                         }"
                         x-init="updateClock(); setInterval(() => updateClock(), 1000)"
                         class="relative bg-white/95 backdrop-blur-xl border border-white/80 p-4 min-[360px]:p-5 sm:p-7 rounded-2xl sm:rounded-3xl shadow-xl shadow-indigo-950/5 space-y-4 sm:space-y-5">
                        
                        <!-- Header do Mockup -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                </span>
                                <span class="text-[11px] sm:text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Sincronizado • GMT-3
                                </span>
                            </div>
                            <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[10px] sm:text-[11px] font-bold">
                                REP-P + PTRP
                            </span>
                        </div>

                        <!-- Display do Relógio Digital -->
                        <div class="text-center py-2 px-3 bg-gradient-to-b from-slate-50 to-white rounded-xl sm:rounded-2xl border border-slate-100 shadow-2xs">
                            <div class="text-4xl min-[360px]:text-5xl sm:text-6xl font-black text-slate-900 tracking-tight font-mono-numbers py-0.5"
                                 x-text="time || '14:28:45'">
                                14:28:45
                            </div>
                            <div class="text-[11px] sm:text-xs font-semibold text-slate-500 capitalize" 
                                 x-text="date || 'segunda-feira, 01 de outubro de 2026'">
                                segunda-feira, 01 de outubro de 2026
                            </div>
                        </div>

                        <!-- Informações do Colaborador Simulado -->
                        <div class="bg-indigo-50/60 border border-indigo-100/80 rounded-xl sm:rounded-2xl p-3 sm:p-4 flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs sm:text-sm shadow-xs shrink-0">
                                CM
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs sm:text-sm font-bold text-slate-900 truncate">Carlos Eduardo Mendes</div>
                                <div class="text-[11px] sm:text-xs text-slate-500 flex items-center gap-1.5 truncate">
                                    <span>Matrícula #0412</span>
                                    <span>•</span>
                                    <span class="text-indigo-600 font-medium">Recursos Humanos</span>
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                Ativo
                            </span>
                        </div>

                        <!-- Cerca Virtual & Status do GPS -->
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between text-slate-600 font-medium text-[11px] sm:text-xs">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Cerca Virtual (GPS Validado)
                                </span>
                                <span class="font-bold text-emerald-600">8m / 100m raio</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 12%"></div>
                            </div>
                        </div>

                        <!-- Botão de Simulação Interativo -->
                        <div class="pt-0.5">
                            <button @click="simulatePunch()" 
                                    :disabled="registered"
                                    type="button" 
                                    class="w-full py-3 sm:py-3.5 px-4 rounded-xl font-bold text-xs sm:text-sm text-white shadow-md transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer"
                                    :class="registered ? 'bg-emerald-600 shadow-emerald-600/30' : 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/25 active:scale-98'">
                                <svg x-show="!registered" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                </svg>
                                <svg x-show="registered" x-cloak class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span x-text="registered ? 'Ponto Confirmado!' : 'Simular Batida de Ponto'"></span>
                            </button>

                            <!-- Toast / Mensagem de Feedback -->
                            <div x-show="registered" 
                                 x-cloak
                                 x-transition
                                 class="mt-2 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs text-center font-medium"
                                 x-text="feedbackMessage">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
