#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

root=Path(__file__).resolve().parents[1]
core=(root/'assets/js/panel-core.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><body>
<main id="panelContent">
<form id="flow">
  <input class="form-control" id="n1" type="text" inputmode="numeric" enterkeyhint="next" value="۱۲">
  <input class="form-control" id="n2" type="text" inputmode="decimal" enterkeyhint="done" value="۱٫۵">
</form>
<form id="choiceFlow">
  <input class="form-control" id="beforeChoice" type="text" inputmode="numeric" enterkeyhint="next" value="۲">
  <select class="form-control" id="choiceNative" style="display:none"><option>یک</option></select><button class="panel-choice-trigger" id="choiceTrigger" type="button">انتخاب</button>
  <input class="form-control" id="afterChoice" type="text" enterkeyhint="done">
</form>
<form id="genericPost" method="post"><input class="form-control" id="genericA" type="text"><input class="form-control" id="genericB" type="password"><button type="submit">ثبت</button></form>
<input class="form-control" id="live" type="search" inputmode="search" enterkeyhint="search" data-keyboard-dismiss-on-enter value="مهران">
</main><div id="panelToast"></div>
<script>{core}</script><script>window.genericSubmitCount=0;document.getElementById('genericPost').addEventListener('submit',e=>{{e.preventDefault();window.genericSubmitCount++;}});</script></body></html>'''

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,360,390,412):
        ctx=browser.new_context(viewport={'width':width,'height':800},is_mobile=True,has_touch=True)
        page=ctx.new_page(); page.set_content(html); page.wait_for_timeout(30)
        assert page.evaluate("CafeUI.keyboard.isTouchContext()") is True, width

        page.locator('#n1').focus(); page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")=='n2', width

        # IME composition Enter must not be consumed by the app-level keyboard contract.
        page.evaluate("document.getElementById('n2').focus(); document.getElementById('n2').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,isComposing:true}));")
        assert page.evaluate("document.activeElement.id")=='n2', width

        page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")!='n2', width

        page.locator('#beforeChoice').focus(); page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")=='choiceTrigger', width

        page.locator('#live').focus(); page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")!='live', width
        assert page.locator('#n1').get_attribute('inputmode')=='numeric'
        assert page.locator('#n2').get_attribute('inputmode')=='decimal'
        assert page.locator('#live').get_attribute('enterkeyhint')=='search'
        assert page.locator('#genericA').get_attribute('enterkeyhint')=='next', width
        assert page.locator('#genericB').get_attribute('enterkeyhint')=='done', width
        page.locator('#genericA').focus(); page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")=='genericB', width
        page.keyboard.press('Enter'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement.id")!='genericB', width
        assert page.evaluate('window.genericSubmitCount')==0, width
        ctx.close()

    desktop=browser.new_context(viewport={'width':1366,'height':900},is_mobile=False,has_touch=False)
    page=desktop.new_page(); page.set_content(html); page.wait_for_timeout(20)
    assert page.evaluate('CafeUI.keyboard.isTouchContext()') is False
    page.locator('#genericB').focus(); page.keyboard.press('Enter'); page.wait_for_timeout(20)
    assert page.evaluate('window.genericSubmitCount')==1
    desktop.close(); browser.close()

print('Panel keyboard/input browser passed at 320/360/390/412 touch and desktop: touch Next/Done/Search is predictable, IME Enter is preserved, and desktop native Enter submit remains intact.')
