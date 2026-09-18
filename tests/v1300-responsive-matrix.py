#!/usr/bin/env python3
from pathlib import Path
import ast, json, subprocess
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
WIDTHS=[320,360,390,412,768,1366,1440]

# Operator fixture with real shared controllers.
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source); operator_html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        operator_html=ast.literal_eval(node.value);break
assert isinstance(operator_html,str)
start=operator_html.index('window.CafeUI='); end=operator_html.index('const table=',start)
operator_html=operator_html[:start]+operator_html[end:]
confirm='''<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true"><button data-panel-confirm-cancel></button><section class="panel-confirm-card"><h2 id="panelConfirmTitle"></h2><p id="panelConfirmMessage"></p><button class="btn btn-light" data-panel-confirm-cancel>انصراف</button><button class="btn btn-primary" data-panel-confirm-ok>تأیید</button></section></div><div id="panelToast" class="panel-toast hidden"></div>'''
operator_html=operator_html.replace('</main><script>',f'</main>{confirm}<script>',1)
operator_css=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in WIDTHS:
        height=844 if width<768 else 900
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(operator_html)
        for css in operator_css: page.add_style_tag(path=str(css))
        page.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'))
        page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'))
        page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
        page.wait_for_selector('.order-status-action[data-status="cancelled"]')
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 2'), f'operator overflow {width}'
        page.click('[data-order-acceptance="kitchen"]'); page.click('[data-panel-confirm-ok]'); page.wait_for_timeout(80)
        assert page.locator('[data-acceptance-status="kitchen"]').inner_text()=='متوقف'
        page.click('.order-status-action[data-status="cancelled"]'); page.click('[data-panel-confirm-ok]'); page.wait_for_timeout(80)
        assert any(r.get('url')=='/status' and r.get('status')=='cancelled' for r in page.evaluate('window.__requests'))
        page.close()
    browser.close()
print('RC6 responsive operator matrix passed at 320, 360, 390, 412, 768, 1366 and 1440 px; quick order is covered by staff-quick-order-browser.py.')
