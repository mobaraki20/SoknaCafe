#!/usr/bin/env python3
from pathlib import Path
import ast
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; operator action integration skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source);html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        html=ast.literal_eval(node.value);break
assert isinstance(html,str)
# Use the real shared panel controller, not the fixture stub.
start=html.index('window.CafeUI=')
end=html.index('const table=',start)
html=html[:start]+html[end:]
confirm='''<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true"><button data-panel-confirm-cancel></button><section class="panel-confirm-card"><h2 id="panelConfirmTitle"></h2><p id="panelConfirmMessage"></p><button class="btn btn-light" data-panel-confirm-cancel>انصراف</button><button class="btn btn-primary" data-panel-confirm-ok>تأیید</button></section></div><div id="panelToast" class="panel-toast hidden"></div>'''
html=html.replace('</main><script>',f'</main>{confirm}<script>',1)
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(html)
    for css in CSS: page.add_style_tag(path=str(css))
    page.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'))
    page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'))
    page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
    page.wait_for_selector('.order-status-action[data-status="cancelled"]')

    # Stop kitchen: custom confirmation, real action, persisted state.
    page.click('[data-order-acceptance="kitchen"]')
    assert page.locator('#panelConfirmLayer').is_visible()
    assert 'توقف سفارش آنلاین' in page.locator('#panelConfirmTitle').inner_text()
    page.click('[data-panel-confirm-ok]');page.wait_for_timeout(120)
    assert page.locator('[data-acceptance-status="kitchen"]').inner_text()=='متوقف'
    assert any(r.get('url')=='/controls' and r.get('scope')=='kitchen' and r.get('enabled') is False for r in page.evaluate('window.__requests'))

    # Reject order: same shared dialog, server call, no silent click.
    page.click('.order-status-action[data-status="cancelled"]')
    assert page.locator('#panelConfirmLayer').is_visible()
    assert 'رد سفارش' in page.locator('#panelConfirmTitle').inner_text()
    page.click('[data-panel-confirm-ok]');page.wait_for_timeout(120)
    assert any(r.get('url')=='/status' and r.get('order_id')==20 and r.get('status')=='cancelled' for r in page.evaluate('window.__requests'))
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    browser.close()
print('Operator action integration passed with the real panel controller: stop-ordering and reject-order confirmations both reach their APIs and update state.')
