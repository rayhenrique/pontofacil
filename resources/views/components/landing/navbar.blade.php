<header x-data="{ mobileMenuOpen: false, scrolled: false }" 
        x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
        :class="scrolled ? 'bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs' : 'bg-white/80 backdrop-blur-sm border-b border-slate-200/40'"
        class="sticky top-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 sm:h-20">
            <!-- Brand Logo à Esquerda -->
            <a href="#hero" class="flex items-center gap-2.5 sm:gap-3 group focus:outline-hidden shrink-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-gradient-to-tr from-indigo-700 via-indigo-600 to-indigo-500 text-white flex items-center justify-center shadow-md shadow-indigo-600/20 group-hover:scale-105 transition-transform duration-200 shrink-0">
                    <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="text-lg sm:text-xl font-extrabold tracking-tight text-slate-900 group-hover:text-indigo-600 transition-colors">
                        Ponto<span class="text-indigo-600">Fácil</span>
                    </span>
                    <span class="hidden sm:block text-[9px] uppercase font-bold tracking-widest text-slate-500">
                        REP-P + PTRP
                    </span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden lg:flex items-center gap-6 xl:gap-7 text-sm font-medium text-slate-600">
                <a href="#recursos" class="hover:text-indigo-600 transition-colors">Recursos</a>
                <a href="#para-empresas" class="hover:text-indigo-600 transition-colors">Para empresas</a>
                <a href="#seguranca" class="hover:text-indigo-600 transition-colors">Segurança</a>
                <a href="#portaria-671" class="hover:text-indigo-600 transition-colors">Portaria 671</a>
                <a href="{{ route('receipts.verify') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100/80 border border-emerald-200/80 text-xs font-bold transition-all shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span>Verificar comprovante</span>
                </a>
            </nav>

            <!-- Botão Entrar Desktop -->
            <div class="hidden lg:flex items-center">
                <a href="{{ Auth::check() ? route('home') : route('login') }}" 
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-md shadow-indigo-600/20 hover:shadow-lg hover:shadow-indigo-600/30 transition-all duration-200 transform hover:-translate-y-0.5">
                    <span>Entrar</span>
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            <!-- Menu Hambúrguer à Direita (Mobile) -->
            <div class="flex items-center lg:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        type="button" 
                        class="inline-flex items-center justify-center p-2.5 rounded-xl text-slate-700 hover:text-indigo-600 hover:bg-slate-100 active:bg-slate-200 transition-colors focus:outline-hidden min-h-[44px] min-w-[44px] cursor-pointer"
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
         @click.outside="mobileMenuOpen = false"
         class="lg:hidden bg-white/98 backdrop-blur-2xl border-b border-slate-200/80 shadow-2xl px-4 pt-3 pb-6 space-y-3">
        
        <!-- Mobile Navigation Links com touch target confortável (min 44px) -->
        <nav class="flex flex-col space-y-1">
            <a @click="mobileMenuOpen = false" 
               href="#recursos" 
               class="flex items-center min-h-[44px] px-3.5 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100 transition-colors">
                Recursos
            </a>
            <a @click="mobileMenuOpen = false" 
               href="#para-empresas" 
               class="flex items-center min-h-[44px] px-3.5 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100 transition-colors">
                Para empresas
            </a>
            <a @click="mobileMenuOpen = false" 
               href="#seguranca" 
               class="flex items-center min-h-[44px] px-3.5 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100 transition-colors">
                Segurança
            </a>
            <a @click="mobileMenuOpen = false" 
               href="#portaria-671" 
               class="flex items-center min-h-[44px] px-3.5 py-2.5 rounded-xl text-base font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 active:bg-indigo-100 transition-colors">
                Portaria 671
            </a>
            <a @click="mobileMenuOpen = false" 
               href="{{ route('receipts.verify') }}" 
               class="flex items-center gap-2.5 min-h-[44px] px-3.5 py-2.5 rounded-xl text-sm font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/80 hover:bg-emerald-100 active:bg-emerald-200 transition-colors">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span>Verificar comprovante</span>
            </a>
        </nav>

        <!-- CTA Full-Width no Mobile: Entrar no sistema -->
        <div class="pt-2 border-t border-slate-100">
            <a @click="mobileMenuOpen = false"
               href="{{ Auth::check() ? route('home') : route('login') }}" 
               class="w-full min-h-[48px] inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-sm text-center shadow-md shadow-indigo-600/20 active:scale-98 transition-all">
                <span>Entrar no sistema</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
</header>
