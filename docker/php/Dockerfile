# syntax=docker/dockerfile:1

# ---- base: PHP-FPM with the extensions the application needs --------------------------------
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache git unzip libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

# The container runs as an arbitrary user (see "user:" in docker-compose.yml), so give it a writable home.
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
EXPOSE 9000
ENTRYPOINT ["sh", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]

# ---- development: source code is bind mounted, dependencies are installed on first start ------
FROM base AS development

# ---- production: self-contained image ---------------------------------------------------------
FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

ENV RUN_MIGRATIONS=false
USER www-data
