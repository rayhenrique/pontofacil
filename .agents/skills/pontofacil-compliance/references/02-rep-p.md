# 02 - Modalidade REP-P, Instrumentos Coletivos & Governança

---

## 1. Princípios Jurídicos Invioláveis (Art. 74 da Portaria 671)

A legislação trabalhista estabelece vedações expressas para qualquer sistema informatizado de controle de ponto. O PontoFácil 2.0 foi desenhado para impossibilitar as seguintes práticas:

1. **Restrição de Horário:** O sistema **nunca impede** o trabalhador de bater o ponto, independentemente do horário (fora de escala, antes da hora, após o expediente ou em feriados).
2. **Marcação Fictícia / Automática:** É expressamente vedado inserir marcações de ponto automáticas como se tivessem sido efetuadas pelo colaborador (exceto intervalo pré-assinalado previsto em instrumento válido).
3. **Exigência de Autorização Prévia para Sobrejornada:** O empregado não pode ser barrado ou impedido de registrar hora extra por falta de anuência prévia da chefia.
4. **Alteração da Marcação Original:** A marcação gravada originalmente no coletor é perpétua e imutável.

> **Consequência Arquitetural Primária:** Toda e qualquer correção, desconsideração de duplicidade ou registro esquecido ocorre exclusivamente como um **evento de tratamento no PTRP**, mantendo o registro original intacto no ledger da instalação.

---

## 2. Decisão Arquitetural: REP-P como Padrão × REP-A Condicionado

### 2.1 Modalidade Padrão: REP-P (por Programa)
- O PontoFácil opera nativamente como **REP-P**, pois roda em ambiente dedicado em nuvem/VPS e **não depende de autorização sindical ou negociação coletiva** para ter validade jurídica plena (Art. 75, III).
- Requisitos obrigatórios atendidos na instalação:
  - Registro de programa de computador no INPI;
  - Emissão do Atestado Técnico e Termo de Responsabilidade para a empresa da instalação (`Company`);
  - Geração nativa do AFD padronizado e assinado digitalmente com certificado ICP-Brasil;
  - Emissão imediata do Comprovante de Registro de Ponto do Trabalhador.

### 2.2 Modalidade Opcional: REP-A (Alternativo)
- A instalação permite ativar o modo **REP-A** exclusivamente quando a empresa cadastrar um instrumento coletivo válido:
  - Tipo de instrumento: **ACT** (Acordo Coletivo) ou **CCT** (Convenção Coletiva);
  - Número de registro / processo no Ministério do Trabalho (Sistema Mediador);
  - Vigência explícita (`effective_from` e `effective_to`);
  - Entidade sindical signatária e categoria abrangida;
  - Cópia digital do documento comprobatório.
- **Alerta de Expiração:** Quando o prazo de vigência do instrumento expira, o sistema desativa automaticamente a conformidade REP-A e emite alertas urgentes para renovação ou retorno ao padrão REP-P.

---

## 3. Ponto por Exceção (Art. 74, § 4º da CLT)

> **Atenção:** Ponto por exceção **não se confunde com REP-A**. O ponto por exceção é uma prerrogativa prevista na CLT para dispensa de anotação dos horários regulares, registrando-se apenas ocorrências anômalas (horas extras, atrasos, faltas, saídas antecipadas).

O PontoFácil 2.0 parametriza a modalidade por contrato de trabalho (`Employment`):
- `FULL` (Registro de todos os movimentos de entrada e saída);
- `EXCEPTION` (Registro restrito a sobrejornada e exceções).
- **Validade Jurídica:** O modo por exceção só pode ser ativado caso haja comprovação de acordo individual escrito válido ou previsão expressa em CCT/ACT.

---

## 4. Registro no INPI & Atestado Técnico

### 4.1 Certificado de Registro no INPI (§ 39 do PRD)
- O código-fonte do PontoFácil possui registro formal de Programa de Computador junto ao **INPI (Instituto Nacional da Propriedade Industrial)**.
- O número deste registro consta no arquivo de configuração do sistema, no cabeçalho dos arquivos fiscais AFD e AEJ e nos atestados gerados.

### 4.2 Atestado Técnico e Termo de Responsabilidade (§ 38 do PRD)
- Documento emitido eletronicamente pela desenvolvedora (**KL Tecnologia**) atestando a conformidade legal do software perante a Portaria 671/2021.
- Contém: identificação da desenvolvedora, dados da empresa contratante (`Company`), versão exata do software, registro INPI, declaração de não-alterabilidade de dados e assinatura digital qualificada.
- Mantido na instalação com controle estrito de versões a cada release do software.

---

## 5. Políticas Trabalhistas Versionadas (`labor_policy_versions`)

Nenhuma regra de cálculo ou política interna pode ser alterada diretamente no banco de dados sem versionamento histórico.

```text
Hierarquia de Resolução Normativa na Empresa:
┌────────────────────────────────────────┐
│ 1. Lei Federal (CLT / Portaria 671)    │
├────────────────────────────────────────┤
│ 2. Instrumento Coletivo (ACT / CCT)    │
├────────────────────────────────────────┤
│ 3. Contrato Individual de Trabalho     │
├────────────────────────────────────────┤
│ 4. Política Interna do Estabelecimento │
└────────────────────────────────────────┘
```

- Cada política possui intervalo de validade (`valid_from` e `valid_until`).
- Quando uma competência mensal é fechada, a regra aplicada naquele mês é **congelada**, garantindo que recálculos futuros em auditorias respeitem a norma vigente à época dos fatos.

---

## 6. Dashboard de Conformidade Regulatória (§ 37 do PRD)

O painel administrativo do PontoFácil 2.0 conta com um módulo de monitoramento da instalação dedicada:

```text
┌────────────────────────────────────────────────────────────────────────┐
│                     DASHBOARD DE CONFORMIDADE                          │
├───────────────────┬───────────────────┬────────────────────────────────┤
│ [REP-P / INPI]    │ [ICP-Brasil A1]   │ [Atestado Técnico MTP]         │
│ Certificado Válido│ Vence em 140 dias │ Emitido para Estabelecimento   │
├───────────────────┼───────────────────┼────────────────────────────────┤
│ [AFD do Mês]      │ [AEJ do Mês]      │ [Integridade do Ledger]        │
│ Validado (0 erros)│ Validado (0 erros)│ 100% Hashes SHA-256 Íntegros   │
├───────────────────┴───────────────────┴────────────────────────────────┤
│ [Acordos Coletivos Vigentes]: 2 ativos | 1 expirando em 30 dias        │
│ [Último Backup Local / Remoto]: Há 4 horas (RPO ≤ 15 min)              │
└────────────────────────────────────────────────────────────────────────┘
```
