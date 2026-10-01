# MVP-SCOPE.md (Atualizado)

## O que ESTÁ INCLUSO (Aprovado e Implementado)
- **Registro de Ponto com Validação Dupla:** Leitura de QR Code Físico + Coordenadas de Geolocalização (GPS) com validação de raio via Haversine.
- **Autenticação e Perfis (Roles):** Três níveis de acesso bem definidos: Administrador (`admin`), Gestor de Setor (`manager`) e Colaborador (`employee`).
- **Gestão de Setores & Equipes:** CRUD de setores com atribuição de gestor responsável e permissão para gestores cadastrarem exclusivamente em seus setores.
- **Configurações da Empresa & QR Code:** Painel do Admin para visualizar, imprimir e rotacionar o QR Code físico oficial, além de calibrar a posição GPS da sede e o raio permitido.
- **Espelho de Ponto Mensal (Timesheet):** Histórico de jornadas agrupado por dia, com visualização para colaboradores, gestores de equipe e administradores.
- **Ajuste Manual e Auditoria:** RH pode efetuar lançamentos manuais mediante justificativa formal registrada na trilha de auditoria.
- **Relatórios Gerenciais:** Filtros por período de datas, setor e colaborador para conferência de jornada.
- **Interface Mobile-First:** Barra de navegação inferior (Bottom Tab Bar), gaveta lateral (Off-Canvas Drawer), tabelas com rolagem horizontal e relógio digital oficial.
- **Localização Completa:** Sistema 100% em Português do Brasil (PT-BR) e fuso horário oficial `America/Maceio` (GMT-3).
- **Single-Tenant:** O sistema roda para uma única matriz de empresa (1 CNPJ principal).

## O que NÃO ESTÁ INCLUSO (Postergado para V2)
- Módulo SaaS Multi-tenant (múltiplas empresas assinantes no mesmo banco).
- Integração direta via API com relógios de ponto biométricos físicos convencionais (REP-C).
- Cálculos avançados automáticos de folha (banco de horas, adicional noturno, DSR, DSR sobre horas extras).
- Emissão de espelho de ponto em PDF assinado com certificado digital ICP-Brasil (REP-P).
- Reconhecimento Facial por biometria de imagem.

## Métricas de Sucesso do MVP
- **Técnica:** O sistema impede com 100% de eficácia que uma requisição POST adulterada manipule o horário da batida (o servidor sempre decide a hora no fuso de Maceió).
- **Usuário:** Um colaborador consegue abrir o sistema no celular, escanear o QR Code físico e registrar o ponto em menos de 10 segundos.