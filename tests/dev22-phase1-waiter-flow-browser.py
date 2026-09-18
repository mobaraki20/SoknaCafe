#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
css='\n'.join((R/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
js=(R/'assets/js/menu.js').read_text(encoding='utf-8')
tables=[{'id':1,'name':'میز 1','table_number':1,'sort_order':1},{'id':5,'name':'میز 5','table_number':5,'sort_order':5}]
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="csrf-test"><style>:root{{--primary:#365b4c;--accent:#b85c38;--guest-card:#fff;--guest-line:#ddd5ca;--guest-ink:#2c2723;--guest-soft:#6f675f;--font-ui:Tahoma}}{css}</style></head><body class="guest-menu-page guest-mode-public has-waiter">
<button id="waiterButton"><span id="waiterButtonText">فراخوان گارسون</span></button>
<div class="success-modal hidden" id="waiterModal"><div class="success-box"><h2 id="waiterModalTitle">گارسون</h2><p id="waiterModalText"></p><div class="public-table-quick hidden" id="publicTableQuick"><span><strong id="publicTableQuickName"></strong></span><button id="changePublicTable">تغییر میز</button></div><section class="public-table-picker" id="publicTableSelectWrap"><span id="publicTableSelection"></span><input type="hidden" id="publicWaiterTable"><div class="public-table-grid" id="publicTableGrid"></div></section><div class="modal-actions"><button id="confirmWaiter">فراخوان گارسون</button><button id="closeWaiter">بستن</button></div></div></div>
<div id="guestToast" class="hidden"></div><section id="eventsSheet" class="hidden"></section><div id="itemDetailModal" class="hidden"></div>
<script>
window.CAFE_MENU=[];window.CAFE_CURRENCY='تومان';window.CAFE_WAITER_API_URL='/fake';window.CAFE_PUBLIC_WAITER_TABLES={json.dumps(tables,ensure_ascii=False)};window.CAFE_MESSAGES={{waiter_button:'فراخوان گارسون',waiter_sent:'درخواستت رسید.'}};
window.__requests=[];
window.fetch=async (url,opts={{}})=>{{const body=opts.body?JSON.parse(opts.body):{{}};window.__requests.push(body); if(body.action==='status') return {{ok:true,json:async()=>({{success:true,status:'new',call_code:'WTEST',owned:true}})}}; return {{ok:true,json:async()=>({{success:true,status:'new',call_code:'WTEST',owned:true}})}};}};
</script><script>{js}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=b.new_page(viewport={'width':390,'height':760})
    errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(html,wait_until='load')
    page.click('#waiterButton')
    assert page.locator('#confirmWaiter').is_disabled(), 'confirm must start disabled before table choice'
    page.click('[data-public-table="5"]')
    assert not page.locator('#confirmWaiter').is_disabled(), 'confirm must enable immediately after selecting a table'
    assert page.locator('#publicWaiterTable').input_value()=='5'
    page.click('#confirmWaiter')
    page.wait_for_timeout(100)
    reqs=page.evaluate('window.__requests')
    creates=[r for r in reqs if r.get('action')=='create']
    assert len(creates)==1,reqs
    assert creates[0].get('public_table_id')==5,creates[0]
    assert not errors,errors
    b.close()
print('dev22 phase1 waiter flow browser PASS: select -> immediate confirm -> create(public_table_id)')
