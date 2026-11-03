#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/lib/compose.sh"

docker_compose up -d --build
docker_compose exec app composer install --no-interaction --prefer-dist

if docker_compose exec -T app sh -c '
    if [ -z "${DOOGLE_ADMIN_PASSWORD:-}" ]; then
        exit 20
    fi

    php bin/create-admin "${DOOGLE_ADMIN_USERNAME:-admin}" "${DOOGLE_ADMIN_EMAIL:-admin@example.local}"
'; then
    :
else
    status=$?

    if [ "$status" -ne 20 ]; then
        exit "$status"
    fi

    printf '%s\n' "Admin user not created. Set DOOGLE_ADMIN_PASSWORD to create one during startup."
fi

app_port=$(env_value DOOGLE_APP_PORT 8000)
phpmyadmin_port=$(env_value DOOGLE_PHPMYADMIN_PORT 8081)

printf '%s\n' "Doogle:     http://localhost:$app_port"
printf '%s\n' "Doogle Crawl: http://localhost:$app_port/crawl.php"
printf '%s\n' "phpMyAdmin: http://localhost:$phpmyadmin_port"
