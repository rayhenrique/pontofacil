---
id: conferencia-mensal-e-fechamento
title: Conferência Mensal de Frequência e Fechamento
subtitle: RH e Gestor
category: conferencia
profiles:
  - manager
  - admin
icon: check-badge
keywords: conferencia mensal, fechamento, reabertura, snapshot, congelamento, bloqueios, lote, ressalva, ocorrencias, gestor, rh
order: 8
featured: true
summary: Procedimento administrativo para gestores e RH auditarem jornadas, conferirem ocorrências, aprovarem em lote e fecharem a competência formalmente.
---

## 1. O que é o Módulo de Conferência Mensal

O módulo **Conferência Mensal** (`/admin/monthly-review`) é o painel de governança onde os líderes de setor e o Departamento de Recursos Humanos validam administrativamente a frequência de toda a equipe antes do encerramento da competência:

* **Independência Operacional:** A conferência de frequência existe independentemente da organização adotar ou não banco de horas.
* **Segregação de Papéis:**
  * **Gestores:** Conferem exclusivamente os servidores dos setores sob sua gestão.
  * **Administradores de RH:** Visualizam todos os setores e são os únicos autorizados a executar o **Fechamento Formal** ou a **Reabertura Auditada** da competência.
* **Validação Administrativa:** A conferência não edita batidas fiscais originais; ela atesta que as marcações, atestados e ocorrências do colaborador foram examinados e homologados pela chefia.

---

## 2. Passo a Passo da Rotina Mensal de Conferência

A rotina de conferência mensal deve ser realizada ao término de cada mês seguindo este fluxo:

### Passo 1: Selecionar a Competência e os Filtros
1. Acesse **Conferência Mensal** (`/admin/monthly-review`).
2. Utilize o navegador de competência no topo (ex: `Outubro / 2026`).
3. Se desejar, filtre por um **Setor** específico ou digite o nome/matrícula no campo de busca rápida.
4. O topo da tela exibirá os quatro cards de resumo:
   * **Conferidos:** Servidores com conferência concluída regular.
   * **Conferidos com Ressalva:** Servidores validados, porém com observações registradas.
   * **Com Ocorrência:** Servidores com faltas, batidas ímpares ou solicitações pendentes.
   * **Aguardando Conferência:** Servidores pendentes de análise.

### Passo 2: Analisar a Situação de Cada Servidor
A lista exibe a linha de cada colaborador com:
* Nome civil, matrícula e setor.
* Escala vinculada.
* **Horas Previstas:** Carga esperada na competência.
* **Horas Trabalhadas:** Total de horas físicas apuradas.
* **Diferença:** Saldo positivo (horas excedentes) ou negativo (atrasos/déficits).
* **Status:** Badge indicativo (`Aguardando Conferência`, `Com Ocorrência`, `Conferido` ou `Conferido com Ressalva`).

### Passo 3: Abrir o Espelho para Tirar Dúvidas
Se o colaborador estiver com status *Com Ocorrência* ou houver divergência de horas:
1. Clique no ícone de **Olho / Espelho** na coluna de ações da linha.
2. O sistema abrirá o Espelho de Ponto detalhado daquele servidor em uma nova aba para você verificar quais dias tiveram atraso, batida esquecida ou atestado médico.

### Passo 4: Realizar a Conferência Individual
1. Na linha do servidor, clique no botão **Conferir**.
2. O modal **Conferir Frequência do Servidor** será exibido:
   * O sistema resume os dados apurados (Horas previstas, Horas realizadas, Saldo e Ocorrências não tratadas).
   * **Se regular:** Deixe o campo de ressalvas em branco e clique em **Confirmar Frequência**. O status mudará para `Conferido` (`reviewed`).
   * **Se houver ressalva fundamentada:** Caso exista alguma pendência conhecida que não impeça a validação (ex: *Servidor aguardando publicação de portaria de licença-prêmio* ou *Atestado médico entregue em cópia física com processo administrativo nº 123/2026*), digite a justificativa no campo **Ressalva / Justificativa**. O status mudará para `Conferido com Ressalva` (`reviewed_with_reservation`).

### Passo 5: Realizar a Conferência em Lote (Quando Aplicável)
Para otimizar o tempo em equipes numerosas:
1. No topo da tabela, marque a caixa de seleção **Selecionar Elegíveis**.
2. O PontoFácil selecionará automaticamente apenas os colaboradores **elegíveis** (aqueles que não possuem ocorrências impeditivas não tratadas).
3. Clique no botão **Conferir em Lote**.
4. Confirme no modal. O sistema homologará todos os colaboradores selecionados de uma só vez, registrando seu usuário e data/hora em cada um.

---

## 3. O que são Impedimentos de Fechamento

Para proteger a organização contra fechamentos precipitados com pendências jurídicas, o PontoFácil calcula automaticamente os **Impedimentos de Fechamento**:

O botão de Fechamento fica travado enquanto houver:
* Colaboradores que ainda estão com status *Aguardando Conferência*.
* Colaboradores com *Ocorrências pendentes* não tratadas ou não justificadas.
* Solicitações de tratamento de ponto (atestados ou batidas esquecidas) ainda com status *Pendente* no PTRP.

No topo da página, um banner em âmbar listará exatamente os motivos impeditivos (ex: *"Existem 3 colaboradores sem conferência concluída"*).

---

## 4. Como Executar o Fechamento Formal da Competência

Assim que todos os impedimentos forem sanados e a equipe estiver conferida (status verde *Pronta para Fechamento*):

1. O Administrador de RH clica no botão em destaque: **Fechar Competência**.
2. No modal **Concluir e Fechar Competência**:
   * Informe eventuais *Notas de Fechamento* (opcional, ex: *Fechamento ordinário de outubro/2026 aprovado pela comissão de frequência*).
   * Clique em **Fechar e Congelar Mês**.
3. **O que acontece no fechamento:**
   * Uma transação segura no banco de dados cria o registro `ClosedPeriod` da competência.
   * Todos os dados de cada servidor (horas previstas, trabalhadas, saldos, faltas, adicionais noturnos e plantões) são congelados em **Snapshots Imutáveis** (`ClosedPeriodEmployeeSnapshot`).
   * A competência passa a exibir a tarja verde **Competência Fechada (Snapshot v1)**.
   * O Espelho de Ponto daquele mês passa a ler estritamente os snapshots congelados, impedindo recálculos ou alterações retroativas acidentais.

---

## 5. Como Reabrir uma Competência Formalmente

Se após o fechamento for descoberto um erro grave que exija retificação oficial (ex: decisão judicial ou retificação de atestado com efeito retroativo):

1. Acesse **Conferência Mensal** (`/admin/monthly-review`) no mês fechado.
2. O Administrador de RH clica no botão **Reabrir Mês (Auditado)**.
3. No modal **Reabertura Formal de Competência**:
   * O sistema exige o preenchimento de uma **Justificativa Formal Obrigatória** (com no mínimo 10 caracteres).
   * O sistema alerta expressamente: *"A reabertura é um procedimento excepcional e auditado. As alterações realizadas gerarão uma nova versão de snapshot (v2) no próximo fechamento."*
4. Clique em **Confirmar Reabertura Auditada**.
5. O período é reaberto, o motivo e o autor da reabertura são gravados no log de auditoria permanente, e os ajustes necessários podem ser realizados antes do novo fechamento.
