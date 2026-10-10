<section id="hero" class="relative pt-12 pb-20 md:pt-20 md:pb-32 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- Coluna Esquerda: Texto Principal & Chamada para Ação -->
            <div class="lg:col-span-7 space-y-8 text-center lg:text-left">
                
                <!-- Badge de Lançamento / Conformidade -->
                <div class="gsap-hero-element inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 backdrop-blur-md border border-indigo-100 shadow-xs">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-600"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-950">
                        Controle de Jornada Digital • REP-P + PTRP
                    </span>
                </div>

                <!-- Título Cinematográfico -->
                <h1 class="gsap-hero-element text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-[1.1]">
                    Do Caos da Folha à <br class="hidden sm:inline" />
                    <span class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-600 bg-clip-text text-transparent">
                        Precisão em 1 Segundo.
                    </span>
                </h1>

                <!-- Subtítulo / Descrição de Valor -->
                <p class="gsap-hero-element text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                    Chega de planilhas manuais e biometrias quebradas. Controle de ponto eletrônico inteligente com 
                    <strong class="font-semibold text-slate-900">validação cruzada por QR Code dinâmico e geolocalização exata (GPS)</strong>, 
                    com arquitetura preparada para a Portaria 671 (REP-P e PTRP) e trilha de auditoria.
                </p>

                <!-- Ações / CTAs -->
                <div class="gsap-hero-element flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                    <a href="{{ Auth::check() ? route('home') : route('login') }}" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-base shadow-xl shadow-indigo-600/30 hover:shadow-2xl hover:shadow-indigo-600/40 transition-all duration-200 transform hover:-translate-y-1">
                        <span>{{ Auth::check() ? 'Acessar Sistema' : 'Acessar PontoFácil' }}</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a href="#demonstracao" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-4 rounded-2xl bg-white/80 hover:bg-white text-slate-700 hover:text-indigo-600 font-semibold text-base border border-slate-200/80 shadow-xs hover:shadow-md transition-all duration-200">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        <span>Ver Demonstração Interativa</span>
                    </a>
                </div>

                <!-- Garantias / Prova Rápida -->
                <div class="gsap-hero-element pt-4 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs font-semibold text-slate-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Sem compra de relógio físico</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Cerca virtual com GPS antifraude</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Ledger REP-P com hash SHA-256</span>
                    </div>
                </div>

            </div>

            <!-- Coluna Direita: Mockup Real-time do Sistema (Relógio ao Vivo) -->
            <div class="lg:col-span-5 flex justify-center">
                <div class="gsap-hero-mockup w-full max-w-md relative">
                    
                    <!-- Efeito de Brilho Traseiro (Glow) -->
                    <div class="absolute -inset-1.5 bg-gradient-to-tr from-indigo-500/30 to-purple-500/20 rounded-[32px] blur-xl opacity-75"></div>

                    <!-- Card Principal Glassmorphism -->
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
                         class="relative bg-white/90 backdrop-blur-xl border border-white/60 p-6 sm:p-8 rounded-3xl shadow-2xl shadow-indigo-950/10 space-y-6">
                        
                        <!-- Header do Mockup -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                </span>
                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Sincronizado • GMT-3
                                </span>
                            </div>
                            <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-[11px] font-bold">
                                REP-P + PTRP
                            </span>
                        </div>

                        <!-- Display do Relógio Digital -->
                        <div class="text-center py-2 bg-gradient-to-b from-slate-50 to-white rounded-2xl border border-slate-100 shadow-2xs">
                            <div class="text-5xl sm:text-6xl font-black text-slate-900 tracking-tight font-mono-numbers py-1"
                                 x-text="time || '14:28:45'">
                                14:28:45
                            </div>
                            <div class="text-xs font-semibold text-slate-500 capitalize" 
                                 x-text="date || 'segunda-feira, 01 de outubro de 2026'">
                                segunda-feira, 01 de outubro de 2026
                            </div>
                        </div>

                        <!-- Informações do Colaborador Simulado -->
                        <div class="bg-indigo-50/60 border border-indigo-100/80 rounded-2xl p-4 flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                                CM
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-slate-900 truncate">Carlos Eduardo Mendes</div>
                                <div class="text-xs text-slate-500 flex items-center gap-1.5 truncate">
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
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-600 font-medium">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Cerca Virtual (GPS Validado)
                                </span>
                                <span class="font-bold text-emerald-600">8m / 100m raio</span>
                            </div>
                            <!-- Barra de progresso do raio -->
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 12%"></div>
                            </div>
                        </div>

                        <!-- Botão de Simulação Interativo -->
                        <div class="pt-1">
                            <button @click="simulatePunch()" 
                                    :disabled="registered"
                                    type="button" 
                                    class="w-full py-3.5 px-4 rounded-xl font-bold text-sm text-white shadow-md transition-all duration-200 flex items-center justify-center gap-2"
                                    :class="registered ? 'bg-emerald-600 shadow-emerald-600/30' : 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/25 active:scale-98'">
                                <svg x-show="!registered" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                </svg>
                                <svg x-show="registered" x-cloak class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span x-text="registered ? 'Ponto Confirmado!' : 'Simular Batida de Ponto'"></span>
                            </button>

                            <!-- Toast / Mensagem de Feedback -->
                            <div x-show="registered" 
                                 x-cloak
                                 x-transition
                                 class="mt-2.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs text-center font-medium"
                                 x-text="feedbackMessage">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
