#!/usr/bin/env python3
from pathlib import Path
import os, sys
ROOT=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1':
        print('Playwright unavailable in release gate:',e); sys.exit(1)
    print('SKIP: Playwright unavailable'); sys.exit(0)

def fail(msg): print('1.32.14 browser FAILED:',msg); sys.exit(1)
with sync_playwright() as p:
    try: browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium')
    except Exception as e:
        if os.getenv('SOKNA_RELEASE_GATE')=='1': fail('Chromium launch failed: '+str(e))
        print('SKIP: Chromium unavailable'); sys.exit(0)
    page=browser.new_page(viewport={"width":390,"height":844})
    # Money owner: SSR-like Persian input stays grouped, Latin programmatic typing is localized, FormData is ASCII.
    page.set_content('<html><body><main id="panelContent"><form id="f"><input class="form-control" inputmode="numeric" data-money-input name="amount" value="۲٬۲۵۰٬۰۰۰"></form></main></body></html>')
    page.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'))
    if page.input_value('[name=amount]')!='۲٬۲۵۰٬۰۰۰': fail('money initial value changed incorrectly')
    page.fill('[name=amount]','3500000'); page.dispatch_event('[name=amount]','input')
    if page.input_value('[name=amount]')!='۳٬۵۰۰٬۰۰۰': fail('money live grouping/persian digits failed')
    fd=page.evaluate("() => Array.from(new FormData(document.getElementById('f')).entries())")
    if fd != [['amount','3500000']]: fail('money FormData ASCII normalization failed: '+repr(fd))

    # Jalali: 42 stable cells and swipe changes title without changing cell count.
    page2=browser.new_page(viewport={"width":390,"height":844}, has_touch=True)
    page2.set_content('<html><body><input id="d" data-jalali-date value="۱۴۰۵/۰۵/۱۵"><button data-open-jalali="d">open</button></body></html>')
    page2.add_style_tag(path=str(ROOT/'assets/css/tokens.css')); page2.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'))
    page2.add_script_tag(path=str(ROOT/'assets/js/panel-jalali.js'))
    page2.click('[data-open-jalali="d"]')
    count=page2.locator('[data-jalali-days] > *').count()
    if count!=42: fail(f'jalali cell count {count}, expected 42')
    title1=page2.locator('[data-jalali-title]').inner_text()
    box=page2.locator('[data-jalali-days]').bounding_box()
    page2.dispatch_event('[data-jalali-days]','pointerdown',{'pointerType':'touch','button':0,'pointerId':7,'clientX':box['x']+300,'clientY':box['y']+100})
    page2.dispatch_event('[data-jalali-days]','pointerup',{'pointerType':'touch','button':0,'pointerId':7,'clientX':box['x']+200,'clientY':box['y']+102})
    title2=page2.locator('[data-jalali-title]').inner_text()
    if title1==title2: fail('jalali swipe did not change month')
    if page2.locator('[data-jalali-days] > *').count()!=42: fail('jalali changed height/cell count after swipe')

    # Financial compact metadata: 390 two columns, 360 one column; touch utilities 44px.
    html='''<html><body><div class="panel-body"><main id="panelContent"><section class="invoice-receipt-meta"><dl><div><dt>A</dt><dd>1</dd></div><div><dt>B</dt><dd>2</dd></div></dl></section><button class="panel-icon-action">x</button><div class="financial-receipt-row"><div><strong>آیتم بلند آزمایشی</strong><small>۱ × ۲۲۰٬۰۰۰</small></div><strong>۲۲۰٬۰۰۰</strong></div></main></div></body></html>'''
    for width,cols in [(390,2),(360,1)]:
        pg=browser.new_page(viewport={'width':width,'height':800}); pg.set_content(html); pg.add_style_tag(path=str(ROOT/'assets/css/tokens.css')); pg.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'))
        got=pg.evaluate("() => getComputedStyle(document.querySelector('.invoice-receipt-meta dl')).gridTemplateColumns.split(' ').length")
        if got!=cols: fail(f'metadata columns at {width}: {got}, expected {cols}')
        rect=pg.locator('.panel-icon-action').bounding_box()
        if rect['width']<44 or rect['height']<44: fail('utility touch target below 44')
        if pg.evaluate("() => document.documentElement.scrollWidth > document.documentElement.clientWidth"): fail(f'horizontal overflow at {width}')
        pg.close()
    browser.close()
print('1.32.14 browser PASS')
