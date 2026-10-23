#!/bin/sh
# Entrypoint of the app image, whatever it runs (php-fpm, Horizon, the
# scheduler, the migrations): caches the configuration, routes, events and
# views for the environment the container starts with, then hands over to
# the PHP image's own entrypoint.
set -eu

php artisan optimize --no-interaction

exec docker-php-entrypoint "$@"
