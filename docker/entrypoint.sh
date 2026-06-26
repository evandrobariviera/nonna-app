#!/bin/bash
set -e

echo "==> Iniciando Nonna App..."

# Aguarda o banco ficar disponível
echo "==> Aguardando banco de dados..."
until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" > /dev/null 2>&1; do
    echo "    Banco indisponível — aguardando 2s..."
    sleep 2
done
echo "==> Banco disponível."

# Descobre pacotes e gera cache de configurações
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Migrations automáticas
echo "==> Rodando migrations..."
php artisan migrate --force

# Seeders idempotentes (insertOrIgnore — seguros para rodar múltiplas vezes)
echo "==> Populando providers de IA..."
php artisan db:seed --class=AiProviderSeeder --force

echo "==> Pronto. Iniciando serviços..."
exec "$@"
