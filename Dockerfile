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

# Trabajador de subtítulos automáticos. faster-whisper necesita glibc, así que
# usa Debian en vez de Alpine. Comparte el almacenamiento con la aplicación.
# Debe ir antes de "runtime": la etapa final sigue siendo la imagen web.
FROM php:8.4-cli-trixie AS subtitles
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg python3 python3-venv libicu-dev libpq-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
# Modelo "small" (~480 MB) incluido en la imagen: el servidor no descarga nada
# al arrancar y el audio nunca sale del servidor.
RUN python3 -m venv /opt/whisper \
    && /opt/whisper/bin/pip install --no-cache-dir faster-whisper==1.2.1 \
    && /opt/whisper/bin/python -c "from faster_whisper import WhisperModel; WhisperModel('small', device='cpu', compute_type='int8', download_root='/opt/whisper-models')" \
    && chmod -R a+rX /opt/whisper-models
# Mismo uid/gid que www-data en Alpine (82) para leer y escribir el volumen compartido.
RUN groupmod -g 82 www-data && usermod -u 82 -g 82 www-data
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
ENV STREAMING_SUBTITLES_PYTHON=/opt/whisper/bin/python \
    STREAMING_SUBTITLES_MODEL_DIR=/opt/whisper-models \
    STREAMING_SUBTITLES_MODEL=small \
    HF_HUB_OFFLINE=1
USER www-data
CMD ["php", "artisan", "subtitles:generate", "--watch"]

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache nginx supervisor su-exec ffmpeg python3 icu-libs libpq libzip libpng libjpeg-turbo freetype libcurl \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libxml2-dev oniguruma-dev postgresql-dev curl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl exif gd intl mbstring opcache pcntl pdo_pgsql xml zip \
    && apk del .build-deps

# Laravel keeps converted MP4s in private directories (0700). Run Nginx's
# workers as the same www-data user as PHP and the media processors so internal
# X-Accel-Redirect locations can read them without making them public.
RUN sed -i -E 's/^[[:space:]]*user[[:space:]]+nginx([[:space:]]+nginx)?;/user www-data www-data;/' /etc/nginx/nginx.conf \
    && grep -qx 'user www-data www-data;' /etc/nginx/nginx.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/uebtv-build-v2 /var/www/html/public/uebtv-build-v2
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/uebtv-entrypoint
RUN chmod +x /usr/local/bin/uebtv-entrypoint \
    && mkdir -p /run/nginx /var/lib/uebtv/recordings/raw /var/lib/uebtv/recordings/vod /tmp/hls \
    && nginx -t \
    && chown -R www-data:www-data /var/www/html /var/lib/nginx

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/uebtv-entrypoint"]
CMD ["web"]
