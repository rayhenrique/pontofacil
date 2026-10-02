#!/bin/bash
# Script de Deploy Contínuo (Atualização) para o PontoFácil
# Desenvolvido por KL Tecnologia

set -e

echo "====================================================="
echo "🚀 INICIANDO DEPLOY - PONTOFÁCIL"
echo "====================================================="

APP_DIR="/home/kltecnologia-pontofacil/htdocs/pontofacil.kltecnologia.com"
cd "$APP_DIR"

# Evita erro do Git 'dubious ownership' se executado como root
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

# Coloca o sistema em manutenção de forma segura
echo "🛠️ Colocando o sistema em manutenção..."
php artisan down --render="errors::503" --secret="kltecnologia-deploy" || true

# Baixa as novidades do Git com reset limpo
echo "📥 Baixando novidades do repositório (Git Fetch & Reset)..."
git fetch --all
git reset --hard origin/master

# Instala/Atualiza pacotes PHP
echo "📦 Instalando pacotes do Composer (Produção)..."
composer install --optimize-autoloader --no-dev --no-interaction

# Executa migrações de banco
echo "🗄️ Executando Migrations..."
php artisan migrate --force

# Garante a publicação dos assets físicos do Livewire
echo "🌐 Publicando assets do Livewire..."
php artisan livewire:publish --assets || true

# Cria o link simbólico do storage público para acesso a uploads e logotipo
echo "🔗 Garantindo symlink do storage (php artisan storage:link)..."
php artisan storage:link 2>/dev/null || true

# Compila Assets (Tailwind/Vite)
echo "🎨 Compilando assets NPM..."
npm install
npm run build

# Otimização e Limpeza de Cache
echo "🧹 Limpando e recriando caches do Laravel..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Ajusta permissões caso tenha sido executado como root
echo "🔒 Ajustando permissões de arquivos para o CloudPanel..."
chown -R kltecnologia-pontofacil:kltecnologia-pontofacil "$APP_DIR" 2>/dev/null || true
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

# Retira do modo de manutenção
echo "✅ Voltando o sistema para online..."
php artisan up

echo "====================================================="
echo "🎉 DEPLOY CONCLUÍDO COM SUCESSO!"
echo "====================================================="

