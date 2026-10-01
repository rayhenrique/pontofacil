# PontoFácil - Sistema de Controle de Ponto Eletrônico (REP-A)

O **PontoFácil** é uma plataforma corporativa web moderna, segura e antifraude para controle eletrônico de ponto alternativo, integralmente aderente às diretrizes da **Portaria 671 do Ministério do Trabalho e Emprego (REP-A)**.

O sistema elimina fraudes na marcação de ponto por meio de uma **validação dupla rigorosa**: leitura de **QR Code físico corporativo** no local de trabalho combinada à **geolocalização (GPS)** do dispositivo móvel do colaborador, com carimbo de tempo inviolável cravado pelo servidor no **fuso horário oficial de Maceió (GMT-3)**.

---

## 🚀 Principais Módulos e Funcionalidades

### 1. Batida de Ponto Inteligente com Regra Híbrida (Smart Punch)
- **Leitura Ótica Rápida:** Scanner integrado no navegador (`html5-qrcode` empacotado localmente no bundle Vite), sem dependência de conexões externas.
- **Validação Geográfica Antifraude:** Captura automática das coordenadas GPS (`navigator.geolocation`) e cálculo instantâneo da distância em relação ao local permitido pela **Fórmula de Haversine**.
- **Estrutura Híbrida Inteligente (Fallback por Setor):**
  - Cada setor/filial pode opcionalmente definir seu **próprio QR Code**, **Latitude**, **Longitude** e **Raio permitido**.
  - **Fallback Automático:** Caso o setor deixe esses campos vazios, o sistema recorre de forma transparente ao QR Code e GPS globais da matriz corporativa.
  - Feedback de erro contextual indicando se a tolerância excedida refere-se ao setor do colaborador ou à sede da empresa.
- **Carimbo de Tempo Server-Side:** O relógio do dispositivo do cliente é desconsiderado; o horário gravado é unicamente o do servidor oficial, prevenindo qualquer tentativa de adulteração de hora.
- **Relógio Digital em Tempo Real:** Interface visual moderna com relógio digital atualizado por segundo, sincronizado com o horário de Maceió (GMT-3).

### 2. Espelho de Ponto & Totalização de Horas (Timesheet)
- **Cálculo Diário de Horas Trabalhadas:** Totalização automática da jornada considerando múltiplos pares de batidas (entrada, saída para almoço, retorno e saída final).
- **Indicador de Jornada em Andamento:** Identificação visual em tempo real quando o colaborador realizou uma entrada ímpar no dia.
- **Cards de Resumo Mensal:** Métricas consolidadas de **Horas Trabalhadas no Mês**, **Dias Trabalhados** e **Média Diária**.
- **Filtros e Visualização Segmentada:** Filtros por mês e ano. Gestores visualizam colaboradores da sua equipe e Administradores possuem acesso irrestrito.

### 3. Sistema Global de Popups Modais (Feedback & Confirmações)
- **Notificações Visuais:** Todas as mensagens de alerta, sucesso, informação e erro abrem em popups modais elegantes com ícones animados e estilo mobile-first.
- **Diálogos de Confirmação:** Substituição de caixas nativas do navegador (`window.confirm()`) por modais elegantes de dupla checagem com destaque visual para ações de exclusão.

### 4. Perfis de Acesso & Gestão de Equipes (Roles)
- **Administrador (RH):** Acesso completo a todos os módulos, relatórios gerenciais consolidados, auditoria, configurações globais e emissão de QR Code.
- **Gestor de Setor:** Acesso ao módulo *"Gestão de Equipe"*, permitindo cadastrar, editar e acompanhar os colaboradores exclusivamente vinculados aos setores sob sua responsabilidade (`manager_id`).
- **Colaborador:** Acesso direto à tela de batida de ponto e consulta ao histórico pessoal mensal.

### 5. Configurações da Empresa & Gerador de QR Code
- Painel exclusivo para o Administrador visualizar o QR Code criptográfico oficial da empresa.
- **Ferramenta de Impressão:** Geração de folha padrão de impressão para fixação do QR Code na entrada do estabelecimento ou em cada setor.
- **Rotação de Chave Criptográfica:** Capacidade de regenerar o hash do QR Code com 1 clique caso o código físico seja comprometido.
- **Calibração de GPS:** Definição da latitude, longitude e raio permitido (em metros) com botão de **"Capturar Minha Posição Atual"** via GPS.

### 6. Ajustes Manuais & Trilha de Auditoria Imutável
- Interface para o RH lançar batidas esquecidas com justificativa textual obrigatória.
- Todas as alterações manuais são registradas permanentemente na tabela de auditoria (`time_adjustments`), registrando quem fez a alteração, quando foi feita, horários envolvidos e a motivação legal.

### 7. Relatórios Gerenciais
- Filtragem flexível de registros por intervalo de datas, setor e colaborador.
- Totalizadores de registros e distinção clara entre batidas automáticas (QR Code + GPS) e manuais (RH).

### 8. Central de Ajuda, Linha do Tempo de Versões & Login Moderno
- **Linha do Tempo Visual (Changelog Timeline):** Exibição interativa e categorizada do histórico de versões em formato de timeline conectada, destacando a versão ativa (`v1.5.0`).
- **Manual do Usuário Interativo:** Guia em cards temáticos para colaboradores, gestores e RH.
- **Tela de Login Institucional:** Visual modernizado com link oficial da [KL Tecnologia](https://kltecnologia.com), alternador de visualização de senha e credenciais seguras.
- **Utilitário de Teste:** Comando `php artisan ponto:test-data` (com suporte a `--clean`) para geração de dados fictícios completos para validação em desenvolvimento.

---

## 🛠️ Stack Tecnológica

- **Backend:** [PHP 8.3+](https://www.php.net) / [Laravel 13](https://laravel.com)
- **Componentização:** [Livewire 4](https://livewire.laravel.com) (Arquitetura *View-Based Components*)
- **Frontend & Reatividade:** [Alpine.js](https://alpinejs.dev) & [Tailwind CSS v4](https://tailwindcss.com)
- **Leitura & Geração de QR Code:** `html5-qrcode` e `qrcode` (compilados localmente no bundle Vite)
- **Banco de Dados:** MySQL 8+ (InnoDB, `utf8mb4_unicode_ci`)
- **Fuso Horário Oficial:** `America/Maceio` (GMT-3)
- **Idioma Padrão:** Português do Brasil (`pt_BR`)

---

## 📦 Estrutura de Diretórios Relevantes

```
pontofacil/
├── app/
│   ├── Console/Commands/SeedPontoTestData.php  # Comando utilitário de teste
│   ├── Enums/UserRole.php                     # Roles: Admin, Manager, Employee
│   ├── Models/                                # User, Employee, Sector, TimeEntry, TimeAdjustment, SystemSetting
│   └── Policies/UserPolicy.php                # Políticas de autorização
├── config/
│   └── app.php                                # Configuração de locale pt_BR e timezone America/Maceio
├── database/migrations/                       # Migrações (incluindo estrutura híbrida de setores)
├── lang/
│   └── pt_BR/                                 # Tradução completa (validation, auth, passwords, pagination)
├── ref/                                       # Documentação técnica de arquitetura (PRD, SCHEMA, TASKS)
├── resources/
│   ├── js/app.js                              # Integração do scanner e gerador de QR Code
│   └── views/
│       ├── components/                        # Componentes Livewire View-Based
│       │   ├── admin/                         # Módulos: employees, sectors, users, audit, reports, settings
│       │   ├── auth/login.blade.php           # Tela de login institucional
│       │   ├── help.blade.php                 # Central de ajuda e timeline de versões
│       │   ├── modal-feedback.blade.php       # Sistema global de popups e confirmações
│       │   ├── time-punch.blade.php           # Registro de ponto (QR + GPS híbrido)
│       │   ├── timesheet.blade.php            # Espelho de ponto com cálculo de horas
│       │   └── version-notifier.blade.php     # Modal notificador de nova versão
│       └── layouts/app.blade.php              # Layout base responsivo (Sidebar + Drawer + Tab Bar + Modais)
├── routes/web.php                             # Rotas declarativas do sistema
├── tests/Feature/                             # Suíte de testes automatizados PHPUnit (22 testes, 75 asserções)
└── versoes.md                                 # Histórico detalhado de versões do sistema
```

---

## ⚙️ Instalação e Execução Local

### Pré-requisitos
- PHP 8.2 ou superior com extensões `pdo_mysql`, `mbstring`, `openssl`.
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
   Acesse no navegador: `http://localhost:8000`

---

## 🧪 Testes Automatizados

Para rodar a suíte completa de testes automatizados cobrindo autorização, cálculo de jornada, isolamento de setores, estrutura híbrida e camadas modais:

```bash
php artisan test
```

> **Status da Suíte:** `22 testes, 75 asserções — 100% aprovados.`

---

## 🚀 Guia de Deploy em Produção

Para instruções completas de deploy em servidores VPS com **CloudPanel** e **Nginx** utilizando certificados SSL (**HTTPS** obrigatório para câmera e geolocalização), consulte o arquivo:
- [Guia de Deploy Passo a Passo (deploy.md)](file:///c:/Users/rayhe/Downloads/pontofacil/deploy.md)

---

## 🔒 Licença e Direitos Autorais

**ATENÇÃO: Este software NÃO É Open Source (Código Aberto).**

Todos os direitos são reservados. A cópia, distribuição, modificação ou uso comercial deste código-fonte sem autorização prévia e expressa são estritamente proibidos.

Desenvolvido por **[KL Tecnologia](https://kltecnologia.com)**.
