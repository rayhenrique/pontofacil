---
id: instalacao-no-celular-e-pwa
title: Acessar o PontoFácil no Celular e Criar Atalho (Sem PWA Completo)
subtitle: Navegador e Atalho — versão atual
category: celular
profiles:
  - employee
  - manager
  - admin
icon: device-phone-mobile
keywords: celular, pwa, instalar, android, iphone, safari, chrome, tela de inicio, atalho, offline, camera, gps, logout
order: 11
featured: true
summary: Como utilizar pelo navegador, criar atalho no celular e identificar limitações de instalação e uso offline na v2.6.20.
---

## 1. Experiência no Celular (Web App / Atalho)

O PontoFácil foi desenvolvido com design 100% **Mobile-First**. Isso significa que toda a interface se adapta perfeitamente à tela do seu smartphone, com botões confortáveis ao toque, navegação com o polegar e barra de menu inferior.

**Nesta versão ainda não há PWA completo:** use o endereço institucional no navegador e, se disponível, adicione um **atalho à tela inicial**. A disponibilidade de uma opção nativa de *Instalar aplicativo* depende do navegador e dos requisitos técnicos do site; não é garantida no PontoFácil v2.6.20.

---

## 2. Passo a Passo de Instalação por Sistema Operacional

### Como Instalar no Android (Google Chrome)
1. Abra o navegador **Google Chrome** no seu celular Android.
2. Digite o endereço do PontoFácil da sua empresa ou órgão (ex: `https://ponto.seumunicipio.gov.br`).
3. Toque no botão de menu do Chrome (o ícone de **três pontos verticais `⋮`** no canto superior direito).
4. No menu que se abrir, procure e toque na opção:
   * **"Adicionar à tela inicial"**, quando disponível.
5. O Chrome exibirá uma janela de confirmação com o nome e o ícone do PontoFácil. Toque em **Adicionar** (ou *Instalar*).
6. Se o navegador oferecer o recurso, será criado um atalho na tela inicial. Ele pode abrir uma aba normal do navegador; não há garantia de execução em tela cheia nesta versão.

### Como Instalar no iPhone e iPad (Apple Safari)
1. Abra o navegador oficial **Safari** no seu iPhone (a instalação de atalhos no iOS é feita exclusivamente através do Safari).
2. Acesse a página do PontoFácil.
3. Na barra de ferramentas do Safari, toque no botão de **Compartilhar** (o ícone de um **quadrado com uma seta apontando para cima `⎋`**).
4. Role as opções para cima e toque em:
   * **"Adicionar à Tela de Início"**.
5. O Safari mostrará o ícone e o nome sugerido. Toque em **Adicionar** no canto superior direito.
6. O ícone do atalho poderá aparecer na tela inicial. O funcionamento depende da versão do iOS e do navegador.

### Como Instalar no Computador / Desktop (Chrome / Edge)
1. Abra o PontoFácil no Google Chrome ou Microsoft Edge no computador.
2. Utilize os recursos de **Criar atalho** do navegador, quando disponíveis.
3. Não presuma que exista uma opção de instalação de PWA nesta versão.

---

## 3. Transparência Técnica: A Marcação de Ponto Funciona Offline?

> ⚠️ **INFORMAÇÃO IMPORTANTE DE CONFORMIDADE:**  
> **NÃO. A marcação de ponto NÃO funciona offline nesta versão.**  
> O registro oficial do PontoFácil exige conexão ativa com a internet (Wi-Fi ou dados móveis 3G/4G/5G).

### Por que a conexão é necessária para bater o ponto?
1. **Carimbo de Tempo Soberano:** O horário da batida é auditado e chancelado pelo servidor oficial no fuso horário do estabelecimento, impedindo que alterações no relógio interno do celular fraudem a hora do registro.
2. **Número Sequencial Monotônico (NSR):** A Portaria 671/2021 MTP exige que cada marcação receba um número estritamente sequencial crescente em tempo real.
3. **Comprovante:** o sistema tenta gerar um comprovante consultável após a marcação. A assinatura digital ICP-Brasil é uma implementação futura.

*Dica de Uso:* Se a conexão do seu celular oscilar no momento da batida, utilize o botão inteligente **"Verificar se foi registrado"** para reconciliar o status antes de tentar novamente.

---

## 4. Como Configurar as Permissões no Celular

Para que a leitura do QR Code funcione sem empecilhos, certifique-se de que o navegador tem permissão para usar os recursos:

* **Permissão de Câmera (Obrigatória):** Necessária para abrir o leitor óptico e escanear o QR Code da empresa.
* **Permissão de Localização / GPS (Condicional):** com **GPS Obrigatório** ativado no setor, a falta de localização ou a precisão inadequada **impede a marcação**. Com a trava desativada, o GPS pode ser utilizado como evidência sem bloquear o registro. Consulte [GPS e QR por setor](/ajuda?artigo=gps-e-qr-por-setor).

### Como redefinir permissões bloqueadas:
* **No Android (Chrome):** Toque no ícone de *Ajustes de Site* ao lado da URL → Toque em *Permissões* → Mude *Câmera* e *Localização* para *Permitir*.
* **No iOS (Safari):** Abra *Ajustes do iPhone* → Role até *Safari* → Em *Acesso à Câmera*, escolha *Permitir*.

---

## 5. Segurança em Dispositivos Compartilhados

Se você utilizar um celular, tablet ou computador compartilhado com outros colegas de setor:

1. **Sempre faça Logout ao terminar:** Após registrar sua batida ou conferir seu espelho, abra o menu lateral e clique no botão **Sair (`Logout`)**.
2. **Não salve senhas no navegador público:** Desmarque a opção de preenchimento automático de senha quando o aparelho não for seu dispositivo pessoal.
3. **Privacidade das Marcações:** Cada colaborador só consegue acessar o histórico e comprovantes de ponto autenticando-se com seu próprio e-mail e senha.
