# MVP-SCOPE.md

## O que ESTÁ INCLUSO (Aprovado)
- **Registro de Ponto com Validação Dupla:** Leitura de QR Code Físico + Coordenadas de Geolocalização.
- **Autenticação:** Baseada em e-mail e perfis de usuário (Admin e Funcionário).
- **Espelho de Ponto Mensal:** Listagem consolidada para visualização (sem cálculo automático complexo de atrasos).
- **Ajuste Manual e Auditoria:** RH pode corrigir batidas ausentes mediante registro de justificativa.
- **Single-Tenant:** O sistema roda para uma única matriz de empresa (1 CNPJ principal).

## O que NÃO ESTÁ INCLUSO (Postergado para V2)
- Módulo SaaS Multi-tenant.
- Integração com Relógios de Ponto Físicos (REPs convencionais via API).
- Cálculos automáticos de Banco de Horas, Horas Extras, DSR e Adicional Noturno.
- Emissão de espelho de ponto em PDF com assinatura digital qualificada ICP-Brasil (REP-P).
- Reconhecimento Facial.

## Métricas de Sucesso do MVP
- **Técnica:** O sistema impede com 100% de eficácia que uma requisição POST adulterada manipule o horário da batida (o servidor sempre decide a hora).
- **Usuário:** Um funcionário consegue abrir o sistema, ler o QR Code e registrar o ponto em menos de 10 segundos.