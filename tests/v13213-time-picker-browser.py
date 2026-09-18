#!/usr/bin/env python3
from pathlib import Path
import os,sys
ROOT=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1': print('1.32.14 time picker browser FAILED: Playwright unavailable',e);sys.exit(1)
    print('SKIP: Playwright unavailable');sys.exit(0)

def fail(m): print('1.32.14 time picker browser FAILED:',m);sys.exit(1)

def fa(s):
    return str(s).translate(str.maketrans('0123456789','۰۱۲۳۴۵۶۷۸۹'))

with sync_playwright() as p:
    try: b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    except Exception as e:
        if os.getenv('SOKNA_RELEASE_GATE')=='1': fail('Chromium launch failed: '+str(e))
        print('SKIP: Chromium unavailable');sys.exit(0)
    for width in (320,360,390,412,768,1366):
        pg=b.new_page(viewport={'width':width,'height':844},has_touch=width<721,is_mobile=width<721)
        pg.set_content('''<html dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="panel-content">
        <label>زمان‌بندی<input class="form-control" id="t15" type="time" aria-label="ساعت شروع" data-minute-step="15" step="900" value="18:30"></label>
        <label>رویداد<input class="form-control" id="t5" type="time" aria-label="ساعت رویداد" data-minute-step="5" step="300" value="00:00"></label>
        </main></body></html>''')
        pg.add_style_tag(path=str(ROOT/'assets/css/tokens.css'))
        pg.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'))
        pg.add_script_tag(path=str(ROOT/'assets/js/panel-time-picker.js'))
        trig=pg.locator('button[aria-label="ساعت شروع"]')
        if '۶:۳۰ بعد از ظهر' not in trig.inner_text(): fail(f'12h trigger display wrong at {width}: '+trig.inner_text())
        trig.click()
        if pg.locator('.panel-time-hours .panel-time-option').count()!=12: fail(f'hour count != 12 at {width}')
        if pg.locator('.panel-time-minutes .panel-time-option').count()!=4: fail(f'15-minute count != 4 at {width}')
        am=pg.locator('[data-panel-time-period="am"]').bounding_box(); pm=pg.locator('[data-panel-time-period="pm"]').bounding_box()
        if not am or not pm or am['x'] <= pm['x']: fail(f'AM is not physically right of PM at {width}')
        one=pg.locator('[data-hour="1"]').bounding_box(); two=pg.locator('[data-hour="2"]').bounding_box()
        if not one or not two or one['x'] >= two['x']: fail(f'hour numbers do not start LTR with 1 at {width}')
        m0=pg.locator('[data-minute="0"]').bounding_box(); m15=pg.locator('[data-minute="15"]').bounding_box()
        if not m0 or not m15 or m0['x'] >= m15['x']: fail(f'minute numbers do not start LTR at {width}')
        for sel in ('.panel-time-hours','.panel-time-minutes'):
            if pg.locator(sel).evaluate('e => e.scrollHeight > e.clientHeight + 1'): fail(f'nested grid scroll exists {sel} at {width}')
        for btn in pg.locator('.panel-time-option').all():
            box=btn.bounding_box()
            if box and box['height'] < 43.5: fail(f'time touch target below 44 at {width}')
        if pg.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'): fail(f'horizontal overflow at {width}')
        # 6 PM -> 18:45 canonical
        pg.locator('[data-panel-time-period="pm"]').click(); pg.locator('[data-hour="6"]').click(); pg.locator('[data-minute="45"]').click(); pg.locator('[data-panel-time-apply]').click()
        if pg.locator('#t15').input_value()!='18:45': fail('6 PM did not serialize to 18:45')
        # Explicit 12 AM edge -> 00:00
        trig.click(); pg.locator('[data-panel-time-period="am"]').click(); pg.locator('[data-hour="12"]').click(); pg.locator('[data-minute="0"]').click(); pg.locator('[data-panel-time-apply]').click()
        if pg.locator('#t15').input_value()!='00:00': fail('12 AM did not serialize to 00:00')
        # Explicit 12 PM edge -> 12:00
        trig.click(); pg.locator('[data-panel-time-period="pm"]').click(); pg.locator('[data-hour="12"]').click(); pg.locator('[data-minute="0"]').click(); pg.locator('[data-panel-time-apply]').click()
        if pg.locator('#t15').input_value()!='12:00': fail('12 PM did not serialize to 12:00')
        pg.locator('button.panel-time-close').click() if pg.locator('#panelTimeLayer').is_visible() else None
        pg.locator('button[aria-label="ساعت رویداد"]').click()
        if pg.locator('.panel-time-minutes .panel-time-option').count()!=12: fail(f'5-minute count != 12 at {width}')
        if pg.locator('.panel-time-minutes').evaluate('e => e.scrollHeight > e.clientHeight + 1'): fail(f'5-minute nested scroll at {width}')
        pg.close()
    b.close()
print('1.32.14 time picker browser PASS')
