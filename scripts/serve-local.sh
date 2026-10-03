#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
video_worker_pid=""
if ! pgrep -f '[s]cheduled:process --watch' >/dev/null 2>&1; then
    php artisan scheduled:process --watch &
    video_worker_pid=$!
fi
cleanup() { kill "$video_worker_pid" "${video_server_pid:-}" 2>/dev/null || true; }
trap cleanup EXIT
trap 'exit 0' TERM INT

# `composer run dev` can be started while a local Laravel server is already
# running in another terminal. Reuse that server instead of making
# concurrently stop Vite, the queue listener, and the logs when port 8000
# refuses a second listener.
if php -r '$socket = @fsockopen("127.0.0.1", 8000, $errno, $error, 1); if ($socket) { fclose($socket); exit(0); } exit(1);'; then
    echo "Puerto 8000 ocupado: se conserva el servidor existente y el procesador de videos sigue activo."
    if [[ -n "$video_worker_pid" ]]; then wait "$video_worker_pid"; fi
    while true; do sleep 3600; done
fi

cd public
php -d upload_max_filesize=500M -d post_max_size=512M -d max_input_time=600 -d max_execution_time=600 -S 0.0.0.0:8000 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php &
video_server_pid=$!
wait "$video_server_pid"
