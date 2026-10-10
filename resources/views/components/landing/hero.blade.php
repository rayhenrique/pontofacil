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
                <h1 class="gsap-hero-element text-2xl min-[360px]:text-[26px] sm:text-3xl md:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-[1.2] sm:leading-[1.15]">
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
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 sm:px-7 sm:py-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-sm sm:text-base shadow-xs hover:shadow-sm transition-all duration-200 transform active:scale-[0.99] sm:hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 min-h-[48px]">
                        <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no PontoFácil' }}</span>
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a href="#recursos" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3.5 sm:px-6 sm:py-4 rounded-xl bg-white hover:bg-slate-50 text-slate-700 hover:text-indigo-600 font-semibold text-sm sm:text-base border border-slate-200 shadow-2xs hover:shadow-xs transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 min-h-[48px]">
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

            <!-- Coluna Secundária: Mockup Imediato do Relógio / Registro Real do PontoFácil -->
            <div class="lg:col-span-5 flex justify-center w-full">
                <div class="gsap-hero-mockup w-full max-w-sm sm:max-w-md relative">
                    
                    <!-- Mockup Fiel da Interface Real do PontoFácil (/ponto) -->
                    <div x-data="{
                            time: '',
                            date: '',
                            registered: false,
                            lastPunch: '18:02 • Entrada (NSR #000004281)',
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
                                this.lastPunch = punchTime + ' • Saída (NSR #000004282)';
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
                         class="relative bg-white border border-slate-200/90 p-4 min-[360px]:p-5 sm:p-6 rounded-2xl shadow-sm sm:shadow-md space-y-3 sm:space-y-3.5">
                        
                        <!-- Barra Superior: Contexto do Colaborador & Horário Oficial -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3 class="text-sm min-[360px]:text-base font-bold text-slate-900 tracking-tight">
                                        Olá, Carlos
                                    </h3>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        Em jornada
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium">Setor de Operações • CLT 40h</p>
                            </div>
                            <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                Matrícula #0482
                            </span>
                        </div>

                        <!-- Card do Relógio Digital Fiel ao PontoFácil (Estilo /ponto) -->
                        <div class="bg-gradient-to-r from-indigo-700 via-indigo-800 to-indigo-900 rounded-2xl shadow-xs px-4 py-3 sm:py-3.5 text-white text-center">
                            <p class="text-[10px] sm:text-xs uppercase tracking-widest text-indigo-200 font-semibold mb-0.5 capitalize" 
                               x-text="date || 'segunda-feira, 01 de outubro de 2026'">
                                segunda-feira, 01 de outubro de 2026
                            </p>
                            <div class="text-3xl min-[360px]:text-4xl sm:text-5xl font-extrabold tracking-tight font-mono text-white leading-tight"
                                 x-text="time || '22:41:08'">
                                22:41:08
                            </div>
                            <div class="mt-1 flex items-center justify-center gap-1.5 text-[11px] sm:text-xs text-indigo-200">
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Horário Oficial de Maceió (GMT-3)
                            </div>
                        </div>

                        <!-- Viewfinder Ilustrativo de Câmera / Leitor QR Code -->
                        <div class="relative w-full py-2.5 px-3 bg-slate-50 rounded-xl border border-dashed border-indigo-200/90 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="relative w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 shrink-0">
                                    <div class="absolute top-0.5 left-0.5 w-1.5 h-1.5 border-t border-l border-indigo-600"></div>
                                    <div class="absolute top-0.5 right-0.5 w-1.5 h-1.5 border-t border-r border-indigo-600"></div>
                                    <div class="absolute bottom-0.5 left-0.5 w-1.5 h-1.5 border-b border-l border-indigo-600"></div>
                                    <div class="absolute bottom-0.5 right-0.5 w-1.5 h-1.5 border-b border-r border-indigo-600"></div>
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z" />
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <p class="text-[11px] sm:text-xs font-bold text-slate-800">QR Code do Setor</p>
                                    <p class="text-[10px] text-slate-500">Câmera pronta para validação</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60">
                                Sinal Ativo
                            </span>
                        </div>

                        <!-- Situação da Jornada Atual -->
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

                        <!-- Botão de Ação: Registrar ponto -->
                        <div>
                            <button @click="simulatePunch()" 
                                    :disabled="registered"
                                    type="button" 
                                    aria-label="Registrar ponto demonstrativo"
                                    class="w-full py-3 sm:py-3.5 px-5 rounded-xl font-bold text-sm sm:text-base text-white shadow-xs transition-all duration-200 flex items-center justify-center gap-2.5 cursor-pointer touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 min-h-[46px]"
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

                        <!-- Rodapé com Metadados Reais de Registro e Evidência -->
                        <div class="pt-2 border-t border-slate-100 space-y-1.5 text-xs">
                            <!-- Último Registro -->
                            <div class="flex items-center justify-between text-slate-600 text-[11px] sm:text-xs">
                                <span class="font-medium text-slate-500">Último registro:</span>
                                <span class="font-bold text-slate-900 font-mono" x-text="lastPunch">18:02 • Entrada</span>
                            </div>

                            <!-- Localização Registrada como Evidência -->
                            <div class="flex items-center justify-between text-slate-600 text-[11px]">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    <span class="font-medium">Localização registrada</span>
                                </div>
                                <span class="text-slate-400 font-mono text-[10px]">Precisão 8m</span>
                            </div>

                            <!-- Comprovante Disponível -->
                            <div class="flex items-center justify-between text-slate-500 text-[11px]">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <span>Comprovante disponível após a marcação</span>
                                </div>
                                <span class="text-indigo-600 font-mono text-[10px] font-semibold">PF-2026-8942</span>
                            </div>

                            <!-- Toast / Confirmação da Demonstração -->
                            <div x-show="feedbackReceipt" 
                                 x-cloak
                                 x-transition
                                 class="mt-1.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] text-center font-medium">
                                ✓ Comprovante PF-2026-8942 gerado • Demonstração visual ilustrativa
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
