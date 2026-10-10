<!-- CTA Final Full-Width Banner -->
<section id="contato" class="relative py-10 sm:py-14 lg:py-16 overflow-hidden bg-slate-950 text-white border-t border-slate-900">
    <!-- Efeito Sutil de Fundo -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-indigo-900/20 rounded-full blur-3xl pointer-events-none"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-5 sm:space-y-6">
        
        <p class="text-xs font-bold uppercase tracking-widest text-indigo-400">
            Organize a jornada da sua equipe em um só lugar
        </p>

        <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight max-w-4xl mx-auto leading-[1.15]">
            Pronto para organizar a jornada da sua equipe? <br class="hidden sm:inline" />
            <span class="text-indigo-400 font-bold block sm:inline mt-1 sm:mt-0 text-xl sm:text-3xl md:text-4xl lg:text-5xl">
                Registre, acompanhe e feche jornadas com menos retrabalho.
            </span>
        </h2>

        <p class="text-sm sm:text-base md:text-lg text-slate-300 max-w-2xl mx-auto font-normal leading-relaxed">
            Registre pontos, acompanhe ocorrências, banco de horas e fechamento em um único sistema.
        </p>

        <!-- CTA Principal e Secundário -->
        <div class="pt-2 sm:pt-4 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
            <a href="{{ Auth::check() ? route('home') : route('login') }}" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-3.5 sm:py-4 rounded-xl bg-white hover:bg-slate-100 text-indigo-950 font-bold text-sm sm:text-base shadow-xs transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 min-h-[48px]">
                <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no PontoFácil' }}</span>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="#recursos" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 sm:py-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 font-semibold text-sm sm:text-base border border-slate-800 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 min-h-[48px]">
                <span>Conhecer os recursos</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </a>
        </div>

    </div>
</section>

<!-- Rodapé Sóbrio e Enxuto -->
<footer class="bg-slate-950 text-slate-400 text-xs py-10 sm:py-12 border-t border-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-6 border-b border-slate-900">
            <!-- Marca e Descrição Curta -->
            <div class="space-y-2 max-w-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="text-base font-extrabold text-white tracking-tight">
                        Ponto<span class="text-indigo-400">Fácil</span>
                    </span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed">
                    Controle de ponto e gestão de jornada.
                </p>
            </div>

            <!-- Links Rápidos de Produto -->
            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs" aria-label="Links do rodapé">
                <a href="#recursos" class="text-slate-400 hover:text-indigo-400 transition-colors">Recursos</a>
                <a href="#para-empresas" class="text-slate-400 hover:text-indigo-400 transition-colors">Para empresas</a>
                <a href="#seguranca" class="text-slate-400 hover:text-indigo-400 transition-colors">Segurança</a>
                <a href="#portaria-671" class="text-slate-400 hover:text-indigo-400 transition-colors">Portaria 671</a>
                <a href="{{ route('receipts.verify') }}" class="text-emerald-400 hover:text-emerald-300 font-semibold transition-colors">Verificar comprovante</a>
                <a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 font-semibold transition-colors">Entrar</a>
            </nav>
        </div>

        <!-- Linha Inferior com Desenvolvedor e Copyright -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
            <div>
                © {{ date('Y') }} PontoFácil. Todos os direitos reservados.
            </div>
            <div class="flex items-center gap-4">
                <span>Desenvolvido por 
                    <a href="https://kltecnologia.com" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="text-slate-400 hover:text-indigo-400 transition-colors underline decoration-slate-700 hover:decoration-indigo-400">
                        KL Tecnologia
                    </a>
                </span>
            </div>
        </div>

    </div>
</footer>
