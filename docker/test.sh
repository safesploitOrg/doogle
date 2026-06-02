#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/lib/compose.sh"

docker_compose run --rm --no-deps app composer validate --strict
docker_compose run --rm --no-deps app composer test
docker_compose run --rm --no-deps app composer analyse
