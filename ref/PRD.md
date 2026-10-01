# PRD.md (Product Requirements Document - Atualizado)

## 1. Visão Geral
O **PontoFácil** é um sistema corporativo single-tenant de controle de jornada de trabalho eletrônico alternativo aderente às diretrizes da **Portaria 671 (REP-A)** do Ministério do Trabalho e Emprego. O objetivo primordial é garantir a inviolabilidade dos registros de ponto por meio de **validação dupla antifraude**: leitura ótica de um QR Code físico fixado na sede da empresa aliada à conferência do raio de geolocalização (GPS) emitido pelo dispositivo móvel do colaborador, com carimbo de tempo determinado exclusivamente pelo servidor oficial em fuso horário de Maceió (GMT-3).

---

## 2. Perfis de Acesso (Roles)
- **Administrador / RH (`admin`):**
  - Acesso irrestrito a todos os módulos do sistema.
  - Cadastro e manutenção de Setores, Colaboradores e Usuários com definição de credenciais.
  - Gestão e impressão do QR Code físico oficial da empresa e calibração das coordenadas/raio de GPS.
  - Acesso ao Espelho de Ponto geral e emissão de Relatórios Gerenciais consolidados.
  - Realização de Ajustes Manuais retroativos com justificativa obrigatória e consulta à Trilha de Auditoria imutável.
- **Gestor de Setor (`manager`):**
  - Acesso à tela de registro de ponto próprio e espelho de jornada.
  - Módulo **"Gestão de Equipe"**: Cadastro, visualização e manutenção exclusiva dos colaboradores vinculados aos setores sob sua responsabilidade (`manager_id`).
  - Visualização do Espelho de Ponto dos colaboradores de seus respectivos setores.
- **Colaborador (`employee`):**
  - Acesso ao registro de ponto diário via câmera e localização.
  - Consulta ao seu próprio histórico mensal no Espelho de Ponto (Timesheet).
  - Consulta à Central de Ajuda e Manual de Uso.

---

## 3. Requisitos Funcionais (Core Features)

- **RF01 - Autenticação & Sessões:**
  - Login seguro com e-mail corporativo e senha criptografada.
  - Proteção por Policies do Laravel (`UserPolicy`) e Enums tipados (`UserRole`).
- **RF02 - Registro de Ponto (Smart Punch):**
  - Leitura de QR Code através de scanner nativo (`html5-qrcode` empacotado localmente, sem CDNs externas).
  - Captura mandatória da geolocalização via `navigator.geolocation` do navegador.
  - Validação da distância em relação à empresa através da Fórmula de Haversine; recusa do ponto caso o colaborador esteja fora do raio autorizado.
- **RF03 - Validação e Carimbo de Tempo Server-Side:**
  - O horário da batida é gravado exclusivamente pelo servidor em fuso horário oficial `America/Maceio` (GMT-3), impossibilitando fraude por adulteração do relógio do cliente.
- **RF04 - Espelho de Ponto (Timesheet):**
  - Visualização mensal detalhada com agrupamento automático por dia e indicadores de Entrada/Saída.
  - Colaboradores visualizam o próprio espelho; Gestores filtram membros de sua equipe; Administradores filtram qualquer funcionário.
- **RF05 - Ajuste Manual de Ponto:**
  - Lançamento de batidas retroativas pelo RH com justificativa obrigatória.
- **RF06 - Trilha de Auditoria Imutável:**
  - Registro de auditoria (`time_adjustments`) contendo administrador responsável, data/hora do ajuste, horários envolvidos e justificativa.
- **RF07 - Gestão de Setores:**
  - Criação de departamentos e atribuição de Gestores responsáveis aos setores.
- **RF08 - Gestão de Funcionários:**
  - Cadastro de colaboradores contendo Nome, E-mail, Setor, CPF e Telefone, com criação simultânea da conta de usuário.
- **RF09 - Relatórios Gerenciais:**
  - Filtros combinados por intervalo de datas, setor e colaborador, exibindo quantidade de registros e origem da batida.
- **RF10 - Configurações da Empresa & QR Code:**
  - Exibição e geração do QR Code criptográfico oficial na tela do Admin, com botão de impressão em folha A4 para fixação no estabelecimento.
  - Rotação segura do QR Code caso ocorra vazamento da chave física.
  - Configuração das coordenadas GPS da sede e raio de tolerância (com botão de auto-captura GPS).
- **RF11 - Central de Ajuda e Notificador de Versões:**
  - Exibição de modal automático para os usuários contendo as novidades de cada nova versão lançada (`versoes.md`).

---

## 4. Requisitos Não-Funcionais
- **Segurança & Criptografia:** Exigência de ambiente seguro HTTPS em produção para permissão de câmera e geolocalização.
- **Responsividade Mobile-First:** Navegação otimizada para smartphones (Off-Canvas Drawer e Bottom Navigation Bar).
- **Localização:** Sistema 100% em Português do Brasil (PT-BR) e fuso horário oficial `America/Maceio` (GMT-3).
- **Performance & Isolamento:** Biblioteca QR Code e scripts empacotados pelo Vite sem requisições CDN de terceiros.