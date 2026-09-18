#!/usr/bin/env python3
from pathlib import Path
import ast
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; printing browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source);html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        html=ast.literal_eval(node.value);break
assert isinstance(html,str)
html=html.replace("printing:{pending:0,problem:0,agent_online:true}","printing:{pending:2,problem:1,agent_online:false}")
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in ((1440,980),(390,844)):
        page=browser.new_page(viewport={'width':width,'height':height});page.set_content(html)
        for css in CSS: page.add_style_tag(path=str(css))
        page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
        page.wait_for_selector('[data-select-table="1"]',state='attached')
        page.wait_for_selector('#attentionBoard .attention-followups-v1301')
        followups=page.locator('.attention-followups-v1301')
        assert followups.count()==1
        followups.evaluate('(el)=>el.open=true')
        status=page.locator('#attentionBoard').inner_text()
        assert 'سرویس چاپ در دسترس نیست' in status and '۲ کار در انتظار' in status
        page.click('#tabTables')
        assert 'سرویس چاپ در دسترس نیست' not in page.locator('#tablesPanel').inner_text()
        page.click('[data-select-table="1"]')
        prebill=page.locator('.session-action[data-action="print_prebill"]')
        assert prebill.is_enabled();prebill.click();page.wait_for_timeout(60)
        requests=page.evaluate('window.__requests')
        assert any(r.get('action')=='print_prebill' and r.get('table_id')==1 for r in requests)
        page.click('.open-settlement-action')
        assert page.locator('#checkoutPrintFinal').is_checked() and not page.locator('#checkoutPrintFinal').is_disabled()
        page.click('[data-settlement="direct"]');page.click('#confirmDirectSettlement');page.wait_for_timeout(60)
        requests=page.evaluate('window.__requests')
        assert any(r.get('action')=='checkout_direct' and r.get('print_final') is True and 'payment_method' not in r for r in requests)
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
        page.close()
    browser.close()
print('Printing browser checks passed: print health, prebill queueing, direct settlement, optional final invoice, and no payment-method payload.')
