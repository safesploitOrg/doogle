#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

docker compose -f "$COMPOSE_FILE" build
docker compose -f "$COMPOSE_FILE" run --rm --no-deps app composer install --no-interaction --prefer-dist
