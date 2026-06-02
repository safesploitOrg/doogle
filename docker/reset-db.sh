#!/usr/bin/env sh
set -eu

if [ "${1:-}" != "--force" ]; then
    printf '%s\n' "This removes the Docker MariaDB volume and all local Doogle data."
    printf '%s\n' "Run: ./docker/reset-db.sh --force"
    exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/lib/compose.sh"

docker_compose down -v
docker_compose up -d --build
