FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j$(nproc) bcmath intl pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN git config --system --add safe.directory /var/www/html \
    && mkdir -p /var/www/.composer \
    && chown www-data:www-data /var/www/.composer /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./

USER www-data

RUN composer install --no-interaction --no-scripts --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize

CMD ["php-fpm"]
