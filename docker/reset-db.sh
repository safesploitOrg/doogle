#!/usr/bin/env sh
set -eu

if [ "${1:-}" != "--force" ]; then
    printf '%s\n' "This removes the Docker MySQL volume and all local Doogle data."
    printf '%s\n' "Run: ./docker/reset-db.sh --force"
    exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

docker compose -f "$COMPOSE_FILE" down -v
docker compose -f "$COMPOSE_FILE" up -d --build
