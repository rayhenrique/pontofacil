---
id: configuracao-inicial-rh
title: Configuração Inicial da Organização (Guia do RH e Administrador)
subtitle: Administrador
category: configuracao
profiles:
  - admin
icon: building-office
keywords: configuracao, primeiro acesso, empresa, orgao, logotipo, setores, usuarios, funcionarios, jornadas, escalas, qr code, teste
order: 2
featured: true
summary: Tutorial passo a passo com a sequência obrigatória de 14 etapas para implantar o PontoFácil do zero em prefeituras, órgãos públicos ou empresas privadas.
---

## 1. Ordem Obrigatória de Implantação e Dependências

Para que o PontoFácil funcione corretamente e sem retrabalho, os cadastros devem seguir rigorosamente uma cadeia de dependências lógicas:

```
1. Dados da Organização e Logotipo
             │
             ▼
2. Estabelecimentos e Fusos Horários
             │
             ▼
3. Setores de Lotação
             │
             ▼
4. Usuários de Acesso e Gestores
             │
             ▼
5. Modelos de Jornadas e Escalas
             │
             ▼
6. Servidores e Colaboradores (Vinculados a Setor e Escala)
             │
             ▼
7. QR Codes e Perímetros Físicos
             │
             ▼
8. Teste Controlado e Validação Inicial
```

Tentar cadastrar servidores antes de criar setores e escalas resultará em campos obrigatórios vazios. Siga o passo a passo sequencial abaixo.

---

## 2. Passo a Passo Sequencial (14 Etapas)

### Etapa 1: Primeiro Acesso Seguro
1. Acesse o sistema pelo navegador utilizando a URL oficial fornecida pela equipe de TI ou hospedagem (ex: `https://ponto.seumunicipio.gov.br` ou `http://localhost:8000/login`).
2. Digite as credenciais do usuário inicial de Administração fornecidas no momento da instalação.
3. Após o primeiro login com sucesso, acesse seu perfil para definir uma senha forte e individual.

### Etapa 2: Configurar Dados da Organização
1. No menu lateral ou no topo, acesse **Configurações** (`/admin/settings`).
2. No card **Dados da Empresa & Logotipo Oficial**, preencha os dados institucionais:
   * **Razão Social:** Nome jurídico completo da prefeitura, autarquia ou empresa (ex: *Município de Exemplo* ou *Empresa Matriz Ltda*).
   * **Nome Fantasia:** Nome de exibição usual (ex: *Prefeitura de Exemplo*).
   * **CNPJ:** CNPJ oficial (14 dígitos). O sistema aplica a máscara automaticamente.
   * **Telefone e E-mail de Contato / RH:** Dados oficiais para contato institucional.
   * **Endereço da Sede:** Logradouro, número, cidade, UF e CEP.
3. Caso a sua organização seja um órgão público, preencha também os campos de identificação governamental:
   * **Órgão / Entidade Principal:** Ex: *Prefeitura Municipal*.
   * **Sub-Entidade / Secretaria:** Ex: *Secretaria Municipal de Administração*.
   * **Linha Superior (Estado/UF):** Ex: *Estado de Alagoas*.
4. Clique no botão **Salvar Dados da Empresa**.

### Etapa 3: Configurar o Logotipo Oficial
1. Ainda na tela de **Configurações**, localize o botão **Escolher logotipo** no topo do formulário institucional.
2. Selecione uma imagem em formato PNG, JPG ou SVG com fundo transparente ou branco (tamanho recomendado: até 2 MB).
3. Aguarde o upload da imagem e clique em **Salvar Dados da Empresa**.
4. O logotipo passará a ilustrar o topo da navegação, os cartazes de QR Code e o cabeçalho impresso oficial de todas as Folhas de Ponto e Relatórios Mensais.

### Etapa 4: Conferir Estabelecimentos e Fusos Horários
1. O PontoFácil opera com instâncias dedicadas contendo estabelecimentos associados à organização.
2. Cada estabelecimento possui um **fuso horário oficial** (timezone IANA, como `America/Maceio`, `America/Sao_Paulo`, `America/Manaus`, `America/Cuiaba` ou `America/Belem`).
3. Verifique se o fuso horário cadastrado no estabelecimento reflete o horário legal da localidade física onde os servidores trabalham.
   * *Atenção:* O sistema calcula as marcações com base no fuso horário do estabelecimento onde o colaborador está lotado.

### Etapa 5: Cadastrar os Setores de Lotação
1. No menu principal, acesse **Setores** (`/admin/sectors`).
2. Clique no botão **Adicionar Setor**.
3. Preencha:
   * **Nome do Setor:** Ex: *Secretaria de Saúde*, *Pronto Atendimento Municipal*, *Almoxarifado Central*, *Recursos Humanos*.
   * **Descrição (Opcional):** Resumo das atividades do departamento.
4. Clique em **Salvar Setor**. Repita o procedimento para cada setor que possua equipe de trabalho.

### Etapa 6: Cadastrar Usuários e Níveis de Acesso
1. No menu principal, acesse **Usuários** (`/admin/users`).
2. Clique no botão **Adicionar Usuário**.
3. Preencha:
   * **Nome Completo:** Nome civil do servidor ou gestor.
   * **E-mail (Login):** E-mail corporativo ou institucional que será utilizado para entrar no sistema.
   * **Nível de Acesso:**
     * `Colaborador`: Servidor padrão com acesso ao registro e espelho próprio.
     * `Gestor`: Líder de setor autorizado a gerenciar funcionários e conferir frequências.
     * `Administrador`: RH com acesso pleno a configurações e fechamento.
     * `Auditor`: Acesso de fiscalização e auditoria.
   * **Senha:** Senha inicial de acesso (o usuário poderá alterá-la depois).
4. Clique em **Salvar Usuário**.

### Etapa 7: Vincular Responsáveis aos Setores
1. Retorne a **Setores** (`/admin/sectors`) e clique em **Editar** no setor desejado.
2. No campo **Responsável do Setor**, selecione o usuário cadastrado com perfil de *Gestor*.
3. Clique em **Atualizar Setor**. A partir deste momento, esse gestor visualizará exclusivamente os servidores lotados nesse setor.

### Etapa 8: Configurar Modelos de Horários e Escalas
1. No menu principal, acesse **Horários & Escalas** (`/admin/shifts`).
2. Clique no botão **Nova Escala / Jornada**.
3. O sistema exibirá o configurador intuitivo por modelos. Escolha o modelo compatível:
   * **Administrativo (ADM):** 2 turnos com intervalo (ex: 08:00–12:00 e 13:00–17:00, seg a sex).
   * **Horário Corrido (6H):** 1 turno contínuo (ex: 07:00–13:00).
   * **Plantão 12 Horas:** Ciclo 12x36 (diurno 07:00–19:00 ou noturno 19:00–07:00 com data-âncora).
   * **Plantão 24 Horas:** Ciclo 24x72 para categorias autorizadas por lei.
4. Dê um nome claro à escala (ex: *Administrativo Geral 40h*, *Plantão Enfermagem 12x36 Par*) e clique em **Salvar e Criar Escala**.

### Etapa 9: Cadastrar Servidores e Funcionários
1. No menu principal, acesse **Servidores** (`/admin/employees`).
2. Clique em **Adicionar Funcionário**.
3. Preencha:
   * **Nome Completo e E-mail:** Se o usuário já tiver sido criado, use o mesmo e-mail para conectar o login; se não tiver, o sistema criará o acesso correspondente.
   * **CPF e Telefone:** Documentação do colaborador.
   * **Setor de Lotação:** Selecione o setor criado na Etapa 5.
   * **Regime Jurídico:** CLT, Estatutário Municipal, Estatutário Estadual ou Estatutário Federal.
   * **Tipo de Vínculo:** Efetivo (Concursado), Contratado (PSS), Comissionado, Temporário ou Estagiário.
   * **Cargo / Função:** Ex: *Enfermeiro(a)*, *Assistente Administrativo*, *Médico(a)*.
   * **Modelo de Escala:** Selecione a escala criada na Etapa 8.
   * **Início da Vigência da Escala:** Data a partir da qual o colaborador passa a cumprir essa jornada (ex: data de admissão ou primeiro dia do mês).
4. Clique em **Salvar Funcionário**.

### Etapa 10: Configurar o QR Code Institucional ou Setorial
O PontoFácil adota uma **arquitetura híbrida de QR Code**:
1. **QR Code Global da Empresa:**
   * Em **Configurações** (`/admin/settings`), no card **QR Code Oficial da Empresa**, clique em **Imprimir QR Code**.
   * O sistema abrirá uma página limpa formatada para folha A4 com o logotipo, nome da empresa e o QR Code. Imprima e afixe na recepção ou entrada principal.
2. **QR Code Exclusivo por Setor (Opcional):**
   * Se a sua organização tiver postos de trabalho descentralizados (ex: postos de saúde, escolas, garagens), acesse **Setores** (`/admin/sectors`), clique em **Ver QR** no setor correspondente e gere um código exclusivo para aquela localidade.

### Etapa 11: Configurar a Cerca Virtual de GPS (Opcional)
1. Em **Configurações** (`/admin/settings`), localize a seção **Geolocalização & Raio de Validação (GPS)**.
2. Estando fisicamente no local da sede, clique no botão **Capturar Minha Posição Atual (GPS)** (ou digite a Latitude e Longitude manualmente).
3. Defina o **Raio Permitido (metros)** (ex: 100 metros ou 200 metros).
4. Clique em **Salvar Coordenadas**.
   * *Aviso Legal Importante:* O GPS serve como evidência contextual de auditoria. Marcações fora do raio não são impedidas de serem registradas, mas são sinalizadas com aviso de perímetro para o gestor e RH.

### Etapa 12: Definir Regras de Compensação / Banco de Horas
1. Se a organização adotar banco de horas, acesse **Configurações** (`/admin/settings`) e configure a política padrão (Acumular / CARRY_OVER ou Compensação Mensal).
2. Para órgãos públicos ou empresas sem banco de horas (onde todas as diferenças vão para apuração direta), deixe o banco desativado. O sistema apurará as horas físicas normalmente no Espelho de Ponto.

### Etapa 13: Realizar um Teste Controlado de Marcação
1. Com um usuário de teste ou o próprio cadastro do RH, abra o menu **Bater Ponto** (`/ponto`).
2. Clique em **Iniciar leitura do QR Code**.
3. Autorize o acesso à câmera e à localização quando solicitado pelo navegador.
4. Aponte a câmera para o cartaz impresso do QR Code.
5. Verifique a confirmação: modal em verde com tipo de batida (*Entrada*), horário oficial local do estabelecimento, número sequencial (NSR) e link para o comprovante.

### Etapa 14: Conferir as Primeiras Marcações no Espelho
1. Acesse **Espelho de Ponto** (`/timesheet`).
2. Selecione o colaborador testado e a competência atual.
3. Verifique se a marcação de entrada apareceu na linha do dia correspondente com o NSR e horário exato.
4. Acesse **Central de Comprovantes** (`/comprovantes`) e faça o download do comprovante em PDF para validar a assinatura eletrônica e o código de autenticidade.
5. A implantação está concluída e o sistema está pronto para uso operacional por toda a equipe.
