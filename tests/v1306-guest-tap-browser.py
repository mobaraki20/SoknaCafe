#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; guest tap browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
HTML='''<!doctype html><html><body class="guest-menu-page"><button id="bell">bell</button><article id="item" role="button" tabindex="0" data-menu-item>item</article><a id="link" href="#">link</a></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':800});page.set_content(HTML)
    for css in [ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/guest-menu.css']:
        page.add_style_tag(path=str(css))
    for sel in ('#bell','#item','#link'):
        value=page.locator(sel).evaluate("el=>getComputedStyle(el).webkitTapHighlightColor")
        assert value in ('rgba(0, 0, 0, 0)','transparent'),(sel,value)
    page.keyboard.press('Tab')
    outline=page.locator('#bell').evaluate("el=>getComputedStyle(el).outlineStyle")
    assert outline!='none',outline
    browser.close()
print('Guest tap browser passed: bell/item/link have transparent native tap paint while keyboard focus remains visible.')
