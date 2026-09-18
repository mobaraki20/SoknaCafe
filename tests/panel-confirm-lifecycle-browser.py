#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'assets/js/panel-core.js').read_text(encoding='utf-8')
CSS = '\n'.join((ROOT / path).read_text(encoding='utf-8') for path in [
    'assets/css/tokens.css',
    'assets/css/app.css',
    'assets/css/panel.css',
    'assets/css/panel-layout.css',
    'assets/css/panel-components.css',
])
HTML = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>.hidden{{display:none!important}}{CSS}</style></head><body class="panel-body"><main id="panelContent"><button id="launch">اجرای عملیات</button></main><div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true" data-dialog-backdrop><section class="panel-confirm-card"><h2 id="panelConfirmTitle"></h2><p id="panelConfirmMessage"></p><div class="panel-confirm-actions"><button class="btn btn-light" type="button" data-panel-confirm-cancel>انصراف</button><button class="btn btn-primary" type="button" data-panel-confirm-ok>تأیید</button></div></section></div><div id="panelToast" class="panel-toast hidden"></div></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 390, 'height': 844})
    page.set_content(HTML)
    page.add_script_tag(content=JS)

    # Escape must resolve the pending promise as false, not leave it hanging.
    page.focus('#launch')
    page.evaluate("() => { window.__confirmResult='pending'; CafeUI.confirm('پیام','عنوان',{trigger:document.getElementById('launch')}).then(v=>window.__confirmResult=v); }")
    assert page.locator('#panelConfirmLayer').is_visible()
    page.keyboard.press('Escape')
    page.wait_for_timeout(80)
    assert page.locator('#panelConfirmLayer').is_hidden()
    assert page.evaluate('window.__confirmResult') is False
    assert page.locator('#launch').evaluate('(e)=>document.activeElement===e')

    # Backdrop close must also resolve false.
    page.evaluate("() => { window.__confirmResult='pending'; CafeUI.confirm('پیام','عنوان',{trigger:document.getElementById('launch')}).then(v=>window.__confirmResult=v); }")
    page.locator('#panelConfirmLayer').click(position={'x': 4, 'y': 4})
    page.wait_for_timeout(80)
    assert page.evaluate('window.__confirmResult') is False

    # Explicit cancel resolves false.
    page.evaluate("() => { window.__confirmResult='pending'; CafeUI.confirm('پیام','عنوان',{trigger:document.getElementById('launch')}).then(v=>window.__confirmResult=v); }")
    page.click('[data-panel-confirm-cancel]')
    page.wait_for_timeout(80)
    assert page.evaluate('window.__confirmResult') is False

    # Explicit confirmation resolves true and subsequent confirms still work.
    page.evaluate("() => { window.__confirmResult='pending'; CafeUI.confirm('پیام','عنوان',{trigger:document.getElementById('launch')}).then(v=>window.__confirmResult=v); }")
    page.click('[data-panel-confirm-ok]')
    page.wait_for_timeout(80)
    assert page.evaluate('window.__confirmResult') is True

    browser.close()

print('Panel confirm lifecycle passed: Escape, backdrop and cancel resolve false; OK resolves true; focus is restored.')
