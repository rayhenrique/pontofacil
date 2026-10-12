---
id: apresentacao-e-visao-geral
title: Apresentação e Visão Geral do PontoFácil
subtitle: Conceitos Fundamentais
category: visao-geral
profiles:
  - employee
  - manager
  - admin
  - auditor
icon: book-open
keywords: apresentacao, visao geral, o que faz, registro, apuracao, frequencia, perfis, seguranca, lgpd, competencia, rep-p, ptrp
order: 1
featured: false
summary: Entenda a finalidade do PontoFácil, a separação entre registro fático e apuração, os perfis de acesso e o ciclo de vida completo da frequência.
---

## 1. O que é o PontoFácil

O **PontoFácil** é uma plataforma institucional dedicada exclusivamente ao **registro, apuração, conferência e relatório consolidado de frequência** de trabalhadores e servidores públicos. 

O sistema foi concebido para atender tanto empresas privadas regidas pela Consolidação das Leis do Trabalho (**CLT**) quanto órgãos públicos municipais, estaduais e federais com servidores estatutários ou contratados.

---

## 2. O que o PontoFácil faz e o que ele NÃO faz

Para garantir clareza operacional e jurídica entre os setores de Recursos Humanos e a gestão, é essencial compreender o escopo exato do sistema:

### O que o PontoFácil FAZ:
* **Registra batidas fáticas de ponto eletrônico:** Coleta o instante exato em que o servidor ou colaborador entra, sai ou realiza pausas/intervalos, associando a leitura óptica do QR Code oficial.
* **Preserva o fato original na íntegra:** O registro original da marcação nunca é alterado ou excluído da base soberana de dados.
* **Apura jornadas de trabalho:** Confronta as marcações reais com as escalas previstas de cada colaborador, identificando horas normais trabalhadas, atrasos, faltas, saídas antecipadas, horas noturnas e plantões.
* **Permite o tratamento transparente de exceções:** Possibilita que colaboradores solicitem correções de esquecimento ou justifiquem ausências mediante atestados médicos para avaliação do gestor e do RH.
* **Oferece conferência administrativa mensal:** Fornece painel específico para que gestores e o RH analisem e validem a frequência antes do fechamento.
* **Emite relatórios consolidados e arquivos legais:** Gera a folha de ponto mensal em PDF, relatórios em CSV UTF-8 para integração administrativa e os arquivos fiscais oficiais previstos em normas trabalhistas.

### O que o PontoFácil NÃO FAZ:
* **NÃO calcula salários, proventos, descontos financeiros ou contracheques:** O sistema apura estritamente **tempo físico de trabalho** (horas e minutos). Valores monetários, alíquotas de impostos (INSS, IRPF, previdência própria) e créditos bancários são atribuições exclusivas do sistema de Folha de Pagamento.
* **NÃO realiza pagamentos ou quitações monetárias:** O PontoFácil não emite ordens bancárias, PIX nem comprovantes de pagamento financeiro.
* **NÃO arbitra débitos salariais automaticamente:** A ausência de banco de horas ou o registro de falta não debita dinheiro automaticamente; gera apenas o registro de ocorrência no relatório de frequência que será encaminhado ao setor competente.

---

## 3. Diferença entre Registro e Apuração de Frequência

Uma das regras mais importantes da legislação trabalhista e do PontoFácil é a separação rigorosa entre dois momentos distintos:

| Etapa | O que acontece | Onde ocorre no sistema |
|---|---|---|
| **Registro (Coleta)** | O ato físico do servidor ao apontar a câmera e ler o QR Code no início ou fim da jornada. Esse dado é gravado de forma imutável com carimbo de data/hora, número de registro sequencial e identificação do trabalhador. | Módulo **Bater Ponto** (`/ponto`) |
| **Apuração (Tratamento)** | O cálculo lógico da jornada. O sistema compara as batidas registradas com a escala vinculada ao servidor, apurando se houve cumprimento integral, diferença positiva (horas a mais) ou negativa (atraso/falta). | Módulos **Espelho de Ponto** (`/timesheet`) e **Conferência Mensal** (`/admin/monthly-review`) |

> **Princípio da Não Alteração:** Mesmo quando uma solicitação de correção de batida esquecida é aprovada pelo gestor, a batida original nunca é editada. O sistema cria um evento de tratamento formal com justificativa e identificação de quem aprovou, preservando o histórico auditável.

---

## 4. Perfis de Acesso e Responsabilidades

O PontoFácil organiza as permissões dos usuários em quatro perfis bem definidos:

1. **Colaborador / Servidor (`employee`):**
   * Registra suas próprias marcações de ponto via QR Code.
   * Consulta o relógio oficial com o fuso horário do seu estabelecimento.
   * Acompanha diariamente seu Espelho de Ponto e saldo de jornada.
   * Emite e imprime sua Folha de Ponto mensal.
   * Baixa comprovantes digitais de cada batida.
   * Solicita ajustes e anexa justificativas/atestados médicos.

2. **Gestor de Setor (`manager`):**
   * Visualiza a equipe e servidores pertencentes exclusivamente aos setores sob sua responsabilidade.
   * Cadastra e atualiza dados cadastrais e escalas dos colaboradores do seu setor.
   * Acompanha ocorrências, faltas e atrasos da equipe em tempo real.
   * Avalia solicitações de inclusão de batida e atestados da sua equipe.
   * Realiza a **Conferência Mensal de Frequência** dos seus setores antes do fechamento pelo RH.
   * Emite o Relatório Consolidado de Frequência dos seus setores.

3. **Administrador de RH (`admin`):**
   * Possui visão global de todos os setores e estabelecimentos da organização.
   * Configura os dados institucionais da empresa/órgão, logotipo e QR Codes.
   * Gerencia usuários, níveis de acesso e lotações funcionais.
   * Cria modelos de horários, escalas administrativas e plantões (12x36, 24x72).
   * Define políticas de jornada e regras de banco de horas (quando aplicável).
   * Executa o **Fechamento Formal da Competência Mensal**, congelando os dados em snapshots imutáveis.
   * Reabre competências fechadas sob justificativa formal auditada.
   * Gera os relatórios consolidados finais e os pacotes de fiscalização do Ministério do Trabalho.

4. **Auditor Fiscal (`auditor`):**
   * Perfil de leitura e conformidade legal.
   * Acessa o módulo de Fiscalização (`/admin/fiscalizacao`) para conferir registros fiscais, integridade de hashes e emitir arquivos AFD e AEJ.

---

## 5. Segurança, Integridade e Privacidade (LGPD)

O PontoFácil adota boas práticas de segurança e proteção de dados pessoais:

* **Instância Dedicada (Single-Tenant):** Cada organização possui sua própria base de dados isolada, garantindo total privacidade institucional e independência cadastral.
* **Hashes Criptográficos SHA-256:** Cada marcação oficial gera uma assinatura digital resumida calculada sobre os dados do registro (empregador, trabalhador, horário e NSR), permitindo verificar a qualquer momento se o dado foi adulterado.
* **Número Sequencial de Registro (NSR):** Numeração estritamente crescente e contínua por estabelecimento, sem saltos e sem duplicidade.
* **Proteção de Dados do Colaborador:** Na visualização pública e no preenchimento de formulários, CPFs e dados sensíveis utilizam máscaras protetivas (`123.***.***-45`).
* **Geolocalização como Evidência:** As coordenadas de GPS coletadas no momento da leitura do QR Code atuam exclusivamente como evidência contextual antifraude, **sem bloquear o registro** caso o servidor esteja temporariamente fora do perímetro autorizado.

---

## 6. O Conceito de Competência Mensal

No PontoFácil, todo o ciclo de frequência opera em torno da **Competência Mensal** (formato Mês/Ano, por exemplo: `10/2026`).

* **Competência Aberta (Em Andamento):** Durante o mês em curso, as jornadas são apuradas em tempo real (dados provisórios). Colaboradores podem bater ponto, solicitar tratamentos de ocorrências e gestores podem acompanhar os saldos diariamente.
* **Competência Fechada (Congelada):** Após o término do mês e a conferência de todos os servidores, o Administrador de RH executa o fechamento formal. Todos os cálculos de jornada são gravados em *snapshots imutáveis*. Novos registros retroativos são bloqueados, garantindo estabilidade jurídica para envio ao setor de pagamento.

---

## 7. Fluxo Completo: Do Registro ao Relatório Final

O ciclo operacional padrão do PontoFácil segue seis passos sequenciais:

```
[ 1. Registro Diário ] ──► Servidor lê o QR Code e grava o ponto
           │
[ 2. Apuração Viva ]   ──► Sistema calcula horas, atrasos e faltas no Espelho
           │
[ 3. Tratamento ]      ──► Servidor solicita ajustes; Gestor/RH analisa e decide
           │
[ 4. Conferência ]     ──► Gestor/RH confere e valida a frequência de cada servidor
           │
[ 5. Fechamento ]      ──► RH encerra a competência e congela os snapshots
           │
[ 6. Relatório Final ] ──► Emissão do relatório consolidado e envio ao setor de pagamento
```
