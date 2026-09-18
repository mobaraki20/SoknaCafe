#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
manifest=read('manifest.php')
layout=read('includes/panel_layout.php')
sw=read('service-worker.js')
menu=read('menu/index.php')
favicon=read('favicon.php')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

need("Cache-Control: no-cache, max-age=0, must-revalidate" in manifest,'manifest must revalidate rather than stay cached for an hour')
need("header('ETag: ' . $etag)" in manifest and 'HTTP_IF_NONE_MATCH' in manifest,'manifest needs validator-based revalidation')
need("manifest.php?v=' . rawurlencode(app_release_version() . '.' . favicon_revision())" in layout,'staff manifest URL must change with release/favicon revision')
precache=sw.split('const STATIC=',1)[0]
need("'./favicon.php?size=192'" not in precache and "'./favicon.php?size=512'" not in precache,'dynamic icons must not be pinned in SW precache')
need("Cache-Control: public, max-age=0, must-revalidate" in favicon,'dynamic favicon endpoint must revalidate')
need("assets/js/pwa.js" not in menu,'guest menu must not register/own staff PWA lifecycle')
need('window.PWA_SW_URL' not in menu and 'window.PWA_ENABLED' not in menu,'guest menu must not configure staff service worker')
need("assets/js/push-runtime.js" in menu,'guest response kick runtime must remain available')
print('PASS dev22 phase5 PWA contracts')
