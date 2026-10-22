---
id: servidores-e-funcionarios
title: Gestão de Servidores e Funcionários
subtitle: Cadastro e Vínculo Funcional
category: servidores
profiles:
  - admin
  - manager
icon: users
keywords: servidores, funcionarios, cadastro, matricula, cargo, regime, clt, estatutario, escala, vigencia, desativar, excluir, setor
order: 4
featured: false
summary: Como cadastrar, atualizar, transferir de setor e manter o histórico de servidores públicos e trabalhadores CLT no PontoFácil.
---

## 1. Cadastro Funcional vs. Usuário de Acesso

No PontoFácil, uma pessoa física possui duas dimensões complementares:

* **O Usuário de Acesso (`User`):** É a conta de segurança utilizada para fazer login com e-mail e senha. Define o perfil de autorização (`employee`, `manager`, `admin`, `auditor`).
* **O Cadastro de Servidor (`Employee`):** É o registro funcional e trabalhista, contendo matrícula, cargo, setor de lotação, regime jurídico e a escala de trabalho atribuída.

Ao cadastrar um funcionário no menu **Servidores** (`/admin/employees`), o sistema sincroniza automaticamente o usuário de acesso com o cadastro funcional, garantindo que o servidor já possa acessar o sistema com o e-mail cadastrado.

---

## 2. Campos Obrigatórios e Opcionais no Cadastro

Ao acessar **Servidores** e clicar em **Adicionar Funcionário** (ou **Editar**), o formulário é dividido em dois blocos objetivos:

### Bloco 1: Identificação e Contato
* **Nome Completo:** Nome civil oficial do colaborador.
* **E-mail (Login):** E-mail institucional ou pessoal que servirá de login exclusivo.
* **CPF:** Cadastro de Pessoa Física (o sistema valida os dígitos verificadores e aplica a máscara `000.000.000-00`).
* **Telefone:** Celular para contato com DDD.
* **Setor de Lotação:** Setor onde o servidor exerce suas atividades.
  * *Para Gestores:* Exibe apenas os setores sob responsabilidade do gestor autenticado.
  * *Para Administradores:* Exibe todos os setores da organização.

### Bloco 2: Jornada e Vínculo Trabalhista
* **Regime Jurídico:**
  * `CLT (Consolidação das Leis do Trabalho)`: Trabalhadores regidos pela legislação trabalhista privada.
  * `Estatutário Municipal`: Servidores municipais regidos por lei/estatuto municipal.
  * `Estatutário Estadual`: Servidores vinculados a estatuto estadual.
  * `Estatutário Federal`: Servidores regidos pela Lei Federal nº 8.112/1990 ou congênere.
  * `Outro Regime Específico`: Contratos especiais expressamente previstos em lei.
* **Tipo de Vínculo:**
  * *Efetivo (Concursado)*
  * *Contratado (Processo Seletivo / PSS)*
  * *Comissionado (Cargo de Confiança)*
  * *Temporário*
  * *Estagiário*
* **Cargo / Função:** Ex: *Enfermeiro(a)*, *Médico(a)*, *Técnico de Enfermagem*, *Auxiliar Administrativo*, *Agente Comunitário de Saúde (ACS)*.
* **Zona:** Urbana ou Rural.
* **Modelo de Escala:** Seleção da jornada de trabalho pré-configurada (Administrativo, Horário Corrido, Plantão 12x36, etc.).
* **Início da Vigência da Escala:** Data a partir da qual o colaborador passa a cumprir essa escala.

### Dados Normativos Adicionais (Para Regimes Estatutários):
Quando o regime selecionado for estatutário (municipal, estadual ou outro), o formulário disponibiliza campos normativos para respaldar auditorias:
* **Ente Federativo / Órgão:** Ex: *Prefeitura Municipal de Exemplo*.
* **Referência Normativa / Estatuto:** Ex: *Lei Municipal nº 1.234/2018 (Estatuto dos Servidores)*.
* **Número e Identificação do Ato:** Ex: *Portaria de Nomeação nº 567/2024*.
* **Início da Vigência do Ato:** Data de posse ou início do exercício.

---

## Perfil Normativo Aplicável (novidade v2.6.20)
O RH agora pode escolher o **Perfil Normativo Aplicável** e sua data de vigência no cadastro do servidor. Os perfis são criados e aprovados em `/admin/labor-rule-profiles`. Para escalas 12x36 e 24x72, um perfil aprovado, compatível e vigente deve autorizar a modalidade. Gestores não podem conceder perfis normativos.

**Atenção à ordem na tela atual:** ao salvar perfil e escala especial pela primeira vez em uma única operação, a atribuição da escala pode ser validada antes da gravação do novo vínculo normativo. Para evitar a mensagem de perfil ausente, salve primeiro o servidor e seu perfil; atribua a escala em seguida.

O PontoFácil ainda usa **um setor de lotação principal** por cadastro de funcionário; o suporte a atuação regular em vários setores está planejado, não implementado.

Consulte [Perfis Normativos](/ajuda?artigo=perfis-normativos-e-autorizacao).

## 3. Alteração de Escala e Atribuição com Vigência

A vida funcional de um servidor pode exigir mudança de setor ou de horário de trabalho (por exemplo, de uma jornada de 40h administrativa para um plantão de 12h ou horário corrido de 6h).

### Como alterar a escala preservando o histórico passado:
1. Acesse **Servidores** (`/admin/employees`) e localize o servidor.
2. Clique no botão **Editar**.
3. No campo **Modelo de Escala**, selecione a nova jornada desejada.
4. No campo **Início da Vigência da Escala**, informe a **data exata** a partir da qual a nova regra passa a valer (ex: `01/11/2026`).
5. Clique em **Salvar Alterações**.

> **Preservação Histórica:** O PontoFácil adota vigência temporal para atribuição de escalas (`WorkScheduleAssignment`). Os meses anteriores continuarão sendo apurados de acordo com a escala antiga que estava vigente na época. A nova escala será aplicada exclusivamente a partir da data de início informada.

---

## 4. Como Corrigir Cadastros

Erros de digitação em nome, telefone, cargo, vínculo ou matrícula podem ser corrigidos a qualquer momento:
1. Localize o colaborador na lista de **Servidores** (`/admin/employees`).
2. Clique em **Editar**.
3. Altere as informações necessárias no formulário.
4. Clique em **Salvar Alterações**. As alterações refletirão imediatamente nas novas telas e relatórios da competência aberta.
   * *Atenção:* Em competências já formalmente fechadas pelo RH, os snapshots congelados preservam os dados cadastrais da data do fechamento para manter a integridade documental.

---

## 5. Como Remover ou Desativar Acesso

Se um colaborador for exonerado, demitido ou encerrar seu contrato de trabalho:
1. Acesse **Servidores** (`/admin/employees`).
2. Localize o registro do colaborador e clique no botão **Excluir** (em vermelho).
3. O sistema abrirá um modal de confirmação: *"Tem certeza que deseja excluir o colaborador? Esta ação removerá o funcionário e o acesso de usuário dele do sistema."*
4. Clique em **Sim, Excluir**.

### O que acontece com o histórico de pontos já registrados?
* As batidas de ponto físicas (`PunchEvents`), os números sequenciais de registro (NSR), os hashes e os snapshots de meses anteriores já fechados **continuam preservados no banco de dados e nos arquivos de fiscalização**.
* A exclusão do cadastro funcional impede que o usuário continue entrando no sistema ou batendo novos pontos a partir daquele momento.

---

## 6. O que fazer quando falta cadastro ou escala

* **O servidor tenta bater ponto mas não tem escala vinculada:**
  * O PontoFácil **NÃO bloqueia o registro de ponto** por falta de escala. O servidor conseguirá ler o QR Code e gravar a batida com NSR normalmente.
  * No entanto, no **Espelho de Ponto**, a linha do dia ficará com aviso indicando que a apuração de horas depende da atribuição de escala.
  * O RH ou Gestor deve acessar **Servidores**, clicar em **Editar** no colaborador e selecionar a escala correta com a data de vigência apropriada. Assim que a escala for salva, o espelho calculará as horas previstas e trabalhadas retroativamente.
