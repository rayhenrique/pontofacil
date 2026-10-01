#!/bin/bash
# Script de Deploy Contínuo (Atualização) para o PontoFácil
# Desenvolvido por KL Tecnologia

echo "====================================================="
echo "🚀 INICIANDO DEPLOY - PONTOFÁCIL"
echo "====================================================="

# Entra em modo de manutenção
echo "🛠️ Colocando o sistema em manutenção..."
php artisan down --render="errors::503" --secret="kltecnologia-deploy" || true

# Atualiza do GitHub
echo "📥 Baixando novidades do repositório (Git Pull)..."
git pull origin master

# Instala/Atualiza pacotes PHP
echo "📦 Instalando pacotes do Composer (Produção)..."
composer install --optimize-autoloader --no-dev

# Executa migrações de banco
echo "🗄️ Executando Migrations..."
php artisan migrate --force

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

# Retira do modo de manutenção
echo "✅ Voltando o sistema para online..."
php artisan up

echo "====================================================="
echo "🎉 DEPLOY CONCLUÍDO COM SUCESSO!"
echo "====================================================="
