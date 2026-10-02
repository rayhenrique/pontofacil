# 05 - Especificações dos Arquivos Fiscais: AFD e AEJ (MTE 2026)

---

## 1. Visão Geral dos Artefatos Fiscais Oficiais

O Ministério do Trabalho e Emprego (MTE) atualizou os padrões dos arquivos de fiscalização trabalhista (novos leiautes vigentes desde **31/07/2026**). No PontoFácil 2.0, cada instalação dedicada gera seus arquivos fiscais diretamente da base de dados da empresa cliente:

```text
┌────────────────────────────────┐         ┌────────────────────────────────┐
│   AFD (Arquivo Fonte Dados)    │         │  AEJ (Arquivo Eletrônico Jorn) │
├────────────────────────────────┤         ├────────────────────────────────┤
│ - Gerado exclusivamente REP-P  │         │ - Gerado exclusivamente PTRP   │
│ - Base: ledger punch_events    │         │ - Base: apuração e tratamentos │
│ - Marcações brutas originais   │         │ - Escalas, horários e banco    │
│ - Assinatura CAdES (.p7s)      │         │ - Assinatura CAdES (.p7s)      │
└────────────────────────────────┘         └────────────────────────────────┘
```

---

## 2. AFD: Arquivo Fonte de Dados do REP-P

O AFD é extraído **diretamente da tabela `punch_events`** do estabelecimento solicitado, sem passar por filtros de cálculo, tolerância ou regras de tratamento.

### 2.1 Padrão de Projeto: Geradores Versionados por Leiaute
Para proteger o código contra alterações de leiaute pelo Ministério do Trabalho, a geração de AFD adota o padrão Strategy:

```php
namespace App\Domain\Compliance\AFD;

interface AfdGeneratorInterface
{
    public function generate(Establishment $establishment, Carbon $startDate, Carbon $endDate): AfdExportResult;
}

// Implementação do padrão oficial MTE 31/07/2026
class AfdGenerator_2026_07_31 implements AfdGeneratorInterface
{
    // Validação estrita de campos, cálculo de CRC e codificação ANSI/UTF-8
}
```

### 2.2 Estrutura e Registros Obrigatórios
1. **Cabeçalho:** Identificação da empresa (`Company`), CNPJ do estabelecimento (`Establishment`), número de registro no INPI do PontoFácil 2.0, período de apuração e carimbo de tempo de geração.
2. **Registros de Marcação (com CPF):** Para cada batida no período: NSR sequencial do estabelecimento, data e hora local, fuso, CPF do colaborador e hash SHA-256 encadeado.
3. **Trailer:** Totalizadores de registros e hash final com cálculo de CRC conforme especificação do Anexo da Portaria 671.

---

## 3. AEJ: Arquivo Eletrônico de Jornada do PTRP

O AEJ é gerado pelo motor de tratamento do PTRP após o processamento das jornadas, demonstrando para a fiscalização trabalhista todo o histórico de eventos da competência:

### 3.1 Blocos Estruturados do AEJ
- **Bloco 1 — Identificação do Empregador e do PTRP:** Dados cadastrais da empresa (`Company`), CNPJ, registro no INPI e versão do software.
- **Bloco 2 — Cadastro dos Empregados:** CPF, matrícula, cargo, data de admissão e regime de contratação.
- **Bloco 3 — Horários e Escalas Contratuais:** Tabela com horários previstos, intervalos contratuais e códigos de escala.
- **Bloco 4 — Marcações Reais e Tratadas:**
  - Marcações originais com seus respectivos NSRs do AFD;
  - Marcações manuais incluídas com justificativa formal;
  - Marcações desconsideradas com o respectivo código fiscal de motivo.
- **Bloco 5 — Apuração Mensal:** Horas normais, extras por percentual (50%, 100%), horas noturnas, faltas e atrasos.
- **Bloco 6 — Movimentação de Banco de Horas:** Saldo anterior, créditos do período, débitos e saldo atual transportado.

---

## 4. Compliance Signing Service & ICP-Brasil

A instalação conta com um serviço isolado e seguro para operações criptográficas:

```text
Aplicação Laravel
       ↓
App\Domain\Compliance\Signing\ComplianceSigningService
       ↓
[Certificado Digital ICP-Brasil A1 da Empresa / Arquivo Criptografado]
       ├── Geração de Assinatura PAdES (Comprovantes do Trabalhador em PDF)
       └── Geração de Assinatura CAdES Detached (.p7s para AFD e AEJ)
```

> **Regra de Segurança de Chaves:** O certificado digital e-CNPJ da empresa contratante é armazenado de forma segura na VPS do cliente, com permissões estritas de leitura do sistema operacional. Chaves privadas e senhas **nunca são commitadas no repositório Git**.

---

## 5. Testes com Arquivos de Referência (Golden Files)

O pipeline de testes automatizados do PontoFácil 2.0 possui testes com arquivos de amostra oficiais homologados pelo MTE:
- Geração de AFD para cenários pré-definidos e validação byte a byte do layout e CRC;
- Geração de AEJ com cruzamento de jornadas 5x2, 6x1 e 12x36;
- Teste de assinatura digital CAdES e PAdES validando se os arquivos gerados passam com sucesso nos validadores oficiais do Governo Federal.
- **Qualquer alteração de código que corrompa o formato de um arquivo regulatório quebra o build imediatamente.**
