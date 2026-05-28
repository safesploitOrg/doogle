#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

docker compose -f "$COMPOSE_FILE" up -d --build
docker compose -f "$COMPOSE_FILE" exec app composer install --no-interaction --prefer-dist

printf '%s\n' "Doogle:     http://localhost:${DOOGLE_APP_PORT:-8000}"
printf '%s\n' "phpMyAdmin: http://localhost:${DOOGLE_PHPMYADMIN_PORT:-8081}"
