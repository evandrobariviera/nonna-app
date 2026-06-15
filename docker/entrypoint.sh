#!/bin/bash
set -e

echo "==> Iniciando Nonna App..."

# Aguarda o banco ficar disponível
echo "==> Aguardando banco de dados..."
until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    echo "    Banco indisponível — aguardando 2s..."
    sleep 2
done
echo "==> Banco disponível."

# Cache de configurações (melhora performance em produção)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Migrations automáticas
echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Pronto. Iniciando serviços..."
exec "$@"
