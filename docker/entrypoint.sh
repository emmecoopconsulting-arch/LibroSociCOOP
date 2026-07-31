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

# APP_KEY cifra le credenziali salvate nel database. Il file dell'immagine viene
# ricreato ad ogni aggiornamento, quindi ne conserviamo una copia nel volume
# persistente di storage. Una APP_KEY passata dall'host ha sempre la precedenza.
APP_KEY_FILE="storage/app/.app-key"

set_app_key() {
    key="$1"
    escaped_key=$(printf '%s' "$key" | sed 's/[&|\\]/\\&/g')

    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${escaped_key}|" .env
    else
        printf '\nAPP_KEY=%s\n' "$key" >> .env
    fi
}

if [ -n "${APP_KEY:-}" ]; then
    set_app_key "$APP_KEY"
    printf '%s\n' "$APP_KEY" > "$APP_KEY_FILE"
elif [ -s "$APP_KEY_FILE" ]; then
    set_app_key "$(tr -d '\r\n' < "$APP_KEY_FILE")"
elif grep -q '^APP_KEY=base64:' .env; then
    sed -n 's/^APP_KEY=//p' .env | head -n 1 > "$APP_KEY_FILE"
else
    php artisan key:generate --force --no-interaction
    sed -n 's/^APP_KEY=//p' .env | head -n 1 > "$APP_KEY_FILE"
fi

chmod 600 "$APP_KEY_FILE"
chown www-data:www-data "$APP_KEY_FILE"

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until nc -z "${DB_HOST}" "${DB_PORT:-3306}"; do
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
