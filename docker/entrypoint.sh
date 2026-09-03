#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Run: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force

    if [ "${SEED_DATABASE:-false}" = "true" ]; then
        php artisan db:seed --force
    fi

    php artisan storage:link --force
    php artisan optimize
fi

exec "$@"


