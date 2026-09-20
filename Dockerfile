# ---- フロントエンドのビルド ----------------------------------------------
FROM node:22-slim AS assets
WORKDIR /app
COPY laravel_app/package*.json ./
RUN npm ci
COPY laravel_app/ ./
RUN npm run build

# ---- アプリケーション ------------------------------------------------------
FROM php:8.4-cli
WORKDIR /workdir/laravel_app

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/opt/composer \
    PATH="$PATH:/opt/composer/vendor/bin"

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# 依存だけ先に入れてレイヤーキャッシュを効かせる
COPY laravel_app/composer.json laravel_app/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY laravel_app/ ./
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host", "0.0.0.0", "--port", "8000"]
