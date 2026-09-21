#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Render the secret locally, never print it or pass it in command arguments.
stream_snippet=$(mktemp)
trap 'rm -f "$stream_snippet"' EXIT
php scripts/stream-control-snippet.php > "$stream_snippet"
sudo python3 scripts/install-stream-control.py "$stream_snippet"
