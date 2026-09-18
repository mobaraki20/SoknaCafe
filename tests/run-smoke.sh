#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
exec bash tests/run-1360-dev-gate.sh
