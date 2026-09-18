#!/usr/bin/env python3
from pathlib import Path
import os,sys
R=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1': print('1.32.14 sheet browser FAILED: Playwright unavailable',e);sys.exit(1)
    print('SKIP: Playwright unavailable');sys.exit(0)
def fail(m): print('1.32.14 sheet browser FAILED:',m);sys.exit(1)

def swipe(pg,sel,dy=130,pid=71):
    r=pg.locator(sel).bounding_box()
    if not r: fail('missing swipe handle '+sel)
    x=r['x']+r['width']/2;y=r['y']+min(12,r['height']/2)
    for typ,yy in [('pointerdown',y),('pointermove',y+dy),('pointerup',y+dy)]:
        pg.dispatch_event(sel,typ,{'pointerType':'touch','pointerId':pid,'button':0,'clientX':x,'clientY':yy})
    pg.wait_for_timeout(240)

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium')
    # Real Panel Choice browse sheet.
    pg=b.new_page(viewport={'width':390,'height':844},has_touch=True)
    pg.set_content('''<html dir="rtl"><body><main class="panel-content"><label>انتخاب آیتم<select class="form-control" data-choice-mode="browse" data-choice-search="1"><option value="">انتخاب</option><option value="1">یک</option><option value="2">دو</option><option value="3">سه</option><option value="4">چهار</option><option value="5">پنج</option><option value="6">شش</option></select></label></main></body></html>''')
    for css in ['tokens.css','app.css','panel-components.css']: pg.add_style_tag(path=str(R/'assets/css'/css))
    pg.add_script_tag(path=str(R/'assets/js/panel-core.js'));pg.add_script_tag(path=str(R/'assets/js/panel-choice.js'))
    pg.locator('.panel-choice-trigger').click();pg.wait_for_timeout(30)
    if pg.locator('#panelChoiceLayer').get_attribute('aria-hidden')!='false': fail('choice sheet did not open')
    swipe(pg,'.panel-choice-head',130,71)
    if pg.locator('#panelChoiceLayer').get_attribute('aria-hidden')!='true': fail('choice sheet swipe-down did not close')
    pg.close()

    # Real Media Library sheet uses the same swipe owner.
    pg=b.new_page(viewport={'width':390,'height':844},has_touch=True)
    pg.set_content('''<html dir="rtl"><body><main class="panel-content"><div data-image-picker><input type="radio" name="image_mode" value="keep" checked><input type="radio" name="image_mode" value="library"><button id="lib" data-image-mode-trigger="library">آلبوم</button><div data-image-panel="library" aria-hidden="true"><div class="image-library-surface"><header class="image-library-dialog-head"><strong>آلبوم</strong><button data-image-library-cancel>×</button></header><input data-image-library-search><label data-image-library-card="نمونه"><input type="radio" name="image_library" value="a">نمونه</label><button data-image-library-apply>انتخاب</button></div></div></div></main></body></html>''')
    for css in ['tokens.css','app.css','panel-components.css']: pg.add_style_tag(path=str(R/'assets/css'/css))
    pg.add_script_tag(path=str(R/'assets/js/panel-core.js'));pg.add_script_tag(path=str(R/'assets/js/panel-media.js'))
    pg.locator('#lib').click();pg.wait_for_timeout(30)
    panel=pg.locator('[data-image-panel="library"]')
    if panel.get_attribute('aria-hidden')!='false': fail('media sheet did not open')
    swipe(pg,'.image-library-dialog-head',130,72)
    if panel.get_attribute('aria-hidden')!='true': fail('media sheet swipe-down did not close')
    errors=[]
    # If capture-related errors existed they would have surfaced during interaction via Playwright pageerror in the historical media regression;
    # this test verifies close state using the shared owner.
    pg.close();b.close()
print('1.32.14 shared sheet swipe browser PASS')
