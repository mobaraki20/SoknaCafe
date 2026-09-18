#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

purchases=read('admin/purchases.php')
inventory=read('admin/inventory.php')
activity=read('admin/activity_report.php')
flow=read('assets/js/inventory-form-flow.js')
adjustment=read('admin/inventory_adjustment.php')
css='\n'.join(read(p) for p in [
    'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css',
    'assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css'
])

# Source-level workflow/UI contracts.
assert 'purchase-page-head inventory-toolbar' in purchases
assert 'purchase-intro-card' not in purchases
assert 'اقلام موردنیاز را برای خرید انتخاب کن و بعد از تحویل، مقدار واقعی را ثبت کن.' in purchases
assert 'purchase-history-row' in purchases and 'data-row-href=' in purchases and "['movement_id']" in purchases and "&from=purchases" in purchases
assert 'برای مشاهده یا اصلاح، روی ردیف بزن.' in purchases
assert 'inventory_adjustment_allowed_modes' in inventory and 'inventory-movement is-navigable' not in inventory  # conditional class, not hard-coded static fixture
assert 'data-row-href=' in inventory and 'inventory-movement-chevron' in inventory
assert '>اصلاح</a>' not in inventory[inventory.index('<div class="inventory-movement-list">'):inventory.index('<?php if($movementPages>1):')]
assert 'اطلاعات خام Audit' not in activity
assert 'سابقه ثبت‌شده بدون تغییر حفظ می‌شود.' in activity
assert "window.innerWidth > 760" in flow and "'(pointer: coarse)'" in flow
assert "$returnTo=$returnContext==='purchases'?'purchases.php':'inventory.php?tab=movements';" in adjustment
assert 'name="from" value="purchases"' in adjustment

html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--line:#e5ddd3;--muted:#736b64;--font-ui:system-ui;--panel-line:#e5ddd3;--panel-surface:#fff}}body{{margin:0}}{css}</style></head><body class="panel-body panel-section-inventory"><main class="panel-content"><div class="panel-surface-stack purchase-page">
<header class="purchase-page-head inventory-toolbar"><div class="panel-copy-stack"><strong>خرید و تحویل</strong><small class="muted">اقلام موردنیاز را برای خرید انتخاب کن و بعد از تحویل، مقدار واقعی را ثبت کن.</small></div><div class="inventory-toolbar-actions"><a class="btn btn-light">اعلام نیاز</a><button class="btn btn-light">اشتراک فهرست در حال خرید</button></div></header>
<section class="card purchase-history-card"><div class="card-head"><div><h2>آخرین تحویل‌ها</h2><small>ورودهای واقعی ثبت‌شده از همین فرآیند خرید.</small></div></div><div class="purchase-history-list"><article class="purchase-history-row is-navigable" data-row-href="inventory_adjustment.php?id=42" role="link" tabindex="0"><div class="purchase-history-main"><strong>شیر دومینو</strong><small>مدیر · امروز ۱۸:۲۰</small></div><span class="purchase-history-qty">۱۰ لیتر</span><span class="purchase-history-chevron">‹</span></article></div></section>
<article class="inventory-movement is-navigable" data-row-href="inventory_adjustment.php?id=42" role="link" tabindex="0"><div class="movement-main"><strong>شیر دومینو</strong><small>ورود خرید · امروز</small></div><div class="movement-qty"><strong>۱۰ لیتر</strong><small>بار</small></div><div class="movement-cost"><strong>۱٬۲۰۰٬۰۰۰ تومان</strong><small>قیمت مشخص</small></div><div class="movement-action"><span class="inventory-movement-chevron">‹</span></div></article>
<form id="flow" data-inventory-flow><input id="initial" class="form-control" data-inventory-step data-inventory-autofocus><input id="second" class="form-control" data-inventory-step></form>
</div></main><script>{flow}</script></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    mobile=browser.new_page(viewport={'width':390,'height':844})
    mobile.set_content(html,wait_until='load'); mobile.wait_for_timeout(80)
    assert mobile.evaluate('document.activeElement && document.activeElement.id') != 'initial'
    assert mobile.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    ph=mobile.locator('.purchase-history-row').bounding_box(); mv=mobile.locator('.inventory-movement').bounding_box()
    assert ph and ph['height'] >= 54
    assert mv and mv['height'] >= 54
    actions=mobile.locator('.purchase-page-head .inventory-toolbar-actions').bounding_box(); assert actions and actions['width'] <= 390
    mobile.close()

    desktop=browser.new_page(viewport={'width':1024,'height':900})
    desktop.set_content(html,wait_until='load'); desktop.wait_for_timeout(80)
    assert desktop.evaluate('document.activeElement && document.activeElement.id') == 'initial'
    desktop.close(); browser.close()

print('1.36 operational UI/workflow parity PASS: compact purchase header, whole-row correction targets, human activity copy, and no unsolicited mobile inventory keyboard.')
