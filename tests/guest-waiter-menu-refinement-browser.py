#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/path).read_text(encoding='utf-8') for path in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--accent:#b85c38;--guest-surface:#fff;--guest-bg:#f7f3ec;--guest-ink:#2c2723;--guest-soft:#665f58;--v18-ink:#2c2723;--v18-muted:#6f675f;--v18-terracotta:#a45538;--v18-line:#ddd5ca;--v18-card:#fff;--font-ui:Tahoma}}{css}</style></head><body class="guest-menu-page">
<header class="guest-header"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">S</div><div class="guest-brand-copy"><strong>سکنا</strong><span>منوی میز شما</span></div></div><div class="guest-header-actions"><button class="guest-event-access"><svg class="ui-icon"></svg><span>رویدادها</span></button><div class="guest-table-label"><strong>میز ۲۰</strong></div></div></div></header>
<main class="guest-main"><article class="menu-item-v12"><div class="item-media-v12"></div><div class="item-copy-v12"><div><h3>آب دوغ خیار</h3><div class="item-attributes"><span class="menu-tag tag-gold">فقط سه‌شنبه‌شب‌ها سرو می‌شود.</span></div><p>ماست، خیار، سبزی تازه، گردو و نان محلی.</p></div><div class="item-action-row"><strong>۲۶۰٬۰۰۰ تومان</strong></div></div></article></main>
</body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (390,320):
        page=browser.new_page(viewport={'width':width,'height':844})
        page.set_content(html,wait_until='load')
        label=page.locator('.guest-event-access span')
        assert label.is_visible(),f'Event label is hidden at {width}px.'
        assert label.inner_text().strip()=='رویدادها',f'Event label is unclear at {width}px.'
        event_box=page.locator('.guest-event-access').bounding_box()
        assert event_box and event_box['width']>=76,f'Event access is too narrow for icon and label at {width}px: {event_box}'
        p_style=page.locator('.item-copy-v12 p').evaluate('(e)=>{const s=getComputedStyle(e);return {font:parseFloat(s.fontSize),line:parseFloat(s.lineHeight),display:s.display}}')
        assert p_style['font']>=13,f'Item description remains too small at {width}px: {p_style}'
        assert p_style['line']/p_style['font']>=1.65,f'Item description line-height is too tight at {width}px: {p_style}'
        assert page.locator('.item-attributes .menu-tag').is_visible(),f'Existing attribute tag is not visible at {width}px.'
        overflow=page.evaluate('document.documentElement.scrollWidth-window.innerWidth')
        assert overflow<=1,f'Guest header or menu overflows horizontally at {width}px by {overflow}px.'
        page.close()
    browser.close()
print('Guest waiter/menu visual checks passed: explicit mobile events label, readable item copy, existing service tag and no 320/390px overflow.')
