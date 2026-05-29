#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

if [ "$#" -lt 1 ]; then
    printf '%s\n' "Usage: ./docker/crawl.sh https://example.com"
    exit 1
fi

docker compose -f "$COMPOSE_FILE" exec -T app php bin/crawl "$@"
