---
id: gps-e-qr-por-setor
title: GPS, QR Code e Regras por Setor — Como Configurar e Conferir
subtitle: RH, gestores e colaboradores
category: configuracao
profiles:
  - employee
  - manager
  - admin
icon: map-pin
keywords: gps, bloqueio, localização, latitude, longitude, raio, precisão, coordenadas, qr code, obrigatório, setor, matriz, geofencing, fora da area
order: 6
featured: true
summary: Entenda o QR Code global ou setorial, o GPS opcional e a trava por setor na versão atual, inclusive mensagens de erro e evidências.
---

## 1. QR Code global ou próprio de cada setor

O PontoFácil solicita a leitura de um **QR Code aceito para o setor de lotação principal** do funcionário.

- **QR Code global:** configurado em `/admin/settings`. Pode ser utilizado quando o setor não possui código próprio.
- **QR Code setorial:** configurado em `/admin/sectors` e prevalece sobre o global naquele setor.
- **Rotação do código:** ao gerar um novo QR Code, o cartaz antigo deixa de ser aceito no respectivo contexto. Reimprima e substitua os cartazes.
- **Atenção aos plantões em outra unidade:** a versão atual não resolve automaticamente um segundo setor para o mesmo empregado com base no plantão; solicite orientação ao RH em vez de trocar o cadastro para contornar o código.

## 2. Como o RH configura localização

1. Em **Configurações** (`/admin/settings`), cadastre latitude, longitude e raio da sede, quando utilizados.
2. Em **Setores** (`/admin/sectors`), abra o setor para cadastrar QR Code e coordenadas próprios, caso ele tenha posto físico independente.
3. Informe o raio em metros considerando o prédio e a qualidade habitual do sinal.
4. No setor, verifique a opção **GPS Obrigatório**, que está desativada por padrão.
5. Salve e realize testes controlados dentro e fora do perímetro.

**Diferença importante:** configurar coordenadas para auditoria **não é o mesmo** que ativar a trava. É a opção setorial `GPS Obrigatório` que aciona a rejeição na validação estrita.

## 3. Comportamento real na v2.6.20

| GPS obrigatório no setor | Situação | Resultado atual |
|---|---|---|
| Desativado | Sem coordenadas informadas | Registro pode prosseguir com QR válido; localização não confirmada |
| Desativado | GPS fora do raio e referência configurada | Batida registrada, com aviso de perímetro e evidência na auditoria |
| Ativado | GPS válido, precisão suficiente e dentro do raio | Batida permitida |
| Ativado | Fora do raio | Batida **rejeitada**; não é gerado registro oficial |
| Ativado | Sem permissão, coordenadas inválidas, precisão insuficiente ou configuração inconsistente | Batida **rejeitada** com mensagem orientativa |

A validação estrita considera precisão máxima de **150 metros**, além do raio cadastrado; sinais imprecisos podem impedir a marcação. O QR Code continua obrigatório.

**Não confunda o aviso com uma ocorrência aprovada:** atualmente não existe um fluxo administrativo específico para gestor/RH justificar ou homologar divergências de GPS. Os avisos podem constar na confirmação e nos dados de auditoria, mas ainda não representam um processo formal de análise.

## 4. O que fazer diante de erro de localização

- **“Fora da área permitida”**: confirme se está no local cadastrado, solicite revisão das coordenadas/raio ao RH e verifique se seu setor principal é o correto.
- **“Ative a permissão de GPS”**: autorize a localização nas configurações de privacidade do navegador e tente novamente.
- **“Precisão insuficiente”**: tente em área com sinal melhor, sem alterar artificialmente a posição do aparelho.
- **“Não foi possível validar a área”**: RH deve verificar latitude, longitude e raio do setor.
- **Trabalha em hospital ou UBS diferente do setor cadastrado**: comunique à chefia/RH. O suporte nativo a múltiplos setores ainda é uma evolução planejada.

Quando a marcação não é efetuada, **não invente outro horário nem simule localização**. Registre a situação conforme o procedimento administrativo do órgão e, se necessário, solicite tratamento de ponto pelo espelho.

## 5. Privacidade, gestão e revisão de política

Coordenadas e evidências são informações pessoais; restringir a consulta a pessoas autorizadas e evitar exposição em documentos impressos sem necessidade. A validação por GPS não comprova sozinha presença física ou ausência.

Está planejada uma evolução para **registrar a batida e abrir ocorrência auditável quando houver divergência**, com análise do gestor/RH e observação no espelho. **Essa funcionalidade ainda não está disponível nesta versão**: não documentá-la como concluída.

A opção de bloqueio deve ser avaliada pelo responsável institucional e jurídico antes da adoção, considerando seu regime funcional e os procedimentos para falha de aparelho, conectividade e GPS.
