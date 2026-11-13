FROM node:22-bookworm-slim AS node

FROM php:8.4-cli-bookworm AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" dom exif gd intl mbstring pdo_pgsql sockets zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

WORKDIR /var/www/html

COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --optimize-autoloader \
    && node /usr/local/lib/node_modules/npm/bin/npm-cli.js install --global pnpm@12.6.0 \
    && pnpm install --frozen-lockfile \
    && pnpm run build \
    && rm -rf node_modules

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6 \
        libfreetype6-dev \
        libicu-dev \
        libicu72 \
        libjpeg62-turbo \
        libjpeg62-turbo-dev \
        libonig-dev \
        libonig5 \
        libpng-dev \
        libpng16-16 \
        libpq-dev \
        libpq5 \
        libxml2-dev \
        libxml2 \
        libwebp-dev \
        libwebp7 \
        libzip-dev \
        libzip4 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" dom exif gd intl mbstring pdo_pgsql sockets zip \
    && apt-get purge -y --auto-remove \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libwebp-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=build /var/www/html /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN mkdir -p storage/app storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

FROM app AS dev

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npm install --global pnpm@12.6.0 \
    && composer install --no-interaction --prefer-dist --no-progress \
    && pnpm install --frozen-lockfile

FROM app AS octane

RUN php artisan octane:install --server=roadrunner --no-interaction --force \
    && mkdir -p /usr/local/etc/roadrunner \
    && mv rr /usr/local/bin/rr \
    && mv .rr.yaml /usr/local/etc/roadrunner/rr.yaml \
    && chmod 0755 /usr/local/bin/rr

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000", "--workers=2", "--max-requests=500", "--rr-config=/usr/local/etc/roadrunner/rr.yaml"]

FROM dev AS octane-dev

RUN php artisan octane:install --server=roadrunner --no-interaction --force \
    && mkdir -p /usr/local/etc/roadrunner \
    && mv rr /usr/local/bin/rr \
    && mv .rr.yaml /usr/local/etc/roadrunner/rr.yaml \
    && chmod 0755 /usr/local/bin/rr

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000", "--workers=2", "--max-requests=500", "--rr-config=/usr/local/etc/roadrunner/rr.yaml"]

FROM nginx:1.28-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

EXPOSE 80
