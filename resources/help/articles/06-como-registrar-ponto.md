---
id: como-registrar-ponto
title: Como Registrar meu Ponto (Guia do Servidor)
subtitle: Servidor
category: registro
profiles:
  - employee
  - manager
  - admin
icon: qr-code
keywords: bater ponto, como registrar, camera, qr code, leitor, gps, scanner, estados, comprovante, reconciliacao, conexao
order: 6
featured: true
summary: Tutorial passo a passo para o servidor registrar entrada, intervalos e saída com leitura óptica de QR Code e emitir seu comprovante instantâneo.
---

## 1. Passo a Passo do Registro Diário

Registrar o ponto no PontoFácil leva menos de 5 segundos. Siga as etapas abaixo:

### Passo 1: Acessar a tela de Registro
1. Abra o navegador do seu celular ou computador e acesse o endereço do PontoFácil.
2. Faça login com seu e-mail e senha cadastrados.
3. A tela inicial já é o módulo **Bater Ponto** (`/ponto`).
4. Verifique o relógio oficial no topo da tela: ele exibe as horas, minutos e segundos no fuso horário exato do seu estabelecimento de trabalho.

### Passo 2: Iniciar a Leitura do QR Code
1. Clique no botão azul em destaque: **Iniciar leitura do QR Code**.
2. Na primeira vez em que utilizar o sistema, o navegador solicitará autorização para usar a **Câmera** do dispositivo:
   * Toque em **Permitir** (ou *Durante o uso do app*).
3. O navegador também pode solicitar permissão para acessar sua **Localização (GPS)**:
   * Toque em **Permitir**. A localização serve como evidência de apoio e não bloqueia sua marcação.

### Passo 3: Apontar para o QR Code Oficial
1. Aponte a câmera para o cartaz oficial do QR Code afixado na entrada do seu setor, recepção ou posto de trabalho.
2. Mantenha o QR Code enquadrado dentro do quadrado demarcado na tela (moldura com cantos azuis).
3. A leitura é instantânea: assim que o código é identificado, o leitor emite um aviso sonoro/tátil e congela a imagem.

### Passo 4: Confirmação e Comprovante Instantâneo
1. O sistema envia a marcação ao servidor e abre um modal de confirmação em verde com:
   * **Tipo da Marcação:** Identifica automaticamente se é *Entrada* ou *Saída* com base no seu histórico do dia.
   * **Horário Local Exato:** Horário registrado no servidor (ex: `08:01:24`).
   * **NSR (Número Sequencial de Registro):** Numeração oficial da batida (ex: `#000004281`).
   * **Data e Fuso Horário Oficial.**
2. No próprio modal de confirmação, você tem duas opções:
   * **Baixar Comprovante:** Abre o comprovante assinado digitalmente em PDF.
   * **Concluir:** Fecha a confirmação e retorna à tela inicial.

---

## 2. Compreendendo os Estados do Scanner

O leitor do PontoFácil possui uma máquina de estados visual que informa exatamente o que está acontecendo:

```
[ 1. Idle ]       ──► Pronto para começar ("Iniciar leitura do QR Code")
        │
[ 2. Starting ]   ──► Ativando a câmera do celular
        │
[ 3. Scanning ]   ──► Câmera aberta, procurando o QR Code
        │
[ 4. Location ]   ──► QR Code identificado, colhendo coordenadas de GPS
        │
[ 5. Submitting ] ──► Enviando a marcação segura ao servidor
        │
[ 6. Success / Warning ] ──► Ponto gravado com sucesso no registro oficial
```

* **Azul / Índigo (`scanning`):** A câmera está ligada e buscando o código.
* **Verde (`success`):** Ponto gravado e confirmado no servidor.
* **Âmbar (`warning`):** Ponto confirmado com aviso! Significa que o ponto foi **gravado com sucesso**, mas o GPS indicou que você estava além do raio configurado pela empresa. A observação fica registrada na auditoria, mas sua batida é válida.
* **Vermelho (`error`):** Falha ao acessar a câmera ou QR Code inválido.
* **Âmbar com alerta de rede (`unknown`):** Conexão instável durante o envio. Veja a seção de reconciliação abaixo.

---

## 3. O que Fazer se a Conexão Oscilar (Reconciliação sem Duplicidade)

Se a sua internet móvel ou Wi-Fi oscilar exatamente no segundo em que você clicou para bater o ponto, o sistema entrará no estado de **Conexão instável (`unknown`)**:

> ⚠️ **NÃO TENTE BATER O PONTO REPETIDAS VEZES!**  
> Ficar apontando a câmera repetidamente pode gerar marcações duplicadas desnecessárias (por exemplo, registrar uma saída logo após a entrada).

Em vez disso, use os botões inteligentes de reconciliação exibidos na tela:
1. Clique no botão azul: **Verificar se foi registrado**.
2. O sistema consultará a chave de idempotência segura para checar se o servidor recebeu o registro antes da queda da conexão.
3. Se o ponto tiver chegado ao servidor: ele exibirá a confirmação verde com o horário exato em que foi gravado.
4. Se o ponto realmente não tiver chegado: o sistema liberará o botão para você reenviar a mesma tentativa com segurança.

---

## 4. O que Fazer se a Câmera não Abrir

Caso o navegador exiba uma mensagem de erro ao tocar em *Iniciar leitura*:

1. **Verifique se o site está usando HTTPS:** Câmeras em celulares só funcionam em conexões seguras (`https://`).
2. **Permissão Bloqueada no Google Chrome (Android / PC):**
   * Toque no ícone de **Cadeado** ou **Ajustes de Site** na barra de endereço (ao lado da URL).
   * Localize a permissão **Câmera** e altere para **Permitir**.
   * Recarregue a página e tente novamente.
3. **Permissão Bloqueada no Safari (iPhone / iPad):**
   * Toque no botão **aA** na barra de endereço inferior do Safari.
   * Selecione **Ajustes do Site** e mude a **Câmera** para **Permitir**.
   * Se persistir, abra os *Ajustes do iPhone → Safari → Câmera → Permitir*.

---

## 5. Como Conferir suas Batidas de Hoje

Na própria tela de **Bater Ponto** (`/ponto`), logo abaixo do leitor, existem dois cards de acompanhamento imediato:

1. **Card "Último registro":** Mostra o horário, o tipo da última batida confirmada e um atalho direto para visualizar o comprovante.
2. **Card "Sua jornada hoje":** Lista todas as marcações que você realizou no dia de hoje (Entrada, Almoço, Retorno, etc.), com a hora exata e o status de cada registro.

Se você perceber que esqueceu de bater o ponto em algum horário anterior, clique no link **"Esqueceu de registrar? Solicitar correção no Espelho →"** para abrir o formulário de inclusão de batida esquecida.
