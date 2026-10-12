---
id: modulos-avancados-e-governanca
title: Módulos Avançados, Governança e Fiscalização MTE
subtitle: Administrador e Auditor
category: avancado
profiles:
  - admin
  - auditor
icon: shield-check
keywords: fiscalizacao, afd, aej, portaria 671, auditoria, politicas, calendario, banco de horas, verificacao publica, hashes
order: 10
featured: false
summary: Documentação técnica dos módulos de auditoria, políticas de compensação, calendário laboral, verificação pública e exportações fiscais da Portaria 671/2021 MTP.
---

## 1. Distinção entre Módulos Rotineiros e Técnicos

O PontoFácil separa claramente as tarefas do dia a dia (bater ponto, espelho, conferência e relatórios) dos módulos administrativos e técnicos de retaguarda:

* **Módulos Rotineiros (Frequentes):** Bater Ponto, Espelho de Ponto, Folha de Ponto, Solicitações de Ajuste, Conferência Mensal e Relatórios.
* **Módulos Técnicos (Configuração e Fiscalização):** Políticas de Compensação, Banco de Horas, Calendário Laboral, Auditoria do Sistema, Fiscalização e Verificação Pública de Comprovantes.

Abaixo está o guia de operação de cada módulo técnico disponível para os perfis autorizados.

---

## 2. Políticas de Jornada e Compensação (`/admin/settlement-policies`)

O módulo **Políticas de Compensação** (`/admin/settlement-policies`) permite ao RH definir como as diferenças de horas (saldos positivos e negativos) devem ser tratadas para cada grupo de servidores:

* **Escopo de Aplicação:** A política pode ser configurada para:
  * Toda a Empresa/Órgão (Padrão Global).
  * Um Estabelecimento específico (ex: Filial ou Secretaria).
  * Um Colaborador individual (ex: cargo de direção com regra diferenciada).
* **Modalidades Disponíveis:**
  1. *Sem Banco de Horas (Apuração Direta):* Toda diferença apurada fica registrada no Espelho de Ponto e no Relatório de Frequência, sem acumulação para períodos futuros.
  2. *Compensação no Mês:* As horas extras compensam atrasos dentro da própria competência mensal.
  3. *Banco de Horas Acumulativo:* As horas positivas e negativas acumulam saldo com prazo limite de compensação (ex: 6 meses ou 1 ano).
* **Vigência Temporal:** Toda política possui data de início (`effective_from`) e, opcionalmente, data de término, assegurando que alterações não desfaçam regras aplicadas a competências passadas.

---

## 3. Banco de Horas Opcional (`/admin/time-bank`)

Quando a organização opta por operar banco de horas formal:
* **Extrato do Banco:** O painel lista todas as transações de crédito e débito de cada colaborador em minutos exatos.
* **Ajustes Manuais Auditados:** O RH pode lançar créditos ou débitos manuais excepcionais (por exemplo, compensação de folga concedida por convocação eleitoral). O lançamento exige:
  * Seleção do colaborador e data de referência.
  * Quantidade de minutos.
  * Motivo formal obrigatório (gravado na auditoria).
* **Visualização de Saldos:** Exibe o saldo consolidado de cada servidor, separando claramente colaboradores com banco ativo daqueles sem banco de horas (`Não aplicável`).

---

## 4. Calendário Laboral e Feriados (`/admin/calendar`)

No módulo **Calendário Laboral** (`/admin/calendar`), o RH parametriza dias não úteis institucionais:
* **Feriados Nacionais, Estaduais e Municipais:** Cadastro de datas que suspendem a exigência de jornada normal para os trabalhadores administrativos.
* **Pontos Facultativos e Recessos:** Registro de decretos municipais ou portarias de recesso administrativo.
* **Escopo por Estabelecimento:** Permite cadastrar feriados municipais que afetam apenas uma unidade em cidade específica sem impactar outras filiais da organização.
* *Atenção aos Plantonistas:* No PontoFácil, escalas de plantão 12x36 e 24x72 continuam operando normalmente em feriados caso estejam programadas no ciclo, conforme as normas aplicáveis à continuidade de serviços públicos essenciais.

---

## 5. Auditoria Interna do Sistema (`/admin/audit`)

O módulo de **Auditoria** (`/admin/audit`) atua como a "caixa preta" de segurança do PontoFácil:
* **Rastreabilidade Completa:** Todo evento crítico do sistema é registrado permanentemente na tabela `audit_logs`.
* **O que é auditado:**
  * Inclusões, edições e exclusões de funcionários e setores.
  * Aprovação e rejeição de solicitações de tratamento de ponto (com identificação do avaliador e justificativa).
  * Fechamento e reabertura formal de competências mensais.
  * Ajustes manuais de saldo de banco de horas.
  * Alterações em configurações institucionais e rotação de QR Code.
* **Metadados Gravados:** Usuário responsável, endereço IP de conexão, data e hora com carimbo de tempo, valores anteriores (`old_values`) e novos valores (`new_values`).

---

## 6. Fiscalização Trabalhista e Arquivos AFD / AEJ (`/admin/fiscalizacao`)

Exclusivo para o **Administrador de RH** e para o **Auditor Fiscal**, o módulo `/admin/fiscalizacao` atende às exigências de fiscalização do Ministério do Trabalho e Emprego (Portaria 671/2021 MTP):

* **Arquivo Fonte de Dados (AFD):** Arquivo texto estritamente regulamentado contendo todas as marcações originais do registrador eletrônico de ponto (REP-P), com NSRs sequenciais, CPFs dos trabalhadores, horários locais e hashes SHA-256.
* **Arquivo Eletrônico de Jornada (AEJ):** Arquivo estruturado gerado pelo programa de tratamento de ponto (PTRP) com o detalhamento das jornadas cumpridas, escalas previstas e tratamentos aplicados na competência fechada.
* **Download do Pacote de Fiscalização:** Permite baixar o pacote compactado contendo o AFD e o AEJ do estabelecimento para entrega aos Auditores Fiscais do Trabalho.

> **Transparência Regulatória:** O PontoFácil disponibiliza a estrutura técnica e os leiautes exigidos pela Portaria 671/2021. Documentos assinados com certificados digitais ICP-Brasil dependem da instalação e configuração do certificado digital A1 corporativo da própria organização no servidor.

---

## 7. Verificação Pública de Comprovantes (`/verificar-comprovante`)

A rota `/verificar-comprovante` é uma página pública (acessível sem necessidade de login):
1. O trabalhador, sindicato ou auditor pode digitar o **Código de Autenticidade** que consta impresso no rodapé de qualquer comprovante de ponto (formato `PF-XXXX-XXXX-XXXX`).
2. O sistema realiza a busca imediata no banco de dados e calcula a integridade do **Hash SHA-256**:
   * **Se autêntico:** Exibe o selo verde *Registro Autêntico e Íntegro no PontoFácil*, mostrando o nome do trabalhador, dados do empregador, NSR monotônico, horário exato da batida e o hash fiscal.
   * **Se adulterado ou inexistente:** Exibe aviso de não localização do código.
3. Isso garante que qualquer comprovante impresso ou em PDF possa ter sua autenticidade comprovada em qualquer momento sem intermediários.
