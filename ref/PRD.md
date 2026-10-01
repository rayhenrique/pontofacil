# PRD.md (Product Requirements Document)

## 1. Visão Geral
Sistema de controle de ponto single-tenant aderente à Portaria 671 (foco em REP-A - Registro Alternativo). O objetivo é garantir um registro de ponto antifraude, combinando a leitura de um QR Code físico no local de trabalho com validação de geolocalização do dispositivo do funcionário.

## 2. Perfis de Acesso (Roles)
- **Admin/RH (`admin`):** Acesso total. Pode visualizar espelhos de todos os funcionários, cadastrar usuários, gerar/atualizar o QR Code da empresa e realizar ajustes manuais de ponto com justificativa.
- **Funcionário (`employee`):** Acesso restrito. Pode apenas registrar o próprio ponto e visualizar seu espelho mensal (histórico).

## 3. Requisitos Funcionais (Core Features MVP)
- **RF01 - Autenticação:** Login simples via e-mail e senha.
- **RF02 - Registro de Ponto (Smart Punch):**
  - Interface web responsiva acessando a câmera via `html5-qrcode` (ou similar).
  - Captura obrigatória das coordenadas GPS (`navigator.geolocation`).
  - O sistema só registra o ponto se o QR Code lido bater com o hash atual da empresa.
- **RF03 - Validação de Horário (Server-side):** O timestamp da batida de ponto deve ser gerado pelo banco de dados/servidor, ignorando o horário enviado pelo cliente.
- **RF04 - Espelho de Ponto (Timesheet):** Listagem mensal agrupada por dia mostrando as batidas (Entrada/Saída).
- **RF05 - Ajuste Manual de Ponto:** O RH pode inserir ou corrigir uma batida de ponto retroativa.
- **RF06 - Trilha de Auditoria:** Todo ajuste manual deve exigir uma justificativa em texto e salvar quem fez a alteração, o horário antigo e o novo.

## 4. Requisitos Não-Funcionais
- **Segurança:** O sistema requer ambiente HTTPS para acessar a câmera e o GPS.
- **Performance:** As consultas de espelho de ponto devem ser indexadas por `user_id` e `date`.