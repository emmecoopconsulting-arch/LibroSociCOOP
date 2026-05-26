#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until mysqladmin ping \
        -h"${DB_HOST}" \
        -P"${DB_PORT:-3306}" \
        -u"${DB_USERNAME:-root}" \
        -p"${DB_PASSWORD:-}" \
        --silent; do
        sleep 2
    done
fi

php artisan storage:link --force --no-interaction || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

exec "$@"

