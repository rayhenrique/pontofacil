---
id: espelho-comprovantes-e-correcoes
title: Espelho de Ponto, Comprovantes e Solicitações de Ajuste
subtitle: Servidor e Gestor
category: espelho
profiles:
  - employee
  - manager
  - admin
icon: document-text
keywords: espelho de ponto, comprovantes, ajustes, solicitacoes, atestado, esquecimento, inclusao, desconsideracao, saldo, folha de ponto
order: 7
featured: false
summary: Como consultar suas horas diárias, solicitar inclusão de batida esquecida, anexar atestados médicos e baixar seus comprovantes fiscais assinados.
---

## 1. O que é o Espelho de Ponto

O **Espelho de Ponto** (`/timesheet`) é a tela onde você acompanha detalhadamente toda a sua frequência no mês:

* **Navegador de Competência:** Permite alternar entre os meses do ano (ex: *Outubro de 2026*) para conferir períodos atuais e históricos.
* **Indicadores do Mês:**
  * **Horas Apuradas:** Total de horas físicas efetivamente trabalhadas no mês.
  * **Dias com Jornada:** Quantidade de dias em que houve expediente registrado.
  * **Ocorrências Pendentes:** Dias com batidas ímpares, faltas ou jornadas incompletas que precisam de justificativa.
* **Linhas do Calendário:** Cada dia do mês mostra o status da jornada (`Concluída`, `Em andamento`, `Incompleta`, `Folga` ou `Feriado`), as marcações registradas nos respectivos pares (Entrada, Saída, Intervalos) e o saldo apurado.

---

## 2. Como Solicitar Correção de Ponto (Tratamentos no PTRP)

Se você esqueceu de bater o ponto, marcou duas vezes por engano ou precisou se ausentar por motivo de saúde, o PontoFácil permite enviar uma solicitação formal pelo próprio Espelho de Ponto:

### Como abrir o formulário de solicitação:
1. Acesse **Espelho de Ponto** (`/timesheet`).
2. Existem duas formas de abrir o formulário:
   * **Pelo botão geral:** Clique no botão azul **Solicitar ajuste** no topo da página.
   * **Direto no dia com ocorrência:** Na linha do dia que estiver incompleta, clique no botão **Ajuste**. A data de referência já virá preenchida automaticamente.

### Os Três Tipos de Solicitação Disponíveis:

#### Tipo 1: Inclusão de Batida (Batida Esquecida)
Use quando você trabalhou normalmente, mas esqueceu de registrar o ponto na entrada, no retorno do almoço ou na saída.
1. No campo *Tipo de Ajuste*, selecione **Inclusão de Batida**.
2. Informe:
   * **Data da Ocorrência:** Dia em que a batida foi omitida.
   * **Horário Efetivo:** Hora e minuto exatos em que você iniciou ou encerrou o expediente (ex: `08:00`).
   * **Sentido da Batida:** Entrada ou Saída.
   * **Justificativa Detalhada:** Explique o motivo do esquecimento (ex: *Esquecimento de registro no retorno do intervalo de almoço em razão de atendimento urgente a munícipe*).
3. Clique em **Enviar Solicitação**.

#### Tipo 2: Justificativa de Ausência com Atestado Médico
Use quando faltou ao serviço por motivo de saúde, doação de sangue, convocação judicial ou afastamento legal.
1. Selecione **Justificativa de Ausência (Atestado Médico / Declaração)**.
2. Informe:
   * **Data da Ausência.**
   * **Motivo / Categoria:** Selecione a opção correspondente (Atestado Médico, Consulta, Convocação da Justiça Eleitoral, Luto, Casamento, etc.).
   * **Texto da Justificativa:** Ex: *Atestado médico de 1 dia emitido pela UPA Central anexado para abono legal*.
   * **Anexar Comprovante / Atestado:** Clique no campo de upload para anexar foto ou PDF do atestado médico.
3. Clique em **Enviar Solicitação**.

#### Tipo 3: Desconsideração de Marcação Indevida
Use se você registrou o ponto duas vezes seguidas por engano (por exemplo, bateu às `08:00:10` e novamente às `08:00:45`).
1. Selecione **Desconsideração de Marcação**.
2. Selecione a data e a marcação original duplicada na lista.
3. Descreva a justificativa: *Batida duplicada registrada acidentalmente por erro de leitura no leitor óptico*.
4. Clique em **Enviar Solicitação**.

> **Nota de Integridade:** A desconsideração **nunca apaga** a batida física do banco de dados nem reordena os NSRs. Ela apenas instrui o motor de cálculo da jornada a ignorar aquela marcação específica na contagem de horas trabalhadas, registrando quem solicitou e quem autorizou.

---

## 3. Como Acompanhar a Decisão do Gestor ou RH

Após o envio, a solicitação entra com status **Pendente** (`pending`) para análise do gestor do seu setor ou do RH:

1. No próprio Espelho de Ponto, os dias com solicitações em análise exibem um ícone de documento em amarelo.
2. Ao clicar para ver os detalhes da solicitação:
   * **Se Aprovada:** O status muda para verde (`approved`), o nome de quem aprovou e o horário da decisão são gravados, e o espelho de ponto recalcula a jornada com as horas ajustadas.
   * **Se Rejeitada:** O status muda para vermelho (`rejected`), e o motivo formal da recusa informado pelo avaliador é disponibilizado para leitura do colaborador.

---

## 4. Central de Comprovantes de Ponto

Conforme a Portaria 671/2021 MTP, todo trabalhador tem o direito de receber o comprovante de cada registro realizado:

1. Acesse o menu **Comprovantes** (`/comprovantes`).
2. O sistema lista cronologicamente todas as suas marcações efetuadas.
3. Cada comprovante exibe:
   * Data e horário local com identificação do fuso.
   * Direção da marcação (*Entrada*, *Saída*).
   * Número Sequencial de Registro (**NSR**).
   * Código alfanumérico de autenticidade (ex: `PF-2026-8942-01`).
   * Hash criptográfico SHA-256 do registro fiscal.
4. Clique em **Baixar PDF** para salvar o comprovante assinado eletronicamente no seu aparelho.

---

## 5. Como Imprimir a Folha de Ponto Mensal

No final de cada mês, para assinar ou guardar uma cópia física da sua frequência:
1. Acesse **Folha de Ponto** (`/folha-ponto`).
2. Selecione a competência desejada.
3. O sistema formatará toda a sua apuração em uma folha padronizada para tamanho **A4**.
4. Clique no botão **Imprimir Folha de Ponto**.
5. A caixa de impressão do navegador será aberta automaticamente com cabeçalho institucional, tabela de horários, resumo de horas e campo de assinatura.

---

## 6. Diferença entre Saldo Matemático de Jornada e Banco de Horas

Para evitar dúvidas no fechamento:
* **Saldo Diário de Jornada:** É a simples diferença matemática entre o tempo que você trabalhou e a sua escala prevista (ex: trabalhou 8h15 em uma escala de 8h = saldo do dia de `+15min`).
* **Tolerância Legal (Art. 58 §1º da CLT):** Variações de até 5 minutos por registro, observado o limite máximo diário de 10 minutos, não são computadas nem como atraso nem como hora extra.
* **Banco de Horas (Opcional):** É o regime de compensação institucional regulamentado pela organização. Quando ativo, as diferenças autorizadas acumulam para folga futura. Quando desativado, o saldo matemático físico é apenas apurado e enviado no relatório de frequência para decisão do setor administrativo.
