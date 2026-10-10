<header x-data="{ mobileMenuOpen: false, scrolled: false }" 
        x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
        :class="scrolled ? 'bg-white/90 backdrop-blur-md border-b border-slate-200/80 shadow-xs' : 'bg-white/70 backdrop-blur-sm border-b border-slate-200/40'"
        class="sticky top-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Brand Logo -->
            <a href="#hero" class="flex items-center gap-3 group focus:outline-hidden">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-700 via-indigo-600 to-indigo-500 text-white flex items-center justify-center shadow-lg shadow-indigo-600/25 group-hover:scale-105 transition-transform duration-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-extrabold tracking-tight text-slate-900 group-hover:text-indigo-600 transition-colors">
                        Ponto<span class="text-indigo-600">Fácil</span>
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-600">REP-P + PTRP</span>
                </div>
            </a>

            <!-- Central Badge: Portaria 671 / MTP -->
            <div class="hidden md:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50/80 border border-indigo-100/90 text-indigo-900 shadow-2xs">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-semibold tracking-wide">Arquitetura preparada para a Portaria 671</span>
            </div>

            <!-- Desktop Navigation Links -->
            <nav class="hidden lg:flex items-center gap-7 text-sm font-medium text-slate-600">
                <a href="#recursos" class="hover:text-indigo-600 transition-colors">Recursos</a>
                <a href="#comparativo" class="hover:text-indigo-600 transition-colors">Comparativo</a>
                <a href="#demonstracao" class="hover:text-indigo-600 transition-colors">Demonstração</a>
                <a href="#evolucao" class="hover:text-indigo-600 transition-colors">Evolução</a>
                <a href="{{ route('receipts.verify') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100/80 border border-emerald-200/80 text-xs font-bold transition-all shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span>Validação Pública</span>
                </a>
            </nav>

            <!-- Action Button & Mobile Toggle -->
            <div class="flex items-center gap-3">
                <a href="{{ Auth::check() ? route('home') : route('login') }}" 
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-md shadow-indigo-600/20 hover:shadow-lg hover:shadow-indigo-600/30 transition-all duration-200 transform hover:-translate-y-0.5">
                    <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Acessar Sistema' }}</span>
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>

                <!-- Mobile Hamburger Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        type="button" 
                        class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-indigo-600 hover:bg-slate-100 transition-colors focus:outline-hidden"
                        aria-label="Abrir Menu">
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Drawer / Dropdown -->
    <div x-show="mobileMenuOpen" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4"
         class="lg:hidden bg-white/95 backdrop-blur-xl border-b border-slate-200/80 shadow-xl px-4 pt-3 pb-6 space-y-3">
        
        <!-- Mobile Badge -->
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50/80 text-indigo-900 text-xs font-semibold w-fit">
            <span class="inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            Arquitetura preparada para a Portaria 671
        </div>

        <nav class="flex flex-col space-y-2 pt-2">
            <a @click="mobileMenuOpen = false" href="#recursos" class="px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                Recursos
            </a>
            <a @click="mobileMenuOpen = false" href="#comparativo" class="px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                Comparativo
            </a>
            <a @click="mobileMenuOpen = false" href="#demonstracao" class="px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                Demonstração
            </a>
            <a @click="mobileMenuOpen = false" href="#evolucao" class="px-3 py-2.5 rounded-lg text-base font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                Evolução
            </a>
            <a @click="mobileMenuOpen = false" href="{{ route('receipts.verify') }}" class="px-3 py-2.5 rounded-xl text-sm font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/80 hover:bg-emerald-100/70 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span>Validação Pública (Sem Login)</span>
            </a>
            <div class="pt-2">
                <a href="{{ Auth::check() ? route('home') : route('login') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-center shadow-md">
                    <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no Sistema' }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </nav>
    </div>
</header>
