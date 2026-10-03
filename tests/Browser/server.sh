#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [[ -f bootstrap/cache/config.php ]]; then
    echo 'La prueba requiere configuración sin cachear; no se inició ninguna migración.' >&2
    exit 1
fi
export DB_CONNECTION=sqlite DB_DATABASE="/tmp/uebtv-react-e2e-$$.sqlite"
export LARAVEL_STORAGE_PATH="$(mktemp -d /tmp/uebtv-browser-storage-XXXXXX)"
mkdir -p "$LARAVEL_STORAGE_PATH/app/private" "$LARAVEL_STORAGE_PATH/framework/views" "$LARAVEL_STORAGE_PATH/framework/sessions" "$LARAVEL_STORAGE_PATH/framework/cache/data" "$LARAVEL_STORAGE_PATH/logs"
export SESSION_DRIVER=file SESSION_COOKIE=uebtv_e2e CACHE_STORE=array
export APP_URL=http://127.0.0.1:8017 APP_ENV=testing
cleanup() { [[ -z "${browser_server_pid:-}" ]] || kill "$browser_server_pid" 2>/dev/null || true; rm -f "$DB_DATABASE"; rm -rf -- "$LARAVEL_STORAGE_PATH"; }
trap cleanup EXIT
trap 'exit 0' TERM INT
php artisan migrate --force
php tests/Browser/seed.php
ffmpeg -hide_banner -loglevel error -f lavfi -i color=c=0x102f56:s=320x180:r=1:d=60 -c:v libx264 -pix_fmt yuv420p -movflags +faststart -y /tmp/uebtv-react-test.mp4
cd public
php -d upload_max_filesize=500M -d post_max_size=512M -S 127.0.0.1:8017 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php &
browser_server_pid=$!
wait "$browser_server_pid"
