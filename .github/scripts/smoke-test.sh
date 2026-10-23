#!/usr/bin/env bash
# Smoke test of the production stack (compose.prod.yaml) with its images
# already built as ${IMAGE_PREFIX}-{app,web,ssr}:${IMAGE_TAG}: the migrations
# run, /up answers through Caddy and the home page arrives rendered by the SSR
# server. CI runs it after building the images (ci.yml, docker job); locally,
# once Compose has built and tagged them from the template:
#
#   ENV_FILE=.env.production.example docker compose -f compose.prod.yaml \
#       --env-file .env.production.example build
#   IMAGE_TAG=latest .github/scripts/smoke-test.sh
#
# It brings up a Compose project of its own (laravel-forum-smoke) on
# https://localhost (ports 80 and 443) with throwaway secrets, never reads or
# writes .env.production, and removes the project, volumes included, at the
# end. A failure prints the state and the logs of every service.
set -euo pipefail

cd "$(dirname "$0")/../.."

project=laravel-forum-smoke
export IMAGE_PREFIX="${IMAGE_PREFIX:-ghcr.io/jaisato/laravel-forum}"
export IMAGE_TAG="${IMAGE_TAG:-ci}"
ENV_FILE="$(mktemp)"
export ENV_FILE

compose() {
    docker compose -p "$project" -f compose.prod.yaml --env-file "$ENV_FILE" "$@"
}

cleanup() {
    local status=$?

    if [ "$status" -ne 0 ]; then
        compose ps --all || true
        compose logs --no-color --tail=200 || true
    fi

    compose down --volumes --remove-orphans --timeout 10 || true
    rm -f "$ENV_FILE"

    exit "$status"
}
trap cleanup EXIT

# The production template with throwaway secrets for localhost. Caddy serves
# localhost with its own local CA, hence curl --insecure below.
sed \
    -e "s|^IMAGE_PREFIX=.*|IMAGE_PREFIX=${IMAGE_PREFIX}|" \
    -e "s|^IMAGE_TAG=.*|IMAGE_TAG=${IMAGE_TAG}|" \
    -e "s|^SERVER_NAME=.*|SERVER_NAME=localhost|" \
    -e "s|^APP_URL=.*|APP_URL=https://localhost|" \
    -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 16)|" \
    -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$(openssl rand -hex 16)|" \
    -e "s|^MEILISEARCH_KEY=.*|MEILISEARCH_KEY=$(openssl rand -hex 24)|" \
    -e "s|^MAIL_MAILER=.*|MAIL_MAILER=log|" \
    .env.production.example > "$ENV_FILE"

# Up and healthy, or a failure: app and queue wait for the one-shot migrate.
compose up --detach --wait --wait-timeout 300 --no-build

echo "Migrations:"
compose exec -T app php artisan migrate:status --no-ansi | tail -n 5

echo "Health check (/up through Caddy, Nginx and PHP-FPM):"
curl --fail --silent --show-error --insecure --retry 5 --retry-all-errors --retry-delay 2 \
    --output /dev/null --write-out '%{http_code}\n' https://localhost/up

echo "Home page rendered on the server:"
home="$(curl --fail --silent --show-error --insecure https://localhost/)"

# Without SSR the page is an empty mount point: the header only exists once
# Vue has rendered it.
if ! grep -q '<header' <<<"$home"; then
    echo "The home page did not come rendered by the SSR server." >&2
    exit 1
fi

echo "ok"
