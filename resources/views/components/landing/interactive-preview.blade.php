<section id="demonstracao" class="py-20 md:py-32 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho da Seção -->
        <div class="text-center max-w-3xl mx-auto mb-14 space-y-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                <span>Demonstração Interativa</span>
            </div>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-black text-slate-900 tracking-tight">
                Simplicidade na Ponta do Dedo. <br class="hidden sm:inline" />
                <span class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-600 bg-clip-text text-transparent">
                    Poder Total na Gestão.
                </span>
            </h2>
            <p class="text-base sm:text-lg text-slate-600 font-normal leading-relaxed">
                Alterne entre a experiência ultrarrápida do colaborador e a visão completa de auditoria do gestor de RH.
            </p>
        </div>

        <!-- Container com Alpine.js para as Tabs -->
        <div x-data="{ tab: 'colaborador' }" class="max-w-5xl mx-auto space-y-8">
            
            <!-- Botões Seletor de Tabs -->
            <div class="flex justify-center">
                <div class="inline-flex p-1.5 rounded-2xl bg-slate-200/70 backdrop-blur-md border border-slate-300/60 shadow-inner">
                    <button @click="tab = 'colaborador'" 
                            type="button" 
                            :class="tab === 'colaborador' ? 'bg-white text-indigo-600 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="flex items-center gap-2.5 px-6 py-3 rounded-xl text-sm transition-all duration-200 focus:outline-hidden">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                        </svg>
                        <span>Visão do Colaborador</span>
                        <span class="hidden sm:inline-block text-[11px] px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-bold">1 Toque</span>
                    </button>

                    <button @click="tab = 'gestor'" 
                            type="button" 
                            :class="tab === 'gestor' ? 'bg-white text-indigo-600 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="flex items-center gap-2.5 px-6 py-3 rounded-xl text-sm transition-all duration-200 focus:outline-hidden">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.75 6" />
                        </svg>
                        <span>Visão do Gestor / RH</span>
                        <span class="hidden sm:inline-block text-[11px] px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 font-bold">Painel Total</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: VISÃO DO COLABORADOR (MOCKUP SCANNER QR CODE COM LASER GSAP) -->
            <div x-show="tab === 'colaborador'" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-98"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="bg-white/90 backdrop-blur-xl border border-slate-200/80 rounded-3xl p-6 sm:p-10 shadow-xl shadow-indigo-950/5">
                
                <div class="grid md:grid-cols-12 gap-8 items-center">
                    
                    <!-- Coluna do Scanner Visual Interativo -->
                    <div class="md:col-span-6 flex justify-center">
                        <div class="w-full max-w-xs bg-slate-900 rounded-[36px] p-4 shadow-2xl border-4 border-slate-800 relative">
                            
                            <!-- Notch / Speaker do Celular -->
                            <div class="w-32 h-4 bg-slate-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-900"></div>
                            </div>

                            <!-- Tela da Câmera / Scanner -->
                            <div class="relative bg-slate-950 rounded-[28px] overflow-hidden p-6 aspect-4/5 flex flex-col justify-between items-center text-white border border-slate-800">
                                
                                <!-- Header do App no celular -->
                                <div class="w-full flex items-center justify-between text-[11px] text-slate-400 z-20">
                                    <span class="flex items-center gap-1.5 font-bold text-slate-200">
                                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        PontoFácil
                                    </span>
                                    <span class="text-emerald-400 font-semibold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                        GPS Ativo
                                    </span>
                                </div>

                                <!-- Box Central de Leitura do QR Code com Retículos de Mira -->
                                <div class="relative w-48 h-48 sm:w-52 sm:h-52 bg-slate-900/90 border border-indigo-500/30 rounded-2xl flex items-center justify-center overflow-hidden my-auto shadow-inner">
                                    
                                    <!-- Cantos de Mira (Target Reticles) -->
                                    <div class="absolute top-2 left-2 w-4 h-4 border-t-2 border-l-2 border-cyan-400"></div>
                                    <div class="absolute top-2 right-2 w-4 h-4 border-t-2 border-r-2 border-cyan-400"></div>
                                    <div class="absolute bottom-2 left-2 w-4 h-4 border-b-2 border-l-2 border-cyan-400"></div>
                                    <div class="absolute bottom-2 right-2 w-4 h-4 border-b-2 border-r-2 border-cyan-400"></div>

                                    <!-- QR Code SVG Estilizado -->
                                    <div class="w-32 h-32 opacity-85">
                                        <svg viewBox="0 0 100 100" class="w-full h-full fill-white" xmlns="http://www.w3.org/2000/svg">
                                            <!-- QR Code Pattern Elements -->
                                            <rect x="10" y="10" width="24" height="24" rx="3" fill="#ffffff" />
                                            <rect x="14" y="14" width="16" height="16" rx="2" fill="#0f172a" />
                                            <rect x="18" y="18" width="8" height="8" rx="1" fill="#38bdf8" />

                                            <rect x="66" y="10" width="24" height="24" rx="3" fill="#ffffff" />
                                            <rect x="70" y="14" width="16" height="16" rx="2" fill="#0f172a" />
                                            <rect x="74" y="18" width="8" height="8" rx="1" fill="#38bdf8" />

                                            <rect x="10" y="66" width="24" height="24" rx="3" fill="#ffffff" />
                                            <rect x="14" y="70" width="16" height="16" rx="2" fill="#0f172a" />
                                            <rect x="18" y="74" width="8" height="8" rx="1" fill="#38bdf8" />

                                            <!-- Data Pixels -->
                                            <rect x="42" y="14" width="6" height="6" rx="1" />
                                            <rect x="52" y="18" width="6" height="6" rx="1" />
                                            <rect x="42" y="28" width="8" height="6" rx="1" />
                                            <rect x="14" y="44" width="6" height="6" rx="1" />
                                            <rect x="26" y="48" width="8" height="6" rx="1" />
                                            <rect x="44" y="44" width="12" height="12" rx="2" fill="#818cf8" />
                                            <rect x="64" y="46" width="6" height="6" rx="1" />
                                            <rect x="78" y="48" width="8" height="6" rx="1" />
                                            <rect x="42" y="66" width="6" height="6" rx="1" />
                                            <rect x="54" y="72" width="6" height="6" rx="1" />
                                            <rect x="68" y="78" width="8" height="8" rx="1" />
                                            <rect x="80" y="68" width="8" height="6" rx="1" />
                                        </svg>
                                    </div>

                                    <!-- LINHA LASER GSAP: Anime esta linha subindo e descendo -->
                                    <div class="gsap-scanner-laser absolute inset-x-2 top-2 h-0.5 bg-gradient-to-r from-transparent via-cyan-400 to-transparent shadow-[0_0_16px_#22d3ee] z-20 pointer-events-none"></div>

                                    <!-- Feedback Central Flutuante -->
                                    <div class="absolute bottom-2.5 px-3 py-1 rounded-full bg-slate-950/80 backdrop-blur-xs border border-cyan-500/40 text-[10px] font-semibold text-cyan-300">
                                        Escaneando QR Code...
                                    </div>
                                </div>

                                <!-- Barra Inferior de Ação -->
                                <div class="w-full space-y-2 z-20">
                                    <div class="text-[11px] text-center text-slate-300 font-medium">
                                        📍 Setor TI • Raio 6m de 100m
                                    </div>
                                    <div class="w-full py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-bold text-xs text-center shadow-md">
                                        Registrar Ponto (1 Toque)
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Explicação da Experiência do Colaborador -->
                    <div class="md:col-span-6 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold">
                            <span>Experiência Sem Atrito</span>
                        </div>
                        
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            Bata o ponto em menos de 2 segundos, de qualquer aparelho.
                        </h3>

                        <p class="text-base text-slate-600 leading-relaxed">
                            O colaborador não precisa baixar aplicativos pesados da loja nem lidar com biometrias engorduradas. Ele apenas aponta a câmera para o QR Code fixado no seu setor de trabalho.
                        </p>

                        <div class="space-y-3.5 pt-2">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs font-bold mt-0.5">
                                    1
                                </div>
                                <div class="text-sm">
                                    <strong class="text-slate-900 font-semibold">Leitura instantânea:</strong>
                                    <span class="text-slate-600"> O leitor detecta o QR Code do setor em milissegundos.</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs font-bold mt-0.5">
                                    2
                                </div>
                                <div class="text-sm">
                                    <strong class="text-slate-900 font-semibold">Checagem de GPS transparente:</strong>
                                    <span class="text-slate-600"> O navegador valida se as coordenadas estão dentro do raio da empresa.</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs font-bold mt-0.5">
                                    3
                                </div>
                                <div class="text-sm">
                                    <strong class="text-slate-900 font-semibold">Comprovante eletrônico imediato:</strong>
                                    <span class="text-slate-600"> Assinatura digital inviolável com carimbo de tempo oficial.</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-800 group">
                                <span>Experimentar no sistema</span>
                                <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

            <!-- TAB 2: VISÃO DO GESTOR / RH (DASHBOARD & FECHAMENTO) -->
            <div x-show="tab === 'gestor'" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-98"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="bg-white/90 backdrop-blur-xl border border-slate-200/80 rounded-3xl p-6 sm:p-10 shadow-xl shadow-indigo-950/5">
                
                <div class="space-y-8">
                    <!-- Header do Dashboard do Gestor -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-6">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <h3 class="text-xl font-bold text-slate-900">Painel Geral de Monitoramento • RH</h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Visão consolidada em tempo real com regras da Portaria 671 (REP-A)
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1.5 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold">
                                Folha do Mês: 99.4% Concluída
                            </span>
                            <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-sm">
                                Exportar Relatório
                            </a>
                        </div>
                    </div>

                    <!-- Métricas Principais (Cards de KPI) -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-1">
                            <div class="text-xs font-semibold text-slate-500">Colaboradores Ativos</div>
                            <div class="text-2xl font-black text-slate-900">48 <span class="text-xs font-normal text-slate-400">/ 50</span></div>
                            <div class="text-[11px] text-emerald-600 font-semibold">96% presentes hoje</div>
                        </div>

                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-1">
                            <div class="text-xs font-semibold text-slate-500">Horas Computadas</div>
                            <div class="text-2xl font-black text-slate-900">384h <span class="text-xs font-normal text-slate-400">15m</span></div>
                            <div class="text-[11px] text-indigo-600 font-semibold">Cálculo automatizado</div>
                        </div>

                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-1">
                            <div class="text-xs font-semibold text-slate-500">Ajustes Pendentes</div>
                            <div class="text-2xl font-black text-emerald-600">0</div>
                            <div class="text-[11px] text-emerald-600 font-semibold">Tudo conciliado</div>
                        </div>

                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-1">
                            <div class="text-xs font-semibold text-slate-500">Conformidade 671</div>
                            <div class="text-2xl font-black text-indigo-600">100%</div>
                            <div class="text-[11px] text-indigo-600 font-semibold">Hash SHA-256 ativo</div>
                        </div>
                    </div>

                    <!-- Tabela de Batidas Recentes em Tempo Real -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span>Últimos Registros Auditados</span>
                            <span class="text-slate-400 font-normal">Atualizado há 1 segundo</span>
                        </div>

                        <div class="overflow-x-auto border border-slate-200/70 rounded-2xl">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200/70">
                                    <tr>
                                        <th class="py-3 px-4">Colaborador</th>
                                        <th class="py-3 px-4">Setor</th>
                                        <th class="py-3 px-4">Horário</th>
                                        <th class="py-3 px-4">Tipo</th>
                                        <th class="py-3 px-4">Validação</th>
                                        <th class="py-3 px-4 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="py-3 px-4 font-semibold text-slate-900">Carlos Mendes</td>
                                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">TI & Suporte</span></td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">08:02:14</td>
                                        <td class="py-3 px-4 text-emerald-600 font-semibold">Entrada Manhã</td>
                                        <td class="py-3 px-4">QR Setor + GPS (4m)</td>
                                        <td class="py-3 px-4 text-right"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">Auditado</span></td>
                                    </tr>
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="py-3 px-4 font-semibold text-slate-900">Mariana Silva</td>
                                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">Recursos Humanos</span></td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">08:15:30</td>
                                        <td class="py-3 px-4 text-emerald-600 font-semibold">Entrada Manhã</td>
                                        <td class="py-3 px-4">QR Setor + GPS (12m)</td>
                                        <td class="py-3 px-4 text-right"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">Auditado</span></td>
                                    </tr>
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="py-3 px-4 font-semibold text-slate-900">Roberto Alves</td>
                                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">Operações / Matriz</span></td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">12:01:05</td>
                                        <td class="py-3 px-4 text-amber-600 font-semibold">Saída Almoço</td>
                                        <td class="py-3 px-4">QR Matriz + GPS (7m)</td>
                                        <td class="py-3 px-4 text-right"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">Auditado</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Barra de Informações Jurídicas -->
                    <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                        <div class="flex items-center gap-3 text-indigo-950 font-medium">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span>Espelho de Ponto pronto para auditoria trabalhista e emissão de relatórios oficiais.</span>
                        </div>
                        <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-800 shrink-0">
                            Acessar Módulo Gestor →
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>
