# Histórico de Versões (Changelog)

## v2.6.13 (Atual)
- **Correção Crítica no Calendário e Paginação da Folha de Ponto:**
  - **Feriados Aplicados Rigorosamente na Data Correta:** Corrigido o processamento de eventos do calendário em lote que aplicava indevidamente o feriado do mês (ex: Nossa Senhora Aparecida em 12/10) em todos os dias anteriores da folha. Os eventos agora são estritamente filtrados pelo dia de ocorrência, preservando o escopo territorial e prioridade.
  - **Paginação A4 e Fim das Páginas em Branco:** Ajustadas as regras de impressão (`@media print`) para remover restrições de altura e rolagem dos contêineres principais no navegador (Edge/Chrome). A folha agora fragmenta perfeitamente em páginas contínuas, garantindo a exibição de todos os dias do mês (01 a 31), sem páginas em branco e sem cortes.
  - **Observações Diárias Objetivas e Compactas:** Textos legais extensos e notas normativas de apuração foram concentrados exclusivamente no Quadro de Tratamentos e Legenda, mantendo a altura das linhas da tabela compacta e uniforme.
  - **Garantia do Bloco de Assinaturas e Tratamentos:** O quadro de justificativas/tratamentos auditados, a legenda da Portaria 671/2021 MTP e o bloco de assinaturas (Servidor, Coordenador, Responsável pelo Setor e RH) permanecem preservados no final do documento.
  - **Fidelidade Cadastral em Meses Fechados:** Nome, cargo, setor, carga horária, matrícula e dados do estabelecimento são resgatados estritamente do snapshot congelado, com fallback claro para "Informação histórica não disponível" em arquivos legados, desativando a prévia editável em períodos fechados.
  - **Plantões e Intervalos Transparentes:** Plantões previstos sem marcação real apresentam data "—" e hora "Não registrada", com intervalos programados identificados claramente como "(Previsto)".

## v2.6.12
- **Consolidação Técnica e Simplificação da Folha de Ponto:**
  - **Apresentação Objetiva e Foco nas Batidas Reais:** Na folha impressa, exibição prioritária das marcações efetivamente registradas, intervalos usufruídos, horas apuradas e ocorrências, eliminando colunas redundantes de horários previstos em cada linha e evitando sobrecarga visual.
  - **Resumo da Escala Vigente no Cabeçalho:** O cabeçalho do documento consolida o nome da escala e sua programação horária oficial quando disponível (ex: `Escala 12x36 (Previsto: 07:00 às 19:00)`), dispensando duplicações em cada linha.
  - **Quadro Complementar de Programação (RH):** Para plantões e escalas especiais, disponibilização de quadro complementar opcional na barra de controle, exibindo a referência completa da programação homologada de forma segregada, sem misturar horários previstos com batidas reais.
  - **Identificação Clara de Falta de Registro:** Batidas pendentes exibem "Não registrada", nunca preenchendo o campo com horários programados.
  - **Modelos Especializados por Jornada:** Suporte completo com seleção automática ou manual entre os modelos Administrativo (convencional com 4 marcações e suporte a batidas extras), Horário Corrido (8 colunas limpas com orientação retrato), Jornada Noturna (com marcação explícita de virada de dia e horas físicas/legais separadas), Escala de Plantões (11 colunas objetivas em paisagem) e Folha em Branco para preenchimento manual.
  - **Plantões na Virada de Mês:** Plantões iniciados no mês anterior que terminam no mês atual são apresentados na escala documental para conferência completa, com horas atribuídas à competência de origem, garantindo zero duplicação de horas na virada de competência.
  - **Jornadas Noturnas Vinculadas ao PTRP:** Eliminação de regras heurísticas no Blade; exibição fiel da vinculação de turnos realizada pelo motor de cálculo do PTRP para turnos 22h–06h, 19h–07h, 00h–06h e plantões de 24h, anotando a conclusão no dia seguinte (+1d).
  - **Intervalos Reais:** Remoção definitiva do valor padrão fictício de 60 minutos; o sistema apresenta o intervalo real usufruído, o previsto em escala ou "Sem intervalo" / "Não informado".
  - **Impressão A4 Adaptativa:** Aplicação dinâmica de orientação por modelo (A4 Retrato para Horário Corrido; A4 Paisagem para Administrativo, Noturno e Plantões), cabeçalho repetido em páginas subsequentes (`thead table-header-group`), linhas sem divisão de página (`tr break-inside: avoid`), observações sem truncamento na impressão e visualização mobile preservada.
  - **Alta Performance sem Consultas N+1:** Pré-carregamento em consulta única mensal de eventos de calendário e plantões ativos, gerando apenas os dados do modelo ativo selecionado.
  - **Integridade Histórica:** Competências fechadas consom estritamente os dados congelados do snapshot imutável, sem consultar tabelas ativas nem recalcular registros passados.

## v2.6.11
- **Usabilidade, Filtros Rápidos e Navegação no Celular:**
  - **Filtros e Busca Instantânea:** As telas de Colaboradores, Usuários, Setores, Conferência Mensal e Relatórios agora contam com busca instantânea (por nome, e-mail, cargo, matrícula e CPF com ou sem pontuação) com proteção contra travamentos ao digitar e botão de 1 clique para limpar todos os filtros aplicados.
  - **Visualização Otimizada para Celular (320px+):** Em smartphones e telas menores, as listagens administrativas passam a se organizar em cartões verticais objetivos, eliminando barras de rolagem horizontais desconfortáveis e oferecendo botões de toque com tamanho mínimo confortável (44px) para dedos.
  - **Navegação Histórica sem Barreiras:** O seletor de anos na Folha de Ponto e na Central de Comprovantes agora permite consultar livremente o histórico de até 8 anos fiscais anteriores, sem travas artificiais de 2 anos.
  - **Identidade Visual e Acessibilidade:** Ajustes no contraste e padronização visual das marcações institucionais de QR Code e níveis de acesso de acordo com as normas de acessibilidade (WCAG AA) e design do sistema.
- **Espelho de Ponto sem Banco de Horas:**
  - **Ocultação Automática quando Inativo:** Quando o servidor ou a empresa não utiliza banco de horas na competência consultada, o espelho oculta automaticamente o painel de banco de horas, saldos anteriores/finais, movimentações de crédito/débito e conciliações bancárias.
  - **Resumo Fiel de Jornada:** O resumo das diferenças de jornada permanece completo e transparente, destacando horas trabalhadas, dias de expediente, jornadas concluídas e o saldo líquido apurado entre o previsto e o realizado.
  - **Respeito à Competência Fechada:** Meses já fechados respeitam fielmente a modalidade histórica congelada no momento do fechamento, sem recalcular regras retroativamente.
- **Central de Comprovantes com Filtros Avançados e Proteção por Perfil:**
  - **Busca Unificada e Filtros Combinados:** Localização rápida de comprovantes eletrônicos por nome do servidor, e-mail, código de autenticidade ou NSR, combinando busca textual com filtros por setor, funcionário, competência e tipo de operação (Entrada/Saída).
  - **Segregação Rigorosa por Perfil:** Servidores visualizam estritamente seus próprios comprovantes; gestores acessam apenas funcionários dos setores autorizados sob sua responsabilidade; administradores e auditores possuem visualização adequada com auditoria.
  - **Proteção e Conformidade:** Exibição responsável de dados pessoais com redução da exposição de CPF na listagem, preservação integral de comprovantes fiscais originais, validação de integridade e link direto para verificação pública.
- **Refinamento Visual e Redução de Ruído Mobile-First:**
  - **Bater Ponto Mais Claro:** Relógio tabular destacado, instruções compactas, eliminação de mensagens repetidas e exibição explícita da data na última marcação para não confundir com a jornada do dia atual.
  - **Relatórios e Conferência Objetivos:** Unificação de advertências provisórias em um aviso único e claro em competências abertas; cabeçalhos e barras de ação compactadas mantendo ações primárias e exportações intactas.
  - **Configurações com Divulgação Progressiva:** Opções avançadas de cabeçalho de folha de ponto agrupadas em seção retrátil acessível, mantendo o formulário principal limpo e focado no essencial.
- **Sincronização da Folha de Ponto com Tratamentos do PTRP:**
  - **Reflexo Imediato de Ajustes Aprovados:** Ajustes manuais de batidas e justificativas de ausência deferidos pelo RH passam a refletir imediatamente na tabela e no resumo da Folha de Ponto em competências abertas, dispensando o fechamento do mês para atualizar os dados do colaborador.
  - **Diferenciação Clara das Ocorrências:** Distinção visual e legal transparente entre marcações eletrônicas originais do REP (`Registro eletrônico`), inserções manuais autorizadas (`Ajuste aprovado`), marcações desconsideradas com preservação do histórico, ausências legalmente amparadas (`JUSTIFICADA`), solicitações aguardando análise e pedidos indeferidos pelo RH.
  - **Integridade de Registros e NSR:** Batidas manuais aprovadas preenchem as colunas de entrada/saída e participam dos totais trabalhados sem jamais criar números de NSR fictícios nem se passar por registros eletrônicos originais.
  - **Quadro de Tratamentos e Ajustes no Padrão Portaria 671/2021 MTP:** Inclusão de anexo/quadro discriminado de ocorrências e tratamentos auditados com data, tipo de ajuste, referência de horário, situação e justificativa/motivo registrado, mantendo a folha perfeitamente ajustada para impressão em página A4.
  - **Fidelidade à Competência Fechada:** Em meses fechados, a Folha de Ponto utiliza exclusivamente os dados congelados do snapshot imutável, garantindo que alterações cadastrais ou tratamentos posteriores não modifiquem o histórico formal arquivado.
  - **Escala Real no Cabeçalho:** O cabeçalho exibe a escala ou modalidade de plantão efetivamente atribuída ao colaborador, apresentando "Escala não configurada" quando não houver definição e nunca atribuindo "Padrão Administrativo" de forma enganosa.

## v2.6.10
- **Horários e Escalas com Permissões Claras:**
  - Modelos de jornada (criar, alterar, desativar) são cuidados só pelo RH/Administrador. Gestor usa os modelos aprovados com sua equipe e cuida dos plantões do seu setor.
  - Plantões 12×36 e 24×72 valem só com norma configurada para o regime. Sem norma, o sistema explica o motivo e recusa a atribuição — sem travar o ponto.
  - A tela ganhou três abas leves (Horários, Servidores e Plantões), cada uma carregando só o necessário. Horário fixo não gera plantão artificial.

## v2.6.9
- **Relatórios Fiéis ao Mês Fechado:**
  - O relatório do mês fechado mostra exatamente quem estava lá na época, com nome, cargo e setor da época — mesmo se a pessoa mudou de setor, foi desligada ou teve o cadastro alterado depois.
  - Gestor vê só o histórico dos setores que gerencia; ninguém acessa outro setor só por saber um código.
  - Sem conferência individual, ninguém aparece como responsável por ela. Sem histórico, aparece "Histórico indisponível" em vez de número inventado.
  - PDF, CSV e tela com os mesmos totais; CSV protegido contra fórmulas escondidas.

## v2.6.8
- **Conferência Acompanha Mudanças na Jornada:**
  - Ao conferir, o sistema registra uma impressão digital dos dados analisados (marcações, tratamentos, escala, plantões, calendário e regras).
  - Se chegar marcação nova, tratamento aprovado ou mudança de escala/plantão/calendário depois da conferência, ela aparece como desatualizada e o mês só fecha após nova análise.
  - Mudanças sem impacto no mês (outra competência ou só renomear) não invalidam nada. Mês fechado continua lendo só os registros congelados.

## v2.6.7
- **Fechamento Mensal Unificado e Protegido:**
  - O mês só fecha pela Conferência Mensal, depois de todos os servidores conferidos e sem pendências. Não há mais atalho de fechamento pelo Banco de Horas (que agora leva para a conferência).
  - Somente o RH/Administrador fecha. Gestores, servidores e auditores são bloqueados.
  - Se algo mudar depois da conferência (escala ou tratamento), o sistema pede nova validação antes de fechar.
  - Competência vazia ou já fechada também é bloqueada, com avisos simples do que falta.

## v2.6.6
- **Conferência Mensal e Relatórios com Integridade Garantida:**
  - Sem conferência registrada, o sistema mostra "Aguardando conferência" — nunca aprova sozinho nem inventa confirmação.
  - Mês fechado lê somente os registros congelados. Se faltar histórico de alguém, aparece "Histórico indisponível" em vez de número inventado.
  - Se a escala ou um tratamento mudar depois da conferência, o sistema pede nova validação antes de fechar o mês.
  - Dias futuros aparecem como previstos, sem virar falta ou desconto antes da hora. Dias incompletos não entram nos saldos definitivos.
  - Exportações validadas e protegidas: o CSV não executa fórmulas escondidas em nomes e os arquivos do mês fechado batem com os registros congelados.

## v2.6.5
- **Conferência Mensal e Relatórios Bem Mais Rápidos:**
  - As telas de conferência e relatório agora carregam os dados do mês de uma vez só, em vez de consultar dia por dia para cada servidor. Com 50 servidores, a abertura caiu de cerca de 11 segundos para cerca de 2 segundos.
  - Listas com páginas de 20 servidores, mantendo os totais gerais sempre visíveis. Filtros por servidor ficam quase instantâneos.
  - Os números da apuração continuam exatamente os mesmos (horas, diferenças, atrasos, faltas, noturno e tratamentos). Mês fechado segue lendo apenas os registros congelados.

## v2.6.4
- **Horários e Escalas Organizados em Três Visões:**
  - A tela de escalas agora tem três abas simples: *Horários* (todos os modelos cadastrados, mesmo sem ninguém vinculado), *Servidores* (quem usa cada horário e por quanto tempo) e *Plantões* (grade do mês para 12×36, 24×72 e 6×1).
  - Horários de expediente fixo (administrativo e corrido) aparecem logo após o cadastro e são usados na apuração sem precisar gerar plantões artificiais.
  - Atribuição individual ou em lote com início e fim de vigência, sem apagar o histórico e sem sobrepor escalas incompatíveis. Mês fechado continua bloqueado.
  - Plantão 24×72 só pode ser atribuído a quem tem autorização para o regime. Desativar um horário em uso é bloqueado até a troca por outro.

## v2.6.3
- **Proteção das Escalas, Conferência Mensal e Relatórios:**
  - Cada gestor agora só enxerga e altera os servidores dos seus próprios setores. Tentar usar o código de outro setor ou colaborador é bloqueado com mensagem simples de acesso negado.
  - A abertura da conferência individual só carrega dados de quem o usuário pode conferir. Servidor comum e auditor não conseguem confirmar, fechar ou despachar.
  - Os filtros de setor, estabelecimento e servidor são conferidos no servidor, sem confiar apenas na tela. Relatórios e encaminhamentos de outros setores ficam inacessíveis.
  - Alterações e cancelamentos de plantão em mês já fechado continuam bloqueados, preservando marcações, escalas, auditorias e snapshots.

## v2.6.2
- **Central de Ajuda Completa, Pesquisável & Manuais do Usuário:**
  - **Interface Moderna e Acessível (`/ajuda`):**
    - Redesenho completo com abas dedicadas: *Manuais*, *Perguntas Frequentes (FAQ)*, *Glossário Normativo* e *Histórico de Atualizações*.
    - Indicadores visuais por tempo estimado de leitura, perfis de usuário e categorias operacionais.
    - Suporte a impressão formal em formato A4 (`no-print` inteligente) e visualização limpa de documentos.
  - **Motor de Busca Instantâneo com Relevância Contextual:**
    - Busca em tempo real por palavras-chave com ranking ponderado (título, resumo, tags e corpo do artigo).
    - Filtros dinâmicos por perfil profissional (Servidor, Gestor, Administrador RH, Auditor Fiscal) e categorias de processos.
    - Deep linking total via URL (`?tab=...`, `?artigo=...` e `?q=...`) permitindo compartilhamento direto de instruções operacionais.
  - **11 Manuais Operacionais Estruturados:**
    - Cobertura completa de ponta a ponta: Visão Geral, Configuração Inicial pelo RH, Órgãos e Unidades, Gestão de Servidores, Horários e Jornadas Flexíveis/Plantões, Como Bater Ponto, Espelho de Ponto e Solicitações de Ajuste, Conferência Mensal pelo RH, Relatório Consolidado de Frequência, Governança/Auditoria/Compliance Portaria 671 e Instalação do Atalho no Celular (PWA/Android/iOS).
  - **FAQ Interativo e Glossário Normativo:**
    - 50 perguntas e respostas frequentes organizadas por assunto prático com links diretos para manuais e telas do sistema.
    - Glossário com conceitos fundamentais da legislação trabalhista e Portaria 671/2021 MTP (REP-P, PTRP, ARP, AFD, AEJ, NSR, SHA-256 e ICP-Brasil).
  - **Notificador de Atualização Integrado:**
    - Modal de novidades automático para os usuários ao acessarem a versão v2.6.2.

## v2.6.1
- **Simplificação UX/UI Mobile-First e Experiência Operacional:**
  - **Navegação Adaptada por Papel:**
    - RH e Gestores com foco operacional no topo: *Conferência Mensal*, *Servidores*, *Horários & Escalas*, *Solicitações* e *Relatórios*.
    - Banco de Horas e configurações avançadas alocados em área secundária recolhível ("Configurações & Apoio").
    - Visão do colaborador enxuta: *Bater Ponto*, *Espelho de Ponto*, *Folha de Ponto*, *Comprovantes* e *Ajuda*.
    - Barra inferior mobile adaptativa com atalhos operacionais imediatos para gestores e colaboradores.
  - **Experiência do Servidor Otimizada:**
    - Fluxo mobile-first limpo com touch targets >= 44px e contraste AA.
    - Ação contextual integrada no card de jornada para solicitar correção no Espelho.
    - Eliminação de jargões técnicos (termos como ledger e quitação financeira substituídos por extrato de movimentações e compensação).
  - **Navegação com Preservação de Filtros:**
    - Transição contextual preservando competência e setor entre Espelho de Ponto, Conferência Mensal e Relatórios Consolidados.
  - **Responsividade e Acessibilidade:**
    - Validado em 320px, 360px, 390px, 430px, 768px, 1024px, 1280px e 1440px sem rolagem horizontal da página.

## v2.6.0
- **Relatório Mensal Consolidado de Frequência para o Setor de Pagamento:**
  - **Módulo Reformulado (`/admin/reports`):**
    - Substituição integral do relatório legado que listava apenas `TimeEntry` cru sem apuração PTRP.
    - Geração de relatório consolidado oficial para o setor responsável pelo pagamento, com foco estrito em horas físicas e frequência, sem calcular salários, holerites, descontos monetários ou lançamentos financeiros.
    - Filtros por competência (mês/ano), estabelecimento, setor de lotação e servidor individual.
  - **Dados Consolidados por Servidor:**
    - Identificação completa: Nome, Matrícula, CPF, Regime/Vínculo Jurídico, Cargo/Função e Lotação/Setor.
    - Carga prevista da competência (horas reais de expediente conforme escala e calendário).
    - Horas físicas apuradas e diferenças discriminadas (saldo líquido, horas positivas/extras e horas negativas/débitos).
    - Faltas apuradas, atrasos na entrada, horas noturnas físicas e plantões/escalas cíclicas realizados.
    - Quantidade de ocorrências e tratamentos aprovados no período.
    - Situação da conferência mensal e ressalvas fundamentadas registradas pelo RH e chefias.
  - **Fidelidade e Imutabilidade Normativa:**
    - **Competência Fechada:** Leitura exclusiva e estrita de snapshots congelados (`ClosedPeriodEmployeeSnapshot`), sem recalcular jornadas ou depender de alterações em cadastros e escalas atuais.
    - **Competência Aberta:** Apresentação clara como **"PRÉVIA PROVISÓRIA"**, com tarja de alerta destacando ausência de valor homologatório até o fechamento formal.
  - **Exportação Institucional (PDF / Impressão e CSV UTF-8):**
    - **Documento para Impressão / PDF:** Layout formal A4 Paisagem com identificação institucional, CNPJ, competência, data/hora de emissão, versão de fechamento, cartões de metadados, tabela consolidada com totais gerais e campos formais para assinatura do RH e ciência do setor de pagamento.
    - **Arquivo CSV UTF-8:** Exportação com cabeçalhos administrativos padronizados, delimitador ponto-e-vírgula (`;`) e BOM UTF-8 (`\xEF\xBB\xBF`) para compatibilidade direta com Excel e sistemas administrativos.
    - Folha de Ponto individual (`/folha-ponto`) permanece plenamente disponível e intacta.
  - **Histórico de Emissões e Despacho à Folha:**
    - Auditoria em banco (`monthly_attendance_report_logs`) registrando usuário emissor, filtros aplicados, data e formato.
    - Registro explícito de despacho/encaminhamento formal ao setor de folha de pagamento, com data, responsável e memorando/despacho, sem simulação automática de entrega.
  - **Controle de Acesso Rigoroso:**
    - RH e administradores acessam todos os servidores e estabelecimentos.
    - Gestores autorizados visualizam e exportam exclusivamente dados das equipes dos setores que gerenciam.

## v2.5.9
- **Conferência Mensal de Frequência pelo RH e Gestores:**
  - **Módulo Operacional Dedicado (`/admin/monthly-review`):**
    - Módulo totalmente independente do Banco de Horas, reutilizando apuração existente e fechamento auditado.
    - Seleção rápida de competência mensal com filtros por setor autorizado e busca por colaborador, CPF ou matrícula.
    - Classificação analítica com status operacionais: *Aguardando conferência*, *Com ocorrência*, *Conferido* e *Conferido com ressalva*.
    - Exibição transparente por servidor de: escala de trabalho, horas previstas, trabalhadas, diferenças de jornada e ocorrências impeditivas.
  - **Fluxo de Homologação e Gestão de Ressalvas:**
    - Acesso direto ao Espelho de Ponto (`/timesheet`) para consulta detalhada de batidas, abonos e tratamentos.
    - Registro de auditoria completo de quem conferiu, data/hora da homologação, competência e ressalva fundamentada.
    - Exigência estrita de ressalva/justificativa fundamentada (mínimo de 5 caracteres) para servidores com ocorrências pendentes (batidas incompletas, solicitações não tratadas ou falta de escala).
  - **Conferência em Lote com Regra de Segurança:**
    - Homologação em lote com confirmação explícita restrita a servidores elegíveis (sem ocorrências pendentes). Servidores com pendências são automaticamente preservados para conferência individual criteriosa.
  - **Segregação de Funções e Fechamento com Snapshot Congelado:**
    - Gestores conferem apenas suas equipes autorizadas; Fechamento formal da competência restrito ao Administrador/RH.
    - Bloqueio rígido do fechamento ordinário se houver servidores sem conferência ou ocorrências não ressalvadas.
    - Fechamento congela a apuração e as conferências em snapshots imutáveis (`ClosedPeriodEmployeeSnapshot`) com hash determinístico SHA-256.
    - Consulta de competência fechada consome **exclusivamente snapshots congelados**, sem qualquer recálculo com escalas atuais.
    - Reabertura formal com auditoria de justificativa (mínimo 10 caracteres) desvincula as conferências anteriores preservando a integridade histórica.
  - **Interface Responsiva e Acessível:**
    - Visualização Mobile-First com cards operacionais compactos e ação contextual "Conferir".
    - Tabela de alta densidade para desktop com seleção em lote e ação de destaque "Concluir conferência e fechar mês".

## v2.5.8
- **Configuração Simplificada de Horários e Plantões por Modelos:**
  - O RH agora configura a jornada ou escala de trabalho sem necessidade de conhecimentos técnicos, selecionando entre modelos intuitivos:
    - **Administrativo:** Dias selecionáveis com padrão visual de segunda a sexta, dois turnos com intervalo intrajornada (ex.: 08:00–12:00 e 13:00–17:00).
    - **Horário Corrido:** Dias selecionáveis com entrada e saída únicas (ex.: 07:00–13:00), com tratamento de pausa remunerada ou deduzida da jornada líquida.
    - **Plantão 12 Horas:** Suporte a regimes 12×36 diurnos e noturnos com virada de meia-noite contínua como jornada lógica única, data-âncora, intervalo e vigência.
    - **Plantão 24 Horas:** Suporte a regimes 24×72 com intervalo regulamentar, data-âncora e validação normativa restrita.
    - **Personalizado / Avançado:** Mantém total flexibilidade para escalas atípicas e ciclos customizados.
  - **Cálculo Exato pelo Calendário Real:**
    - Cargas diárias e semanais derivadas da própria escala.
    - Carga prevista mensal calculada pelo número real de dias úteis e ocorrências na competência, eliminando a presunção arbitrária de 200 horas mensais.
    - Eliminação de seleção silenciosa ou criação fictícia de `WorkSchedule::first()`.
  - **Prévia em Tempo Real e Aplicação com Vigência:**
    - O RH visualiza a prévia de carga diária, carga semanal e horas previstas no mês antes de salvar.
    - Aplicação em lote para múltiplos servidores com preservação rigorosa do histórico retroativo e respeito às competências passadas.
  - **Não Bloqueio do Registro de Ponto:**
    - Batidas de ponto (REP-P) e apurações diárias nunca são bloqueadas por ausência ou pendência de escala.

## v2.5.7
- **Foco Exclusivo no Controle de Frequência:**
  - O PontoFácil é exclusivamente um sistema de registro, apuração, conferência e relatório de frequência para empresas e órgãos públicos. Não processa salários, pagamentos, cálculos monetários ou folha de pagamento. O setor de pagamento externo recebe apenas o relatório consolidado de frequência.
  - **Fechamento Mensal Desacoplado do Banco de Horas:**
    - O fechamento da competência congela a frequência apurada (horas previstas, trabalhadas, diferenças positivas e negativas, faltas, atrasos, intervalos, plantões e ocorrências) sem executar nenhuma operação financeira fictícia (`PayrollPayment`) nem quitações automáticas.
    - Empresas que não utilizam banco de horas registram pontos, apuram jornadas, conferem espelhos e fecham competências normalmente, sem exigência de banco de horas nem criação de contas fantasmas.
    - A ausência de regime de banco de horas não implica pagamento automático nem zeramento de saldos: as diferenças positivas e negativas permanecem identificáveis nos relatórios e nos snapshots congelados.
  - **Preservação Histórica e Integridade:**
    - Todas as tabelas e movimentações históricas permanecem íntegras para fins de conformidade e auditoria, sem exclusões nem migrações destrutivas.
    - Ajuste nos textos e fluxos para esclarecer a separação entre apuração de ponto e processamento da folha salarial (que ocorre externamente).

## v2.5.6
- **Auditoria Integrada de Jornada e Compensação:**
  - Melhoramos a conferência das jornadas e das compensações. Agora os saldos, pendências e períodos fechados são apresentados de forma mais clara e consistente.
  - **Espelho Simples e Humano para o Colaborador:**
    - O trabalhador entende com facilidade quanto trabalhou, qual jornada estava prevista e a diferença apurada (exemplo: *"Você trabalhou 7h27 de uma jornada prevista de 8h. Diferença: -33 minutos."*).
    - Dias com divergências exibem orientações claras e tratamentos pendentes de análise de forma amigável.
    - Quando a escala ainda não estiver cadastrada, o sistema orienta com transparência: *"Não foi possível calcular o saldo porque sua escala ainda precisa ser configurada."*.
    - Não exibe cartões de banco de horas para colaboradores ou organizações que não utilizam banco de horas, e saldos pendentes nunca são apresentados como quitados sem comprovação.
  - **Resumo Operacional Estruturado para o RH:**
    - O gestor e o RH contam com seis frentes diretas para tomada de decisão: pendências de jornada, solicitações aguardando aprovação, diferenças a tratar, saldos de banco quando aplicáveis, destinações aguardando quitação e competências prontas para fechamento.
    - Quitação documentada rápida com modal acessível e responsivo, exigindo comprovação documental fática (holerite, recibo, portaria).
  - **Concorrência e Integridade Histórica:**
    - Bloqueios pessimistas impedem quitações simultâneas duplicadas, fechamentos concorrentes e estornos repetidos.
    - Competências já fechadas permanecem rigorosamente congeladas em snapshots imutáveis e protegidas contra recálculos ou alterações posteriores em escalas e políticas.
- **Simplificação das Configurações de Jornada e Compensação:**
  - **Experiência Simples e Mobile-First:**
    - Configurar a compensação de horas ficou mais simples. Agora você escolhe como sua organização trata as diferenças de jornada e visualiza um resumo antes de salvar.
    - O formulário responde diretamente a três perguntas essenciais: para quem vale a regra (empresa, estabelecimento ou colaborador), o que será feito com as diferenças e quando a regra começa a valer.
  - **Dois Níveis de Configuração com Divulgação Progressiva:**
    - Configuração básica objetiva com opções claras e acessíveis pelo celular e computador.
    - Configurações avançadas (prazos, limites, transporte de horas e tolerâncias) organizadas em seção expansível, sem exigir campos técnicos desnecessários para modalidades simples.
  - **Sugestões de Configuração Rápida:**
    - Modelos sugeridos para agilizar o preenchimento: Empresa CLT sem banco, CLT com compensação no mês, CLT com banco de horas e Órgão público estatutário.
    - As sugestões apenas pré-preenchem os campos como ponto de partida, sem tomar decisões jurídicas automáticas nem aplicar regras sem confirmação explícita do gestor.
  - **Linguagem Amigável sem Jargões:**
    - Substituição de termos excessivamente técnicos por explicações diretas e funcionais do destino das horas.
  - **Revisão Objetiva e Fluxo de Aprovação Seguro:**
    - Resumo claro antes de salvar, permitindo voltar e corrigir dados a qualquer momento.
    - Distinção transparente entre "Salvar rascunho" e "Aprovar e ativar", com mensagens de confirmação operacionais ("Política salva." e "Política aprovada e ativada.").
  - **Listagem Administrativa Adaptada:**
    - Visualização compacta em tabela no computador e cartões adaptados no celular, projetada para telas a partir de 320px sem colunas esmagadas nem rolagem lateral indesejada.
  - **Segurança contra Alterações Destrutivas:**
    - Avisos claros sobre sobreposição de vigências e proteção contra alterações em períodos já formalmente fechados.

## v2.5.5
- **Correção de Políticas de Jornada, Segurança e Preservação Histórica:**
  - **Precedência Determinística e Isolamento entre Organizações:**
    - Aplicação estrita da ordem de precedência: política específica do vínculo, política do estabelecimento, política da empresa e fallback seguro não-configurado.
    - Isolamento de `company_id` impedindo que regras de uma empresa afetem inadvertidamente colaboradores de outra organização.
  - **Distinção e Segurança no Regime Sem Banco de Horas (`NoBank`):**
    - Sem banco de horas passa a significar unicamente a inexistência de contabilização em banco de horas, sem presunção arbitrária de destinação a pagamento ou desconto em folha.
    - Créditos e débitos apurados são rigorosamente preservados. Na ausência de autorização expressa em política aprovada, a situação é rotulada como *"Destinação pendente de definição"*.
  - **Tratamento Específico para Servidores Estatutários vs. Empregados CLT:**
    - Veda a aplicação automática de tolerâncias (Art. 58 CLT) e acordos tácitos privados a servidores estatutários federais, estaduais e municipais, exigindo fundamentação normativa formal aprovada administrativamente.
  - **Políticas Versionadas com Proteção de Competências Passadas:**
    - Atualizações normativas exigem novas vigências ou versões com data de início e aprovação explícita, sem modificar retroativamente cálculos e fechamentos históricos.
  - **Consultas ao Espelho e Extrato Estritamente Somente-Leitura:**
    - Remoção definitiva de cadastros automáticos com CPFs ou matrículas fictícias durante a leitura do espelho de ponto. Colaboradores sem cadastro funcional concluído visualizam orientação segura: *"Seu cadastro funcional ainda não está completo. Procure o responsável pelo RH."*.
    - Consultas ao extrato de banco de horas não geram contas automáticas na base de dados.
  - **Proteção de Competências Fechadas sem Snapshot:**
    - Competências fiscalmente fechadas sem snapshot histórico imutável bloqueiam recálculos ao vivo e aplicação de escalas presentes, informando com segurança *"Histórico indisponível"* e gerando diagnóstico administrativo autorizado.
  - **Classificação Precisa dos Indicadores de Apuração:**
    - Distinção transparente entre os estados *Parcial*, *Apurada*, *Fechada*, *Pendente de configuração* e *Histórico indisponível*, eliminando a exibição de `00:00` quando não houve cálculo real.

## v2.5.4
- **Segurança e Transparência na Gestão de Horas e Compensações:**
  - **Proteção Integral do Saldo do Colaborador:**
    - O fechamento da folha ou do mês não apaga nem zera mais automaticamente as horas trabalhadas.
    - Qualquer quitação ou compensação de horas passa a exigir comprovação real (como recibo, holerite ou folga concedida), garantindo que nenhum saldo desapareça sem registro.
  - **Validação Segura de Saldos e Horas Disponíveis:**
    - O sistema confere automaticamente as horas reais apuradas antes de qualquer autorização, impedindo o pagamento duplicado do mesmo saldo ou a quitação de valores acima do apurado.
    - Separação clara entre horas extras a receber (créditos) e atrasos a compensar (débitos), evitando misturas indevidas.
  - **Alçadas e Permissões Claras:**
    - Gestores operam apenas colaboradores de suas equipes e não podem aprovar as próprias horas, garantindo conformidade e transparência para a empresa.
    - Colaboradores podem acompanhar seus saldos e solicitações de forma transparente.
  - **Estornos e Correções Auditáveis:**
    - Caso uma quitação precise ser desfeita, o sistema registra um estorno rastreável com justificativa, restaurando o saldo com total segurança jurídica para colaboradores e empregadores.

## v2.5.3
- **Refatoração UX e Resiliência do Registro de Ponto (`/ponto`):**
  - **Experiência de Câmera Mobile-First e Suporte Amplo a Notebooks/Desktops:**
    - Inicialização inteligente via `Html5Qrcode.getCameras()`, com seleção automática da webcam integrada em computadores e da câmera traseira em dispositivos móveis.
    - Fallback resiliente para `{ facingMode: 'user' }` caso restrições de orientação ambiental não sejam suportadas pelo hardware.
    - Viewfinder com dimensões permanentes no DOM para evitar renderização com largura e altura nulas.
    - Liberação física total de `MediaStreamTrack` ao cancelar, garantindo desligamento imediato do sensor e do LED da câmera.
  - **Geolocalização Opcional e Não-Bloqueante com Prefetch:**
    - Solicitação proativa de permissão de geolocalização em segundo plano logo ao iniciar a leitura, sem interromper nem atrasar a câmera.
    - O registro do ponto prossegue normalmente mesmo se o GPS estiver desativado, for negado ou atingir timeout.
  - **Máquina de Estados Explícita (Alpine.js + Livewire):**
    - Estados rigorosos e mutuamente exclusivos: `idle`, `starting`, `scanning`, `location`, `submitting`, `success`, `warning`, `error`, `unknown`.
    - Eliminação de estados contraditórios e transições visuais claras sem travamentos.
  - **Aprimoramento de UX Writing e Correção de Textos:**
    - Substituição de termos imprecisos como "Câmera pronta" e "Registro inviolável".
    - Textos objetivos e operacionais: "Leitura não iniciada", "Ativando câmera...", "Aponte para o QR Code da empresa", "QR Code identificado", "Registrando marcação...".
  - **Botão de Ação Dinâmico e Ergonomia Touch:**
    - Ações adaptativas conforme o estado ("Iniciar leitura do QR Code", "Cancelar leitura", "Tentar novamente", "Verificar se foi registrado").
    - Área de toque mínima de 44px (`min-h-[44px]`) e prevenção de múltiplos envios concorrentes.
  - **Diagnóstico e Permissões de Câmera Contextualizadas:**
    - Mensagens amigáveis para permissão negada, ausência de câmera, dispositivo ocupado por outro app e contexto não-seguro (HTTP).
  - **Confirmação Específica, Tranquila e Acessível:**
    - Modal operacional exibindo exclusivamente dados persistidos do servidor (direção, horário no fuso do estabelecimento, data, NSR formatado).
    - Diferenciação clara entre registro de ponto confirmado e status da emissão do comprovante (emitido vs. em processamento).
    - Cores funcionais com emerald para sucesso, amber para observações e red para erros reais.
  - **Recuperação e Reconciliação Pós-Instabilidade de Rede:**
    - Estado `unknown` com opção de verificar status antes de qualquer reenvio, prevenindo tentativas desnecessárias.
  - **Layout de Duas Colunas (Desktop) e Card "Sua Jornada Hoje":**
    - Apresentação dos registros reais do dia do colaborador autenticado, com links diretos para comprovantes e relógio digital oficial.
  - **Acessibilidade:**
    - Atributos `aria-live`, fechamento por tecla Escape, retorno de foco e suporte a `prefers-reduced-motion`.
- **Segurança, Idempotência e Resiliência do Módulo Bater Ponto (`/ponto`):**
  - **Prevenção de Duplicidade e Idempotência:**
    - Bloqueio imediato e síncrono de callbacks concorrentes no scanner durante a mesma leitura de QR Code.
    - Desativação imediata da câmera e proteção contra cliques repetidos nos botões de registro.
    - Implementação de chave de idempotência única por tentativa (`idempotency_key`), persistida com restrição `unique` no banco de dados e serializada via lock distribuído (`Cache::lock`).
    - Retentativas ou chamadas concorrentes com a mesma chave retornam a batida já gravada sem consumir novo NSR e sem duplicar registros.
    - Marcações legítimas subsequentes não sofrem bloqueio arbitrário por tempo.
  - **Determinação de Entrada e Saída Imune a Jornadas Noturnas:**
    - Eliminação do filtro limitador de dia civil (`whereDate`).
    - Criação da Action `DetermineNextPunchDirectionAction`, resolvendo a direção a partir da sequência cronológica real ininterrupta (`occurred_at_utc desc`).
    - Suporte robusto a plantões noturnos (22h -> 06h), intervalos que cruzam meia-noite (23h30 -> 00h30), dias consecutivos, viradas de mês/ano e trocas de fuso horário.
  - **Padronização de Timezone Dinâmico e Servidor como Referência:**
    - Relógio da interface e mensagens refletem o fuso horário oficial do estabelecimento (`$establishment->resolvedTimezone()`) com fallback oficial (`config('app.timezone')`).
    - Todos os timestamps da mesma batida (`occurred_at_utc`, `occurred_at_local`, hashes fiscais e de auditoria) são gerados a partir de um instante físico único congelado no servidor (`Carbon::now('UTC')`).
  - **Remoção de Coordenadas Fictícias e Resiliência de GPS:**
    - Eliminação de coordenadas fixas de Maceió (-9.6658 / -35.7350).
    - Quando o perímetro não está configurado, o sistema registra as coordenadas reais disponíveis sem calcular distância fictícia e define o perímetro como não verificado (`location_valid = null`), sem recusar a marcação.
    - Timeout de 5 segundos na coleta de GPS: permissão negada, GPS desativado ou timeout não impedem nem prendem a marcação (Art. 74/78 Portaria 671 MTP), preservando a validação estrita do QR Code.
  - **Resiliência e Reconciliação em Falhas de Rede:**
    - Tratamento completo com `catch`, `finally` e modal de instabilidade que não presume falso descarte.
    - Reconciliação assíncrona por identificador da tentativa (`checkPunchStatus`) antes de oferecer reenvio seguro com a mesma chave.
  - **Conformidade Fiscal e Comprovantes:**
    - Preservação estrita do ledger ARP, NSR monotônico por estabelecimento, cadeia de hashes fiscais Tipo 7 e integridade dos eventos de domínio.
    - Resiliência na emissão de comprovantes PAdES com recuperação sob demanda.

## v2.5.2
- **Redefinição do Design System Oficial e Diretrizes UX/UI (`DESIGN.md`):**
  - **Fonte Única da Verdade para UX e UI:**
    - Substituição integral do documento `DESIGN.md` na raiz do projeto por uma nova especificação normativa que reflete a interface real do sistema.
  - **Identidade Própria e Domínio de Ponto:**
    - O dado de jornada (horários, entradas, saídas, NSR, comprovantes, saldos, espelho) é consolidado como o próprio elemento visual de design do PontoFácil.
  - **Mobile-First Compulsório:**
    - Referência mínima estrita em 320px sem overflow horizontal, touch targets mínimos de ~44px e independência de `:hover`.
  - **Paleta Restrita e Semântica:**
    - Índigo corporativo (marca), Slate/Gray (neutros), Emerald (ativo/saldo positivo), Amber (ocorrência/tolerância) e Red/Rose (erro/crítico). Uso de roxo/purple restrito e não decorativo.
  - **Tipografia e Dados Tabulares:**
    - `Instrument Sans` no sistema autenticado e `Plus Jakarta Sans` / `Inter` na landing, com `font-mono` e `tabular-nums` obrigatórios em dados cronológicos e fiscais.
  - **Hierarquia de Radius, Sombras e Motion Seguro:**
    - Padronização em `rounded-lg`, `rounded-xl` e `rounded-2xl`, eliminando excessos de `rounded-3xl` e `shadow-2xl`.
    - Regra estrita de motion: conteúdo essencial nunca depende de JavaScript para renderizar visível (`opacity: 0` proibido por padrão).
  - **Conformidade Normativa e Descarte de Padrões Legados:**
    - Arquitetura REP-P + PTRP (Portaria 671 MTP), eliminação de referências legadas a "Glassmorphism Suave", fuso fixo de Maceió e promessas regulatórias antecipadas.

## v2.5.1
- **Revisão Final de UX Writing, Sobriedade e Acabamento da Landing Page:**
  - **Remoção de Expressões Genéricas e Hype de Marketing:**
    - Eliminação completa de clichês e termos superlativos ("nova era", "Comece agora mesmo", "Implantação Instantânea", "Poder Total", "segurança máxima", "inviolável", "100% seguro", "perpétuo").
    - Adoção de redação operacional clara voltada para empresários, RH, DP, gestores e colaboradores: *"Organize a jornada da sua equipe em um só lugar"* e *"Registre, acompanhe e feche jornadas com menos retrabalho"*.
  - **CTA Final Sóbrio e Enxuto:**
    - Título refinado: *"Pronto para organizar a jornada da sua equipe?"* com subtítulo *"Registre, acompanhe e feche jornadas com menos retrabalho."* e corpo explicativo *"Registre pontos, acompanhe ocorrências, banco de horas e fechamento em um único sistema."*.
    - Botão primário sóbrio: *"Entrar no PontoFácil"* e secundário: *"Conhecer os recursos"*. Remoção do badge "Implantação Instantânea".
  - **Hero Benefit-First:**
    - Badge do topo reestruturado para benefícios antes de siglas técnicas: *"Controle de jornada digital • Simples no celular, seguro para a empresa"*, reservando detalhes técnicos de REP-P/PTRP para a seção de conformidade.
  - **Correção de Afirmações Técnicas:**
    - Substituição de afirmações de segurança indevidas ("assinatura de segurança" / "comprovante assinado") por termos técnicos precisos: *"registro com dados de integridade"* e *"comprovante digital com dados de integridade"*.
    - Zero declarações precipitadas de homologação governamental prévia, certificado ativo, INPI concluído ou assinatura ICP-Brasil enquanto pendentes.
  - **Simplificação do Rodapé (~40% mais enxuto):**
    - Remoção de detalhes de implementação interna desnecessários no footer comercial (NTP.br, SHA-256, cerca virtual, fuso de Maceió GMT-3). O fuso horário é responsabilidade de configuração por estabelecimento.
    - Estrutura limpa: marca PontoFácil (*"Controle de ponto e gestão de jornada"*), links de navegação (`Recursos`, `Para empresas`, `Segurança`, `Portaria 671`, `Verificar comprovante`, `Entrar`), direitos autorais e *"Desenvolvido por KL Tecnologia"*.
  - **Validação de Navegação e Responsividade Mobile-First:**
    - Todos os anchors (`#hero`, `#como-funciona`, `#para-empresas`, `#recursos`, `#seguranca`, `#portaria-671`, `#demonstracao`, `#contato`) e rotas ativas checados sem links órfãos ou quebrados.
    - Testado visualmente e medido sem qualquer overflow horizontal (scrollWidth <= innerWidth) em 320px, 360px, 375px, 390px, 430px, 768px, 1024px, 1280px e 1440px+.
    - Testes automatizados atualizados (`LandingPageTest`) com 100% de aprovação (175 testes no total).
- **Identidade Própria e Design com Elementos Reais do Domínio de Ponto (Landing Page):**
  - **Identidade do Produto Sem Dependência de Ícones Genéricos:**
    - O próprio dado de jornada virou elemento de design: horário (`08:01:32`), operação (`ENTRADA`/`SAÍDA`), `NSR #000004281`, jornadas, saldos (`+09m`, `-01m`, `+16m`), comprovantes (`PF-2026-8942-01`), espelhos de ponto e conciliação.
  - **Mockup Hero Fiel à Interface Real (`/ponto`):**
    - Reprodução dos elementos reais de `time-punch.blade.php`: relógio digital com gradiente da marca, data em caixa alta, horário oficial GMT-3 com pulso em tempo real, contexto de jornada do colaborador, viewfinder de leitor de QR Code com cantos de mira e evidências de contexto.
  - **Novo Componente Proprietário: Ciclo do Registro de Ponto:**
    - Criação de `punch-lifecycle.blade.php`: "O que acontece quando alguém registra o ponto?", apresentando a sequência técnica da batida (08:01:32 • Entrada • NSR 000004281 → Registro original preservado na ARP → Comprovante disponível → Jornada atualizada no PTRP) com segregação explícita entre dado bruto e tratamentos posteriores.
  - **Demonstração Integrada de Espelho de Ponto Real (`recursos`):**
    - Incorporação de tabela real de apuração diária no card principal de recursos com colunas `Dia | Entrada | Saída | Entrada | Saída | Saldo` (`Seg 05`, `Ter 06`, `Qua 07`) e a diretriz central *"Do registro ao fechamento sem perder rastreabilidade."*.
  - **Demonstração Interativa Revisitada (`demonstracao`):**
    - Revisão do título para *"Registrar leva segundos. Gerenciar continua completo."*, preservando o badge de categoria e incorporando métricas reais de acompanhamento de equipe e tabela auditada com NSR e evidência de localização no painel do gestor.
  - **Conformidade Normativa e Evidência de Localização:**
    - Tratamento uniforme da localização como evidência contextual de conformidade (sem bloqueios indevidos nem linguagem proibida).
- **Aprimoramento de Ritmo, Composição Editorial e Hierarquia Visual (Landing Page):**
  - **Eliminação da Repetição Estrutural (Cards Repetitivos):**
    - Redução de mais de 40% das caixas/cards isolados da landing page, substituindo padrões monótonos por fluxos conectados, matrizes de capacidades e diagramas visuais.
  - **Como Funciona em Fluxo Conectado:**
    - Substituição de 4 cards isolados por um fluxo de processo contínuo com linha conectora (horizontal no desktop e vertical no mobile).
  - **Assimetria Visual em Colaborador x Empresa:**
    - Fim dos dois cards idênticos: experiência do colaborador com preview da interface mobile e capacidades da empresa/RH em matriz estruturada de duas colunas.
  - **Hierarquia Editorial em Recursos Principais:**
    - Fim da sensação de catálogo: 1 recurso principal destacado com visual de apuração CLT (*Espelho Automatizado em Tempo Real*) e 3 recursos secundários integrados.
  - **Narrativa de Trilha de Auditoria em Segurança:**
    - Substituição de 4 cards genéricos por um fluxo narrativo de segurança real ancorado por evento auditado (`08:01:32 • NSR 000004281 • SHA-256`) e etapas sequenciais de integridade.
  - **Diagrama Arquitetural da Portaria 671:**
    - Substituição dos 6 cards anteriores por um diagrama de pipeline funcional (`REP-P → ARP → PTRP → Espelho/AEJ`), 3 princípios normativos fundamentais e nota técnica de transparência regulatória.
  - **Correção Crítica no GSAP (Visibilidade Sem Dependência de JS):**
    - Remoção de `opacity: 0` inicial via GSAP. Todo o conteúdo é renderizado 100% visível por padrão, prevenindo áreas vazias em screenshots full-page ou carregamento rápido.
  - **Calibração de Ritmo e Espaçamento Vertical:**
    - Otimização do espaçamento vertical (`py-10` a `py-16`), aproximando títulos de conteúdos para manter engajamento a cada scroll.
- **Refinamento de Direção de Arte e Identidade Visual (Landing Page):**
  - **Identidade Corporativa e Sóbria:**
    - Redução significativa da estética genérica de template SaaS / IA, alinhando a landing page a um padrão visual de software corporativo maduro (Linear na disciplina visual, Stripe na sobriedade e clareza para RH).
  - **Simplificação da Paleta e Tokens de Marca:**
    - Centralização da identidade visual via tokens CSS (`--brand-50` a `--brand-950`).
    - Paleta estrita: Índigo (marca PontoFácil), Slate (neutros/textos), Emerald (sucesso/validação pública), Amber (atenção/regulatório MTP) e Red (erros).
    - Eliminação completa de uso decorativo de roxo/purple em seções e cards.
  - **Eliminação de Gradientes de Texto:**
    - Remoção de `bg-gradient-to-r`, `bg-clip-text` e `text-transparent` em títulos da landing, aplicando `slate-900` para títulos e `indigo-600` / `indigo-400` sólido para destaque.
  - **Substituição de Pills Repetitivos por Labels Editoriais:**
    - Seções agora identificadas com numeração e tipografia editorial discreta (`01 — COMO FUNCIONA`, `02 — COLABORADOR X EMPRESA`, `03 — RECURSOS PRINCIPAIS`, `04 — SEGURANÇA E RASTREABILIDADE`, `05 — PORTARIA 671 / MTP`, `06 — DEMONSTRAÇÃO DA INTERFACE`).
  - **Hierarquia de Bordas (Radius) e Sombras (Shadows):**
    - Padronização de botões (`rounded-lg`/`rounded-xl`), cards (`rounded-xl`) e containers (`rounded-2xl`), eliminando o uso disperso de `rounded-3xl`.
    - Substituição de sombras pesadas (`shadow-xl`/`shadow-2xl`) por bordas refinadas `border-slate-200/90` e sombras sutis (`shadow-xs`/`shadow-sm`), reservando destaque para o mockup central.
  - **Navegação Desktop Sóbria e Minimalista:**
    - Remoção dos 4 ícones inline dos links de navegação no desktop (`Recursos`, `Para empresas`, `Segurança`, `Portaria 671`), preservando os ícones funcionais em *Verificar comprovante* e *Entrar*. Menu mobile com ícones monocromáticos em `currentColor`.
  - **Moderação Tipográfica:**
    - Remoção de `font-black`, priorizando `font-bold` e reservando `font-extrabold` estritamente para títulos principais (Hero e CTA).
- **Reformulação Visual, Otimização Mobile-First e Acessibilidade (Landing Page Comercial):**
  - **Reorganização Didática em 8 Seções Estruturadas:**
    - Sequência lógica orientada a conversão e clareza: 1. Hero com interface real (`#hero`), 2. Como funciona (`#como-funciona`), 3. Para o colaborador / Para a empresa (`#para-empresas`), 4. Principais recursos (`#recursos`), 5. Segurança e rastreabilidade (`#seguranca`), 6. Estrutura preparada para a Portaria 671 (`#portaria-671`), 7. Demonstração da interface (`#demonstracao`) e 8. CTA final (`#contato`).
    - Apresentação didática explicando primeiro o produto, depois os benefícios e somente depois os detalhes técnicos e fiscais.
  - **Nova Seção 'Como funciona' em 4 Etapas:**
    - Fluxo ponta a ponta simples e intuitivo (1. Colaborador registra o ponto, 2. Sistema gera registro e comprovante, 3. Gestores acompanham jornadas e ocorrências, 4. RH trata ajustes, banco de horas e fechamento) com cards verticais mobile-first, 4 colunas em desktop e sem termos jurídicos exagerados.
  - **Nova Seção 'Colaborador x Empresa' (Para quem registra vs Para quem gerencia):**
    - Seletor de abas mobile-first para telas pequenas (< 1024px) e duas colunas amplas no desktop.
    - Destaque claro dos 5 recursos do colaborador e dos 6 pilares de gestão da empresa.
  - **Hero Mockup Fiel à Interface Real do Produto:**
    - Simulação demonstrativa interativa do painel do colaborador com saudação personalizada, relógio digital com segundos em tempo real, status da jornada, último registro e geração imediata de comprovante após a marcação.
    - Geolocalização apresentada estritamente como evidência e apoio à validação, sem bloqueio de ponto por GPS.
  - **Redução de Excesso Visual e Atenuação Gráfica:**
    - Eliminação completa de `shadow-2xl` na landing comercial pública, adotando sombras suaves e contemporâneas (`shadow-xs`, `shadow-sm`, `shadow-md`).
    - Substituição de gradientes decorativos em textos por cores sólidas de alto contraste (`text-indigo-600` e `text-indigo-400`).
    - Atenuação drástica de halos neon, grade pontilhada e esferas `blur-3xl`.
  - **Calibração Mobile-First Estrita (320px a 1440px):**
    - Espaçamentos verticais adaptativos (`py-12 sm:py-14 md:py-16 lg:py-20`), eliminando áreas vazias excessivas.
    - Cards padronizados com `p-5 sm:p-6` e títulos contidos em até `text-4xl` no celular.
    - Zero overflow horizontal verificado em todas as resoluções de tela (320px, 360px, 390px, 430px, 768px, 1024px e 1440px).
  - **Revisão de SEO, Metadados e Open Graph:**
    - Título objetivo: `<title>PontoFácil | Controle de Ponto e Gestão de Jornada</title>`.
    - Meta description e cards de compartilhamento Open Graph / Twitter sincronizados.
    - Keywords coerentes sem keyword stuffing e remoção total de menções a REP-A, homologação antecipada ou promessas de conformidade absoluta.
  - **Acessibilidade WCAG 2.1 AA & Navegação por Teclado:**
    - Skip link funcional (`href="#hero"`) para leitores de tela e usuários de teclado.
    - Respeito à preferência `prefers-reduced-motion` no CSS e script GSAP.
    - Anéis de foco visível (`focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-indigo-600`) em todos os elementos interativos.
    - Semântica ARIA completa (`role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-expanded`, `aria-controls`, `aria-label`).
    - Fechamento do menu mobile via tecla `Escape`.
    - Áreas de toque confortáveis (mínimo de 44x44px em botões e links).
  - **Preservação Arquitetural & Remoção da Timeline Comercial:**
    - A timeline técnica de versões foi removida da landing comercial pública por se tratar de informação técnica interna, preservando o histórico integral neste changelog e na área administrativa.

## v2.5.0
- **Conformidade MTE 31/07/2026: AFD REP-P Oficial & AEJ do PTRP (Portaria 671/2021 MTP):**
  - **AFD REP-P Oficial (Leiaute MTE 31/07/2026):**
    - **Nomenclatura Oficial:** Implementada a regra do item 10.3 do MTE (`AFD_{inpi}_{cnpj}_REP_P.txt`) quando houver registro no INPI; mantém nomenclatura de prévia/desenvolvimento (`AFD_{cnpj}_{inicio}_{fim}.txt`) enquanto o INPI estiver pendente, sem bloquear a geração.
    - **Geração Direta da ARP:** Gerado estritamente a partir do ledger `arp_events`, preservando NSR original contíguo, ordem cronológica e tipos fiscais sem renumerar eventos.
    - **Registro Tipo 1 (Cabeçalho - 302 caracteres):** Refeito exatamente no leiaute oficial versão `004`, identificação do empregador, 17 espaços para INPI pendente (sem strings fictícias nem zeros inventados), datas ISO (`AAAA-MM-dd`), data/hora de geração ISO (24 posições), identificador e documento do desenvolvedor, modelo do software com 30 espaços para REP-P e CRC-16 Kermit CCITT-TRUE (`123456789 -> 2189`).
    - **Registro Tipo 2 (Empregador/Estabelecimento - 331 caracteres):** NSR (9), tipo (1), gravação ISO (24), CPF do responsável (14 car. pela esquerda), identificador do empregador, razão social (150), local da prestação de serviços (100) e CRC-16 Kermit.
    - **Registro Tipo 4 (Ajuste de Relógio - 73 caracteres):** NSR (9), tipo (1), data/hora antes (24 car. ISO), data/hora depois (24 car. ISO), CPF do responsável (11) e CRC-16 Kermit.
    - **Registro Tipo 5 (Trabalhador - 118 caracteres):** NSR (9), tipo (1), gravação ISO (24), operação (I/A/E), CPF do trabalhador (12 dígitos numéricos), nome padronizado (52 car.), demais dados (4 car.), CPF do responsável (11) e CRC-16 Kermit.
    - **Registro Tipo 6 (Eventos Sensíveis REP-P - 36 caracteres):** Gravação ISO (24) e códigos oficiais do REP-P (`07` = disponibilidade de serviço, `08` = indisponibilidade de serviço, `02` = retorno de energia), sem descrição textual no AFD.
    - **Registro Tipo 7 (Marcações REP-P - 137 caracteres):** Separação de ocorrência e gravação para marcações offline, coletor oficial (`01` = mobile, `02` = web, `03` = desktop, `04`/`05` = dispositivo), tipo de marcação (`0`/`1`), CPF (12) e hash fiscal SHA-256 (64 car.) encadeado com o Tipo 7 anterior a partir do `fiscal_hash` imutável da ARP (sem reiniciar cadeia em exportações parciais).
    - **Registro Tipo 9 (Trailer - 64 caracteres):** Posição oficial do identificador final `9` na posição 64, contadores estritos dos tipos 2 a 7 (6 campos de 9 dígitos), sem campos de total de linhas inventados.
    - **Linha de Assinatura CAdES:** Marcador oficial de 100 caracteres `ASSINATURA_DIGITAL_EM_ARQUIVO_P7S` preenchido com espaços à direita. O marcador é requisito posicional da norma e não substitui a validação de assinatura real externa (.p7s).
    - **CRC-16 Kermit:** Validação determinística estrita no padrão CCITT-TRUE (`123456789 -> 2189`), calculada e validada nos registros Tipo 1, 2, 4 e 5.
  - **AEJ Oficial do PTRP (Anexo VI Portaria 671/2021 MTP):**
    - **Registro 01 (Cabeçalho):** Formato delimitado por pipes `|`, versão oficial vigente `001` conforme o Anexo VI da Portaria 671/2021 MTP, com identificação completa do empregador, período e carimbo ISO-8601.
    - **Registro 02 (REPs Utilizados):** Identificador `02|idRepAej|tpRep|numRegRep`. Em status de INPI pendente com fonte `O`, gera arquivo para desenvolvimento com status não homologado (`structureValid = true`, `isHomologated = false`).
    - **Registro 03 (Vínculos):** Relação dos colaboradores apurados na competência.
    - **Registro 04 (Horários Contratuais):** Jornada em minutos e pares de horários contratuais planejados.
    - **Registro 05 (Marcações Tratadas Oficial):** `05|idtVinculoAej|dataHoraMarc|idRepAej|tpMarc|seqEntSaida|fonteMarc|codHorContratual|motivo`. Mapeamento das fontes oficiais (`O` = original, `I` = manual, `P` = pré-assinalada, `X` = exceção, `T` = outra). Código contratual apontado na 1ª entrada do dia e motivo obrigatório para batidas manuais e desconsideradas.
    - **Registro 06 (Matrículas eSocial):** Gerado exclusivamente quando o colaborador possuir mais de um vínculo no AEJ.
    - **Registro 07 (Ausências e Banco de Horas):** Em competência fechada, gerado 100% a partir dos snapshots congelados do `ClosedPeriod` com garantia de imutabilidade retroativa byte a byte.
    - **Registro 08 (PTRP / Desenvolvedor):** `08|nomePrograma|versaoPrograma|tpIdDev|numIdDev|razaoSocialDev|emailDev` sem dados fictícios em `config/compliance.php`.
    - **Registro 99 (Trailer):** Contadores formais de registros 01 a 08.
    - **Linha de Assinatura CAdES:** Marcador preparado ao final do arquivo.
  - **Saneamento e Integridade Criptográfica Real (CAdES / ICP-Brasil):**
    - **Remoção de Validação Falsa:** Eliminadas todas as heurísticas por strings de mock (`VALID_CADES...`), prefixo DER `0x30` ou formato PEM sem validação criptográfica.
    - **Arquitetura Desacoplada de Assinatura:** Introduzida a interface `CadesSignatureVerifierInterface` com implementação segura de produção `PendingCadesSignatureVerifier` (retornando `false` até haver emissão e validação ICP-Brasil de cadeia completa) e stub `IcpBrasilCadesSignatureVerifier` preparado para integração futura.
    - **Normalização Oficial do INPI no AEJ:** O campo `nrRep` do Registro 02 exporta estritamente dígitos numéricos oficiais (`preg_replace('/\D/', '', ...)`), removendo prefixos textuais (como `BR`) e aceitando vazio quando pendente.
    - **Validação Estrutural Real no Generator:** `AfdGenerator_2026_07_31` e `AejGenerator_2026_07_31` executam os respectivos validadores estruturais no conteúdo gerado, atribuindo `structureValid` com base no resultado formal da auditoria.
    - **Nomenclatura AFD em Desenvolvimento:** Adotada a convenção explícita `AFD_DEV_{CNPJ}_{DATA_INICIO}_{DATA_FIM}.txt` quando sem INPI cadastrado, e `AFD_{inpi}_{cnpj}_REP_P.txt` (item 10.3 Portaria 671 MTE) com INPI registrado.
  - **Separação de Estados de Validação e Homologação:**
    - Estados claramente distintos: `structureValid` (conformidade do layout), `signatureValid` (assinatura CAdES .p7s real validada), `isHomologated` e `homologationReason`.
    - Inconsistência do AEJ corrigida: certificado pendente nunca resulta em `isHomologated = true`.
  - **Pacote Fiscal MTE (README e Manifesto):**
    - README atualizado para referenciar o ledger central da ARP (`arp_events`), CRC-16/KERMIT, SHA-256 Tipo 7 e o modo não homologado de desenvolvimento.
  - **Congelamento Documental das Referências Oficiais MTE (31/07/2026):**
    - Os leiautes oficiais de referência usados na implementação foram congelados em `ref/mte/2026-07-31/` (`afd.pdf` e `aej.pdf`).
    - Hashes SHA-256 imutáveis registrados em `SHA256SUMS.txt` garantem rastreabilidade documental contra eventuais substituições silenciosas de conteúdo na mesma URL do portal `gov.br`.
    - Futuras mudanças regulatórias do MTE exigirão nova referência versionada em diretório próprio (`ref/mte/YYYY-MM-DD/`), mantendo os registros históricos intactos.
    - Teste automatizado offline `MteReferenceDocumentsIntegrityTest` assegura a integridade contínua dos binários locais sem dependência de runtime ou rede.
  - **Arquitetura Não-Bloqueante (INPI & ICP-Brasil):**
    - O sistema continua 100% operacional sem bloquear registros de ponto, ARP, PTRP, fechamento mensal, banco de horas, espelhos ou exportações de desenvolvimento.
  - **Golden Tests Read-Only:**
    - Fixtures `golden_afd_mte_2026.txt` (versão `004`) e `golden_aej_mte_2026.txt` (versão `001`) congeladas e validadas byte a byte em formato Windows CRLF.
  - **Refatoração Técnica da Seção de Segurança da Landing Page:**
    - **Linguagem Técnica e Confiável:** Substituição de expressões exageradas ou com sugestão de garantia absoluta ("Arquitetura Blindada", "Máxima Segurança Jurídica", "Proteção jurídica total", "Inviolável", "segurança jurídica irrefutável") por terminologia técnica, precisa e auditável.
    - **4 Pilares Técnicos Fundamentais:**
      1. *Rastreabilidade desde o registro e trilha de auditoria:* visão arquitetural com cadeia de custódia e histórico contínuo;
      2. *Registros imutáveis da ARP e NSR sequencial:* preservação perpétua no ledger de eventos com NSR contínuo por estabelecimento;
      3. *Integridade com Hash SHA-256:* encadeamento fiscal a partir do evento anterior para auditoria e conferência sequencial;
      4. *Registro bruto separado do tratamento de jornada:* modelo REP-P (dado original bruto na ARP) desacoplado do PTRP (cálculos, espelho e ajustes sem sobrescrever batidas);
      5. *Comprovantes e histórico de marcações:* recibo digital instantâneo com identificador e carimbo de tempo, com portal de conferência pública acessível a auditores e trabalhadores.
    - **Testes Automatizados:** Atualização de `LandingPageTest` com asserções estritas (`assertSee` para os novos pilares e `assertDontSee` para expressões exageradas e garantias jurídicas absolutas).
  - **Refatoração Sóbria da Seção 'Estrutura preparada para a Portaria 671':**
    - **Comunicação Clara e Sóbria:** Seção técnica sem apelo de marketing ou promessas regulatórias antecipadas, focada em transmitir seriedade e clareza aos gestores e trabalhadores.
    - **6 Pilares da Arquitetura:**
      1. *REP-P registra os eventos brutos:* gravação original e fidedigna no ledger imutável da ARP sem adulteração;
      2. *PTRP trata jornadas, ocorrências e fechamento:* apuração de regras, tolerâncias, banco de horas e espelho formal;
      3. *Registros de ponto não são alterados pelo tratamento:* desacoplamento funcional com preservação da batida original e geração de ocorrências complementares auditadas;
      4. *Comprovantes são gerados após a marcação:* emissão instantânea com carimbo de tempo e identificador de validação pública;
      5. *Trilhas de auditoria são preservadas:* histórico cronológico contínuo e NSR sequencial por estabelecimento para relatórios fiscais (AFD e AEJ);
      6. *INPI e ICP-Brasil como etapas complementares:* registro de programa e certificados ICP-Brasil tratados com total transparência como etapas normativas complementares quando aplicáveis.
    - **Prevenção de Alegações Indevidas:** Garantia de não declaração de homologação prévia do MTE, INPI concluído, assinatura ICP-Brasil ativa ou conformidade definitiva enquanto tais processos estiverem em trâmite regulatório.
    - **Cobertura de Testes:** Atualização de `LandingPageTest` com verificação dos 6 pilares e ausência de declarações regulatórias indevidas.
  - **Adequação da Landing Page e Metadados SEO para REP-P + PTRP (Portaria 671):**
    - Revisão institucional de todo o conteúdo da Landing Page e metadados Open Graph/SEO, apresentando o sistema sob a arquitetura de controle de jornada digital REP-P + PTRP.
    - Remoção de terminologias de REP-A e promessas absolutas ("inviolável", "blindada", "100% aderente"), adotando linguagem de conformidade técnica, chave de integridade SHA-256 e trilha de auditoria para fins fiscais.
    - Atualização das asserções de texto em `LandingPageTest` para garantir conformidade contínua do texto institucional.
  - **Responsividade e Ergonomia da Tela de Bater Ponto (Mobile & Viewport Compacta):**
    - **Visibilidade Contínua do Botão "Escanear QR Code":** Redução de espaçamentos verticais excessivos e dimensionamento fluído do `#qr-reader` (`max-w-[190px]` a `max-w-[250px]`) com guias de foco e mira ("Câmera pronta"), garantindo que o botão principal de batida fique 100% visível sem rolagem em qualquer dispositivo (desktop, laptops 1366x768 e smartphones).
    - **Compactação do Relógio Digital:** Media queries dinâmicas (`@media (max-height)`) para preservar data, relógio oficial em segundos e fuso horário mesmo em telas compactas.
    - **Proteção Contra Sobreposição:** Ajuste do padding inferior do layout principal para manter margem limpa de segurança sobre a barra de navegação móvel (`bottom-nav`).
    - **Dimensionamento Dinâmico de QR Box:** Função de cálculo proporcional do leitor de QR Code para prevenir falhas de inicialização em viewports reduzidas.
  - **Redesenho Mobile-First do Hero da Landing Page:**
    - **Comunicação Direta e Descomplicada:** Foco na clareza do produto em poucos segundos, eliminando hipérboles publicitárias e enfatizando a proposta de valor: "Ponto eletrônico simples para o colaborador. Gestão completa para a empresa."
    - **Legibilidade em Telas Compactas:** Título responsivo limitado a no máximo 3–4 linhas no mobile (`text-2xl min-[360px]:text-[28px] sm:text-4xl lg:text-5xl xl:text-6xl`), com quebras semânticas e subtítulo direto sobre registro, espelhos, ajustes e banco de horas.
    - **Acesso ao Sistema em Destaque:** Ações primárias e secundárias com largura total no mobile (`w-full sm:w-auto`), facilitando o toque com transição dinâmica de "Entrar no PontoFácil" / "Ir para o Sistema" e "Conhecer os recursos".
    - **Fluxo Visual Otimizado:** Posicionamento imediato do mockup interativo de registro de ponto logo após os botões de ação no mobile, eliminação de vácuos verticais desnecessários e paddings compactados (`pt-6 pb-12 sm:pt-10 sm:pb-16`).
    - **Compatibilidade Extensiva:** Excelente leitura e fluidez em larguras estritas (320px, 360px, 390px, 430px e superiores), preservando rigorosamente a identidade visual branco + slate + índigo.
    - **Mockup Autêntico da Tela Real do PontoFácil:** Substituição do card genérico de marketing por uma réplica fiel da interface do colaborador: saudação personalizada ("Olá, Carlos"), relógio digital com precisão ao segundo em fuso oficial GMT-3, indicador situacional da jornada ("Você ainda não registrou a saída"), botão de ação principal proeminente ("Registrar ponto"), exibição do último registro ("18:02 • Entrada"), localização registrada como evidência documental (sem mensagens punitivas de raio ou bloqueios falsos por GPS) e garantia de comprovante disponível após a marcação. Interatividade demonstrativa fluida com feedback visual temporário e zero persistência no banco.
    - **Revisão Conceitual de Geolocalização e Geofence (Evidência Antifraude sem Bloqueio):**
      - **Apresentação Adequada à Legislação:** Revisão completa dos textos da Landing Page para assegurar que a geolocalização seja apresentada estritamente como evidência probatória adicional e apoio à auditoria fiscal e gestão, e nunca como motivo para bloquear ou impedir a marcação do colaborador.
      - **Eliminação de Termos Restritivos:** Remoção de expressões como "o registro só é aceito quando...", "fim das fraudes de localização", "cerca virtual obrigatória", "ponto bloqueado fora do raio" e "bloqueia registros fora da filial".
      - **Padronização Conceitual:** Adoção uniforme de terminologias de integridade: "Localização registrada como evidência", "Cerca virtual para análise de localização", "Evidências adicionais para auditoria" e "QR Code e localização como apoio à validação".
    - **Refatoração Mobile-First da Navbar Institucional:**
      - **Desktop Otimizado:** Marca PontoFácil com badge discreto "REP-P + PTRP", links de navegação estruturados ("Recursos", "Para empresas", "Segurança", "Portaria 671" e "Verificar comprovante") e botão de acesso direto "Entrar".
      - **Experiência Mobile Desobstruída:** Logo alinhado à esquerda e acionador hambúrguer à direita, eliminando botões grandes intrusivos na barra superior mobile para garantir respiro visual em 320px–430px.
      - **Drawer de Navegação Completo:** Menu móvel expansivo com links de toque confortável (`min-h-[44px]`), atalho direto para "Verificar comprovante", fechamento automático após navegação e CTA de largura total "Entrar no sistema" (`min-h-[48px]`).
      - **Saneamento Terminológico:** Remoção definitiva de menções legadas a "SaaS REP-A", preservando a identidade visual corporativa branco + slate + índigo.
  - **Suíte de Testes Expandida:** `172 testes e 923 asserções 100% aprovados`.

## v2.4.0
- **Compliance Portaria MTP 671/2021, ARP Completa e Integridade Criptográfica:**
  - **Armazenamento de Registro de Ponto Completo (ARP Central):**
    - Criação da tabela central `arp_events` como ledger fiscal único e imutável para todos os eventos oficiais (marcações, mutações de empregador e trabalhador, sincronismos de relógio e eventos sensíveis).
    - Numeração Sequencial de Registro (NSR) única e monotônica por estabelecimento garantida atomicamente com lock pessimista (`lockForUpdate()`), sem séries isoladas para batidas.
    - Imutabilidade absoluta: bloqueio técnico de atualização e exclusão (`LogicException`) e integridade referencial protegida contra exclusões em cascata.
  - **Eliminação de Duas Fontes de Verdade (Single Source of Truth):**
    - Unificação do fluxo de registro sob a `RecordPunchEventAction`.
    - Eliminação do dual-write descompassado em `time-punch.blade.php`. A tabela `time_entries` passa a ser uma projeção atômica e subordinada de `punch_events`, persistida na mesma transação.
  - **Validação Geográfica Antifraude (Geofence Não-Bloqueante):**
    - O GPS atua estritamente como evidência e validação antifraude, sem impedir o registro do trabalhador fora do raio autorizado nem alterar carimbos originais de data/hora.
    - Registro transparente com `location_valid = false` e auditoria de distância (`location_distance_meters`). Alerta visual de conformidade emitido na interface.
    - Remoção de qualquer menção inverídica de que a Portaria 671 exigiria geolocalização obrigatória para autorizar batidas.
  - **Separação Estrita de Hashes Fiscais e de Auditoria:**
    - `FiscalHashService`: Cálculo determinístico do hash fiscal oficial MTE Tipo 3 (SHA-256 sobre a cadeia canônica estrita de 37 caracteres).
    - `AuditChainHashService`: Encadeamento criptográfico interno do PontoFácil preservado em `audit_chain_hash` e `previous_audit_hash`.
  - **Suporte ao Registro de Software REP-P no INPI:**
    - Campos estruturados para registro INPI na entidade `Company` com status padrão `pending_registration` e exibição explícita de `PENDENTE REGISTRO` nos arquivos fiscais e comprovantes.
  - **Preparação para Assinatura ICP-Brasil:**
    - Serviço `IcpBrasilSigningService` implementado para integração futura com certificados A1 (PAdES para PDFs e CAdES destacada para AFD/AEJ) com status transparente `pending_certificate`.
  - **Golden Tests Oficiais Read-Only:**
    - Refatoração dos testes fiscais de AFD e AEJ para consumo estritamente somente-leitura de fixtures versionadas, com comparação byte a byte em formato Windows CRLF.
  - **Suíte de Testes Expandida:** `132 testes e 537 asserções 100% aprovados`.

## v2.3.0
- **Modelo Ideal de Tratamento de Ponto & Segregação de Funções (Portaria MTP 671 / PTRP):**
  - **Gestor Imediato (Aprova o Operacional):**
    - Aprovação e recusa de solicitações de inclusão de batida esquecida (`manual_punch_added`) e desconsiderações de marcações duplicadas ou erradas (`punch_disregarded`).
    - Ciência e validação prévia de atestados médicos e justificativas de ausência (`absence_justified`) exclusivamente para colaboradores dos setores sob sua gestão direta.
    - **Segregação de Funções:** Bloqueio formal e técnico de autoaprovação. Solicitações feitas pelo próprio Gestor exigem análise e aprovação exclusiva da Coordenação de RH.
  - **Coordenação de RH / Admin (Auditoria, Poder Total & Fechamento):**
    - Visão global e irrestrita de todos os setores e colaboradores da empresa.
    - Autoridade universal: o Admin possui poder total para aprovar ou recusar qualquer solicitação (em casos de gestores de férias, afastados ou em ausências operacionais).
    - Competência exclusiva para aprovar ou rejeitar solicitações originadas por Gestores.
    - Auditoria final de atestados anexados antes de executar o Fechamento Formal de Competência e emitir os arquivos fiscais oficiais (AEJ / AFD).
  - **Upload e Auditoria de Atestados Médicos:**
    - Campo de anexo de arquivo (PDF, PNG, JPG até 5MB) adicionado ao modal de solicitação de tratamento no Espelho de Ponto (`/timesheet`).
    - Rota de streaming seguro (`/treatment-attachment/{id}`) protegida sob regras de LGPD e Portaria 671, acessível apenas pelo Admin, pelo Gestor do setor correspondente ou pelo próprio trabalhador.
  - **Painel PTRP Aprimorado (`/admin/treatment-requests`):**
    - Filtros dinâmicos por Status, Tipo de Evento, Setor e Colaborador.
    - Badges de alerta destacando solicitações feitas por gestores e bloqueio visual dos botões de ação para o próprio solicitante.
    - Histórico detalhado de decisão identificando expressamente se o evento foi aprovado pela Coordenação de RH ou pelo Gestor do Setor.
  - **Modal Automático de Novidades no Primeiro Login (`version-notifier`):** Notificador reativo que detecta dinamicamente a versão atual em `versoes.md` e exibe um modal ilustrado com as notas de release na primeira autenticação do colaborador pós-atualização, registrando a confirmação em `last_seen_version` para não incomodar novamente.
  - **Suíte de Testes Expandida:** `108 testes e 466 asserções 100% aprovados`.

## v2.2.0
- **Melhorias de Usabilidade, Impressão Limpa e Conformidade Operacional:**
  - **Busca em Tempo Real no Espelho de Ponto (`/timesheet`):** Campo de seleção de colaborador convertido em busca reativa instantânea por Nome ou CPF, com dropdown estilizado e filtro dinâmico.
  - **Impressão Exclusiva da Folha de Ponto (`/folha-ponto`):** Configuração de regras `@media print` para suprimir navegação, sidebar, filtros e menus, imprimindo estritamente a Folha de Ponto Oficial A4 pronta para assinatura física.
  - **Manual do Sistema Segmentado por Perfil (`/ajuda`):** Manual interativo categorizado por perfis de acesso (Colaborador, Gestor, Administrador RH e Auditor / Fiscal do Trabalho), com passo a passo das rotinas diárias e operacionais.
  - **Validação Pública em Destaque na Landing Page (`/`):** Links e badges adicionados na barra superior, menu mobile e rodapé direcionando para a rota pública `/verificar-comprovante`, permitindo a fiscais e auditores validarem a integridade do ponto sem login.
  - **Validação Cadastral & Máscaras em Funcionários (`/admin/employees`):** Máscaras reativas para CPF (`000.000.000-00`) e Telefone (`(82) 9 9999-9999`), validação de e-mail corporativo (`example@email.com`) e formatação visual padronizada do CPF na tabela.
  - **Recuperação de Permissão de Câmera e GPS (`/ponto`):** Modal amigável com orientações detalhadas de desbloqueio no navegador (Chrome, Safari iOS, Edge) caso o usuário recuse o acesso por engano, acompanhado de botão de reativação imediata ("Tentar Novamente").
  - **Correção da Persistência do QR Code nas Configurações (`/admin/settings`):** Isolamento do canvas com `wire:ignore` e escuta dos eventos de ciclo de vida do Livewire (`morph.updated` e `qr-code-regenerated`), garantindo que o QR Code permaneça visível mesmo ao alternar a chave do Banco de Horas.
  - **Sidebar Retrátil Otimizado para Tablets:** Toggle de recolhimento e expansão do sidebar com persistência de estado no `localStorage` (`pf_sidebar_collapsed`), facilitando a navegação em tablets e telas compactas.
  - **Cartaz Oficial de Impressão do QR Code (`/admin/settings`):** Impressão isolada exclusiva do cartaz institucional com dados da empresa, CNPJ, logotipo, instruções e QR Code de alta resolução para fixação na entrada do estabelecimento.

## v2.1.0
- **Calendário Laboral, Feriados e Pontos Facultativos (Fase 20.18):**
  - **Diferenciação Jurídica Rigorosa (Lei 9.093/1995 & Portarias Administrativas):** Feriados legais e pontos facultativos modelados com comportamentos distintos (`work_behavior`). Pontos facultativos não eliminam jornadas automaticamente nem são tratados como feriados sem decisão expressa do Admin/RH.
  - **Tipologia e Escopo Territorial Hierárquico:** Eventos classificados em Feriados (`HOLIDAY`), Pontos Facultativos (`OPTIONAL_DAY`), Recessos/Suspensões (`INSTITUTIONAL_CLOSURE`) e Expedientes Especiais (`SPECIAL_WORKDAY`), com resolução hierárquica por escopo: Estabelecimento -> Municipal -> Estadual -> Nacional.
  - **Suporte a Eventos Parciais (Meio Período):** Permite configurar eventos de meio período (ex: Quarta-feira de Cinzas até as 14h), recalculando a jornada restante com base na intersecção exata com os períodos da escala do trabalhador.
  - **Trabalho em Feriado (`holiday_minutes`):** Horas laboradas em feriados são apuradas separadamente como `holiday_minutes` no DTO da jornada, sem assumir automaticamente horas extras ou banco de horas sem política de convenção aplicável.
  - **Painel Administrativo do Calendário (`/admin/calendar`):** Visão de calendário mensal interativa, filtros por ano/tipo/estabelecimento, cadastro de novos eventos, formulário com fundamentação legal e importação inteligente de feriados nacionais móveis (Páscoa, Sexta-feira Santa, Carnaval sugerido).
- **Central de Fiscalização Trabalhista, Snapshots Imutáveis & Emissão do AEJ (Fase 21):**
  - **Fechamento de Competência Imutável:** Congelamento determinístico com hash SHA-256 canônico englobando colaboradores, escalas, jornadas apuradas, tratamentos, banco de horas e o calendário laboral da competência. Modificações futuras no calendário não alteram meses já encerrados.
  - **Emissão do AEJ (Arquivo Eletrônico de Jornada - Leiaute MTE 31/07/2026):** Geração do arquivo fiscal oficial padronizado (Registros Tipo 1, 2, 3, 4 e 5) com validação posicional e de integridade para a Inspeção do Trabalho.
  - **Modo Prévia vs. Oficial:** Prévia para conferência antes do fechamento e geração definitiva auditada vinculada ao hash da competência.
- **Identidade Visual Corporativa & Logomarca na Folha de Ponto:**
  - Configuração de Razão Social, Nome Fantasia, CNPJ/CNO e upload de logotipo em `/admin/settings`, renderizado no cabeçalho da Folha de Ponto A4 Oficial.
- **Suíte de Testes Automatizados Expandida:** `99 testes e 442 asserções 100% aprovados`.

## v2.0.0
- **Motor de Tratamento PTRP, Jornadas, Tolerância Legal & Banco de Horas Configurável (Fase 20):**
  - **Ledger de Eventos de Tratamento (`treatment_events`):** Registros inalteráveis em ULID para ajustes, batidas esquecidas manuais (`manual_punch_added`), desconsiderações de marcações indevidas (`punch_disregarded`) e abonos de faltas/atestados (`absence_justified`), preservando intacto o fato bruto em `punch_events`.
  - **Fluxo de Solicitações do Trabalhador & Gestão RH (`treatment-requests`):** Colaboradores solicitam ajustes com justificativa obrigatória e carimbo de auditoria; administradores e gestores analisam, aprovam ou rejeitam formalmente com justificativa registrada. Separação rigorosa de funções (trabalhador não pode autoaprovar sua solicitação).
  - **Jornada de Trabalho e Escalas Versionadas (`work_schedules`):** Modelagem desacoplada sem hardcode, com grade horária semanal, intervalos intrajornada, folgas contratuais e tolerâncias legais vinculadas ao colaborador.
  - **Motor de Apuração Analítica (`CalculateDailyJourneyAction`):** Combinação do fato bruto do REP + tratamentos aprovados + escala + diretrizes legais para apurar minutos previstos, trabalhados, ordinários, horas extras, atrasos, saídas antecipadas, intervalos e créditos/débitos para banco de horas.
  - **Tolerância Legal do Art. 58, § 1º da CLT:** Aplicada estritamente na camada de cálculo (até 5 min por batida, com limite de 10 min diários), sem qualquer alteração retroativa do horário registrado no `PunchEvent`.
  - **Banco de Horas em Ledger Imutável (`time_bank_accounts` e `time_bank_transactions`):** Saldo SEMPRE apurado a partir de `SUM(minutes)` de lançamentos auditáveis, jamais sobrescrito como campo numérico mutável.
  - **Configuração da Política de Banco de Horas (`time_bank_policies`):** Painel administrativo em `Admin → Configurações → Banco de Horas`, desativado por padrão e ativado formalmente pelo RH com escolha do modo de fechamento:
    - `CARRY_OVER`: Saldo acumulado transportado integralmente para a próxima competência sem movimentação artificial.
    - `MONTHLY_RESET`: Geração de lançamento compensatório contábil (`monthly_reset = -saldo`) no fechamento formal, zerando o saldo para o próximo mês sem destruir o histórico anterior (inclusive para saldos devedores).
  - **Processo Formal de Fechamento de Competência (`CloseMonthlyPeriodAction`):** Bloqueio estrito de zeramento automático por virada de calendário. O encerramento ocorre exclusivamente por ação formal do RH, congelando a competência em `closed_periods`.
  - **Extrato do Banco de Horas (`/admin/time-bank`):** Painel gerencial com filtros por mês, ano, funcionário e tipo de movimentação, com resumo de créditos, débitos, saldo líquido e modais para ajuste manual e fechamento.
  - **Integração no Espelho de Ponto (`timesheet`):** Card dinâmico de Banco de Horas apresentando saldo anterior, créditos, débitos, ajustes e saldo atual, além de modal direto para solicitação de tratamento pelo trabalhador.
  - **Suíte de Testes Automatizados Expandida:** `68 testes e 280 asserções 100% aprovados`.

## v1.9.0
- **Central de Comprovantes do Trabalhador, Gerador AFD (MTE 2026) & Validação Pública:**
  - **Tabela e Modelo `punch_receipts`:** Vínculo 1:1 rigoroso com `punch_events` via chave estrangeira com proteção de integridade (`restrictOnDelete`), armazenando código de verificação amigável (`PF-XXXX-XXXX-XXXX`), hash SHA-256 e metadados de assinatura.
  - **Central de Comprovantes do Trabalhador:** Painel interativo permanente (`/receipts`) para consulta e visualização de comprovantes de ponto por mês/ano, com busca por código de verificação, detalhes da marcação e download instantâneo.
  - **Motor de Geração de Comprovante em PDF (Sem bibliotecas externas):** Emissão de documento PDF 1.4 binário padronizado contendo dados da empresa empregadora, estabelecimento, trabalhador, CPF, data e horário local, fuso horário, NSR oficial, chave SHA-256 do ponto e link direto para verificação pública.
  - **Identificação Transparente de Desenvolvimento:** Comprovantes marcados expressamente com aviso de ambiente não assinado enquanto pendente certificado ICP-Brasil e registro definitivo no INPI.
  - **Arquitetura de Assinatura Desacoplada (`SigningServiceInterface`):** Interface de domínio limpa permitindo futura assinatura eletrônica PAdES/CAdES com certificado ICP-Brasil sem acoplar bibliotecas criptográficas externas ao core.
  - **Validador Público de Comprovantes (`/receipts/verify`):** Consulta pública por código de verificação que reconstrói e compara em tempo real o hash criptográfico contra o ledger inalterável de ponto, provando autenticidade a qualquer fiscal ou colaborador.
  - **Gerador Oficial de AFD (Portaria 671/2021 — Leiaute MTE 31/07/2026):**
    - Construído exclusivamente sobre os dados brutos inalterados do REP (`punch_events`), em conformidade absoluta com a proibição de uso de dados tratados (`treatment_events`).
    - Registro Tipo 1 (Cabeçalho: 236 posições), Tipo 3 (Marcação REP-P: 101 posições) e Tipo 9 (Trailer: 63 posições com totalizadores e CRC-32).
    - Suporte a filtros por estabelecimento e período temporal com download instantâneo no formato `.txt` formatado com quebras CRLF.
  - **Validador Interno do AFD (`AfdValidator`):** Verificador posicional que checa tipos de registro, tamanhos exatos de linha, monotonicidade cronológica de NSR, formato de datas/horas e consistência de totalizadores.
  - **Golden Tests Automatizados (`AfdGoldenTest`):** Testes com fixture de referência byte-a-byte prevenindo qualquer quebra de conformidade em atualizações futuras.
  - **Suíte de Testes Expandida:** `50 testes e 216 asserções 100% aprovados`.

## v1.8.0
- **Fundação Regulatória REP-P & Estabelecimentos com NSR Atômico (Portaria 671/2021 MTP):**
  - **Empresa Única da Instalação (`companies`):** Entidade de domínio central para a arquitetura de Instância Dedicada (Single-Tenant), armazenando dados cadastrais oficiais e identificação de registro no INPI.
  - **Estabelecimentos com NSR Monotônico Independente (`establishments`):** Matriz e filiais com CNPJ/CNO, endereço, timezone e contador monotônico atômico `nsr_next` protegido com lock pessimista (`lockForUpdate`), sem colisões sob concorrência e sem depender de `MAX(nsr)+1`.
  - **Ledger Imutável de Marcações (`punch_events`):** Registro inalterável com chave primária em ULID, timestamps em UTC e horário local, dados de GPS, fuso horário, hash SHA-256 da carga e encadeamento criptográfico com a marcação anterior (`previous_event_hash`).
  - **Trava Estrita de Imutabilidade:** O modelo `PunchEvent` bloqueia qualquer tentativa de `update()` ou `delete()` com exceção formal sob a legislação trabalhista brasileira.
  - **Serviços DDD de Domínio:** Implementação de `CurrentCompany`, `NsrGeneratorService` e `RecordPunchEventAction`.
  - **Integração na Batida de Ponto (`TimePunch`):** Gravação simultânea no ledger oficial REP-P e exibição imediata do NSR formatado (ex: `NSR #000000001`) no modal de confirmação ao colaborador.
  - **Suíte de Testes Expandida:** Suíte `RepPFoundationTest` adicionando 7 novos testes de concorrência, hash e imutabilidade (`38 testes e 155 asserções 100% aprovados`).

## v1.7.0
- **Cadastro Funcional do Colaborador & Integração da Folha de Ponto:**
  - Adição dos campos funcionais à tabela `employees`: Cargo (`job_title`), Vínculo (`contract_type`), Carga Horária Semanal (`workload`) e Zona (`zone`).
  - Atualização completa do Gerenciador de Funcionários (`employees.blade.php`) com suporte à visualização, criação e edição de colaboradores com listas inteligentes de sugestão.
  - Carregamento automático em tempo real de todos os dados funcionais na Folha de Ponto Oficial A4 (`folha-ponto.blade.php`) diretamente do perfil cadastrado do servidor.
- **Diretrizes e Arquitetura PontoFácil 2.0 (Instância Dedicada / Single-Tenant):**
  - Documentação normativa e de compliance integral em `.agents/skills/pontofacil-compliance/` segundo a Portaria 671/2021 MTP (leiautes MTE 2026), CLT e LGPD.
  - Fixação da regra arquitetural de Instância Dedicada: 1 Empresa cliente = 1 Domínio + 1 Banco de Dados + 1 VPS, expurgando qualquer conceito de multi-tenancy.
  - Especificação do ledger imutável `punch_events` (sem `tenant_id`), sequenciador atômico de NSR por estabelecimento (`nsr_next`), Central de Comprovantes com PAdES e separação estrita REP-P vs PTRP.
- **Suíte de Testes Expandida:**
  - Testes automatizados cobrindo a integridade dos dados cadastrais do servidor e sua injeção na folha de ponto (`31 testes e 128 asserções aprovados`).

## v1.6.0
- **Landing Page Cinematográfica & Página Inicial Oficial:**
  - Implementação da Landing Page moderna do PontoFácil com tipografia Swiss-Modern, tema de cores em tons de índigo e slate, animações cinematográficas de scroll via GSAP (Core + ScrollTrigger com `matchMedia` responsivo) e interatividade com Alpine.js.
  - Mockup de relógio em tempo real com precisão cirúrgica por segundo no fuso oficial de Brasília/Maceió (GMT-3) e simulação de batida instantânea.
  - Demonstração interativa com abas "Visão Colaborador" (com efeito de scanner laser contínuo) e "Visão Gestor/RH" (com métricas consolidadas e tabela auditada em tempo real).
  - A Landing Page agora é a página inicial padrão do sistema (`/`), com links institucionais para a KL Tecnologia (`https://kltecnologia.com`).
- **Relatório Oficial de Folha de Ponto de Funcionário:**
  - Novo módulo de impressão oficial de frequência em formato A4 idêntico ao modelo da Prefeitura Municipal de Teotônio Vilela / Secretaria Municipal de Saúde.
  - Tabela completa de 31 dias dividida em Horário Matutino e Horário Vespertino com demarcação automática de sábados e domingos.
  - Suporte ao modo duplo: preenchimento automático a partir das batidas eletrônicas do sistema ou geração de folha em branco com marcadores (`: `) para preenchimento manual.
  - Metadados customizáveis de Carga Horária, Cargo, Vínculo, Zona e Local/Setor com bloco de assinaturas regulamentares (Servidor, Coordenador, Responsável pelo Setor e Recursos Humanos).
- **Suíte de Testes Automatizados Expandida:**
  - Novos testes automatizados para a Landing Page (`LandingPageTest`) e para o relatório oficial de Folha de Ponto (`FolhaPontoTest`).

## v1.5.0
- **Estrutura Híbrida Inteligente de Setores (Fallback):**
  - Adicionados campos opcionais ao cadastro de cada Setor: QR Code próprio (`qr_code_hash`), Latitude (`latitude`), Longitude (`longitude`) e Raio permitido (`allowed_radius_meters`).
  - **Regra Inteligente com Fallback Automático:**
    - Se o setor tiver localização e/ou QR Code próprios preenchidos, o sistema valida rigorosamente a regra daquele setor/filial.
    - Se o setor deixar os campos em branco, o sistema recorre automaticamente (fallback transparente) ao QR Code e GPS globais da empresa (matriz).
  - **Interface Administrativa de Setores:** Painel com botões para captura direta do GPS do setor, gerador de QR Code exclusivo, visualização e impressão de QR Code por setor e badges informativos na listagem ("QR Setor", "GPS Setor", "Matriz / Padrão Global").
  - **Feedback Geográfico Contextual:** Mensagens de erro informam especificamente se a distância excedeu o limite em relação ao setor do colaborador ou à matriz da empresa.
- **Sistema Global de Modais Popups:**
  - Todas as notificações de alerta, sucesso, informação e erro agora abrem em popups modais elegantes com ícones animados e botões de ação dedicados.
  - Substituição de todas as janelas nativas de confirmação (`confirm()` / `wire:confirm`) por modais elegantes de dupla checagem com destaque visual para ações de exclusão.
- **Redesign do Módulo "Novidades e Versões":**
  - Transformação da exibição em linha do tempo visual contínua (timeline) com nós conectados, destaques VIP para a versão atual, cartões de melhorias estruturados e manual do usuário interativo.
- **Suíte de Testes Automatizados Expandida:** 21 testes e 71 asserções cobrindo cenários de fallback híbrido, regras restritivas por setor, renderização da timeline e notificações de versão.

## v1.4.0
- **Perfil de Gestor:** Novo nível de acesso que permite cadastrar e gerenciar colaboradores exclusivamente nos setores sob sua responsabilidade, com menu e políticas de autorização dedicadas.
- **Configurações da Empresa & QR Code:** Novo módulo administrativo com visualização, geração e impressão do QR Code físico para o estabelecimento, além de calibração das coordenadas GPS e raio permitido.
- **Correção Visual de Modais:** Resolução do efeito de desfoque/camada (backdrop blur) que deixava os modais ilegíveis, garantindo nitidez e legibilidade imediata nas ações de criação e edição.
- **Sistema 100% em PT-BR:** Tradução e localização completas das mensagens de validação com nomes amigáveis para campos, autenticação, paginação e datas/meses em português via Carbon.
- **Fuso Horário Oficial de Maceió (GMT-3):** Configuração do fuso `America/Maceio` para registro de ponto inviolável e relógio digital sincronizado em tempo real.
- **Espelho de Ponto & Cálculo de Horas Trabalhadas:** Totalização automática da jornada considerando múltiplos pares de batidas diárias (entrada, almoço, volta e saída), cálculo de horas trabalhadas por dia, identificador de jornada em andamento e cards de resumo mensal (Total trabalhado, Dias trabalhados, Média diária).
- **Comando de Dados de Teste:** Utilitário `php artisan ponto:test-data` para gerar e limpar (`--clean`) dados fictícios completos para validação rápida em ambiente de desenvolvimento.
- **Auditoria Aprimorada & Testes:** Correção de carregamento de relacionamento do administrador na trilha de auditoria e expansão da suíte de testes automatizados (`15 testes, 42 asserções`).

## v1.3.0
- **Interface Mobile-First:** Design 100% responsivo otimizado para celulares e tablets.
- **Menu Lateral Off-Canvas:** Gaveta deslizante suave com backdrop e botão de menu hambúrguer para dispositivos móveis.
- **Barra de Navegação Inferior (Bottom Tab Bar):** Acesso rápido aos botões Ponto, Espelho e Ajuda na palma da mão.
- **Relógio Digital em Tempo Real:** Visual moderno de relógio de ponto com atualização por segundo no fuso oficial de Brasília.
- **QR Code & GPS Otimizados para Celular:** Scanner com dimensões adaptativas, botões táteis ampliados e feedback instantâneo.
- **Tabelas Administrativas com Scroll Horizontal:** Todas as listagens (Setores, Funcionários, Usuários, Auditoria e Relatórios) adaptadas para telas estreitas sem quebra de layout.
- **Biblioteca QR Code Local:** Empacotamento direto no bundle JS do Vite, eliminando requisições CDN e avisos de Tracking Prevention.

## v1.2.0
- Painel Administrativo concluído com os 5 módulos operacionais.
- Gerenciamento completo de Funcionários e Usuários (com geração automática de credenciais).
- Relatórios Gerenciais com filtros de datas, setor e funcionário.
- Inclusão do campo "Responsável do Setor".

## v1.1.0
- Lançamento inicial do PontoFácil (REP-A).
- Implementação da validação dupla (QR Code + GPS).
- Módulo de Espelho de Ponto (Timesheet) para gestores e funcionários.
- Painel seguro para o RH realizar ajustes manuais.
- Criação e integração da trilha de auditoria para ajustes manuais.

## v0.9.0
- Versão Beta para testes internos.
- Testes iniciais com html5-qrcode.
