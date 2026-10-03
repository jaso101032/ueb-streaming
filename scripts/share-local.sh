#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

# Remote devices cannot use a Vite HMR URL that points back to the host's
# development address. Build assets into public/ and remove Laravel's hot
# marker so every client downloads them from the app on port 8000.
npm run build
rm -f public/hot
exec bash scripts/serve-local.sh
