#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; hotfix menu browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
HTML='''<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body>
<div id="panelContent" style="height:240px;overflow:auto"><div style="height:700px">
<div id="panelToolsMenu"><button id="panelToolsToggle" aria-expanded="false">•••</button><div id="panelToolsPopover" class="hidden" aria-hidden="true"><button role="menuitem" id="toolAction">ابزار</button><a role="menuitem" id="guestLink" href="about:blank#guest" target="_blank">مهمان</a></div></div>
<div id="nested" style="height:80px;overflow:auto"><div style="height:300px"></div></div>
<div class="row-action-menu" data-action-menu id="m1"><button data-action-menu-trigger aria-expanded="false">عملیات ۱</button><div class="row-action-popover" data-action-menu-popover><button role="menuitem" id="a1">ویرایش</button></div></div>
<div class="row-action-menu" data-action-menu id="m2"><button data-action-menu-trigger aria-expanded="false">عملیات ۲</button><div class="row-action-popover" data-action-menu-popover><button role="menuitem" id="a2">چاپ</button></div></div>
<div class="row-action-menu" data-action-menu id="m3"><button data-action-menu-trigger aria-expanded="false">عملیات ۳</button><div class="row-action-popover" data-action-menu-popover><button role="menuitem" id="a3">مشاهده</button></div></div>
</div></div><script>window.__hits=[];for(const id of ['a1','a2','a3','toolAction'])document.getElementById(id).addEventListener('click',()=>window.__hits.push(id));</script></body></html>'''
CSS='''.hidden{display:none!important}.row-action-popover{display:none;position:fixed;background:white;padding:8px}.row-action-menu.is-open>.row-action-popover{display:flex}#panelToolsPopover:not(.hidden){display:flex;position:fixed;background:white;padding:8px}'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for viewport in ({'width':1366,'height':768},{'width':390,'height':844}):
        page=browser.new_page(viewport=viewport)
        page.set_content(HTML);page.add_style_tag(content=CSS);page.add_script_tag(path=str(ROOT/'assets/js/panel-shell.js'));page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'))
        page.click('#m1 [data-action-menu-trigger]');page.wait_for_timeout(80)
        assert page.locator('#m1').evaluate("e=>e.classList.contains('is-open')")
        page.click('#m2 [data-action-menu-trigger]');page.wait_for_timeout(80)
        assert not page.locator('#m1').evaluate("e=>e.classList.contains('is-open')")
        assert page.locator('#m2').evaluate("e=>e.classList.contains('is-open')")
        page.click('#a2');page.wait_for_timeout(30)
        assert page.evaluate("window.__hits.includes('a2')")
        assert not page.locator('#m2').evaluate("e=>e.classList.contains('is-open')")
        page.click('#panelToolsToggle');page.wait_for_timeout(80)
        assert page.locator('#panelToolsPopover').is_visible()
        page.locator('#nested').evaluate('e=>e.scrollTop=120');page.wait_for_timeout(60)
        assert page.locator('#panelToolsPopover').is_visible(), 'nested scroll must not close top tools'
        page.click('#toolAction');page.wait_for_timeout(30)
        assert page.evaluate("window.__hits.includes('toolAction')") and page.locator('#panelToolsPopover').is_hidden()
        page.click('#panelToolsToggle');page.wait_for_timeout(300)
        page.locator('#panelContent').evaluate('e=>e.scrollTop=200');page.wait_for_timeout(300)
        assert page.locator('#panelToolsPopover').is_hidden(), 'primary panel scroll must close tools'
        page.click('#m3 [data-action-menu-trigger]');page.wait_for_timeout(80);page.keyboard.press('Escape');page.wait_for_timeout(30)
        assert not page.locator('#m3').evaluate("e=>e.classList.contains('is-open')")
        page.close()
    browser.close()
print('Hotfix menu browser checks passed: one menu, native action activation, Escape and scoped scroll behavior.')
