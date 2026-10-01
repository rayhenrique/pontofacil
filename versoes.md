# Histórico de Versões (Changelog)

## v1.5.0 (Atual)
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
