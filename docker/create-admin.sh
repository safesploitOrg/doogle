#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/lib/compose.sh"

if [ "$#" -lt 2 ]; then
    printf '%s\n' "Usage: DOOGLE_ADMIN_PASSWORD='password' $0 <username> <email>"
    exit 1
fi

if [ -n "${DOOGLE_ADMIN_PASSWORD:-}" ]; then
    docker_compose exec -T \
        -e DOOGLE_ADMIN_PASSWORD="$DOOGLE_ADMIN_PASSWORD" \
        app php bin/create-admin "$@"
else
    docker_compose exec -T app php bin/create-admin "$@"
fi
