FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache nginx supervisor su-exec ffmpeg python3 icu-libs libzip libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libxml2-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mbstring opcache pcntl pdo_pgsql xml zip \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/uebtv-build-v2 /var/www/html/public/uebtv-build-v2
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/uebtv-entrypoint
RUN chmod +x /usr/local/bin/uebtv-entrypoint \
    && mkdir -p /run/nginx /var/lib/uebtv/recordings/raw /var/lib/uebtv/recordings/vod /tmp/hls \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/uebtv-entrypoint"]
CMD ["web"]
