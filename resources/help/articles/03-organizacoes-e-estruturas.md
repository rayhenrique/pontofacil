---
id: organizacoes-e-estruturas
title: Tipos de Organizações e Estrutura Hierárquica
subtitle: Arquitetura Institucional
category: organizacao
profiles:
  - admin
  - manager
icon: square-3-stack-3d
keywords: prefeitura, orgaos, secretarias, clt, estatutario, estabelecimento, setor, servidor, usuario, hierarquia, single tenant
order: 3
featured: false
summary: Como estruturar prefeituras, órgãos estaduais/federais, autarquias, fundações e empresas privadas no PontoFácil respeitando a hierarquia do domínio.
---

## 1. Instância Dedicada (Single-Tenant)

O PontoFácil adota um modelo de **Instância Dedicada por Organização**. Isso significa que cada prefeitura, fundação, autarquia ou empresa opera em um ambiente independente:

* **Isolamento Total:** Os dados de servidores, folhas de ponto, hashes fiscais e comprovantes não são misturados com nenhuma outra entidade.
* **Segurança e Conformidade LGPD:** As informações ficam restritas aos servidores públicos e colaboradores autorizados da própria organização.
* **Múltiplos Estabelecimentos Internos:** Quando uma entidade possui secretarias em endereços diferentes, hospitais ou unidades descentralizadas, essas divisões são estruturadas **internamente** como estabelecimentos e setores, e não como instâncias separadas.

---

## 2. A Hierarquia Institucional do PontoFácil

A modelagem de dados do sistema segue cinco níveis bem articulados:

```
[ Empresa / Órgão Superior ]  ──► Entidade jurídica máxima (CNPJ central, Razão Social)
             │
[ Estabelecimentos ]         ──► Unidades físicas com CNPJ/Filial e Fuso Horário próprio
             │
[ Setores de Lotação ]       ──► Departamentos, secretarias ou postos de trabalho
             │
[ Servidores / Funcionários ]──► Vínculo funcional (Matrícula, Cargo, Regime e Escala)
             │
[ Usuários de Acesso ]       ──► Credencial de login (E-mail, Senha e Papel de Acesso)
```

### Detalhamento dos Níveis:

1. **Empresa / Órgão Superior:**
   * Representa a pessoa jurídica central (ex: *Município de Exemplo* ou *Empresa Comercial S.A.*).
   * Armazena os parâmetros globais de logotipo, cartaz geral de QR Code e política padrão de banco de horas.
2. **Estabelecimento:**
   * Representa uma filial, secretaria com CNPJ próprio ou unidade administrativa com localização geográfica e **fuso horário** específico.
   * Cada estabelecimento possui sua própria numeração sequencial monotônica e contínua de registros de ponto (**NSR**).
3. **Setor de Lotação:**
   * Representa a subdivisão de trabalho (ex: *Secretaria Municipal de Saúde*, *Pronto-Socorro 24h*, *Escola Municipal Santos Dumont*, *Setor de Obras e Transporte*).
   * Possui um **Gestor Responsável** associado, que supervisiona os colaboradores daquele setor.
   * Pode utilizar o QR Code e o GPS globais da organização ou ter **QR Code e perímetro GPS próprios**.
4. **Servidor / Colaborador (`Employee`):**
   * Representa o vínculo de trabalho fático.
   * Contém número de matrícula funcional, CPF, telefone, regime jurídico (CLT ou Estatutário), tipo de vínculo, cargo, carga horária e a escala de trabalho ativa com data de vigência.
5. **Usuário de Acesso (`User`):**
   * Representa a credencial de segurança para entrar no sistema.
   * Contém e-mail corporativo, senha criptografada e o papel de permissão (`employee`, `manager`, `admin`, `auditor`).
   * Um usuário do tipo `employee` está associado ao seu respectivo cadastro funcional de servidor.

---

## 3. Aplicação em Diferentes Tipos de Organizações

### A. Prefeituras e Secretarias Municipais
* **Empresa / Órgão:** Razão Social = *Prefeitura Municipal de Exemplo*, CNPJ = CNPJ da Prefeitura.
* **Campos Governamentais:** Órgão = *Prefeitura Municipal*, Sub-entidade = *Secretaria Geral*, Linha Superior = *Estado de Alagoas*.
* **Estabelecimentos:** Sede Administrativa, Fundo Municipal de Saúde (CNPJ próprio da Saúde) e Fundo Municipal de Educação.
* **Setores:**
  * Sede: *Gabinete do Prefeito*, *Secretaria de Finanças*, *Recursos Humanos*.
  * Saúde: *Unidade Básica de Saúde Central*, *Hospital Municipal*, *Vigilância Sanitária*.
  * Educação: *Secretaria de Educação*, *Creche Municipal*, *Escolas Municipais*.
* **Regimes dos Servidores:** Estatutário Municipal para concursados efetivos, Contratado para PSS e Comissionado para cargos de confiança.

### B. Órgãos Estaduais e Federais (Autarquias e Fundações)
* **Empresa / Órgão:** Razão Social = *Instituto de Previdência do Estado* ou *Fundação Estadual de Saúde*.
* **Regimes dos Servidores:** Estatutário Estadual ou Federal.
* **Setores:** Lotações conforme a estrutura regimental e organograma do órgão público.

### C. Empresas Privadas (Regime CLT)
* **Empresa / Órgão:** Razão Social e Nome Fantasia comercial da empresa privada.
* **Estabelecimentos:** Matriz e Filiais comerciais/operacionais.
* **Setores:** *Administrativo*, *Operacional*, *Vendas*, *TI*, *Logística*.
* **Regimes dos Colaboradores:** Exclusivamente CLT (com aplicação de acordos e convenções coletivas de trabalho).

---

## 4. O que NUNCA fazer na Estruturação

* ❌ **Não cadastrar setores duplicados para a mesma função:** Mantenha os setores centralizados e use a vinculação de gestores para delegar a administração.
* ❌ **Não criar servidores sem setor:** Todo servidor precisa de um setor de lotação para que os relatórios e espelhos consolidem a hierarquia administrativa corretamente.
* ❌ **Não misturar fusos horários no mesmo estabelecimento:** Se uma organização tiver unidades em fusos horários diferentes (ex: Brasília e Manaus), cada unidade deve estar configurada no seu respectivo estabelecimento.
