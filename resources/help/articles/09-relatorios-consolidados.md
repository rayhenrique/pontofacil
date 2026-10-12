---
id: relatorios-consolidados
title: Relatórios Mensais Consolidados de Frequência
subtitle: Administrador
category: relatorios
profiles:
  - admin
  - manager
icon: document-chart-bar
keywords: relatorios, consolidado, pdf, csv, exportacao, encaminhamento, folha de pagamento, previa provisoria, fechamento, diferenca
order: 9
featured: true
summary: Como emitir relatórios de frequência em PDF e CSV UTF-8 e registrar o encaminhamento oficial ao setor responsável pelo pagamento.
---

## 1. Finalidade do Relatório Consolidado de Frequência

O módulo **Relatórios** (`/admin/reports`) é a ferramenta oficial do PontoFácil para consolidar as informações de frequência de todos os servidores da organização e disponibilizá-las para o setor responsável pelo pagamento (Departamento Pessoal, Recursos Humanos ou Contabilidade Pública).

> ⚠️ **Regra Fundamental de Produto:**  
> O Relatório do PontoFácil informa exclusivamente **dados físicos de frequência e cumprimento de jornada** (horas previstas, horas trabalhadas, saldos de minutos, faltas, atrasos, horas noturnas e plantões).  
> O PontoFácil **NÃO calcula valores monetários, vencimentos, descontos em reais nem emite folha salarial**. O cálculo financeiro e o pagamento bancário ocorrem no sistema de Folha de Pagamento do órgão ou empresa.

---

## 2. Diferença entre Prévia Provisória e Competência Fechada

Ao acessar o relatório, verifique a tarja de status no topo:

### Cenário A: Prévia Provisória (Competência em Aberto)
* Ocorre quando você consulta o relatório do mês corrente antes do fechamento formal.
* O documento exibe a tarja em âmbar: **Prévia Provisória — Competência em Aberto**.
* **Utilidade:** Permite que o RH e gestores acompanhem a tendência do mês, identifiquem antecipadamente servidores com muitas faltas e orientem a equipe antes do encerramento da competência.
* **Aviso de Advertência:** O cabeçalho declara abertamente que os dados são parciais e sujeitos a alteração até o fechamento.

### Cenário B: Competência Fechada (Versão Definitiva Consolidada)
* Ocorre após o Administrador de RH ter fechado o mês na tela de Conferência Mensal.
* O documento exibe a tarja em verde: **Competência Fechada (Snapshot v1)**.
* Os dados refletem com precisão absoluta os snapshots imutáveis homologados, sendo este o documento oficial apto para envio formal ao setor de folha de pagamento.

---

## 3. Filtros Disponíveis no Relatório

Para gerar o documento com o recorte desejado:
1. **Competência:** Selecione o Mês e Ano de apuração.
2. **Estabelecimento:** Filtre por uma unidade específica ou deixe em *Todos os Estabelecimentos*.
3. **Setor / Lotação:** Filtre por uma secretaria ou departamento específico (ex: *Secretaria Municipal de Saúde*).
   * *Para Gestores:* O seletor restringe a visualização automaticamente aos setores sob sua supervisão.
4. **Servidor Específico (Opcional):** Permite filtrar um único colaborador pelo nome ou CPF para conferência individualizada.

---

## 4. O que Contém a Grade Consolidada

A tabela apresenta uma linha por servidor com todos os parâmetros exigidos para integração administrativa:

* **Servidor / Matrícula / CPF:** Identificação civil e funcional protegida.
* **Vínculo e Cargo:** Regime jurídico (CLT, Estatutário), tipo de vínculo (Efetivo, PSS, Comissionado) e cargo/função.
* **Lotação e Escala:** Setor de trabalho e jornada vinculada.
* **Carga Prevista:** Total de horas esperadas no mês (formato `HH:MM`).
* **Horas Trabalhadas:** Total de horas físicas apuradas no relógio.
* **Diferença:** Saldo apurado (positivo com badge verde, negativo com badge âmbar).
* **Faltas e Atrasos:** Quantidade de faltas integrais e atrasos parciais no período.
* **Horas Noturnas:** Horas físicas trabalhadas entre 22h e 05h com identificação da equivalência legal.
* **Plantões:** Quantidade de plantões realizados (quando aplicável).
* **Conferência e Ressalva:** Status da validação pela chefia com texto da ressalva fundamentada (se houver).
* **Atalho ao Espelho:** Link rápido para abrir o Espelho de Ponto detalhado do colaborador em caso de dúvida.

---

## 5. Exportação em PDF e CSV UTF-8

No topo da página, existem dois botões de exportação de dados:

### Exportação em PDF (A4 Paisagem)
1. Clique no botão **Imprimir / PDF**.
2. O sistema abrirá uma janela limpa formatada especificamente em formato **A4 Paisagem (`landscape`)**.
3. O documento contém:
   * Brasão / Logotipo institucional e dados da organização no cabeçalho.
   * Identificação da competência e versão do fechamento.
   * Grade de servidores com paginação e repetição de cabeçalho.
   * Campo formal para assinatura do responsável pela emissão e do Diretor de RH.
4. Você pode imprimir diretamente ou selecionar *Salvar como PDF* no navegador.

### Exportação em CSV (UTF-8)
1. Clique no botão **Exportar CSV**.
2. O sistema baixará imediatamente um arquivo com extensão `.csv` codificado em **UTF-8 com BOM** (compatível com Microsoft Excel, LibreOffice Calc e sistemas de folha de pagamento).
3. O arquivo possui cabeçalhos claros sem abreviações confusas:
   * `cpf`, `matricula`, `nome_servidor`, `setor`, `cargo`, `regime`, `horas_previstas`, `horas_trabalhadas`, `diferenca_minutos`, `faltas`, `atrasos_minutos`, `horas_noturnas_minutos`, `status_conferencia`, `ressalvas`.

---

## 6. Como Registrar o Encaminhamento ao Setor de Pagamento

Para garantir governança e comprovação de entrega administrativa:

1. Após conferir o relatório e gerar o arquivo PDF ou CSV, clique no botão **Registrar Encaminhamento** (no topo ou no final da página).
2. No modal **Registrar Encaminhamento à Folha**:
   * O sistema registra automaticamente a competência, a data/hora do despacho e o usuário responsável.
   * Se a competência estiver aberta, o sistema registrará com a etiqueta de aviso `Provisório`. Se fechada, registrará como `Definitivo`.
   * No campo **Observações / Destinatário (Opcional)**, registre o número do processo administrativo ou e-mail de destino (ex: *Encaminhado ao Departamento de Folha de Pagamento via Processo SEI nº 2026.00123*).
3. Clique em **Confirmar Encaminhamento**.
4. O registro é adicionado permanentemente à tabela **Histórico de Emissões e Encaminhamento**, permitindo consultar a qualquer momento quem enviou o relatório, quando enviou e para qual destinatário.
