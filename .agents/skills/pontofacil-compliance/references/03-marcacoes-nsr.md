# 03 - Ledger Imutável, NSR Atômico & Comprovantes PAdES

---

## 1. O Modelo de Marcação: `punch_events` (Sem Tenant)

Toda marcação física realizada pelo trabalhador na empresa gera um registro inalterável na tabela `punch_events` do banco de dados dedicado daquela instalação.

### 1.1 Esquema da Tabela `punch_events`
Nenhuma tabela de marcação possui `tenant_id` ou redundâncias desnecessárias:

```text
id                       ULID PRIMARY KEY

employment_id            BIGINT NOT NULL REFERENCES employments(id)
establishment_id         BIGINT NOT NULL REFERENCES establishments(id)

nsr                      BIGINT NOT NULL (Sequencial próprio do estabelecimento)

occurred_at_utc          TIMESTAMP WITH TIME ZONE NOT NULL (Carimbo UTC unívoco)
occurred_at_local        TIMESTAMP NOT NULL (Horário no fuso do estabelecimento)

timezone                 VARCHAR(64) NOT NULL (ex: "America/Maceio")
utc_offset               VARCHAR(6) NOT NULL  (ex: "-03:00")

source                   VARCHAR(32) (web_pwa, mobile_app, kiosk, biometric)
collector_type           VARCHAR(32) (browser, mobile_device, dedicated_hardware)

latitude                 DECIMAL(10,8) nullable (Criptografia AES-256 em repouso)
longitude                DECIMAL(11,8) nullable (Criptografia AES-256 em repouso)
location_accuracy        DECIMAL(8,2) nullable (Raio de precisão em metros)

qr_location_valid        BOOLEAN nullable (Indica se validou QR Code do local)
location_valid           BOOLEAN nullable (Indica se estava dentro do geofence)

payload_hash             VARCHAR(64) NOT NULL (Hash SHA-256 da carga útil da batida)
previous_event_hash      VARCHAR(64) nullable (Encadeamento com o registro anterior)

created_at               TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

### 1.2 Restrições e Índices de Alta Performance
```sql
-- Restrição única essencial da Portaria 671: NSR estritamente sequencial por estabelecimento
ALTER TABLE punch_events ADD CONSTRAINT uq_establishment_nsr UNIQUE (establishment_id, nsr);

-- Índices otimizados para relatórios e espelhos
CREATE INDEX idx_punch_employment_utc ON punch_events (employment_id, occurred_at_utc);
CREATE INDEX idx_punch_establishment_utc ON punch_events (establishment_id, occurred_at_utc);
CREATE INDEX idx_punch_payload_hash ON punch_events (payload_hash);
```

> **Proibição Absoluta de Modificação:** Não existem rotas, controllers ou métodos de `UPDATE` ou `DELETE` para `punch_events`. Quaisquer exclusões operacionais ou intervenções manuais são tratadas exclusivamente como eventos de tratamento no PTRP.

---

## 2. Número Sequencial de Registro (NSR) por Estabelecimento

No REP-P, o NSR é próprio de cada **estabelecimento**, inicia obrigatoriamente em `1` e avança unitariamente a cada marcação (`1, 2, 3... 999.999.999`).

Exemplo prático:
```text
Estabelecimento Matriz Maceió:
NSR 1
NSR 2
NSR 3

Estabelecimento Filial Arapiraca:
NSR 1
NSR 2
```

### 2.1 Requisitos Críticos de Geração
1. **Nunca usar `MAX(nsr) + 1`:** Consultas agregadas de `MAX()` geram colisões e duplicidades sob batidas simultâneas concorrentes.
2. **Reserva Atômica via Coluna `nsr_next`:** Uso de transação com lock pessimista na linha do estabelecimento:

```sql
-- Reserva atômica segura contra concorrência
UPDATE establishments 
SET nsr_next = nsr_next + 1 
WHERE id = :establishment_id 
RETURNING nsr_next - 1 AS reserved_nsr;
```

3. **Garantia de Idempotência:** Toda requisição de batida envia um identificador único de tentativa (`idempotency_key`). Se a rede oscilar e o app reenviar a requisição, o servidor reconhece a chave e devolve a marcação já gravada com seu NSR original, impedindo duplicidade.

---

## 3. Fluxo de Registro de Ponto

```text
[Autenticação do Usuário na Instalação]
         ↓
[Identificação do Vínculo & Estabelecimento de Lotação]
         ↓
[Captura do Evento (Data/Hora UTC do Servidor + GPS/QR)]
         ↓
[Análise de Contexto e Risco (Antifraude)]
         ↓
[Reserva Atômica do Próximo NSR do Estabelecimento]
         ↓
[Cálculo de Hash SHA-256 e Encadeamento com Evento Anterior]
         ↓
[Persistência Imutável em punch_events]
         ↓
[Emissão do Comprovante Digital PAdES]
         ↓
[Confirmação Instantânea na Tela (< 2 segundos)]
```

### 3.1 UX Minimalista da Tela de Ponto
A interface do colaborador é focada na máxima simplicidade e velocidade:
- Relógio de precisão em tempo real;
- Botão central destacado: **[ REGISTRAR PONTO ]**;
- Resumo do último ponto registrado (hora e NSR);
- Feedback imediato de confirmação com link direto para o comprovante.
- **Não há bloqueio por horário:** Mesmo em sobrejornada ou fora da escala, o ponto é sempre gravado.

---

## 4. Redefinição do QR Code e Geolocalização (Contexto vs Bloqueio)

No PontoFácil 2.0, o GPS e o QR Code deixam de ser travas punitivas e assumem o papel de **mecanismos de contexto, verificação de local e análise de risco**:

```text
Posicionamento Identificado                Classificação no Evento
Dentro do raio do estabelecimento (Geofence)  → qr_location_valid = true, location_valid = true
GPS com baixa precisão / Indoor               → location_valid = true (com aviso de baixa precisão)
Fora do raio geográfico do setor             → location_valid = false (anomalia registrada)
Permissão de GPS negada no navegador          → location_valid = null (não verificado)
```

> **Princípio:** Falhas de satélite ou permissões de navegador **nunca podem impedir o registro de uma jornada de trabalho que efetivamente ocorreu**. O registro é sempre acolhido e a anomalia é sinalizada para análise pelo gestor no PTRP.

---

## 5. Comprovante de Registro de Ponto e Central de Comprovantes

O Art. 79 da Portaria 671 exige disponibilização eletrônica dos comprovantes das últimas 48 horas no mínimo. O PontoFácil 2.0 entrega uma **Central de Comprovantes Permanente**:

- Todo o histórico de batidas do contrato de trabalho fica disponível para consulta e download pelo trabalhador.
- **Formato PDF com Assinatura PAdES:** O arquivo PDF do comprovante contém certificado digital ICP-Brasil e metadados legais completos (Razão Social, CNPJ da Empresa, Empregado, CPF, Data/Hora UTC e Local, NSR, Identificador REP-P e Hash SHA-256).
- **Tela de Verificação Pública:** Qualquer pessoa (trabalhador, auditor ou terceiro) pode subir o PDF ou ler o QR Code para atestar:
  - Assinatura digital válida;
  - Registro existente no ledger;
  - Hash SHA-256 correspondente;
  - Documento íntegro e não adulterado.
