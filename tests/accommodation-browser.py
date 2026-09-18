#!/usr/bin/env python3
from pathlib import Path
import ast
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; accommodation browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8');tree=ast.parse(source);html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        html=ast.literal_eval(node.value);break
assert isinstance(html,str)
start=html.index('window.__requests=[];window.fetch=')
end=html.index('</script>',start)
custom=r'''window.__requests=[];window.fetch=async(url,options={})=>{const u=String(url);if(u.startsWith('/feed'))return new Response(JSON.stringify(feed),{status:200,headers:{'Content-Type':'application/json'}});const payload=JSON.parse(options.body||'{}');window.__requests.push({url:u,...payload});if(u==='/accommodation'&&payload.action==='search')return new Response(JSON.stringify({success:true,reservations:[{reservation_code:'SK-1',guest_name:'علی رضایی',room_names:['سیف'],check_in:'۱۴۰۵/۰۵/۰۱',check_out:'۱۴۰۵/۰۵/۰۳',charge_allowed:true,charge_block_reason:null,phone_hint:'989•••567'}]}),{status:200,headers:{'Content-Type':'application/json'}});if(u==='/accommodation'&&payload.action==='charge'){window.__chargePayload=payload;return new Response(JSON.stringify({success:true,message:'ثبت شد'}),{status:200,headers:{'Content-Type':'application/json'}})}return new Response(JSON.stringify({success:true,message:'انجام شد'}),{status:200,headers:{'Content-Type':'application/json'}})};'''
html=html[:start]+custom+html[end:]
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/operator-live.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1440,'height':980});page.set_content(html)
    for css in CSS: page.add_style_tag(path=str(css))
    page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
    page.wait_for_selector('[data-select-table="1"]',state='attached');page.click('#tabTables');page.click('[data-select-table="1"]')
    page.click('.open-settlement-action');page.click('[data-settlement="accommodation"]')
    assert page.locator('#accommodationModal').is_visible()
    page.fill('#accommodationSearchQuery','SK-1');page.click('#accommodationSearchButton');page.wait_for_selector('[data-reservation-index="0"]')
    assert 'مانده' not in page.locator('#accommodationSearchResult').inner_text()
    page.click('[data-reservation-index="0"]')
    assert page.locator('#accommodationFinalAmount').inner_text().strip()=='۶۱۰٬۰۰۰ تومان'
    assert page.locator('#accommodationExternalId').inner_text().strip()=='CAFE-S-101'
    page.click('#accommodationPostButton');page.wait_for_timeout(80)
    payload=page.evaluate('window.__chargePayload')
    assert payload['table_id']==1 and payload['reservation_code']=='SK-1' and payload['print_final'] is True
    browser.close()
operator_page=(ROOT/'includes/operator_page.php').read_text(encoding='utf-8')
assert '<?php if($accommodationCanPost): ?>' in operator_page and 'type="hidden" id="accommodationAmount"' in operator_page and 'id="accommodationDates"' in operator_page
print('Accommodation browser flow passed: settlement destination, minimal search, immutable invoice amount, stable session ID, print propagation, and conditional rendering.')
