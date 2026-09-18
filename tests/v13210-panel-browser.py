#!/usr/bin/env python3
from pathlib import Path
import os, sys
ROOT=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1': print('1.32.14 browser FAILED: Playwright unavailable',e);sys.exit(1)
    print('SKIP: Playwright unavailable');sys.exit(0)
def fail(m): print('1.32.14 browser FAILED:',m);sys.exit(1)
with sync_playwright() as p:
    try: browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium')
    except Exception as e:
        if os.getenv('SOKNA_RELEASE_GATE')=='1': fail('Chromium launch failed: '+str(e))
        print('SKIP: Chromium unavailable');sys.exit(0)

    # Composite date control + selection tiles across mobile reference widths.
    html='''<html dir="rtl"><body><main id="panelContent" class="panel-content"><div class="jalali-date-control" id="date"><input class="form-control" value="۱۴۰۵/۰۵/۲۰"><button class="jalali-date-button">C</button></div><div class="tag-check-grid"><label><span>گیاهی</span><input type="checkbox"></label><label><span>بدون قند</span><input type="checkbox"></label></div></main></body></html>'''
    for width in [320,360,390,412]:
        pg=browser.new_page(viewport={'width':width,'height':800},has_touch=True);pg.set_content(html)
        pg.add_style_tag(path=str(ROOT/'assets/css/tokens.css'));pg.add_style_tag(path=str(ROOT/'assets/css/app.css'));pg.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'))
        parent=pg.locator('#date').bounding_box(); btn=pg.locator('.jalali-date-button').bounding_box()
        if not(parent and btn): fail('date control missing geometry')
        if btn['x'] < parent['x']-0.5 or btn['y'] < parent['y']-0.5 or btn['x']+btn['width'] > parent['x']+parent['width']+0.5 or btn['y']+btn['height'] > parent['y']+parent['height']+0.5: fail(f'calendar button overflow at {width}')
        if btn['width']<44 or btn['height']<44: fail(f'calendar touch target below 44 at {width}')
        for label in pg.locator('.tag-check-grid label').all():
            r=label.bounding_box(); inp=label.locator('input').bounding_box(); span=label.locator('span').bounding_box()
            if r['height']<44: fail(f'selection tile below touch size at {width}')
            if abs((inp['y']+inp['height']/2)-(r['y']+r['height']/2))>3 or abs((span['y']+span['height']/2)-(r['y']+r['height']/2))>5: fail(f'selection tile not vertically centered at {width}')
        if pg.evaluate('() => document.documentElement.scrollWidth > document.documentElement.clientWidth'): fail(f'horizontal overflow at {width}')
        pg.close()

    # Time picker semantic steps: real shared runtime, 15 -> 4 minute values; 5 -> 12.
    pg=browser.new_page(viewport={'width':390,'height':844},has_touch=True)
    pg.set_content('''<html dir="rtl"><body><main class="panel-content"><label>ساعت آیتم<input class="form-control" id="t15" type="time" aria-label="ساعت آیتم" data-minute-step="15" step="900" value="06:00"></label><label>ساعت رویداد<input class="form-control" id="t5" type="time" aria-label="ساعت رویداد" data-minute-step="5" step="300" value="06:00"></label></main></body></html>''')
    pg.add_style_tag(path=str(ROOT/'assets/css/tokens.css'));pg.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'));pg.add_script_tag(path=str(ROOT/'assets/js/panel-time-picker.js'))
    pg.locator('button[aria-label="ساعت آیتم"]').click();
    if pg.locator('.panel-time-minutes .panel-time-option').count()!=4: fail('15-minute time input does not render 4 minute options')
    pg.locator('button.panel-time-close').click()
    pg.locator('button[aria-label="ساعت رویداد"]').click();
    if pg.locator('.panel-time-minutes .panel-time-option').count()!=12: fail('5-minute time input does not render 12 minute options')
    if pg.locator('.panel-time-grip').count()!=0: fail('time modal still contains dead sheet grip')
    pg.close()

    # Quick Edit real geometry: flags visible, footer does not cover body; shared swipe can dismiss.
    pg=browser.new_page(viewport={'width':390,'height':844},has_touch=True)
    pg.set_content('''<html dir="rtl"><body><main id="panelContent"></main><div class="item-editor-backdrop"></div><aside class="item-editor-drawer" id="d"><form><header data-item-editor-swipe-handle><h2>ویرایش سریع</h2><button type="button">×</button></header><div class="item-editor-body"><label><span>نام</span><input class="form-control"></label><label><span>قیمت</span><input class="form-control"></label><div class="quick-item-flags"><label><span>قابل سفارش</span><input type="checkbox"></label><label><span>نمایش در منو</span><input type="checkbox"></label><label><span>پیشنهاد کافه</span><input type="checkbox"></label></div><div class="quick-item-schedule"><span>زمان‌بندی</span><strong>۰۶:۰۰ تا ۱۱:۰۰</strong><small>تغییر از ویرایش کامل</small></div><div style="height:420px"></div></div><footer><button class="btn btn-primary">ذخیره تغییرات</button><button class="btn btn-light">ویرایش کامل</button></footer></form></aside></body></html>''')
    for css in ['tokens.css','app.css','panel-components.css','items-management.css']: pg.add_style_tag(path=str(ROOT/'assets/css'/css))
    flags=pg.locator('.quick-item-flags').bounding_box(); footer=pg.locator('.item-editor-drawer footer').bounding_box(); body=pg.locator('.item-editor-body').bounding_box()
    if not flags or flags['height']<132: fail('quick edit flags collapsed/not visible')
    for row in pg.locator('.quick-item-flags label').all():
        if row.bounding_box()['height']<44: fail('quick edit flag row below 44px')
    if body['y']+body['height'] > footer['y']+1: fail('quick edit body is overlaid by footer')
    pg.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'))
    pg.evaluate('''() => { window.__dismissed=0; CafeUI.bindSwipeDismiss({sheet:document.getElementById('d'),handle:document.querySelector('[data-item-editor-swipe-handle]'),onDismiss:()=>{window.__dismissed++}}); }''')
    h=pg.locator('[data-item-editor-swipe-handle]').bounding_box(); x=h['x']+h['width']/2; y=h['y']+10
    pg.dispatch_event('[data-item-editor-swipe-handle]','pointerdown',{'pointerType':'touch','pointerId':22,'button':0,'clientX':x,'clientY':y})
    pg.dispatch_event('[data-item-editor-swipe-handle]','pointermove',{'pointerType':'touch','pointerId':22,'button':0,'clientX':x+2,'clientY':y+120})
    pg.dispatch_event('[data-item-editor-swipe-handle]','pointerup',{'pointerType':'touch','pointerId':22,'button':0,'clientX':x+2,'clientY':y+120})
    pg.wait_for_timeout(30)
    if pg.evaluate('window.__dismissed')!=1: fail('shared swipe-down dismiss contract did not fire')
    pg.close()

    # Receipt metadata responsive + odd last cell full width.
    for width,cols in [(360,1),(390,2),(412,2)]:
        pg=browser.new_page(viewport={'width':width,'height':700});pg.set_content('<html><body><section class="invoice-receipt-meta"><dl><div>A</div><div>B</div><div id="last">C</div></dl></section></body></html>');pg.add_style_tag(path=str(ROOT/'assets/css/tokens.css'));pg.add_style_tag(path=str(ROOT/'assets/css/panel-components.css'))
        got=pg.evaluate("() => getComputedStyle(document.querySelector('.invoice-receipt-meta dl')).gridTemplateColumns.split(' ').length")
        if got!=cols: fail(f'receipt metadata columns at {width}: {got} expected {cols}')
        if cols==2:
            dl=pg.locator('dl').bounding_box(); last=pg.locator('#last').bounding_box()
            if last['width'] < dl['width']*.9: fail(f'odd metadata item did not span full row at {width}')
        pg.close()
    browser.close()
print('1.32.14 browser PASS')
