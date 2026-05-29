#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"

if [ "$#" -lt 2 ]; then
    printf '%s\n' "Usage: DOOGLE_ADMIN_PASSWORD='password' ./docker/create-admin.sh <username> <email>"
    exit 1
fi

docker compose -f "$COMPOSE_FILE" exec -T \
    -e DOOGLE_ADMIN_PASSWORD="${DOOGLE_ADMIN_PASSWORD:-}" \
    app php bin/create-admin "$@"
