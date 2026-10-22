---
id: perfis-normativos-e-autorizacao
title: Perfis Normativos — Como Autorizar Plantões 12x36 e 24x72
subtitle: RH e escalas especiais — v2.6.20
category: escalas
profiles:
  - admin
  - manager
icon: scale
keywords: perfil normativo, labor rule, regime, estatutario municipal, estadual, clt, juridico, vigencia, 12x36, 24x72, aprovado, rascunho, revogado, homologar
order: 5
featured: true
summary: Cadastre, aprove, versione e vincule perfis normativos a servidores; resolva o aviso de autorização de escalas especiais com segurança.
---

## 1. Para que serve

Na versão **v2.6.20**, o RH possui uma tela dedicada em **Configurações → Perfis Normativos** (`/admin/labor-rule-profiles`). Ela define quais regras legais de jornada se aplicam ao servidor em determinada vigência, incluindo autorização para escalas especiais.

**Atenção:** escrever a lei no campo de referência normativa do cadastro do funcionário não basta para autorizar um plantão. É preciso um **perfil normativo aprovado, compatível e vigente**, associado ao servidor ou resolvido segundo as regras aplicáveis.

## 2. Criar um perfil normativo

1. Entre com usuário **Administrador/RH** e abra `/admin/labor-rule-profiles`.
2. Clique em **Novo Perfil Normativo**.
3. Informe nome descritivo, código único, regime jurídico, esfera, órgão/ente, fundamento legal e vigência.
4. Marque somente as **modalidades autorizadas** pelo ato aplicável: jornada padrão, 12x36, 24x72 etc.
5. Revise os parâmetros efetivamente previstos na norma: faixa de adicional noturno, percentual, hora reduzida, prorrogação, compensação em 12x36 e feriados.
6. Salve o perfil como **Rascunho**. Confira os dados com a área responsável antes de homologar.
7. Use a ação **Aprovar**. Somente perfis aprovados e vigentes podem autorizar uma nova escala.

**Exemplo ilustrativo:** “Servidores Municipais — Saúde — Lei nº ...”, regime **Estatutário Municipal**, modalidade **12x36**, data de início conforme vigência real. Não copie referências legais ou percentuais de exemplo sem verificar o ato do município.

## 3. Vincular o perfil ao funcionário

1. Entre em **Servidores** (`/admin/employees`), localize o colaborador e abra **Editar**.
2. Confira seu **Regime Jurídico**.
3. Em **Perfil Normativo Aplicável**, selecione o perfil aprovado compatível.
4. Preencha a **data de início da vigência** do perfil no cadastro.
5. Salve e confirme que o vínculo está aplicado.

O sistema possui histórico temporal de atribuições de perfis normativos, para evitar que mudanças futuras substituam indiscriminadamente configurações antigas.

**Ordem segura ao cadastrar um novo servidor com 12x36:** primeiro salve o funcionário e seu perfil normativo; **depois** edite/atribua a escala especial. Na implementação atual, a tela de servidores processa a atribuição da escala antes do vínculo normativo quando ambos são informados no mesmo salvamento; essa sequência pode gerar aviso indevido. Não altere o regime jurídico para contornar o problema.

## 4. Atribuir a escala 12x36 ou 24x72

1. Acesse **Horários & Escalas** (`/admin/shifts`) e escolha um modelo compatível.
2. Verifique os horários, intervalo, data-âncora e a vigência.
3. Selecione o servidor e a escala no fluxo **Atribuir Escala**.
4. Confira a indicação de conformidade normativa apresentada na interface.
5. Conclua somente quando houver perfil aprovado, vigente, compatível com o regime e autorizando a modalidade.

**Exemplos de mensagens:**

| Indicação | Significado | Ação |
|---|---|---|
| Autorizada | Condições normativas verificadas | Prosseguir |
| Pendente de configuração | Perfil adequado não encontrado | Cadastrar e vincular perfil |
| Rascunho | Perfil ainda não homologado | Revisar e aprovar |
| Revogado | Perfil não pode autorizar novas escalas | Verificar substituição normativa |
| Fora da vigência | Data da escala não coberta | Corrigir data ou utilizar perfil vigente |
| Não autorizada | Perfil não prevê a modalidade | Conferir fundamento legal e configuração |

O **registro de ponto permanece uma operação distinta** da autorização da escala. Uma escala recusada não deve ser contornada com cadastro falso.

## 5. Alterar regras sem corromper o histórico

- **Editar rascunho:** antes da aprovação, revise campos e parâmetros.
- **Criar nova versão:** quando uma norma for alterada, use a opção de versionamento; ela cria outra versão em rascunho, em vez de sobrescrever silenciosamente o perfil aprovado.
- **Revogar:** informe fundamentação obrigatória. A revogação bloqueia novas autorizações associadas ao perfil, preservando referências históricas.
- **Pesquisar e filtrar:** use os filtros de regime jurídico, situação, vigência e busca textual para localizar perfis.

Gestores podem consultar informações pertinentes à sua equipe nos limites autorizados, mas **não podem criar, homologar ou conceder perfis normativos**.

## 6. Dúvidas frequentes

**O servidor é estatutário municipal e a tela diz “perfil normativo aprovado não encontrado”.** Cadastre e aprove um perfil municipal fundamentado na norma aplicável, associe-o ao servidor e confira a data de vigência.

**O perfil existe, mas a escala continua recusada.** Verifique o regime, a aprovação, as datas, a entidade aplicável e se a modalidade `12x36` ou `24x72` está permitida na configuração.

**Posso usar o perfil CLT em servidor municipal?** Não apenas para liberar a escala. O perfil deve corresponder ao vínculo e ao respaldo normativo aplicável.

**Uma mudança hoje muda meses fechados?** A arquitetura preserva atribuições versionadas e snapshots de competência fechada; não tente corrigir histórico alterando parâmetros antigos.

**Quando pedir apoio técnico?** Quando perfil aprovado e vigente for corretamente vinculado, mas a atribuição continuar recusada: informe ID do servidor, regime, perfil, data da escala e mensagem exibida (não inclua senhas ou documentos sensíveis em chamados).
