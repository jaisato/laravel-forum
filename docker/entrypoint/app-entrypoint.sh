#!/bin/sh
# Entrypoint of the app image, whatever it runs (php-fpm, Horizon, the
# scheduler, the migrations): caches the configuration, routes, events and
# views for the environment the container starts with, then hands over to
# the PHP image's own entrypoint.
set -eu

php artisan optimize --no-interaction

# The container that answers requests also caches the API documentation
# (/docs/api): generating it takes seconds of static analysis, which every
# request for it would otherwise pay again.
# A failure here is no reason to keep the forum down: the documentation is
# then generated on request.
if [ "${1:-}" = "php-fpm" ]; then
    php artisan scramble:cache --no-interaction \
        || echo "The API documentation could not be cached; it will be generated on request." >&2
fi

exec docker-php-entrypoint "$@"
