<section id="evolucao" class="py-20 md:py-32 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span>Evolução Contínua</span>
            </div>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-black text-slate-900 tracking-tight">
                Uma Plataforma em <br class="hidden sm:inline" />
                <span class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-600 bg-clip-text text-transparent">
                    Constante Aprimoramento
                </span>
            </h2>
            <p class="text-base sm:text-lg text-slate-600 font-normal leading-relaxed">
                Nossos ciclos frequentes de atualização asseguram que sua empresa esteja sempre à frente em estabilidade, tecnologia e conformidade com a legislação.
            </p>
        </div>

        <!-- Linha do Tempo Vertical -->
        <div class="max-w-4xl mx-auto relative">
            
            <!-- Linha Central Conectora -->
            <div class="absolute left-4 sm:left-8 top-4 bottom-4 w-0.5 bg-gradient-to-b from-indigo-600 via-indigo-300 to-slate-200"></div>

            <div class="space-y-12">
                
                <!-- Versão Atual: v2.0.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador com Destaque Pulsante -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <span class="relative flex h-5 w-5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-5 w-5 bg-emerald-500 border-4 border-white shadow-md"></span>
                        </span>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/90 backdrop-blur-md border-2 border-indigo-500/30 rounded-3xl p-6 sm:p-8 shadow-xl shadow-indigo-950/5 relative overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-indigo-600 text-white font-black text-xs tracking-wider">
                                    v2.0.0
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                                    Versão Atual (Ativa)
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">PTRP, Jornada & Banco de Horas</span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">
                            Motor de Tratamento PTRP, Jornadas, Tolerância CLT Art. 58 e Banco de Horas
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Implementação do ledger inalterável de eventos de tratamento (inclusão manual, desconsideração, abonos), motor de apuração com tolerância legal estrita sem adulterar o fato bruto, banco de horas como ledger de soma auditável com suporte a CARRY_OVER ou MONTHLY_RESET e fechamento formal de competência.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-700">
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Ledger treatment_events com fluxo de solicitação e aprovação com segregação</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Motor CalculateDailyJourneyAction e tolerância legal Art. 58 § 1º CLT</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Banco de horas em ledger SUM(minutes) com CARRY_OVER e MONTHLY_RESET</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Fechamento formal mensal pelo RH sem zeramento automático de virada de mês</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.9.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-white font-black text-xs tracking-wider">
                                    v1.9.0
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">Comprovantes, AFD 2026 & Validação</span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">
                            Central de Comprovantes, Gerador AFD (MTE 2026) e Validação Pública
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Implementação da Central de Comprovantes permanente, motor de geração de PDF com NSR, SHA-256 e dados do REP-P, validador público por código de verificação e gerador oficial do AFD MTE 2026 com golden tests.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-700">
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Central de Comprovantes com acesso permanente ao trabalhador</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Geração de PDF do comprovante com NSR, hash e verificação</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Gerador AFD oficial MTE 31/07/2026 sobre dados brutos do REP</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Página de validação pública e golden tests automatizados</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.8.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-white font-black text-xs tracking-wider">
                                    v1.8.0
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">Fundação REP-P & NSR Atômico</span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">
                            Fundação Regulatória REP-P & Ledger Imutável de Marcações
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Implementação do modelo de domínio Company e Estabelecimentos (Matriz/Filiais) com sequenciador atômico de NSR sem concorrência, ledger imutável em ULID com hash SHA-256 encadeado e integração direta ao ponto.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-700">
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Sequenciador de NSR atômico por estabelecimento (lock pessimista)</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Ledger imutável punch_events com hash SHA-256 encadeado</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Entidade Company e Estabelecimentos para instância dedicada</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Exibição em tempo real do NSR formatado na batida</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.7.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-white font-black text-xs tracking-wider">
                                    v1.7.0
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">Cadastro Funcional & PontoFácil 2.0</span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">
                            Cadastro Funcional do Servidor & Arquitetura Instância Dedicada
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Integração direta de Cargo, Vínculo, Carga Horária e Zona com carregamento automático na Folha de Ponto A4, e estruturação das diretrizes Single-Tenant do PontoFácil 2.0 (REP-P / PTRP).
                        </p>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-700">
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Cargo, vínculo, carga horária e zona no servidor</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Preenchimento automático na folha oficial</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Arquitetura de Instância Dedicada (Zero Multi-Tenant)</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Edição completa de colaboradores pelo gestor/RH</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.6.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-white font-black text-xs tracking-wider">
                                    v1.6.0
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-500">Página Inicial & Relatório Oficial</span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">
                            Landing Page Cinematográfica & Folha de Ponto Oficial A4
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Nova experiência cinematográfica para visitantes na raiz do sistema e emissão da folha de frequência oficial idêntica ao modelo da Prefeitura / Secretaria de Saúde.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-700">
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Landing page na página inicial (/)</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Folha A4 oficial Matutino / Vespertino</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Modo batidas reais ou folha em branco</span>
                            </div>
                            <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Hiperlink KL Tecnologia no rodapé</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.5.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-800 font-bold text-xs">
                                    v1.5.0
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">Alta Disponibilidade</span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mb-2">
                            Estrutura Híbrida Inteligente de Setores & Fallback de GPS
                        </h3>

                        <p class="text-sm text-slate-600 mb-4 leading-relaxed">
                            Flexibilidade total para filiais e departamentos com fallback automático transparente para a matriz da empresa caso o setor não possua regras customizadas.
                        </p>

                        <div class="flex flex-wrap gap-2 text-[11px] font-medium text-slate-600">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">QR Code & GPS Setoriais</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Modais com Dupla Checagem</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Feedback Geográfico</span>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.4.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-800 font-bold text-xs">
                                v1.4.0
                            </span>
                            <span class="text-xs text-slate-600">Módulo RH & Gestão</span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mb-2">
                            Perfil de Gestor, Fuso Oficial (GMT-3) & Espelho de Ponto
                        </h3>

                        <p class="text-sm text-slate-600 mb-3 leading-relaxed">
                            Criação do nível de acesso descentralizado para líderes de setor, cálculo automático de pares de batidas (entrada, intervalo, saída) e relatórios de resumo mensal.
                        </p>

                        <div class="flex flex-wrap gap-2 text-[11px] font-medium text-slate-600">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Controle Setorial de Gestor</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Horário Oficial NTP</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Totalização de Horas</span>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.3.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-indigo-400 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-800 font-bold text-xs">
                                v1.3.0
                            </span>
                            <span class="text-xs text-slate-600">Mobile-First UI</span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mb-2">
                            Experiência 100% Mobile & Scanner Embutido
                        </h3>

                        <p class="text-sm text-slate-600 mb-3 leading-relaxed">
                            Redesign completo para dispositivos móveis com menu lateral off-canvas, barra inferior de navegação tátil e empacotamento local da engine de QR Code no bundle Vite (zero CDNs lentas).
                        </p>

                        <div class="flex flex-wrap gap-2 text-[11px] font-medium text-slate-600">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Zero Latência no Scanner</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Design Responsivo</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Navegação Off-Canvas</span>
                        </div>
                    </div>
                </div>

                <!-- Versão: v1.1.0 -->
                <div class="gsap-timeline-item relative pl-12 sm:pl-20">
                    <!-- Nó / Marcador -->
                    <div class="absolute left-2.5 sm:left-6.5 top-1.5 -translate-x-1/2 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-slate-300 border-4 border-white shadow-sm"></div>
                    </div>

                    <!-- Card de Versão -->
                    <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xs hover:shadow-md transition-shadow">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-800 font-bold text-xs">
                                v1.1.0
                            </span>
                            <span class="text-xs text-slate-600">Marco Regulatório</span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mb-2">
                            Lançamento Inicial da Plataforma (Portaria 671 / MTP)
                        </h3>

                        <p class="text-sm text-slate-600 mb-3 leading-relaxed">
                            Início da plataforma comercial com apoio de QR Code e localização registrada como evidência probatória, além de emissão de comprovantes com código de validação e hash de integridade.
                        </p>

                        <div class="flex flex-wrap gap-2 text-[11px] font-medium text-slate-600">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Portaria 671</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Validação Cruzada</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100">Trilha de Auditoria</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>
