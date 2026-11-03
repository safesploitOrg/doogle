#!/usr/bin/env sh

PROJECT_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
COMPOSE_FILE="$SCRIPT_DIR/compose.yml"
ENV_FILE="$PROJECT_ROOT/.env"

docker_compose()
{
    if docker compose version >/dev/null 2>&1; then
        docker_compose_v2 "$@"
        return
    fi

    if command -v docker-compose >/dev/null 2>&1; then
        docker_compose_v1 "$@"
        return
    fi

    printf '%s\n' "Docker Compose is required. Install the Docker Compose v2 plugin or docker-compose v1." >&2
    return 127
}

docker_compose_v2()
{
    project_name=$(env_value DOOGLE_COMPOSE_PROJECT doogle)

    if [ -f "$ENV_FILE" ]; then
        docker compose --env-file "$ENV_FILE" -p "$project_name" -f "$COMPOSE_FILE" "$@"
        return
    fi

    docker compose -p "$project_name" -f "$COMPOSE_FILE" "$@"
}

docker_compose_v1()
{
    project_name=$(env_value DOOGLE_COMPOSE_PROJECT doogle)

    if [ -f "$ENV_FILE" ]; then
        docker-compose --env-file "$ENV_FILE" -p "$project_name" -f "$COMPOSE_FILE" "$@"
        return
    fi

    docker-compose -p "$project_name" -f "$COMPOSE_FILE" "$@"
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
