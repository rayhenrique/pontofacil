# Histórico de Versões (Changelog)

## v1.9.0 (Atual)
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
