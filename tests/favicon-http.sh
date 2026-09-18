#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT=$((19000 + ($$ % 1000)))
TMP="${TMPDIR:-/tmp}/sokna-favicon-$$"
mkdir -p "$TMP"
php -S "127.0.0.1:${PORT}" -t "$ROOT" >"$TMP/server.log" 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true; rm -rf "$TMP"' EXIT
for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:${PORT}/favicon.php?size=32" -o "$TMP/32.png" 2>/dev/null && break; sleep .1; done
for size in 32 180 192 512; do
  curl -fsS -D "$TMP/$size.headers" "http://127.0.0.1:${PORT}/favicon.php?size=$size" -o "$TMP/$size.png"
  grep -qi '^Content-Type: image/png' "$TMP/$size.headers"
  php -r '$i=getimagesize($argv[1]);if(!$i||(int)$i[0]!=(int)$argv[2]||(int)$i[1]!=(int)$argv[2])exit(1);' "$TMP/$size.png" "$size"
done
printf 'Favicon HTTP endpoint passed.\n'
