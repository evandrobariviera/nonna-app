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

# Migrations automáticas (rodar ANTES dos caches)
echo "==> Rodando migrations..."
php artisan migrate --force || echo "==> Migrate reportou falha — app continua (verifique logs)"

# Seeders idempotentes — não fatal, app inicia mesmo se falhar
echo "==> Populando providers de IA..."
php artisan db:seed --class=AiProviderSeeder --force || echo "    Seeder ignorado (tabelas podem não existir ainda)"

echo "==> Populando mensagens padrão de notificação..."
php artisan db:seed --class=NotificationTemplateSeeder --force || echo "    Seeder ignorado (tabelas podem não existir ainda)"

# Cache de configurações e rotas
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Pronto. Iniciando serviços..."
exec "$@"
