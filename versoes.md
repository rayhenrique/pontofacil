# Histórico de Versões (Changelog)

## v2.5.0 (Atual)
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
  - **Reorganização Estrutural da Landing Page (Mobile-First & Progressão Didática):**
    - **Sequência em 8 Seções:** 1. Hero (`#hero`), 2. Como funciona (`#como-funciona`), 3. Para o colaborador / Para a empresa (`#para-empresas`), 4. Principais recursos (`#recursos`), 5. Segurança e rastreabilidade (`#seguranca`), 6. Portaria 671 / REP-P + PTRP (`#portaria-671`), 7. Demonstração da interface (`#demonstracao`) e 8. CTA final (`#contato`).
    - **Didática de Apresentação:** Explicar primeiro o produto (Hero + Como funciona), depois os benefícios para ambas as partes (Colaborador vs Empresa + Recursos centrais) e somente depois os detalhes técnicos e fiscais (Segurança + Portaria 671).
    - **Compactação e Otimização Mobile:** Espaçamentos verticais calibrados para dispositivos móveis (`py-10 sm:py-14 md:py-20`), eliminando áreas vazias excessivas e melhorando a progressão visual em telas pequenas (320px a 430px).
    - **Nova Seção 'Como funciona' em 4 Etapas:** Fluxo end-to-end simples e didático (1. Colaborador registra o ponto, 2. O sistema gera o registro e o comprovante, 3. Gestores acompanham jornadas e ocorrências, 4. RH trata ajustes, banco de horas e fechamento) com cards verticais mobile-first, 4 colunas no desktop, ícones limpos e linguagem acessível sem diagramas complexos ou afirmações jurídicas absolutas.
    - **Seção 'Colaborador x Empresa' (Para quem registra vs Para quem gerencia):** Divisão mobile-first com seletor de tabs acessível em telas menores (< 1024px) e duas colunas amplas no desktop, eliminando colunas estreitas no mobile. Destaca os 5 recursos centrais do colaborador (registrar ponto, visualizar histórico, receber comprovantes, consultar espelho, solicitar ajustes) e os 6 pilares de gestão da empresa (acompanhar jornadas, tratar ocorrências, banco de horas, fechamento, relatórios, auditoria).
    - **Micro-animações GSAP:** Integração com ScrollTrigger para os novos blocos com staggers suaves em desktop e carregamento leve em mobile.
    - **Preservação Arquitetural & Remoção da Timeline Comercial:** A timeline técnica de versões foi removida da landing comercial pública por se tratar de informação técnica interna. O histórico completo segue rigorosamente preservado neste documento (`versoes.md`), na documentação e no modal interno do sistema (`components/help.blade.php`). Foram eliminados links (`#evolucao`), IDs e animações GSAP residuais da landing, mantendo a página enxuta e focada na proposta de valor.
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
