# syntax=docker/dockerfile:1

# Every image of Laravel Forum comes from this file (docs/04-despliegue.md):
#
#   dev  PHP-FPM for compose.yaml, run as the host user over the mounted
#        code: Composer, Node (Vite and Wayfinder), pcov and Xdebug
#   app  production PHP-FPM: the code, vendor without dev packages, the
#        compiled assets and OPcache. Horizon, the scheduler and the
#        migrations run from it too
#   web  Nginx: public/ with the compiled assets; PHP goes to app:9000
#   ssr  Node: the Inertia SSR bundle (bootstrap/ssr) and nothing else
#
# base, vendor and assets are intermediate stages.

FROM composer:2 AS composer

FROM node:24-alpine3.24 AS node

# ----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine3.24 AS base

# The PHP extensions the forum needs (OPcache comes enabled with the image);
# the build toolchain goes away in the same layer. icu-data-full gives intl
# the Spanish locale data (Alpine ships English alone by default) and exif
# lets the avatars turn the way the photo was taken. FPM runs as the image
# user, so the pool loses its user and group lines.
# hadolint ignore=SC2086
RUN set -eux; \
    apk add --no-cache fcgi freetype icu-data-full icu-libs libjpeg-turbo \
        libpng libpq libwebp libzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS freetype-dev \
        icu-dev libjpeg-turbo-dev libpng-dev libpq-dev libwebp-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pcntl pdo_pgsql zip; \
    pecl install redis-6.3.0; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/pear; \
    apk del --no-network .build-deps; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    sed -i '/^user = /d; /^group = /d' /usr/local/etc/php-fpm.d/www.conf

COPY docker/php/conf.d/forum.ini $PHP_INI_DIR/conf.d/forum.ini
COPY docker/php/php-fpm.d/zz-forum.conf /usr/local/etc/php-fpm.d/zz-forum.conf
COPY --chmod=0755 docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck

# Pool sizes (zz-forum.conf): about 50 MB per child, tune them to the memory
# the container gets.
ENV PHP_FPM_PM_MAX_CHILDREN=20 \
    PHP_FPM_PM_START_SERVERS=4 \
    PHP_FPM_PM_MIN_SPARE_SERVERS=2 \
    PHP_FPM_PM_MAX_SPARE_SERVERS=6 \
    PHP_FPM_PM_MAX_REQUESTS=1000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --start-interval=2s --retries=3 \
    CMD ["php-fpm-healthcheck"]

# ----------------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

# $PHPIZE_DEPS is a list of packages: it has to be split.
# hadolint ignore=SC2086
RUN set -eux; \
    apk add --no-cache git libstdc++ unzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers; \
    pecl install pcov-1.0.12 xdebug-3.5.3; \
    docker-php-ext-enable pcov xdebug; \
    rm -rf /tmp/pear; \
    apk del --no-network .build-deps; \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/dev.ini $PHP_INI_DIR/conf.d/forum-dev.ini
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

# npm and npx, and a user with the host's UID and GID so the files written
# in the mounted code (vendor, node_modules, caches) belong to the host user.
RUN set -eux; \
    ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm; \
    ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx; \
    group="$(getent group "$GID" || true)"; \
    group="${group%%:*}"; \
    if [ -z "$group" ]; then group=forum; addgroup -g "$GID" "$group"; fi; \
    if ! getent passwd "$UID" >/dev/null; then \
        adduser -D -u "$UID" -G "$group" -h /home/forum -s /bin/sh forum; \
    fi

USER ${UID}:${GID}

# ----------------------------------------------------------------------------
FROM base AS vendor

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# The dependencies first: this layer changes only with composer.lock. The
# optional composer_auth secret (COMPOSER_AUTH JSON) lifts GitHub's limits.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    --mount=type=secret,id=composer_auth,env=COMPOSER_AUTH \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev \
        --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

COPY . .

# The autoloader runs package:discover and filament:upgrade (Filament's
# assets in public/); Wayfinder writes the TypeScript routes for assets.
RUN set -eux; \
    mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/testing \
        storage/framework/views storage/logs bootstrap/cache; \
    composer dump-autoload --no-dev --optimize --no-interaction; \
    php artisan wayfinder:generate --with-form

# ----------------------------------------------------------------------------
FROM node AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund

COPY tsconfig.json vite.config.ts ./
COPY lang lang
COPY resources resources
COPY --from=vendor /var/www/html/resources/js/actions resources/js/actions
COPY --from=vendor /var/www/html/resources/js/routes resources/js/routes
COPY --from=vendor /var/www/html/resources/js/wayfinder resources/js/wayfinder
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    vendor/laravel/framework/src/Illuminate/Pagination/resources/views

# The name in the page titles, fixed at build time (keep it equal to APP_NAME).
ARG VITE_APP_NAME="Laravel Forum"
# This stage has no PHP: Wayfinder already ran in vendor.
ENV WAYFINDER_COMMAND=true

RUN npm run build:ssr

# ----------------------------------------------------------------------------
FROM base AS app

WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY docker/php/conf.d/production.ini $PHP_INI_DIR/conf.d/forum-production.ini
COPY --chmod=0755 docker/entrypoint/app-entrypoint.sh /usr/local/bin/app-entrypoint

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

# The code stays read-only for PHP: only storage and the caches are writable.
# public/storage is the link `php artisan storage:link` would create.
RUN set -eux; \
    chown -R www-data:www-data storage bootstrap/cache; \
    ln -sfn ../storage/app/public public/storage

USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ----------------------------------------------------------------------------
FROM nginx:1.29-alpine3.23 AS web

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=vendor /var/www/html/public /var/www/html/public
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

# The site lives in nginx.conf; public/storage serves the avatars from the
# storage volume, mounted read-only at /var/www/html/storage.
RUN set -eux; \
    rm /etc/nginx/conf.d/default.conf; \
    ln -sfn ../storage/app/public /var/www/html/public/storage

USER nginx

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --start-interval=2s --retries=3 \
    CMD ["wget", "-q", "-O", "/dev/null", "http://127.0.0.1:8080/up"]

# ----------------------------------------------------------------------------
FROM node AS ssr

ENV NODE_ENV=production

WORKDIR /srv/ssr

# A self-contained bundle (vite.config.ts: ssr.noExternal) in ES modules.
COPY --from=assets /var/www/html/bootstrap/ssr bootstrap/ssr
RUN printf '{"type": "module"}\n' > package.json

USER node

EXPOSE 13714

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --start-interval=2s --retries=3 \
    CMD ["wget", "-q", "-O", "/dev/null", "http://127.0.0.1:13714/health"]

CMD ["node", "bootstrap/ssr/ssr.js"]
