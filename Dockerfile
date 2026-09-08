# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git unzip libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libgmp-dev libonig-dev libxml2-dev python3 libfcgi-bin \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 bcmath gmp mbstring pdo_mysql zip intl gd pcntl sockets opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress
COPY . .
RUN mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev \
    && php artisan ziggy:generate resources/js/ziggy-routes.js

FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=dependencies /var/www/html .
RUN npm run build

FROM php-base AS app
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /build/public/build /var/www/html/public/build
COPY --from=assets --chown=www-data:www-data /build/bootstrap/ssr /var/www/html/bootstrap/ssr
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage \
    && rm -f /usr/local/bin/composer \
    && printf '[www]\nping.path = /ping\n' > /usr/local/etc/php-fpm.d/zz-health.conf
USER www-data
STOPSIGNAL SIGQUIT
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
RUN rm /etc/nginx/conf.d/default.conf
STOPSIGNAL SIGQUIT

FROM mysql:8.4 AS backup
USER root
RUN microdnf install -y python3 && microdnf clean all
COPY installer/docker/backup.py /usr/local/bin/backup.py
ENTRYPOINT ["python3", "/usr/local/bin/backup.py"]
CMD []
