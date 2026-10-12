#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
token="$(sed -n 's/^STREAMING_CALLBACK_TOKEN=//p' .env | head -n 1)"
if [[ ! "$token" =~ ^[a-f0-9]{64}$ ]]; then
    echo "STREAMING_CALLBACK_TOKEN debe estar configurado con 64 caracteres hexadecimales." >&2
    exit 1
fi

mkdir -p /tmp/hls
podman rm -f uebtv-rtmp >/dev/null 2>&1 || true
podman run -d --name uebtv-rtmp --network host \
    -e STREAMING_CALLBACK_TOKEN="$token" \
    -v "$PWD/scripts/nginx-rtmp-local.conf.template:/etc/nginx/nginx.conf.template:ro,Z" \
    -v /tmp/hls:/tmp/hls:Z \
    docker.io/alfg/nginx-rtmp:latest

echo "RTMP local activo: rtmp://127.0.0.1:1935/live"
echo "Abre UEBTV en: http://127.0.0.1:8080"
