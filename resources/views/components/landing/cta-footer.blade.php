<!-- CTA Final Full-Width Banner -->
<section class="relative py-20 lg:py-28 overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-900 to-slate-950 text-white">
    <!-- Efeitos de Luz e Formas de Fundo -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-500/30 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-purple-500/25 rounded-full blur-3xl pointer-events-none"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-8">
        
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-indigo-200 text-xs font-bold uppercase tracking-wider">
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
            </span>
            <span>Implantação Instantânea</span>
        </div>

        <h2 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight max-w-4xl mx-auto leading-[1.1]">
            Sua gestão de ponto pronta para a nova era. <br class="hidden sm:inline" />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-200 via-white to-indigo-200">
                Comece agora mesmo.
            </span>
        </h2>

        <p class="text-base sm:text-xl text-indigo-100/90 max-w-2xl mx-auto font-normal leading-relaxed">
            Elimine o retrabalho manual com planilhas, blinde sua empresa contra passivos trabalhistas e proporcione a melhor experiência para seus colaboradores.
        </p>

        <!-- CTA Principal -->
        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ Auth::check() ? route('home') : route('login') }}" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-9 py-4 rounded-2xl bg-white hover:bg-slate-100 text-indigo-900 font-extrabold text-base shadow-2xl hover:shadow-white/20 transition-all duration-200 transform hover:-translate-y-1">
                <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no Sistema' }}</span>
                <svg class="w-5 h-5 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="#hero" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-4 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-base border border-white/20 backdrop-blur-md transition-all duration-200">
                <span>Voltar ao Início</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                </svg>
            </a>
        </div>

        <!-- Badges de Confiança -->
        <div class="pt-6 flex flex-wrap items-center justify-center gap-6 text-xs text-indigo-200/80 font-medium">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>Zero taxa de instalação</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>Conforme Portaria 671 / MTP</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>Nuvem de Alta Disponibilidade</span>
            </div>
        </div>

    </div>
</section>

<!-- Rodapé Minimalista Executivo -->
<footer class="bg-slate-950 text-slate-400 text-xs py-14 border-t border-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">
            
            <!-- Coluna Marca -->
            <div class="col-span-2 space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center shadow-md">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="text-base font-extrabold text-white tracking-tight">
                        Ponto<span class="text-indigo-400">Fácil</span>
                    </span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed max-w-sm">
                    Solução de controle de jornada eletrônica alternativa (REP-A) homologada sob a Portaria 671 do Ministério do Trabalho e Previdência. Validação cruzada por QR Code dinâmico e cerca virtual de GPS.
                </p>
                <div class="text-[11px] text-slate-500 font-medium">
                    Desenvolvido com excelência por 
                    <a href="https://kltecnologia.com" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="text-indigo-400 hover:text-indigo-300 font-bold underline decoration-indigo-500/50 hover:decoration-indigo-300 transition-colors inline-flex items-center gap-1 group">
                        <span>KL Tecnologia</span>
                        <svg class="w-3 h-3 text-indigo-400 group-hover:text-indigo-300 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>.
                </div>
            </div>

            <!-- Coluna Navegação -->
            <div class="space-y-3">
                <div class="text-white font-bold text-xs uppercase tracking-wider">Navegação</div>
                <ul class="space-y-2 text-slate-400">
                    <li><a href="#hero" class="hover:text-indigo-400 transition">Início</a></li>
                    <li><a href="#recursos" class="hover:text-indigo-400 transition">Recursos Principais</a></li>
                    <li><a href="#comparativo" class="hover:text-indigo-400 transition">Comparativo</a></li>
                    <li><a href="#demonstracao" class="hover:text-indigo-400 transition">Demonstração</a></li>
                    <li><a href="#evolucao" class="hover:text-indigo-400 transition">Evolução & Versões</a></li>
                </ul>
            </div>

            <!-- Coluna Legislação & Conformidade -->
            <div class="space-y-3">
                <div class="text-white font-bold text-xs uppercase tracking-wider">Conformidade</div>
                <ul class="space-y-2 text-slate-400">
                    <li><span class="text-slate-300">Portaria 671 / MTP</span></li>
                    <li><span class="text-slate-300">Padrão REP-A Oficial</span></li>
                    <li><span class="text-slate-300">Carimbo de Tempo NTP.br</span></li>
                    <li><span class="text-slate-300">Hash Inviolável SHA-256</span></li>
                    <li><span class="text-slate-300">Cerca Virtual com GPS</span></li>
                </ul>
            </div>

            <!-- Coluna Acesso -->
            <div class="space-y-3">
                <div class="text-white font-bold text-xs uppercase tracking-wider">Acesso ao Sistema</div>
                <ul class="space-y-2 text-slate-400">
                    <li><a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 font-semibold transition">Entrar / Fazer Login</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-indigo-400 transition">Portal do Colaborador</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-indigo-400 transition">Painel do Gestor RH</a></li>
                </ul>
            </div>

        </div>

        <!-- Linha de Copyright & Divisão -->
        <div class="pt-8 border-t border-slate-900 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
            <div>
                © {{ date('Y') }} PontoFácil. Todos os direitos reservados.
            </div>
            <div class="flex items-center gap-6">
                <span>Portaria 671 MTP / REP-A</span>
                <span>•</span>
                <span>Fuso Oficial de Maceió (GMT-3)</span>
                <span>•</span>
                <a href="https://kltecnologia.com" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="hover:text-indigo-400 transition-colors underline decoration-slate-700 hover:decoration-indigo-400">
                    KL Tecnologia
                </a>
            </div>
        </div>

    </div>
</footer>
