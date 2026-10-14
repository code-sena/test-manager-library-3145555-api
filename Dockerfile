# syntax=docker/dockerfile:1
FROM composer:2 AS composer_binary

FROM php:8.4-cli-bookworm AS php_base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install -j2 pdo_pgsql mbstring dom \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV TZ=UTC

FROM php_base AS runtime
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize \
    && php artisan package:discover --ansi \
    && php -r 'if (!in_array("argon2id", password_algos(), true)) { exit(1); }'
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/api/v1/health") === "{\"status\":\"ok\"}" ? 0 : 1);'
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]

FROM runtime AS test
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress \
    && composer dump-autoload --no-scripts --optimize
RUN php vendor/bin/phpunit

FROM runtime AS application
