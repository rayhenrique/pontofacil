---
id: solucao-de-problemas-operacionais
title: Solução de Problemas — Registro, Escalas, Ajustes e Relatórios
subtitle: Diagnóstico por mensagem e situação
category: visao-geral
profiles:
  - employee
  - manager
  - admin
  - auditor
icon: life-buoy
keywords: problemas, duvida, erro, ajuda, suporte, corrigir, plantao, perfil normativo, gps, folha de ponto, comprovante, ajuste, senha, relatorio, fechamento
order: 14
featured: true
summary: Consulte um roteiro rápido para resolver os problemas mais comuns sem apagar batidas, modificar registros fiscais ou perder histórico.
---

## 1. Diagnóstico rápido

| O que aconteceu? | Causa provável | Próximo passo |
|---|---|---|
| QR Code não autorizado | Código global antigo ou código de outro setor | Consultar o RH e escanear o cartaz do setor correto |
| Câmera não inicia | Permissão negada, HTTPS ausente ou outra aplicação usando a câmera | Revisar permissões e testar navegador atualizado |
| GPS obrigatório bloqueou a batida | Fora do perímetro, localização ausente ou precisão insuficiente | Verificar mensagem e pedir conferência da configuração ao RH |
| Rede caiu após escanear | Confirmação não chegou ao navegador | Usar **Verificar se foi registrado** antes de repetir |
| Batida efetuada, comprovante indisponível | Falha na geração do comprovante | Anotar horário e NSR e informar RH/TI; não bater novamente só por isso |
| Funcionário não aparece na listagem do gestor | Acesso limitado à lotação principal do empregado | Confirmar setor/gestor com RH |
| Plantão 12x36 recusado por norma | Perfil normativo ausente, incompatível ou sem vigência | Ver [Perfis normativos](/ajuda?artigo=perfis-normativos-e-autorizacao) |
| Horas do espelho não batem | Escala errada, tratamento pendente ou marcações inconsistentes | Conferir a linha do dia e solicitar correção |
| Fechamento mensal bloqueado | Pendências de conferência, escala ou tratamento | Abrir [Conferência mensal](/ajuda?artigo=conferencia-mensal-e-fechamento) |
| Relatório não reflete o ajuste novo | Prévia anterior ou conferência desatualizada | Reabrir a apuração atual e revisar conferência conforme status |

## 2. Esqueceu de bater na entrada

Exemplo: o servidor trabalhou das 08:00 às 12:00 e das 13:00 às 17:00, mas esqueceu a batida das 08:00. As batidas às 12:00, 13:00 e 17:00 podem ter sido inicialmente classificadas como entrada, saída e entrada.

1. Abra `/timesheet` no dia correto e solicite **Incluir Entrada** às 08:00, com justificativa real.
2. Aguarde a decisão do gestor ou RH, conforme autorização.
3. Após aprovação, confira **a sequência completa do dia e o total de horas**.

**Limitação atual:** a v2.6.20 acrescenta a marcação tratada à apuração, mas ainda não garante reclassificar automaticamente todas as direções das batidas seguintes. Se surgirem duas entradas consecutivas ou jornada incompleta, **não presuma que as 8h foram reconhecidas**: encaminhe ao RH para análise e correção formal. As marcações originais devem ser preservadas.

## 3. Justificativa informada na data errada

- Para solicitação **pendente**, o fluxo de cancelamento permite desfazer a solicitação autorizada, informando motivo.
- Para tratamento **aprovado**, a ação de reversão administrativa está disponível para usuários com permissão, com justificativa auditável.
- Competências **fechadas** não permitem cancelar ou reverter diretamente; o RH deve avaliar a reabertura formal, se cabível.
- Depois de cancelar ou reverter o registro incorreto, faça uma **nova solicitação na data correta**. Não apague o histórico original.

Consulte [Espelho, comprovantes e correções](/ajuda?artigo=espelho-comprovantes-e-correcoes).

## 4. Problemas em plantões noturnos e jornadas que atravessam meia-noite

Os plantões 12x36/24x72 formalmente atribuídos podem continuar depois da meia-noite. Jornadas administrativas não devem unir automaticamente a saída de ontem à entrada de hoje.

Quando a saída de um plantão for esquecida, ou quando houver duas escalas sobrepostas, consulte o quadro de ocorrências e procure o RH. Não use inclusões automáticas fictícias à meia-noite.

## 5. Checklist antes de abrir chamado

Anote, sem expor senhas nem documentos médicos: perfil de acesso, rota/tela, data/competência, mensagem de erro, ação realizada, horário e NSR (se houver). Em problemas de escala, acrescente regime jurídico, nome da escala e início de vigência. Em GPS, descreva se havia permissão e se a mensagem mencionou raio, precisão ou configuração.

**Evite compartilhar CPF integral, senha, coordenadas exatas e anexos médicos em canais públicos.**
