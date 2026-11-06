#!/bin/sh
# Prepares the application, then hands over to the container command (php-fpm).
set -eu

cd /var/www/html

[ -f .env ] || cp .env.example .env

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --prefer-dist
fi

if [ -z "${APP_KEY:-}" ] && ! grep -Eq '^APP_KEY=.+' .env; then
    php artisan key:generate --force
fi

# Brings an empty database up to date; safe to repeat. Disable with RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
