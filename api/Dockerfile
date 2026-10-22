# The production API for Render's free web service (512 MB, ~0.1 CPU, ephemeral disk, $PORT).
# FrankenPHP is PHP embedded in the Caddy web server: one process answers HTTP, with no
# nginx + PHP-FPM pair to run and supervise. Everything secret comes from the environment
# at runtime; nothing here bakes in a .env.

# Pinned to an exact release. Bump it on purpose, not by accident.
FROM dunglas/frankenphp:1.12.7-php8.4-bookworm AS base

# pdo_pgsql for Neon; opcache because PHP 8.4 ships it but doesn't enable it. Everything else
# the app needs (mbstring, openssl, tokenizer, dom, fileinfo, ...) is already in the image.
RUN install-php-extensions pdo_pgsql opcache

WORKDIR /app

# --- Build stage: composer and its tools stay here and never reach the final image. ---
FROM base AS vendor

COPY --from=composer:2.9.8 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Dependencies first, so a code-only change reuses this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
# Runs package:discover too, which writes bootstrap/cache/{packages,services}.php for the
# production packages only (the dev ones were never installed).
RUN composer dump-autoload --optimize --no-dev --no-interaction

# --- Runtime ---
FROM base

# Run as a plain user. The image gives the frankenphp binary the capability to bind ports
# below 1024; we bind $PORT (10000 on Render), so drop it, or a non-root start can fail.
# Above the code copies, so a code change doesn't rerun apt.
RUN apt-get update     && apt-get install -y --no-install-recommends libcap2-bin     && setcap -r /usr/local/bin/frankenphp     && rm -rf /var/lib/apt/lists/*     && useradd --create-home --shell /usr/sbin/nologin app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-pesonal.ini"
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/pesonal-entrypoint
# sed: a checkout with Windows line endings would otherwise break the shebang.
RUN sed -i 's/$//' /usr/local/bin/pesonal-entrypoint && chmod 755 /usr/local/bin/pesonal-entrypoint

COPY --from=vendor /app /app

# Only storage/, bootstrap/cache/ and Caddy's own dirs are writable; the code stays root-owned.
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions         storage/framework/views storage/logs bootstrap/cache     && chown -R app:app storage bootstrap/cache /data/caddy /config/caddy

USER app

ENV PORT=8080
EXPOSE 8080

# The base image's healthcheck probes Caddy's metrics on :2019, which admin off turns off, so
# it would report "unhealthy" forever. Render checks /up itself.
HEALTHCHECK NONE

ENTRYPOINT ["pesonal-entrypoint"]
# The base image's CMD would be passed to the entrypoint as arguments; it takes none.
CMD []