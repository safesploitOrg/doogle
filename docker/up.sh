#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

docker compose -f "$COMPOSE_FILE" up -d --build
docker compose -f "$COMPOSE_FILE" exec app composer install --no-interaction --prefer-dist

if [ -n "${DOOGLE_ADMIN_PASSWORD:-}" ]; then
    docker compose -f "$COMPOSE_FILE" exec -T \
        -e DOOGLE_ADMIN_PASSWORD="$DOOGLE_ADMIN_PASSWORD" \
        app php bin/create-admin \
            "${DOOGLE_ADMIN_USERNAME:-admin}" \
            "${DOOGLE_ADMIN_EMAIL:-admin@example.local}"
else
    printf '%s\n' "Admin user not created. Set DOOGLE_ADMIN_PASSWORD to create one during startup."
fi

printf '%s\n' "Doogle:     http://localhost:${DOOGLE_APP_PORT:-8000}"
printf '%s\n' "Doogle Crawl: http://localhost:${DOOGLE_APP_PORT:-8000}/crawl.php"
printf '%s\n' "phpMyAdmin: http://localhost:${DOOGLE_PHPMYADMIN_PORT:-8081}"
