#!/bin/sh
set -eu

case "${1:-web}" in
    web)
        mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
            storage/logs storage/app/private storage/app/public bootstrap/cache \
            /var/lib/uebtv/recordings/raw /var/lib/uebtv/recordings/vod /tmp/hls
        chown www-data:www-data storage storage/framework storage/framework/cache storage/framework/cache/data \
            storage/framework/sessions storage/framework/views storage/logs storage/app storage/app/private \
            storage/app/public bootstrap/cache
        chmod 0777 /var/lib/uebtv/recordings/raw /var/lib/uebtv/recordings/vod /tmp/hls
        php artisan storage:link --force
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        exec /usr/bin/supervisord -c /etc/supervisord.conf
        ;;
    queue)
        exec su-exec www-data:www-data php artisan queue:work --sleep=3 --tries=3 --timeout=0 --no-interaction
        ;;
    scheduled)
        exec su-exec www-data:www-data php artisan scheduled:process --watch
        ;;
    recordings)
        while :; do
            su-exec www-data:www-data php artisan streaming:prepare-recordings
            sleep 15
        done
        ;;
    *)
        exec "$@"
        ;;
esac
