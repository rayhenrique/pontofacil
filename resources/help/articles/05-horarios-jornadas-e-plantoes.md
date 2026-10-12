---
id: horarios-jornadas-e-plantoes
title: Horários, Jornadas e Plantões (Configuração Simplificada)
subtitle: RH e Gestor
category: escalas
profiles:
  - admin
  - manager
icon: calendar-days
keywords: jornadas, horarios, escalas, plantoes, 12x36, 24x72, administrativo, horario corrido, permuta, carga horaria
order: 5
featured: true
summary: Guia completo para criar e atribuir jornadas administrativas, turnos corridos, plantões de 12 horas e 24 horas e gerenciar permutas sem complexidade técnica.
---

## 1. Visão Geral dos Modelos Prontos de Escala

O PontoFácil disponibiliza um configurador moderno baseado em **quatro modelos intuitivos** no menu **Horários & Escalas** (`/admin/shifts`):

| Modelo | Identificador | Estrutura Típica | Exemplo de Aplicação |
|---|---|---|---|
| **Administrativo** | `administrative` | 2 turnos com intervalo intrajornada | 08:00–12:00 / 13:00–17:00 (Segunda a Sexta) |
| **Horário Corrido** | `continuous` | 1 turno contínuo sem interrupção | 07:00–13:00 (6h diárias) ou 08:00–14:00 |
| **Plantão 12 Horas** | `shift_12h` | Ciclo 12×36 com data-âncora | 07:00–19:00 (Diurno) ou 19:00–07:00 (Noturno) |
| **Plantão 24 Horas** | `shift_24h` | Ciclo 24×72 com data-âncora | 07:00–07:00 do dia seguinte (SAMU, Bombeiros) |

---

## 2. Passo a Passo de Configuração por Modelo

Para criar qualquer nova escala, acesse **Horários & Escalas** (`/admin/shifts`) e clique no botão **Nova Escala / Jornada**.

### Modelo A: Administrativo (Dois Turnos)
Ideal para escritórios, secretarias e setores administrativos com pausa para almoço.
1. No seletor de modelos, clique no botão **ADM (Administrativo)**.
2. Defina o **Nome Identificador da Escala** (ex: *Administrativo Geral 40h*).
3. Selecione os **Dias de Trabalho na Semana** (por padrão, Segunda a Sexta vêm marcados; você pode marcar Sábado se houver expediente).
4. Informe os horários previstos:
   * **Entrada Manhã:** Ex: `08:00`
   * **Início Intervalo:** Ex: `12:00`
   * **Fim Intervalo:** Ex: `13:00` (duração do intervalo de almoço: 60 minutos)
   * **Saída Tarde:** Ex: `17:00`
5. O sistema calculará a prévia automática: *8 horas diárias / 40 horas semanais*.
6. Clique em **Salvar e Criar Escala**.

### Modelo B: Horário Corrido (Turno Único Contínuo)
Ideal para órgãos públicos que adotam expediente corrido de 6 horas, recepcionistas, telefonistas ou setores de atendimento ao cidadão.
1. No seletor de modelos, clique no botão **6H (Horário Corrido)**.
2. Defina o nome (ex: *Horário Corrido Saúde 30h*).
3. Selecione os dias de trabalho (ex: Segunda a Sexta).
4. Informe:
   * **Entrada Única:** Ex: `07:00`
   * **Saída Única:** Ex: `13:00`
   * **Pausa/Intervalo (minutos):** Ex: `15` minutos para lanche (conforme art. 71 §1º da CLT ou estatuto aplicável).
   * **Deduzir pausa da carga horária efetiva:** Deixe desmarcado se a pausa for remunerada e integrada à jornada de 6h.
5. Clique em **Salvar e Criar Escala**.

### Modelo C: Plantão 12 Horas (Ciclo 12×36)
Utilizado em hospitais, pronto-atendimentos (UPA), vigilância e centrais de monitoramento.
1. No seletor de modelos, clique no botão **12h (Plantão 12x36)**.
2. Defina o nome (ex: *Plantão UPA Diurno - Turma A*).
3. Configure os parâmetros do ciclo:
   * **Horário de Início:** Ex: `07:00`
   * **Horário de Término:** Ex: `19:00`
   * **Intervalo Intrajornada (min):** Ex: `60` minutos para refeição e descanso.
   * **Data-Âncora do Primeiro Plantão:** Data do primeiro dia em que a turma trabalha (ex: `01/10/2026`). O sistema calcula automaticamente: dia de trabalho, dia seguinte de descanso (36h), próximo plantão e assim sucessivamente.
   * **Plantão Noturno:** Se o plantão for noturno (ex: `19:00` às `07:00`), marque a opção *Plantão Noturno (atravessa meia-noite)*. O sistema tratará a saída no dia seguinte (`+1d`) com a redução ficta noturna no espelho.
4. Clique em **Salvar e Criar Escala**.

### Modelo D: Plantão 24 Horas (Ciclo 24×72)
Destinado a regimes específicos e autorizados em lei (ex: SAMU, socorristas, bombeiros civis ou segurança com legislação expressa).
1. No seletor de modelos, clique no botão **24h (Plantão 24x72)**.
2. Defina o nome (ex: *Plantão SAMU 24x72 - Equipe Alfa*).
3. Configure:
   * **Horário de Início:** Ex: `07:00`
   * **Horário de Término (dia seguinte):** Ex: `07:00`
   * **Intervalo Regulamentar:** Ex: intervalos para refeição e repouso.
   * **Data-Âncora do Primeiro Plantão:** Data do primeiro plantão de 24h. O sistema computará 24 horas de serviço seguidas de 72 horas consecutivas de descanso.
4. Clique em **Salvar e Criar Escala**.

> **Aviso de Legalidade:** A adoção do regime de 24x72 horas depende de previsão legal específica ou norma coletiva autorizada. O PontoFácil disponibiliza a ferramenta matemática e operacional, cabendo ao RH conferir o enquadramento jurídico da categoria.

---

## 3. Gerador de Ciclos e Escalas Programadas

Após criar uma escala de plantão, o gestor ou RH pode gerar a grade mensal completa com um clique:
1. Em **Horários & Escalas** (`/admin/shifts`), clique em **Gerar Plantões / Ciclo**.
2. Selecione o **Modelo de Escala**, o **Setor**, o período inicial e final (ex: do dia 1 ao dia 31 do mês).
3. Escolha se deseja *Incluir registros explícitos de folga no calendário* (para facilitar a visualização das folgas dos plantonistas).
4. Clique em **Gerar Ciclo**. O sistema projeta todos os plantões do mês na grade operacional.

---

## 4. Como Autorizar Permuta e Troca de Plantão

Quando um plantonista precisa trocar o dia do seu plantão com outro colega:
1. Acesse **Horários & Escalas** (`/admin/shifts`).
2. Localize o plantão específico na lista e clique no botão **Permutar** (em roxo).
3. No modal **Autorizar Permuta / Troca de Plantão**:
   * Selecione o **Colaborador Substituto** (que assumirá o plantão).
   * Descreva o **Motivo / Requerimento da Permuta** (para fins de auditoria e conformidade).
4. Clique em **Autorizar Troca**.
5. O plantão é transferido com status `Permutado` (`swapped`), registrando o responsável pela autorização sem apagar o histórico de quem estava originalmente escalado.

---

## 5. Auditoria de Conflitos de Escala

O PontoFácil executa uma verificação preventiva automática no topo da tela de escalas:
* **Conflito de Sobreposição:** Alerta caso o mesmo servidor seja escalado em dois plantões ou setores no mesmo horário.
* **Descanso Interjornada:** Sinaliza quando o intervalo entre o término de uma jornada e o início da próxima for inferior ao descanso legal mínimo de 11 horas consecutivas (art. 66 da CLT).

---

## 6. Diferença entre Cargas Horárias

Para evitar confusões no preenchimento de cadastros e relatórios:
* **Carga Horária Contratual:** O total de horas pactuado no contrato de trabalho ou concurso (ex: *40 horas semanais* ou *30 horas semanais*).
* **Carga Prevista da Competência:** A soma exata de horas das jornadas programadas para aquele mês calendário específico (ex: em um mês de 22 dias úteis na escala de 8h diárias, a carga prevista é de *176 horas*).
* **Horas Físicas Trabalhadas:** O tempo real transcorrido entre as marcações de entrada e saída apuradas no relógio.
* **Equivalência Noturna:** No trabalho noturno (entre 22h e 05h), a lei prevê que 52 minutos e 30 segundos equivalem a 1 hora de trabalho. O PontoFácil apresenta sempre as **horas físicas** e a **equivalência noturna** lado a lado em colunas separadas, sem misturar os valores.
