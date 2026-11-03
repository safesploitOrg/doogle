#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/lib/compose.sh"

if [ "$#" -lt 1 ]; then
    printf 'Usage: %s <username>\n' "$0"
    exit 1
fi

docker_compose exec -T app php bin/admin-reset-totp "$@"
