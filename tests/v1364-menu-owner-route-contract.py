#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT / path).read_text(encoding='utf-8')

menu = read('menu/index.php')
index = read('index.php')
functions = read('includes/functions.php')
waiter = read('api/waiter_call.php')
settings = read('admin/settings.php')
htaccess = read('.htaccess')
sitemap = read('sitemap.php')
robots = read('robots.php')
worker = read('service-worker.js')
about = read('about.php')
menu_js = read('assets/js/menu.js')
menu_css = read('assets/css/guest-menu.css')
modules = read('includes/modules.php')

assert not (ROOT / 'menu.php').exists(), 'legacy root menu.php must be removed'
assert (ROOT / 'menu/index.php').exists(), 'canonical /menu/ entrypoint missing'
assert 'menu.php' not in htaccess
assert 'RewriteRule ^Menu/?$ menu/' in htaccess
assert "redirect(asset('login.php'))" in index and 'assets/css/guest-menu.css' not in index
assert "canonical_asset('menu/')" in menu
assert '<meta name="robots" content="noindex,nofollow,noarchive">' in menu
assert 'guest-mode-public' in menu and 'guest-mode-table' in menu
assert menu.count('assets/js/menu.js') == 1 and 'assets/js/menu-preview.js' not in menu
assert not (ROOT / 'assets/js/menu-preview.js').exists()
assert "return canonical_asset('menu/') . '?table='" in functions
assert "canonical_asset('menu/')" in sitemap and "canonical_asset('index.php')" not in sitemap
assert 'Disallow: /*?table=' not in robots
assert "canonical_asset('menu/')" in about and "asset('menu/'" in about
assert "'menu/index.php'" in modules
assert 'public_waiter_call_enabled' in settings and 'preview_waiter_call_enabled' not in settings
assert "public_table_id" in waiter and "preview_table_id" not in waiter
assert "SELECT id,name FROM cafe_tables WHERE id=? AND active=1 LIMIT 1" in waiter
assert "if ($action === 'create' && $isPublicRequest && !waiter_call_allowed(true))" in waiter
assert "client_token=? AND status='new'" in waiter
assert 'CAFE_PUBLIC_WAITER_TABLES' in menu and 'CAFE_PUBLIC_WAITER_TABLES' in menu_js
assert 'public_table_id' in menu_js
assert 'guest-mode-preview' not in menu_css and 'is-menu-preview' not in menu_css
assert './assets/js/menu-preview.js' not in worker and './assets/js/menu.js' in worker
assert 'index.php?table=' not in '\n'.join([menu, functions, about, sitemap])
print('Canonical /menu/ owner + uppercase QR redirect contracts PASS')
