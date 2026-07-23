#!/bin/bash
set -e

echo "==> Iniciando Nonna App..."

# Aguarda o banco ficar disponível (com teto — evita ficar preso pra sempre
# se a rede pro Postgres não vier, o que travaria o healthcheck indefinidamente)
echo "==> Aguardando banco de dados..."
DB_WAIT_ATTEMPTS=0
DB_WAIT_MAX=30
until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" > /dev/null 2>&1; do
    DB_WAIT_ATTEMPTS=$((DB_WAIT_ATTEMPTS + 1))
    if [ "$DB_WAIT_ATTEMPTS" -ge "$DB_WAIT_MAX" ]; then
        echo "==> Banco continua indisponível após ${DB_WAIT_MAX} tentativas (60s) — abortando boot."
        exit 1
    fi
    echo "    Banco indisponível — aguardando 2s... (tentativa ${DB_WAIT_ATTEMPTS}/${DB_WAIT_MAX})"
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
