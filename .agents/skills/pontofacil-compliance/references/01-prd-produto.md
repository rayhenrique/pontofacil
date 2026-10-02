# 01 - PRD: Visão de Produto, Instância Dedicada & Modelo de Dados

---

## 1. Regra de Arquitetura Fundamental: Instância Dedicada (Single-Tenant)

O **PontoFácil NÃO é e NUNCA será multi-tenant**. Não há planejamento para torná-lo um SaaS compartilhado.

Cada empresa cliente possui:
- Instalação própria do PontoFácil;
- Domínio ou subdomínio próprio (ex: `ponto.empresa-a.com.br`);
- Banco de dados próprio e isolado (ex: `database_empresa_a`);
- Arquivos e diretórios de storage próprios;
- Configuração `.env` própria;
- Backups próprios;
- Certificado HTTPS próprio;
- Base de usuários própria.

```text
ponto.empresa-a.com.br                      ponto.empresa-b.com.br
  ↓                                           ↓
Aplicação PontoFácil (VPS A)                Aplicação PontoFácil (VPS B)
  ↓                                           ↓
database_empresa_a                          database_empresa_b
```

### 1.1 Vedações Estritas de Multi-Tenancy
É expressamente proibido implementar:
- Colunas `tenant_id` em qualquer tabela do sistema;
- Middlewares de tenant, resolução dinâmica de bancos ou chaveamento de contexto;
- Escopos globais de tenant (`TenantScope`, `BelongsToTenant`);
- Tabelas de assinaturas, billing, planos SaaS ou workspaces;
- Papéis como `super_admin_saas`, `platform_admin` ou `tenant_owner`.

---

## 2. Visão do Produto e Separação de Responsabilidades

O PontoFácil 2.0 é uma plataforma web para registro, tratamento, auditoria e gestão de jornada de trabalho (CLT, Portaria MTP nº 671/2021 e LGPD), separando duas responsabilidades essenciais:

```text
┌─────────────────────────────────────────────────────────────┐
│                    PONTOFÁCIL 2.0                           │
├──────────────────────────────┬──────────────────────────────┤
│    REP-P (Registrador)       │    PTRP (Tratamento)         │
│  - Recebe e preserva a       │  - Interpretação de jornadas │
│    marcação original         │  - Ajustes, faltas e abonos  │
│  - Gera o AFD padronizado    │  - Banco de horas            │
│  - Emite comprovantes PAdES  │  - Espelho de Ponto          │
│  - Ledger punch_events       │  - Gera o arquivo AEJ        │
└──────────────────────────────┴──────────────────────────────┘
```

Segundo a orientação oficial do Ministério do Trabalho e Emprego (MTE):
- **O AFD é gerado exclusivamente pelo REP.**
- **O AEJ e o Espelho de Ponto são gerados exclusivamente pelo PTRP.**

---

## 3. Entidade Empresa da Instalação (`Company`)

Cada instalação do PontoFácil representa **apenas uma empresa**. Mantém-se a entidade `Company` porque seus dados institucionais são indispensáveis para identificação trabalhista, relatórios, cabeçalhos fiscais e emissão do Atestado de Conformidade.

### 3.1 Esquema da Tabela `companies`
Normalmente haverá apenas um registro (`companies.id = 1`):

```text
id                     BIGINT AUTO_INCREMENT PRIMARY KEY

legal_name             VARCHAR(255) NOT NULL (Razão Social)
trade_name             VARCHAR(255) nullable (Nome Fantasia)

document_type          VARCHAR(10) DEFAULT 'CNPJ'
document_number        VARCHAR(20) NOT NULL (CNPJ / CPF)

timezone               VARCHAR(64) DEFAULT 'America/Maceio'

address_line           VARCHAR(255) nullable
address_number         VARCHAR(30) nullable
address_complement     VARCHAR(100) nullable
district               VARCHAR(100) nullable
city                   VARCHAR(100) nullable
state                  VARCHAR(2) nullable
postal_code            VARCHAR(20) nullable

active                 BOOLEAN DEFAULT TRUE

created_at             TIMESTAMP
updated_at             TIMESTAMP
```

> **Acesso Limpo:** Não espalhar hardcode `company_id = 1` pelo código. Utilizar um serviço simples ou helper como `CurrentCompany::get()` para recuperar a empresa da instalação atual.

---

## 4. Estabelecimentos (`Establishments`)

Uma empresa pode possuir vários estabelecimentos (Matriz, Filiais, Unidades de Saúde, Postos de Trabalho).

```text
Company (Empresa da Instalação)
   │
   ├── Establishment (Matriz Maceió - NSR 1, 2, 3...)
   ├── Establishment (Filial Arapiraca - NSR 1, 2...)
   └── Establishment (Unidade Teotônio Vilela - NSR 1, 2...)
```

### 4.1 Esquema da Tabela `establishments`
Cada estabelecimento possui **sua própria sequência independente de NSR**, iniciando em 1:

```text
id                     BIGINT AUTO_INCREMENT PRIMARY KEY
company_id             BIGINT NOT NULL REFERENCES companies(id)

name                   VARCHAR(255) NOT NULL

document_type          VARCHAR(10) nullable (CNPJ próprio da filial se houver)
document_number        VARCHAR(20) nullable

caepf                  VARCHAR(20) nullable
cno                    VARCHAR(20) nullable

timezone               VARCHAR(64) DEFAULT 'America/Maceio'

latitude               DECIMAL(10,8) nullable
longitude              DECIMAL(11,8) nullable
allowed_radius_meters  INT nullable

qr_code_hash           VARCHAR(64) nullable

nsr_next               BIGINT DEFAULT 1 NOT NULL (Sequencial atômico do local)

active                 BOOLEAN DEFAULT TRUE

created_at             TIMESTAMP
updated_at             TIMESTAMP
```

---

## 5. Modelo de Dados Relacional sem Tenant

```text
Company
   │
   ├── Establishment
   │       │
   │       ├── Sector (Setores ou Departamentos da Unidade)
   │       ├── Employment (Vínculo de Trabalho)
   │       └── PunchEvent (Ledger de Batidas do Estabelecimento)
   │
   └── Establishment
```

O fluxo de dados liga o usuário ao seu vínculo de trabalho e ao estabelecimento:

```text
User  ──>  Employee  ──>  Employment  ──>  Establishment  ──>  Company
```

- Nenhuma tabela de domínio possui `tenant_id`.
- Chaves estrangeiras de `company_id` são utilizadas apenas onde têm valor de domínio direto. Evita-se redundância quando a relação `PunchEvent -> Establishment -> Company` já identifica a empresa inequivocamente.

---

## 6. Usuários e Perfis de Acesso da Instalação

Os perfis de acesso atendem às necessidades operacionais da empresa instalada:

1. **`admin` (Administrador / RH):**
   - Gestor geral do sistema na empresa cliente;
   - Gerencia estabelecimentos, funcionários, contratos, escalas, feriados e fechamento de folha;
   - Exporta arquivos fiscais (AFD e AEJ) e emite relatórios.
2. **`manager` (Gestor de Setor / Coordenador):**
   - Acompanha assiduidade da equipe sob sua responsabilidade;
   - Analisa ocorrências e aprova/rejeita solicitações de ajuste e atestados;
   - **Não pode alterar nenhuma marcação original.**
3. **`employee` (Colaborador / Servidor):**
   - Registra ponto em interface simples;
   - Acessa imediatamente comprovantes PAdES na Central de Comprovantes;
   - Consulta seu espelho de ponto e solicita correções com justificativa.
4. **`auditor` (Opcional - Fiscal / Auditoria):**
   - Perfil somente leitura para consulta a espelhos, logs de auditoria e exportação de AFD/AEJ.

---

## 7. Feriados com Herança Hierárquica

O cálculo de jornada no PTRP adota herança em camadas na instalação:

```text
Feriados Nacionais (Lei Federal)
       ↓ [herda]
Feriados Estaduais (ex: Emancipação de Alagoas, 16/Set)
       ↓ [herda]
Feriados Municipais (Município do Estabelecimento)
       ↓ [herda]
Feriados Específicos do Estabelecimento / Acordo Coletivo
```

---

## 8. Multi-Timezone Nativo

- **Armazenamento:** Timestamp UTC unívoco (`occurred_at_utc`).
- **Metadados:** Fuso horário IANA (`timezone = 'America/Maceio'`) e offset (`utc_offset = '-03:00'`) daquele estabelecimento.
- **Exibição:** Convertido para o horário local do estabelecimento de trabalho do colaborador.

---

## 9. Deployment e Atualizações do Produto

- **Configuração no `.env`:** Parâmetros técnicos específicos da VPS do cliente (`APP_NAME`, `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Dados trabalhistas e empresariais ficam exclusivamente no banco de dados.
- **Pipeline de Atualização:** Todas as instâncias compartilham o mesmo repositório Git base:
  ```bash
  git pull
  composer install --no-dev --optimize-autoloader
  php artisan migrate --force
  npm run build
  php artisan optimize
  ```
- Cada cliente é isolado fisicamente/logicamente pela própria implantação em sua VPS.
