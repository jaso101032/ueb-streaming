#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
recording_config=$(mktemp)
trap 'rm -f "$recording_config"' EXIT
php scripts/recording-install-config.php > "$recording_config"
if [ "$(id -u)" -eq 0 ]; then
    python3 scripts/install-recordings.py "$recording_config"
else
    sudo python3 scripts/install-recordings.py "$recording_config"
fi
