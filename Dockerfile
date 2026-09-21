# --- Stage 1: build the embedded admin SPA (Polaris + App Bridge) ---
FROM node:20-alpine AS admin-build
WORKDIR /admin
COPY admin/package*.json ./
RUN npm ci
COPY admin/ ./
RUN npm run build

# --- Stage 2: install PHP dependencies ---
FROM composer:2 AS vendor-build
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# --- Stage 3: runtime image (php-fpm + nginx + supervisord) ---
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor gettext postgresql-libs libzip icu oniguruma \
    && apk add --no-cache --virtual .build-deps postgresql-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl opcache \
    && apk del .build-deps

WORKDIR /var/www/html

COPY backend/ ./
COPY --from=vendor-build /app/vendor ./vendor
COPY --from=admin-build /admin/dist ./public/app

RUN mkdir -p /etc/nginx/templates /etc/nginx/http.d storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf.template /etc/nginx/templates/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
