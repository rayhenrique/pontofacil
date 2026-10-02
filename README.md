# PontoFácil 2.0 — Plataforma Web de Controle de Ponto Eletrônico (REP-P / PTRP)

O **PontoFácil 2.0** é uma plataforma corporativa web de alta integridade, rastreabilidade e segurança para registro e tratamento eletrônico de ponto, em plena conformidade com as diretrizes da **Portaria MTP nº 671/2021** (leiautes oficiais MTE 2026), **CLT** e **LGPD**.

Operando nativamente no modelo **REP-P (Registrador Eletrônico de Ponto via Programa)** com módulo **PTRP (Programa de Tratamento de Registro de Ponto)** integrado, o sistema também oferece suporte à modalidade **REP-A (Registrador Alternativo)** quando respaldado por Acordo ou Convenção Coletiva de Trabalho (ACT/CCT).

O sistema elimina fraudes na marcação de ponto por meio de uma **validação dupla rigorosa**: leitura de **QR Code físico corporativo** (com suporte a fallback híbrido matriz/setor) combinada à **geolocalização (GPS)** do dispositivo móvel do colaborador, com carimbo de tempo inviolável cravado pelo servidor no **fuso horário oficial de Maceió (GMT-3)**.

---

## 🏛️ Arquitetura de Instância Dedicada (Single-Tenant)

O PontoFácil adota uma rigorosa filosofia de **Instância Dedicada**:
- **Zero Multi-Tenancy:** Cada empresa cliente possui sua instalação exclusiva em servidor/VPS próprio, com banco de dados dedicado, domínio ou subdomínio exclusivo, arquivos isolados e configuração `.env` independente.
- **Privacidade e LGPD:** Elimina qualquer risco de vazamento cruzado de dados entre empresas, conferindo soberania total ao cliente sobre sua base trabalhista.
- **Hierarquia de Estabelecimentos:** Uma instalação representa a `Company` (Empresa Única), podendo agregar múltiplos `Establishments` (Matriz e Filiais com CNPJ/CNO distintos e contadores independentes de NSR).

---

## 🚀 Principais Módulos e Funcionalidades (v2.0.0)

### 1. Motor de Tratamento PTRP, Jornadas, Tolerância Legal & Banco de Horas (Fase 20)
- **Tratamento de Ponto sem Alterar Fatos Brutos (`treatment_events`):** Registro inalterável em ULID para inclusão manual de batidas esquecidas, desconsideração de marcações indevidas e justificativas de faltas/atestados, mantendo a tabela `punch_events` estritamente imutável.
- **Workflow de Solicitações & Aprovação Segregada:** Colaboradores solicitam correções pelo espelho de ponto; administradores e gestores analisam em painel dedicado (`/admin/treatment-requests`) com justificativa formal e auditoria.
- **Jornada de Trabalho e Escalas Flexíveis (`work_schedules`):** Configuração desacoplada de escalas de trabalho com suporte a horários por dia da semana, intervalo intrajornada e folgas contratuais vinculadas ao colaborador.
- **Motor de Apuração Analítica (`CalculateDailyJourneyAction`):** Combina dados brutos do REP, tratamentos aprovados, escala e regras legais, apurando horas ordinárias, extras, atrasos, faltas, saídas antecipadas e créditos/débitos.
- **Tolerância Legal Conforme Art. 58, § 1º da CLT:** Limite de até 5 minutos por batida e até 10 minutos diários aplicado estritamente na apuração matemática, sem adulterar o horário original registrado.
- **Banco de Horas em Ledger Imutável (`time_bank_accounts` e `time_bank_transactions`):** O saldo é sempre o resultado de `SUM(minutes)` — nunca um valor sobrescrito.
- **Política Configurável pelo RH (`time_bank_policies`):** Desativado por padrão no painel `Admin → Configurações → Banco de Horas`. Quando ativado, o gestor escolhe:
  - `CARRY_OVER`: Saldo acumulado é transportado diretamente para a competência seguinte.
  - `MONTHLY_RESET`: Geração de transação de compensação contábil (`monthly_reset = -saldo`) no fechamento formal, zerando o saldo sem apagar o histórico (inclusive para saldo negativo/devedor).
- **Fechamento Formal de Competência (`CloseMonthlyPeriodAction`):** Bloqueio de zeramentos automáticos por mera virada de calendário à meia-noite; encerramento e congelamento executados formalmente pelo RH em `closed_periods`.
- **Extrato do Banco de Horas (`/admin/time-bank`):** Filtros detalhados por colaborador, competência e tipo de movimentação, saldo acumulado em tempo real, modal de ajuste manual e fechamento mensal.
- **Espelho com Banco de Horas Integrado:** Apresentação clara ao trabalhador de saldo anterior, créditos, débitos, ajustes e saldo atual no espelho de ponto.

### 2. Central de Comprovantes do Trabalhador & Validação Pública (Fase 19A)
- **Central de Comprovantes (`/receipts`):** Interface permanente de autoatendimento para o trabalhador consultar, visualizar e baixar comprovantes de todas as suas marcações de ponto históricas.
- **Emissão de Comprovante em PDF Padronizado:** Emissão sem dependência de bibliotecas externas pesadas, incluindo: Dados da Empresa, Estabelecimento, Nome do Trabalhador, CPF, Data e Horário no fuso oficial, NSR oficial, chave SHA-256 e código de verificação amigável (`PF-XXXX-XXXX-XXXX`).
- **Página Pública de Verificação (`/receipts/verify`):** Consulta pública onde qualquer auditor ou trabalhador pode digitar o código ou apontar para a URL de verificação para checar a autenticidade e o hash contra o ledger inviolável.
- **Gerador Oficial do AFD (Portaria 671/2021 — Leiaute MTE 31/07/2026):**
  - Construído exclusivamente a partir dos registros brutos do REP (`punch_events`), nunca de dados tratados.
  - Registro Tipo 1 (Cabeçalho 236 posições), Tipo 3 (Marcação REP-P 101 posições) e Tipo 9 (Trailer 63 posições com totalizadores e CRC-32).
  - Validador posicional interno (`AfdValidator`) e Golden Tests byte-a-byte (`AfdGoldenTest`).

### 2. Batida de Ponto Inteligente com Regra Híbrida (Smart Punch)
- **Leitura Ótica Rápida:** Scanner integrado no navegador (`html5-qrcode` empacotado localmente no bundle Vite), sem dependência de conexões ou CDNs externas.
- **Validação Geográfica Antifraude:** Captura automática das coordenadas GPS (`navigator.geolocation`) e cálculo instantâneo da distância em relação ao local permitido pela **Fórmula de Haversine**.
- **Estrutura Híbrida Inteligente (Fallback por Setor):**
  - Cada setor/filial pode opcionalmente definir seu **próprio QR Code**, **Latitude**, **Longitude** e **Raio permitido**.
  - **Fallback Automático:** Caso o setor deixe esses campos vazios, o sistema recorre de forma transparente ao QR Code e GPS globais da matriz corporativa.
  - Feedback de erro contextual indicando se a tolerância excedida refere-se ao setor do colaborador ou à sede da empresa.
- **Carimbo de Tempo Server-Side:** O relógio do dispositivo do cliente é desconsiderado; o horário gravado é unicamente o do servidor oficial, prevenindo qualquer tentativa de adulteração de hora.
- **Relógio Digital em Tempo Real:** Interface visual moderna com relógio digital atualizado por segundo, sincronizado com o horário de Maceió (GMT-3).

### 2. Cadastro Funcional Completo do Servidor
- **Ficha Cadastral Trabalhista:** Cadastro e edição de colaboradores vinculados à conta de usuário com informações contratuais essenciais:
  - **Cargo / Função:** Seleção ágil com sugestões das funções mais comuns ou digitação personalizada.
  - **Vínculo Empregatício:** Efetivo / Concursado, Contrato Temporário, Comissionado, Estagiário, CLT Integral, etc.
  - **Carga Horária Semanal:** 40h semanais, 30h semanais, 20h semanais, 12x36 ou 44h semanais.
  - **Zona de Lotação:** Urbana ou Rural.
  - **Setor / Departamento:** Vínculo direto ao setor de trabalho.
  - **Documentação:** CPF validado, telefone de contato e matrícula interna.
- **Interface Modal Responsiva:** Criação e edição rápida via modal popup com validação server-side em tempo real.

### 3. Folha de Ponto A4 Oficial (Modelo Outubro)
- **Espelho Fiel ao Modelo Administrativo do RH:** Relatório padronizado idêntico ao modelo oficial de folha de ponto mensal (`/folha-ponto`).
- **Preenchimento Automático do Cabeçalho:** Ao selecionar o colaborador, o sistema carrega instantaneamente: Nome do Servidor, Cargo, Vínculo, Carga Horária, Zona, Lotação/Setor e Mês de Referência.
- **Grade Mensal de 31 Dias:** Tabela com todas as datas do mês contendo pares de batidas (Entrada/Saída da Manhã e Entrada/Saída da Tarde) e coluna de Ocorrências / Observações (Faltas, Atestados, Folgas, Feriados).
- **Campos Oficiais de Assinatura:** Linhas de assinatura formal para o Servidor e para a Chefia Imediata com indicação do local e data.
- **Estilização para Impressão Perfeita (`@media print`):** Oculta barras de navegação, botões de ação e ajusta fontes, bordas e margens para encaixe perfeito em 1 página A4 sem quebras de layout.

### 4. Espelho de Ponto & Totalização de Horas (Timesheet)
- **Cálculo Diário de Horas Trabalhadas:** Totalização automática da jornada considerando múltiplos pares de batidas (entrada, saída para almoço, retorno e saída final).
- **Indicador de Jornada em Andamento:** Identificação visual em tempo real quando o colaborador realizou uma entrada ímpar no dia.
- **Cards de Resumo Mensal:** Métricas consolidadas de **Horas Trabalhadas no Mês**, **Dias Trabalhados** e **Média Diária**.
- **Filtros e Visualização Segmentada:** Filtros por mês e ano. Gestores visualizam colaboradores da sua equipe e Administradores possuem acesso irrestrito.

### 5. Landing Page Cinematográfica & Apresentação Institucional (`/landing`)
- **Design Suíço Moderno (Swiss-Modern SaaS):** Interface cinematográfica estilizada com Tailwind CSS v4, tipografia limpa, glassmorphism suave e contrastes sóbrios.
- **Animações de Scroll GSAP & ScrollTrigger:** Transições fluidas em stagger, cards de revelação progressiva e mockup interativo de relógio ao vivo no Hero.
- **Tabs Interativas (Alpine.js):** Demonstração da "Visão Colaborador" (com animação de laser scanner simulando leitura de QR Code) e "Visão Gestor / RH".
- **Timeline Visual de Versões:** Apresentação da evolução contínua da plataforma, destacando a versão ativa (`v1.7.0`).
- **Identidade Institucional:** Rodapé com créditos oficiais e link direto para a [KL Tecnologia](https://kltecnologia.com).

### 6. Sistema Global de Popups Modais (Feedback & Confirmações)
- **Notificações Visuais:** Todas as mensagens de alerta, sucesso, informação e erro abrem em popups modais elegantes com ícones animados e estilo mobile-first.
- **Diálogos de Confirmação:** Substituição de caixas nativas do navegador (`window.confirm()`) por modais de dupla checagem com destaque visual para ações de exclusão.

### 7. Perfis de Acesso & Gestão de Equipes (RBAC)
- **Administrador / RH (`admin`):** Acesso irrestrito a todos os módulos, relatórios gerenciais consolidados, auditoria, configurações globais, gestão funcional e emissão de QR Code.
- **Gestor de Setor (`manager`):** Módulo *"Gestão de Equipe"*, permitindo cadastrar, editar e acompanhar os colaboradores exclusivamente vinculados aos setores sob sua responsabilidade (`manager_id`).
- **Colaborador (`employee`):** Acesso direto à tela de batida de ponto e consulta ao histórico pessoal mensal.
- **Auditor Fiscal do Trabalho (`auditor` - planejado PontoFácil 2.0):** Consulta dedicada para auditoria fiscal e download de arquivos fiscais.

### 8. Configurações da Empresa & Gerador de QR Code
- Painel exclusivo para o Administrador visualizar o QR Code criptográfico oficial da empresa.
- **Ferramenta de Impressão:** Geração de folha padrão de impressão para fixação do QR Code na entrada do estabelecimento ou em cada setor.
- **Rotação de Chave Criptográfica:** Capacidade de regenerar o hash do QR Code com 1 clique caso o código físico seja comprometido.
- **Calibração de GPS:** Definição da latitude, longitude e raio permitido (em metros) com botão de **"Capturar Minha Posição Atual"** via GPS.

### 9. Ajustes Manuais & Trilha de Auditoria Imutável
- Interface para o RH lançar batidas esquecidas com justificativa textual obrigatória.
- Todas as alterações manuais são registradas permanentemente na tabela de auditoria (`time_adjustments`), registrando quem fez a alteração, quando foi feita, horários envolvidos e a motivação legal.

### 10. Central de Ajuda, Linha do Tempo de Versões & Login Moderno
- **Linha do Tempo Visual (Changelog Timeline):** Exibição interativa e categorizada do histórico de versões em formato de timeline conectada, destacando a versão ativa (`v1.7.0`).
- **Manual do Usuário Interativo:** Guia em cards temáticos para colaboradores, gestores e RH.
- **Tela de Login Institucional:** Visual modernizado com link oficial da [KL Tecnologia](https://kltecnologia.com), alternador de visualização de senha e credenciais seguras.
- **Utilitário de Teste:** Comando `php artisan ponto:test-data` (com suporte a `--clean`) para geração de dados fictícios completos para validação em desenvolvimento.

---

## 🛠️ Stack Tecnológica

- **Backend:** [PHP 8.3+](https://www.php.net) / [Laravel 13](https://laravel.com)
- **Componentização Reativa:** [Livewire 4](https://livewire.laravel.com) (Arquitetura *View-Based Components*)
- **Frontend & Reatividade:** [Alpine.js](https://alpinejs.dev) & [Tailwind CSS v4](https://tailwindcss.com)
- **Animações de UI & Scroll:** [GSAP 3.12](https://gsap.com) com ScrollTrigger
- **Leitura & Geração de QR Code:** `html5-qrcode` e `qrcode` (compilados localmente no bundle Vite)
- **Banco de Dados:** MySQL 8+ (InnoDB, `utf8mb4_unicode_ci`)
- **Fuso Horário Oficial:** `America/Maceio` (GMT-3)
- **Idioma Padrão:** Português do Brasil (`pt_BR`)

---

## 📦 Estrutura de Diretórios do Projeto

```
pontofacil/
├── .agents/
│   └── skills/
│       └── pontofacil-compliance/             # Diretrizes de compliance Portaria 671 MTP / REP-P / PTRP
│           ├── SKILL.md                       # Especificação do skill de conformidade single-tenant
│           └── references/                    # Decomposição em 9 módulos técnicos (PRD, REP-P, AFD, AEJ, etc.)
├── app/
│   ├── Console/Commands/SeedPontoTestData.php # Gerador de dados fictícios de demonstração
│   ├── Enums/UserRole.php                    # Roles tipadas: Admin, Manager, Employee
│   ├── Models/                               # User, Employee, Sector, TimeEntry, TimeAdjustment, SystemSetting
│   └── Policies/UserPolicy.php               # Políticas granulares de autorização
├── config/
│   └── app.php                               # Configurações de timezone America/Maceio e locale pt_BR
├── database/migrations/                      # Migrações relacionais (incluindo cadastro funcional e setores híbridos)
├── lang/
│   └── pt_BR/                                # Tradução e mensagens amigáveis em português
├── ref/                                      # Documentação arquitetural de referência técnica
├── resources/
│   ├── js/app.js                             # Bundle Vite com scanner QR Code e utilitários
│   └── views/
│       ├── components/                       # Componentes Livewire View-Based
│       │   ├── admin/                        # Módulos: employees (cadastro funcional), sectors, users, audit, reports, settings
│       │   ├── auth/login.blade.php          # Tela de login institucional com link KL Tecnologia
│       │   ├── folha-ponto.blade.php         # Folha de Ponto A4 Oficial (Modelo Outubro)
│       │   ├── help.blade.php                # Central de ajuda e timeline de versões
│       │   ├── landing/                      # Componentes modulares da Landing Page Cinematográfica
│       │   ├── modal-feedback.blade.php      # Sistema global de modais e confirmações
│       │   ├── time-punch.blade.php          # Registro de ponto inteligente (QR + GPS híbrido)
│       │   ├── timesheet.blade.php           # Espelho de ponto com cálculo de horas
│       │   └── version-notifier.blade.php    # Modal notificador de nova versão (v1.7.0)
│       ├── layouts/
│       │   ├── app.blade.php                 # Layout base da aplicação interna (Sidebar + Drawer + Tab Bar + Modais)
│       │   └── landing.blade.php             # Layout base da Landing Page (GSAP, fontes e Tailwind v4)
│       └── landing.blade.php                 # View principal da Landing Page (/landing)
├── routes/web.php                            # Rotas web declarativas
├── tests/Feature/                            # Suíte de testes automatizados PHPUnit (50 testes, 216 asserções)
└── versoes.md                                # Histórico detalhado de releases (v1.0.0 a v1.9.0)
```

---

## ⚙️ Instalação e Execução Local

### Pré-requisitos
- PHP 8.2 ou superior com extensões `pdo_mysql`, `mbstring`, `openssl`, `bcmath`.
- Composer 2.x
- Node.js 18+ e NPM

### Passo a Passo

1. **Clonar o Repositório:**
   ```bash
   git clone https://github.com/rayhenrique/pontofacil.git
   cd pontofacil
   ```

2. **Instalar Dependências:**
   ```bash
   composer install
   npm install
   ```

3. **Configurar o Ambiente (.env):**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Certifique-se de configurar as credenciais do seu banco MySQL no `.env`:*
   ```env
   APP_TIMEZONE=America/Maceio
   APP_LOCALE=pt_BR
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pontofacil
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Executar Migrações e Seeders:**
   ```bash
   php artisan migrate --seed
   ```
   *O seeder criará o usuário administrador padrão (`admin@pontofacil.local` / senha `admin123`) e inicializará os parâmetros de QR Code e coordenadas da empresa.*

5. **Gerar Dados Fictícios de Demonstração (Opcional):**
   ```bash
   php artisan ponto:test-data
   # Para limpar dados de teste: php artisan ponto:test-data --clean
   ```

6. **Compilar os Assets e Iniciar o Servidor:**
   ```bash
   npm run build
   php artisan serve
   ```
   - Página Inicial / Landing Page: `http://localhost:8000/landing` ou `http://localhost:8000`
   - Acesso ao Sistema: `http://localhost:8000/login`
   - Folha de Ponto A4 Oficial: `http://localhost:8000/folha-ponto`

---

## 🧪 Testes Automatizados

Para rodar a suíte completa de testes automatizados cobrindo autorização, cálculo de jornada, isolamento de setores, cadastro funcional, folha de ponto e camadas modais:

```bash
php artisan test
```

> **Status da Suíte:** `74 testes, 314 asserções — 100% aprovados.`

---

## 🚀 Guia de Deploy em Produção (Instância Dedicada)

Para instruções completas de deploy em servidores VPS com **CloudPanel** e **Nginx** utilizando certificados SSL (**HTTPS** obrigatório para câmera e geolocalização), consulte o arquivo:
- [Guia de Deploy Passo a Passo (deploy.md)](file:///c:/Users/rayhe/Downloads/pontofacil/deploy.md)

---

## 🔒 Licença e Direitos Autorais

**ATENÇÃO: Este software NÃO É Open Source (Código Aberto).**

Todos os direitos são reservados. A cópia, distribuição, modificação ou uso comercial deste código-fonte sem autorização prévia e expressa são estritamente proibidos.

Desenvolvido por **[KL Tecnologia](https://kltecnologia.com)**.
