# ── Stage 1: Build assets (Node) ──────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /app
COPY package*.json vite.config.* ./
RUN npm ci

COPY resources/ resources/
COPY public/ public/
RUN npm run build


# ── Stage 2: PHP dependencies ──────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist


# ── Stage 3: Runtime ───────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# Pacotes do sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    bash

# Extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        opcache \
        pcntl \
        bcmath \
        gd

WORKDIR /var/www/html

# Vendor do stage 2
COPY --from=vendor /app/vendor ./vendor

# Código da aplicação
COPY . .

# Assets compilados do stage 1
COPY --from=assets /app/public/build ./public/build

# Finaliza autoload do Composer
COPY --from=vendor /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts \
    && rm /usr/bin/composer

# Configurações
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/php.ini         /usr/local/etc/php/conf.d/app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh   /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && mkdir -p /var/log/supervisor /run/nginx

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
