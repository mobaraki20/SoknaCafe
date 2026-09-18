#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; guest 1.28 quantity check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="guest-menu-page"><main style="padding:20px;display:grid;gap:24px"><article class="cart-line"><div><h4>پاستا</h4></div><div class="qty-control"><button type="button" data-dec>−</button><strong id="cartCount">۱</strong><button type="button" data-inc>+</button></div></article><article><div class="detail-qty-control"><button type="button" data-detail-dec>−</button><strong id="detailCount">۱۲</strong><button type="button" data-detail-inc>+</button></div></article><article class="item-action-row"><div class="inline-qty has-items"><button type="button">−</button><span id="inlineCount">۱۲</span><button type="button" class="inline-add">+</button></div></article></main></body></html>'''
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/guest-menu.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,412):
        page=browser.new_page(viewport={'width':width,'height':700})
        page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        for selector in ('.qty-control','.detail-qty-control','.inline-qty.has-items'):
            box=page.locator(selector)
            buttons=box.locator('button')
            left=buttons.nth(0).bounding_box();right=buttons.nth(1).bounding_box()
            assert left and right
            assert abs(left['width']-right['width']) < .6, (width,selector,left,right)
            assert abs(left['height']-right['height']) < .6, (width,selector,left,right)
            assert page.locator(selector).evaluate("e=>getComputedStyle(e).overflow==='hidden'")
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
        page.screenshot(path=f'/mnt/data/sokna-1280-qty-{width}.png',full_page=True)
        page.locator('#cartCount').evaluate("e=>e.textContent='۱۲'")
        page.locator('#detailCount').evaluate("e=>e.textContent='۱'")
        for selector in ('.qty-control','.detail-qty-control'):
            b=page.locator(selector).locator('button')
            assert abs(b.nth(0).bounding_box()['width']-b.nth(1).bounding_box()['width'])<.6
        page.close()
    browser.close()
print('Guest 1.28 quantity controls passed at 320/390/412px with one- and two-digit counts and equal plus/minus geometry.')
