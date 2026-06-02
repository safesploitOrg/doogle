#!/usr/bin/env sh

PROJECT_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"
ENV_FILE="$PROJECT_ROOT/.env"

docker_compose()
{
    if [ -f "$ENV_FILE" ]; then
        docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
        return
    fi

    docker compose -f "$COMPOSE_FILE" "$@"
}

env_value()
{
    name=$1
    default=$2

    if [ -f "$ENV_FILE" ]; then
        value=$(awk -F= -v key="$name" '$1 == key {sub(/^[^=]*=/, ""); print; exit}' "$ENV_FILE")

        if [ -n "$value" ]; then
            case "$value" in
                \"*\")
                    value=${value#\"}
                    value=${value%\"}
                    ;;
                \'*\')
                    value=${value#\'}
                    value=${value%\'}
                    ;;
            esac

            printf '%s\n' "$value"
            return
        fi
    fi

    printf '%s\n' "$default"
}
