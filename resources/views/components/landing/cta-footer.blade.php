<!-- CTA Final Full-Width Banner -->
<section id="contato" class="relative py-12 sm:py-14 md:py-16 lg:py-20 overflow-hidden bg-slate-950 text-white border-t border-slate-900">
    <!-- Efeito Sutil de Fundo -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-indigo-900/20 rounded-full blur-3xl pointer-events-none"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-6 sm:space-y-8">
        
        <p class="text-xs font-bold uppercase tracking-widest text-indigo-400">Implantação ágil e segura</p>

        <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight max-w-4xl mx-auto leading-[1.15]">
            Sua gestão de ponto pronta para a nova era. <br class="hidden sm:inline" />
            <span class="text-indigo-400">
                Comece agora mesmo.
            </span>
        </h2>

        <p class="text-sm sm:text-base md:text-lg text-slate-300 max-w-2xl mx-auto font-normal leading-relaxed">
            Elimine o retrabalho manual com planilhas, ganhe segurança e rastreabilidade na apuração de horas e proporcione a melhor experiência para seus colaboradores.
        </p>

        <!-- CTA Principal -->
        <div class="pt-2 sm:pt-4 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
            <a href="{{ Auth::check() ? route('home') : route('login') }}" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-3.5 sm:py-4 rounded-xl bg-white hover:bg-slate-100 text-indigo-950 font-bold text-sm sm:text-base shadow-xs transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 min-h-[48px]">
                <span>{{ Auth::check() ? 'Ir para o Sistema' : 'Entrar no Sistema' }}</span>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="#hero" 
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 sm:py-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 font-semibold text-sm sm:text-base border border-slate-800 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 min-h-[48px]">
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
                <span>Arquitetura Portaria 671 (REP-P + PTRP)</span>
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
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 sm:gap-8">
            
            <!-- Coluna Marca -->
            <div class="sm:col-span-2 space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <span class="text-base font-extrabold text-white tracking-tight">
                        Ponto<span class="text-indigo-400">Fácil</span>
                    </span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed max-w-sm">
                    Controle de jornada digital com arquitetura REP-P + PTRP preparada para a Portaria 671 do Ministério do Trabalho e Previdência. Trilha de auditoria, QR Code e localização como apoio à validação.
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
                    <li><a href="#como-funciona" class="hover:text-indigo-400 transition">Como Funciona</a></li>
                    <li><a href="#para-empresas" class="hover:text-indigo-400 transition">Para Empresas</a></li>
                    <li><a href="#recursos" class="hover:text-indigo-400 transition">Recursos Principais</a></li>
                    <li><a href="#seguranca" class="hover:text-indigo-400 transition">Segurança</a></li>
                    <li><a href="#portaria-671" class="hover:text-indigo-400 transition">Portaria 671</a></li>
                    <li><a href="#demonstracao" class="hover:text-indigo-400 transition">Demonstração</a></li>
                </ul>
            </div>

            <!-- Coluna Legislação & Conformidade -->
            <div class="space-y-3">
                <div class="text-white font-bold text-xs uppercase tracking-wider">Conformidade & Fiscalização</div>
                <ul class="space-y-2 text-slate-400">
                    <li><span class="text-slate-300">Portaria 671 / MTP</span></li>
                    <li><span class="text-slate-300">Padrão REP-P + PTRP</span></li>
                    <li><span class="text-slate-300">Carimbo de Tempo NTP.br</span></li>
                    <li><span class="text-slate-300">Hash de Integridade SHA-256</span></li>
                    <li><span class="text-slate-300">Cerca Virtual para Análise de Localização</span></li>
                    <li class="pt-1.5">
                        <a href="{{ route('receipts.verify') }}" class="text-emerald-400 hover:text-emerald-300 font-semibold transition inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span>Validação Pública de Ponto</span>
                        </a>
                    </li>
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
                <span>Portaria 671 MTP (REP-P + PTRP)</span>
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
