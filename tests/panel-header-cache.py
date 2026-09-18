#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
functions = (ROOT / "includes/functions.php").read_text(encoding="utf-8")
layout = (ROOT / "includes/panel_layout.php").read_text(encoding="utf-8")
panel_js = (ROOT / "assets/js/panel-core.js").read_text(encoding="utf-8")
shell_js = (ROOT / "assets/js/panel-shell.js").read_text(encoding="utf-8")
sw = (ROOT / "service-worker.js").read_text(encoding="utf-8")
version = (ROOT / "VERSION.txt").read_text(encoding="utf-8").strip()

assert version
assert "function app_release_version()" in functions
assert "rawurlencode($revision)" in functions
asset_block = functions.split("function asset(string $path): string", 1)[1].split("function canonical_asset", 1)[0]
assert "filemtime(" not in asset_block, "Release assets must not use preserved ZIP timestamps as cache keys."
assert "hash_file('sha256', $file)" in asset_block, "Changed assets must invalidate same-line release caches."
assert "<span class=\"panel-clock-date\"><?= e(jalali_date_input()) ?></span>" in layout
assert "<span class=\"panel-clock-date\"><?= e(format_jalali_compact()) ?></span>" not in layout
assert "document.documentElement.dataset.panelUiReady" in panel_js and "'1'" in panel_js
assert "document.documentElement.dataset.panelShellReady" in shell_js
assert "./assets/js/panel-shell.js" in sw
assert "./assets/js/panel-navigation.js" not in sw
assert f"const RELEASE='{version}'" in sw
assert "url.startsWith('./assets/')?`${url}?v=${RELEASE}`:url" in sw
print("Panel header cache resilience passed: content-addressed release assets, date-only fallback and fresh panel controller.")
