---
id: indice-manual-v2620
title: Manual Completo — Comece Aqui (v2.6.20)
subtitle: Índice por perfil e rotina
category: visao-geral
profiles:
  - employee
  - manager
  - admin
  - auditor
icon: book-open-check
keywords: manual completo, indice, guia, introducao, versao 2.6.20, menu, mapa, funcionalidades, comecar
order: 0
featured: true
summary: Encontre rapidamente todas as funções disponíveis na v2.6.20, os caminhos no menu e os limites das funcionalidades em desenvolvimento.
---

## Bem-vindo ao manual do PontoFácil

**Edição:** 22/10/2026 — **versão documentada:** v2.6.20 (branch master do repositório). Este manual descreve a interface existente, as permissões por perfil e os processos operacionais. A disponibilidade de opções depende do perfil de acesso e da configuração institucional.

**Comece pelo seu perfil:**

| Perfil | Por onde começar | O que faz |
|---|---|---|
| Servidor/colaborador | [Registrar ponto](/ajuda?artigo=como-registrar-ponto) e [espelho/ajustes](/ajuda?artigo=espelho-comprovantes-e-correcoes) | Marca ponto, consulta horários e comprovantes, solicita correções |
| Gestor | [Horários e plantões](/ajuda?artigo=horarios-jornadas-e-plantoes) e [conferência mensal](/ajuda?artigo=conferencia-mensal-e-fechamento) | Gerencia a equipe autorizada e analisa sua frequência |
| RH/administrador | [Configuração inicial](/ajuda?artigo=configuracao-inicial-rh), [perfis normativos](/ajuda?artigo=perfis-normativos-e-autorizacao) e [fechamento](/ajuda?artigo=conferencia-mensal-e-fechamento) | Parametriza organização, escalas, tratamentos, normas e competências |
| Auditor | [Governança e fiscalização](/ajuda?artigo=modulos-avancados-e-governanca) | Consulta recursos e arquivos fiscais nos limites do perfil |

## Mapa das funções e caminhos de acesso

| Função | Endereço no sistema | Quem utiliza |
|---|---|---|
| Login | `/login` | Todos |
| Registrar ponto | `/ponto` | Usuário autenticado |
| Espelho de ponto | `/timesheet` | Servidor, gestor e RH conforme escopo |
| Folha mensal para impressão | `/folha-ponto` | Usuários autorizados |
| Central de comprovantes | `/comprovantes` | Usuários autenticados conforme escopo |
| Central de ajuda, FAQ, glossário e versões | `/ajuda` | Todos |
| Cadastro de servidores | `/admin/employees` | RH e gestores autorizados |
| Horários, atribuições e plantões | `/admin/shifts` | RH e gestores autorizados |
| Solicitações de tratamento | `/admin/treatment-requests` | RH e gestores autorizados |
| Ajustes e correções | `/admin/adjustment` | RH |
| Conferência mensal e fechamento | `/admin/monthly-review` | Gestão; fechamento conforme autorização |
| Relatórios consolidados | `/admin/reports` | RH e gestores autorizados |
| Setores e QR próprios | `/admin/sectors` | RH |
| Usuários e perfis de acesso | `/admin/users` | RH |
| Configurações institucionais, QR e GPS global | `/admin/settings` | RH |
| Perfis normativos e autorizações | `/admin/labor-rule-profiles` | RH |
| Calendário laboral | `/admin/calendar` | RH |
| Políticas de compensação | `/admin/settlement-policies` | RH |
| Banco de horas | `/admin/time-bank` | RH |
| Auditoria | `/admin/audit` | RH |
| Fiscalização e exportação de arquivos | `/admin/fiscalizacao` | RH e auditor |
| Verificação de código do comprovante | `/verificar-comprovante` | Consulta pública com código |

## Artigos organizados por tarefa

**Primeira utilização e cadastro**
1. [Apresentação e conceitos](/ajuda?artigo=apresentacao-e-visao-geral).
2. [Configuração inicial do RH](/ajuda?artigo=configuracao-inicial-rh).
3. [Órgãos, estabelecimentos e setores](/ajuda?artigo=organizacoes-e-estruturas).
4. [Usuários, senhas e permissões](/ajuda?artigo=usuarios-acessos-e-permissoes).
5. [Cadastro de servidores e vínculos](/ajuda?artigo=servidores-e-funcionarios).

**Jornada diária e apuração**
6. [Horários, jornadas e plantões](/ajuda?artigo=horarios-jornadas-e-plantoes).
7. [Perfis normativos e autorização de 12x36/24x72](/ajuda?artigo=perfis-normativos-e-autorizacao).
8. [Como registrar ponto](/ajuda?artigo=como-registrar-ponto).
9. [GPS e QR Code por setor](/ajuda?artigo=gps-e-qr-por-setor).
10. [Espelho, comprovantes, correções e reversão](/ajuda?artigo=espelho-comprovantes-e-correcoes).

**Encerramento, relatórios e governança**
11. [Conferência e fechamento mensal](/ajuda?artigo=conferencia-mensal-e-fechamento).
12. [Relatórios consolidados](/ajuda?artigo=relatorios-consolidados).
13. [Banco de horas, calendário, auditoria e fiscalização](/ajuda?artigo=modulos-avancados-e-governanca).
14. [Celular, navegador e conexão](/ajuda?artigo=instalacao-no-celular-e-pwa).
15. [Solução de problemas e situações frequentes](/ajuda?artigo=solucao-de-problemas-operacionais).

Use também as abas **Perguntas Frequentes**, **Glossário** e **Atualizações** da Central de Ajuda.

## O que ainda não deve ser tratado como função pronta

- **PWA instalável completo/offline:** nesta versão o acesso principal é pelo navegador; adicionar à tela inicial é um atalho, não garantia de instalação como aplicativo independente. Registrar ponto exige conexão.
- **Assinatura digital ICP-Brasil:** integração real de assinatura fica para etapa futura; não confundir comprovante consultável com comprovante digitalmente assinado.
- **Múltiplos setores por funcionário:** a marcação e as permissões ainda usam principalmente a lotação principal; local distinto por plantão exige evolução.
- **Ocorrências de GPS com decisão administrativa:** atualmente existe opção de bloqueio por setor e registro com aviso quando a trava está desligada; ainda não há workflow próprio de aprovação de ocorrência GPS.
- **Reclassificação em cadeia após aprovar batida esquecida:** ainda não há garantia de recalcular automaticamente o sentido das demais marcações brutas; conferir o resultado e encaminhar inconsistências ao RH.

**Importante:** a autorização jurídica para usar escalas especiais e a conformidade para uso oficial devem ser conferidas pelo responsável da organização. O manual descreve a operação do software, não substitui parecer jurídico, homologação ou testes em produção.
