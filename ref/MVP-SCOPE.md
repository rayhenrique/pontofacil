# MVP-SCOPE.md — Escopo e Evolução do PontoFácil (Instância Dedicada)

## 📌 Status Atual: v1.7.0 (Lançamento Oficial)

O PontoFácil opera sob a arquitetura de **Instância Dedicada (Single-Tenant)**: cada cliente possui sua própria infraestrutura em VPS exclusiva, com domínio próprio, banco MySQL dedicado e isolamento total de dados.

---

## ✅ O que ESTÁ IMPLEMENTADO e Homologado (v1.7.0)

1. **Batida de Ponto com Validação Dupla (Smart Punch):**
   - Leitura de QR Code físico corporativo via scanner local no navegador (`html5-qrcode`).
   - Captura mandatória de coordenadas GPS do dispositivo móvel com validação de raio via **Fórmula de Haversine**.
   - Carimbo de tempo oficial cravado unicamente pelo servidor no fuso horário `America/Maceio` (GMT-3).
2. **Estrutura Híbrida Inteligente de Setores (Fallback):**
   - Cada filial ou setor pode definir seu próprio QR Code, coordenadas e raio.
   - Caso estejam em branco, o sistema recorre automaticamente aos parâmetros globais da sede da empresa.
3. **Cadastro Funcional Completo do Servidor:**
   - Modelagem e modal de cadastro/edição de colaboradores contendo:
     - Cargo / Função do servidor.
     - Vínculo Empregatício (Efetivo/Concursado, Temporário, Comissionado, CLT).
     - Carga Horária Semanal (40h, 30h, 20h, etc.).
     - Zona de Lotação (Urbana / Rural).
     - Setor, Matrícula, CPF validado e Telefone.
4. **Folha de Ponto A4 Oficial (Modelo Outubro):**
   - Relatório impresso de folha de ponto fiel ao padrão administrativo oficial do RH (`/folha-ponto`).
   - Cabeçalho preenchido em tempo real ao selecionar o colaborador diretamente dos dados funcionais do banco.
   - Grade de 31 dias para turnos matutino e vespertino com coluna de observações/ocorrências.
   - Campos de assinatura formal do Servidor e da Chefia Imediata com local e data.
   - Estilização para impressão A4 (`@media print`) limpa e sem quebras indesejadas de página.
5. **Landing Page Cinematográfica & Identidade Institucional (`/landing`):**
   - Design Swiss-Modern SaaS com Tailwind CSS v4 e animações de scroll via GSAP 3.12 (ScrollTrigger).
   - Tabs interativas em Alpine.js (Visão Colaborador com simulação de laser scanner e Visão Gestor/RH).
   - Mockup dinâmico de relógio digital ao vivo no Hero.
   - Timeline conectada de versões destacando a versão ativa (`v1.7.0`).
   - Rodapé oficial com link e créditos para a [KL Tecnologia](https://kltecnologia.com).
6. **Espelho de Ponto & Gestão de Jornada (Timesheet):**
   - Agrupamento diário de pares de batidas com totalização de horas.
   - Cards de métricas consolidadas (Total de Horas, Dias Trabalhados e Média Diária).
7. **Sistema Global de Modais Popups:**
   - Feedback visual com modais elegantes para alertas, erros, sucesso e diálogos de confirmação de exclusão.
8. **Autenticação e Perfis Granulares (RBAC):**
   - Administrador / RH (`admin`), Gestor de Setor (`manager`) com módulo exclusivo de Gestão de Equipe, e Colaborador (`employee`).
9. **Configurações da Empresa & QR Code Físico:**
   - Painel de calibração de GPS (com auto-captura) e gerador de folha A4 com o QR Code para fixação na empresa.
10. **Trilha de Auditoria Imutável:**
    - Registro permanente de ajustes manuais do RH com justificativa obrigatória (`time_adjustments`).
11. **Suíte de Testes Automatizados:**
    - 31 testes automatizados cobrindo autorização, regras de cálculo, folha de ponto, camadas modais e changelog.

---

## 🚀 Roadmap de Conformidade Integral — PontoFácil 2.0 (REP-P / PTRP)

A evolução do produto segue rigorosamente a Portaria 671/2021 MTP (leiautes MTE 2026) mantendo a arquitetura de **Instância Dedicada**:

- **Fase 1 (Fundação REP-P e Estabelecimentos):**
  - Modelagem de `Company` (Empresa Única da Instalação) e `Establishments` (Matriz e Filiais com CNPJ/CNO).
  - Contador monotônico e atômico de NSR (`nsr_next`) individual por estabelecimento, protegido com locks de transação.
- **Fase 2 (Comprovante do Trabalhador & Central de Comprovantes):**
  - Geração de Comprovante de Registro de Ponto do Trabalhador em PDF assinado com PAdES.
  - Central de Comprovantes permitindo ao colaborador consultar e baixar todo o seu histórico.
- **Fase 3 (Arquivo Fonte de Dados - AFD):**
  - Exportação do AFD em formato texto posicional (leiaute oficial MTE atualizado em 31/07/2026).
  - Assinatura digital padrão CAdES (.p7s detached).
- **Fase 4 (Motor de Tratamento PTRP & Banco de Horas):**
  - Registro de eventos imutáveis de tratamento (`treatment_events`) para ajustes e abonos sem alterar a batida bruta original.
  - Aplicação da regra legal de tolerância (Art. 58 § 1º CLT: 5 min por batida, até 10 min diários) exclusivamente na fase de apuração do PTRP.
  - Ledger de créditos e débitos do banco de horas com validade e compensação.
- **Fase 5 (Fechamento de Competência & Arquivo Eletrônico de Jornada - AEJ):**
  - Fechamento mensal de competência com congelamento de espelho e hash SHA-256.
  - Exportação do AEJ no leiaute oficial MTE assinado com CAdES (.p7s detached).

---

## 🚫 O que é ESTRITAMENTE VEDADO (Regras de Arquitetura Inegociáveis)

1. **Zero Multi-Tenancy:**
   - Jamais implementar `tenant_id`, middleware de tenant, resolução dinâmica de bancos por tenant, escopos globais de tenant ou papéis de "superadmin SaaS".
   - Cada cliente sempre terá sua própria instalação, banco de dados e servidor.
2. **Não-Bloqueio de Marcação:**
   - O registrador nunca pode recusar uma marcação de ponto por motivo de escala, tolerância, excesso de horas ou falha de conectividade (Art. 74/78 Portaria 671).
3. **Zero Exclusão em Cascata no Histórico Fiscal:**
   - Marcações brutas e eventos passados têm valor probatório permanente; a integridade referencial utiliza `restrictOnDelete`.

---

## 🎯 Métricas de Sucesso

- **Técnica:** O registrador garante 100% de integridade com carimbo de tempo no servidor, sem que o cliente consiga adulterar horário ou quebrar a sequência contínua de NSR.
- **Operacional:** O colaborador registra o ponto via smartphone em menos de 5 segundos.
- **Administrativa:** O setor de RH emite a Folha de Ponto A4 Oficial individual ou em lote pronta para impressão e arquivamento em 1 clique.