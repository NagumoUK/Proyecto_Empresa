FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl mbstring pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["sh", "-c", "if [ ! -f vendor/autoload.php ]; then composer install --no-interaction --prefer-dist --no-progress; fi && exec php artisan serve --host=0.0.0.0 --port=8000"]
