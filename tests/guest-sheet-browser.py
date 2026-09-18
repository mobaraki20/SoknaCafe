#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'assets/js/guest-sheet.js').read_text(encoding='utf-8')

def gesture(page, selector, y0, y1, x=120, pointer_id=7, hold=0):
    page.dispatch_event(selector,'pointerdown',{'pointerId':pointer_id,'pointerType':'touch','button':0,'clientX':x,'clientY':y0,'isPrimary':True})
    page.dispatch_event(selector,'pointermove',{'pointerId':pointer_id,'pointerType':'touch','button':0,'clientX':x,'clientY':y1,'isPrimary':True})
    if hold: page.wait_for_timeout(hold)
    page.dispatch_event(selector,'pointerup',{'pointerId':pointer_id,'pointerType':'touch','button':0,'clientX':x,'clientY':y1,'isPrimary':True})

html='''<!doctype html><html><body><div id="layer"><section id="panel" style="height:500px"><header id="handle" style="height:70px"><button id="inside">دکمه</button><span id="drag">کشیدن</span></header><div id="content" style="height:400px;overflow:auto"><div style="height:900px">content</div></div></section></div><script>window.closedCount=0;</script></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844})
    errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(html); page.add_script_tag(content=source)
    page.evaluate("SoknaGuestSheet.bindSwipeDismiss({layer:document.querySelector('#layer'),panel:document.querySelector('#panel'),handle:document.querySelector('#handle'),close:()=>window.closedCount++})")
    # Short pull snaps back and does not close.
    gesture(page,'#drag',100,135,hold=120); page.wait_for_timeout(260)
    assert page.evaluate('closedCount')==0
    assert page.locator('#panel').evaluate("e=>getComputedStyle(e).transform") in ('none','matrix(1, 0, 0, 1, 0, 0)')
    # Interactive descendants do not start a gesture.
    gesture(page,'#inside',100,240,pointer_id=8); page.wait_for_timeout(180)
    assert page.evaluate('closedCount')==0
    # Deliberate downward gesture closes exactly once.
    gesture(page,'#drag',100,240,pointer_id=9); page.wait_for_timeout(180)
    assert page.evaluate('closedCount')==1
    assert not errors,errors
    page.close(); browser.close()

menu=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
preview=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
for token in ['drawer?.querySelector(\'.drawer-head\')','itemDetailModal?.querySelector(\'.item-detail-grip\')','guestOrdersModal?.querySelector(\'.guest-orders-head\')','eventsSheet?.querySelector(\'.guest-events-head\')']:
    assert token in menu,token
for token in ['itemDetailModal?.querySelector(\'.item-detail-grip\')','eventsSheet?.querySelector(\'.guest-events-head\')']:
    assert token in preview,token
print('Guest sheet swipe owner passed: short snap-back, interactive exclusion, deliberate close, and approved integrations are wired.')
