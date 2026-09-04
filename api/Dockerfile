FROM php:8.4-cli

# git + unzip so Composer can fetch and extract packages (e.g. dev deps like larastan);
# the base image ships neither, so a `composer require` would otherwise fail to extract.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# install-php-extensions auto-handles the system libraries each PHP
# extension needs, so we don't hand-manage apt packages for them.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions pdo_sqlite mbstring bcmath

# Borrow the Composer binary from its official image (multi-stage copy).
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

# Bind to 0.0.0.0 so the server is reachable from outside the container.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]