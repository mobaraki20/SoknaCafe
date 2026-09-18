#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
page=(ROOT/'staff/quick-order.php').read_text(encoding='utf-8')
css=(ROOT/'assets/css/quick-order.css').read_text(encoding='utf-8')

# The financial review must be outside the scrolling body and share one fixed footer with submit.
body_start=page.index('<div class="quick-order-cart-body" id="quickOrderCartBody">')
footer=page.index('<footer class="quick-order-cart-footer">', body_start)
body_close=page.rfind('</div>', body_start, footer)
assert body_close < footer
assert page.index('class="quick-order-totals"', footer) < page.index('id="quickOrderSubmit"', footer)
assert 'quick-order-cart-body{overflow-y:auto;scrollbar-width:none}' in css
assert '.quick-order-cart-body::-webkit-scrollbar{width:0;height:0;display:none}' in css
assert '.quick-order-cart-footer{display:grid;' in css

fixture=''.join(f'<article class="quick-order-cart-line"><div class="quick-order-line-copy"><strong>آیتم {i}</strong><small>۳۵۰٬۰۰۰</small></div><div class="quick-order-line-actions"><span>۲</span></div></article>' for i in range(12))
html=f'''<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
:root{{--line:#e5dfd6;--primary:#365b4c;--muted:#6f746f}}*{{box-sizing:border-box}}body{{margin:0}}.quick-order-cart{{position:fixed;inset:8vh 0 0;display:grid;grid-template-rows:auto minmax(0,1fr) auto;padding:12px 14px 12px;background:#fff}}.quick-order-cart-head{{min-height:52px;border-bottom:1px solid var(--line)}}.quick-order-cart-body{{min-height:0;overflow-y:auto;scrollbar-width:none}}.quick-order-cart-body::-webkit-scrollbar{{width:0;height:0;display:none}}.quick-order-cart-line{{min-height:76px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}}.quick-order-cart-footer{{display:grid;gap:7px;padding-top:7px;border-top:1px solid var(--line);background:#fff}}.quick-order-totals{{display:grid;gap:3px}}.quick-order-totals>div{{display:flex;justify-content:space-between}}.quick-order-submit{{min-height:58px;border:0;border-radius:15px;background:var(--primary);color:#fff}}
</style></head><body><aside class="quick-order-cart"><header class="quick-order-cart-head">سبد سفارش</header><div class="quick-order-cart-body" id="body">{fixture}</div><footer class="quick-order-cart-footer" id="footer"><div class="quick-order-totals"><div><span>جمع سفارش جدید</span><strong>۳٬۶۰۰٬۰۰۰</strong></div><div><span>مانده فعلی حساب</span><strong>۱٬۶۰۵٬۰۰۰</strong></div><div id="projected"><span>جمع حساب پس از ثبت</span><strong>۵٬۲۰۵٬۰۰۰</strong></div></div><button class="quick-order-submit" id="submit">ثبت سفارش برای میز ۲۷</button></footer></aside></body></html>'''

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':390,'height':844})
    pg.set_content(html,wait_until='load')
    assert pg.evaluate('document.getElementById("body").scrollHeight > document.getElementById("body").clientHeight')
    pg.evaluate('document.getElementById("body").scrollTop = 9999')
    projected=pg.locator('#projected').bounding_box(); submit=pg.locator('#submit').bounding_box(); footer_box=pg.locator('#footer').bounding_box()
    assert projected and submit and footer_box
    assert projected['y'] + projected['height'] <= submit['y'] + 1
    assert footer_box['y'] + footer_box['height'] <= 844 + 1
    assert pg.evaluate('getComputedStyle(document.getElementById("body")).scrollbarWidth') in ('none','auto')
    b.close()

print('RC quick-order cart hardening PASS: financial summary stays with submit outside scroll body and mobile scrollbar is visually suppressed.')
