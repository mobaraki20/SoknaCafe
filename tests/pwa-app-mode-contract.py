#!/usr/bin/env python3
from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
layout=read('includes/panel_layout.php')
css=read('assets/css/panel-layout.css')
pwa=read('assets/js/pwa.js')
sw=read('service-worker.js')
offline=read('offline.html')
assert 'viewport-fit=cover' in layout
assert 'pwa-standalone' in layout and 'app-bottom-nav' in layout
assert 'panel-network-banner' in layout and 'pwa-update-banner' in layout
assert 'data-panel-nav-toggle data-app-nav-more' in layout
assert '.pwa-standalone .app-bottom-nav' in css
assert '.pwa-standalone .panel-content' in css and 'safe-area-inset-bottom' in css
assert '.pwa-standalone .panel-topbar' in css and 'safe-area-inset-top' in css
assert 'navigator.onLine === false' in pwa
assert "waitingWorker.postMessage({ type: 'SKIP_WAITING' })" in pwa
assert 'unsafeToReload' in pwa and 'has-panel-dialog' in pwa and '[aria-busy="true"]' in pwa
assert 'controllerchange' in pwa and 'if (updateRequested) location.reload()' in pwa
# No unconditional app reload on controller change and no immediate SW activation.
assert "self.addEventListener('install',event=>event.waitUntil" in sw
install=re.search(r"self\.addEventListener\('install'.*?\);\n",sw,re.S)
assert install and 'skipWaiting' not in install.group(0)
assert "event.data?.type==='SKIP_WAITING'" in sw and 'self.skipWaiting()' in sw
assert "caches.match('./offline.html')" in sw
assert "await cache.add('./offline.html')" in sw
assert 'Promise.allSettled' in sw
assert 'سامانه موقتاً در دسترس نیست' not in re.search(r"if\(req\.mode==='navigate'\).*?return;\}",sw,re.S).group(0) or "new Response('سامانه موقتاً در دسترس نیست'" in sw
# The rich offline HTML has one owner rather than being duplicated in the worker.
assert '<!doctype html>' not in sw.lower()
assert 'viewport-fit=cover' in offline and 'safe-area-inset-bottom' in offline
print('PWA app-mode contracts passed: standalone shell, safe-area, single offline owner, fail-soft precache and user-controlled safe update lifecycle.')
