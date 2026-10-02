# Guia de Deploy - PontoFácil (CloudPanel na Hostinger VPS)

Este documento descreve o passo a passo exato para realizar o deploy do sistema PontoFácil em uma VPS da Hostinger utilizando o painel de gerenciamento **CloudPanel**.

## 1. Criação do Site no CloudPanel

1. Acesse o seu painel do CloudPanel.
2. Navegue até a seção **Sites** e clique em **Add Site**.
3. Escolha a opção **Create a PHP Site** (ou o template específico do Laravel, se disponível).
4. Preencha os dados conforme seu ambiente:
   - **Nome do domínio:** `pontofacil.kltecnologia.com`
   - **Versão do PHP:** `PHP 8.2` (ou superior, ex: 8.3 ou 8.5 conforme prints). O Laravel mais recente requer no mínimo PHP 8.2.
   - **Diretório raiz (Document Root):** Certifique-se de que aponte para a pasta `public`. Ex: `/home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com/public`
   - Crie um usuário de sistema e anote a senha gerada (ou gere uma chave SSH para maior segurança).
5. Clique em **Criar (Create)**.

## 2. Configuração do Banco de Dados

1. Ainda no CloudPanel, acesse o site recém-criado clicando sobre o domínio.
2. Vá até a aba **Bancos de Dados (Databases)**.
3. Clique em **Adicionar novo banco de dados (Add Database)**.
4. Preencha os campos:
   - **Nome do banco de dados:** `pontofacil`
   - **Nome de usuário:** `pontofacil` (ou de sua preferência)
   - **Senha:** Gere uma senha forte e **anote-a**.
5. Clique em **Adicionar banco de dados**.

## 3. Configuração do Repositório (Git) e Instalação

A forma mais recomendada para fazer o deploy é via SSH, conectando-se ao servidor e clonando o repositório público ou privado do GitHub.

1. Acesse o servidor via SSH com o usuário do site criado no Passo 1:
   ```bash
   ssh kltecnologia-pontofacil@72.60.142.2
   ```
2. Navegue até o diretório do projeto:
   ```bash
   cd htdocs/pontofacil.kltecnologia.com
   ```
3. Se o diretório não estiver vazio, limpe os arquivos padrão (cuidado ao deletar):
   ```bash
   rm -rf * .env* .git*
   ```
4. Clone o repositório do projeto (como ele é privado, você precisará configurar uma chave SSH Deploy Key no GitHub ou usar um token de acesso):
   ```bash
   git clone https://github.com/rayhenrique/pontofacil.git .
   ```
5. Instale as dependências do PHP usando o Composer:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```
6. Instale as dependências do Node (para compilar o CSS/JS via Vite):
   ```bash
   npm install
   npm run build
   ```

## 4. Configuração do Ambiente (.env)

1. Copie o arquivo de exemplo para gerar o seu `.env` oficial de produção:
   ```bash
   cp .env.example .env
   ```
2. Edite o arquivo `.env` (você pode usar o editor `nano .env` no terminal ou usar o Gerenciador de Arquivos do próprio CloudPanel na aba "Arquivos"):
   ```env
   APP_NAME=PontoFácil
   APP_ENV=production
   APP_KEY= # (Será gerada no próximo passo)
   APP_DEBUG=false
   APP_URL=https://pontofacil.kltecnologia.com

   APP_TIMEZONE=America/Maceio
   APP_LOCALE=pt_BR
   APP_FALLBACK_LOCALE=pt_BR
   APP_FAKER_LOCALE=pt_BR

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pontofacil
   DB_USERNAME=pontofacil
   DB_PASSWORD=sua_senha_segura_aqui
   ```
3. Gere a chave de segurança da aplicação:
   ```bash
   php artisan key:generate
   ```

## 5. Banco de Dados e Permissões

1. Execute as migrações para construir a estrutura do banco de dados (tabelas de setores, funcionários, ponto, etc):
   ```bash
   php artisan migrate --force
   ```
2. Ajuste as permissões das pastas de cache e armazenamento (essencial para o Laravel):
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R kltecnologia-pontofacil:kltecnologia-pontofacil storage bootstrap/cache
   ```

## 6. Otimizações Finais de Produção

Para garantir máxima performance do sistema em ambiente produtivo, execute a sequência oficial de otimização do Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. Certificado SSL (HTTPS)

1. Volte ao CloudPanel e acesse a aba **SSL/TLS**.
2. Clique em **New Let's Encrypt Certificate** (Certificado Let's Encrypt).
3. Confirme a emissão (seu domínio precisa já estar apontando para o IP da VPS no seu provedor de DNS/Hostinger).
4. Aguarde a validação. A partir desse momento, o sistema abrirá perfeitamente seguro via `https://`.
## 8. Atualizando o Sistema (Deploy Contínuo)

Sempre que você realizar alterações no código localmente e fizer o `push` para o GitHub, siga estes passos para refletir a atualização na VPS.

### Opção A: Executar o Script Automático (Recomendado)

Na sua sessão SSH (como `root` ou como `kltecnologia-pontofacil`):

```bash
cd /home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com
git config --global --add safe.directory /home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com
git fetch --all && git reset --hard origin/master
chmod +x deploy.sh
./deploy.sh
```

### Opção B: Passo a Passo Manual

Se preferir rodar manualmente comando a comando:

```bash
cd /home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com
git config --global --add safe.directory /home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com
git fetch --all && git reset --hard origin/master

# Pacotes e Banco
composer install --optimize-autoloader --no-dev
php artisan migrate --force

# Publicar assets do Livewire (essencial no Nginx do CloudPanel)
php artisan livewire:publish --assets

# Garantir symlink público do storage para arquivos e logotipo
php artisan storage:link

# Compilar CSS e JS
npm install
npm run build

# Limpar e recriar caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ajustar permissões para o CloudPanel
chown -R kltecnologia-pontofacil:kltecnologia-pontofacil .
chmod -R 775 storage bootstrap/cache
```

---

**Desenvolvido por KL Tecnologia.**
