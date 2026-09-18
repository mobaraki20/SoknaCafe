#!/usr/bin/env python3
from pathlib import Path
import ast
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; RC2 operator browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source); html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        html=ast.literal_eval(node.value);break
assert isinstance(html,str)
start=html.index('window.CafeUI='); end=html.index('const table=',start)
html=html[:start]+html[end:]
# Bring the historical browser fixture up to the current RC2 DOM contract.
html=html.replace('<input id="subscriberSearch"><div id="subscriberSearchResult">', '<button id="backSubscriberSettlement" type="button">بازگشت</button><input id="subscriberSearch"><button id="subscriberSearchButton" type="button">جست‌وجو</button><div id="subscriberSearchResult">')
html=html.replace('<input id="accommodationTableId">', '<button id="backAccommodationSettlement" type="button">بازگشت</button><input id="accommodationTableId">')
html=html.replace('<button id="tableDetailTitle" class="table-name-action-v1280"></button>', '<div><button id="tableDetailTitle" class="table-name-action-v1280"></button><span id="tableDetailMeta"></span></div>')
html=html.replace('<div id="tableDetailBody" class="table-detail-body-v1190"></div></aside>', '<div id="tableDetailBody" class="table-detail-body-v1190"></div><footer id="tableDetailFooter" class="table-account-footer-v1301 hidden"></footer></aside>')
confirm='''<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true"><button class="panel-confirm-backdrop" data-panel-confirm-cancel></button><section class="panel-confirm-card"><h2 id="panelConfirmTitle"></h2><p id="panelConfirmMessage"></p><div class="panel-confirm-actions"><button class="btn btn-primary" data-panel-confirm-ok>تأیید</button><button class="btn btn-light" data-panel-confirm-cancel>انصراف</button></div></section></div><div id="panelToast" class="panel-toast hidden"></div><div class="success-modal hidden order-review-modal" id="orderReviewModal" role="dialog" aria-modal="true"><div class="success-box order-review-box"><div class="modal-head-v1280"><div><h2 id="orderReviewTitle">بررسی سفارش</h2><p id="orderReviewMeta"></p></div><button type="button" id="closeOrderReview">×</button></div><div id="orderReviewBody"></div><div class="modal-actions order-review-actions"><button class="btn btn-primary order-status-action" type="button" id="confirmReviewedOrder" data-id="" data-status="accounted">تأیید و ارسال</button><button class="btn btn-light order-status-action" type="button" id="rejectReviewedOrder" data-id="" data-status="cancelled">رد سفارش</button></div></div></div>'''
html=html.replace('</main><script>',f'</main>{confirm}<script>',1)
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
FETCH_FIXTURE=r'''
window.__hf={
 requests:[], acceptance:{cafe:true,kitchen:true,bar:true}, revision:7, feedVersion:1, printCalls:0,
 printing:{pending:1,problem:0,agent_online:false}, unresolved:[],
 orders:[{id:20,order_number:20,table_id:2,table_name:'میز ۲',zone_label:'حیاط',status:'new',waiting_minutes:4,time_ago:'۴ دقیقه پیش',created_time:'۱۰:۰۰',total_amount:320000,allowed_statuses:['accounted','cancelled'],items:[{item_name:'لاته',quantity:2,line_total:320000,item_note:''}]}],
 tables:[
  {id:1,name:'میز ۱',zone_label:'سالن',active:true,session_id:101,duration:'۳۵ دقیقه',bill_order_count:1,unconfirmed_order_count:0,bill_subtotal:610000,bill_discount:0,bill_final_total:610000,bill_items:[{item_name:'قهوه دمی',quantity:1,unit_price:610000,line_total:610000,takeaway_quantity:1,item_note:'',source_lines:[{id:111,ordered_quantity:1,quantity:1,fulfillment_mode:'takeaway'}]}],bill_orders:[{id:11,order_number:11,status:'accounted',allowed_statuses:[],guest_label:'ثبت: مهسا',created_time:'۰۹:۴۵',items:[{id:111,item_name:'قهوه دمی',ordered_quantity:1,quantity:1,unit_price:610000,line_total:610000,item_note:''}]}]},
  {id:2,name:'میز ۲',zone_label:'حیاط',active:true,session_id:102,duration:'۵ دقیقه',bill_order_count:1,unconfirmed_order_count:1,bill_subtotal:0,bill_discount:0,bill_final_total:0,bill_items:[],bill_orders:[{id:20,order_number:20,status:'new',allowed_statuses:['accounted','cancelled'],guest_label:'مهمان ۱',created_time:'۱۰:۰۰',items:[{id:201,item_name:'لاته',ordered_quantity:2,quantity:2,unit_price:160000,line_total:320000,item_note:''}]}]},
  {id:3,name:'میز ۳',zone_label:'سالن',active:false,session_id:null,duration:null,bill_order_count:0,unconfirmed_order_count:0,bill_subtotal:0,bill_discount:0,bill_final_total:0,bill_items:[],bill_orders:[]}
 ], subscribers:[{id:5,name:'مریم عنبری',mobile:'09121234567',balance:250000}]
};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const response=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
window.fetch=async(input,options={})=>{
 const u=typeof input==='string'?input:(input&&input.url?new URL(input.url).pathname:'');const method=String(options.method||'GET').toUpperCase();let body={};try{body=JSON.parse(options.body||'{}')}catch(_){}
 if(String(u).startsWith('/feed')){
  const common={success:true,snapshot:'hf-'+window.__hf.feedVersion,new_ids:window.__hf.orders.map(o=>String(o.id)),call_ids:[],order_acceptance:{...window.__hf.acceptance},order_acceptance_revision:window.__hf.revision,waiter_enabled:true,station_states:{kitchen:false,bar:false},printing:{...window.__hf.printing},accommodation:{enabled:true,can_manage:true,unresolved:structuredClone(window.__hf.unresolved)},item_totals:[]};
  return response({...common,unchanged:false,orders:structuredClone(window.__hf.orders),calls:[],tables:structuredClone(window.__hf.tables)});
 }
 if(String(u).startsWith('/controls')&&method==='GET')return response({success:true,order_acceptance:{...window.__hf.acceptance},order_acceptance_revision:window.__hf.revision,waiter_enabled:true,station_states:{kitchen:false,bar:false}});
 if(String(u).startsWith('/subscribers')&&method==='GET')return response({success:true,items:structuredClone(window.__hf.subscribers)});
 window.__hf.requests.push({url:u,...body});
 if(u==='/controls'&&body.action==='set_order_acceptance'){
   window.__hf.acceptance[body.scope]=Boolean(body.enabled);
   // Deliberately return the same current revision: RC1 falsely reported this persisted outcome as an error.
   return response({success:true,persisted:true,request_id:body.request_id,message:'ذخیره شد',order_acceptance:{...window.__hf.acceptance},order_acceptance_revision:window.__hf.revision,waiter_enabled:true,station_states:{kitchen:false,bar:false}});
 }
 if(u==='/status'){
   const row=window.__hf.orders.find(o=>Number(o.id)===Number(body.order_id));
   if(row){window.__hf.orders=window.__hf.orders.filter(o=>Number(o.id)!==Number(body.order_id));const t=window.__hf.tables.find(t=>Number(t.id)===Number(row.table_id));if(t){t.unconfirmed_order_count=0;t.bill_orders=[];}window.__hf.feedVersion++;}
   return response({success:true,persisted:true,request_id:body.request_id,current_status:body.status,status:body.status,message:'سفارش رد شد.'});
 }
 if(u==='/session'&&body.action==='print_prebill'){
   window.__hf.printCalls++;await sleep(160);return response({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),session_id:101,message:'صورتحساب وارد صف چاپ شد.',print_job:{id:700,duplicate:false}});
 }
 if(u==='/session'&&body.action==='checkout_direct')return response({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),session_id:101,message:'تسویه ثبت شد.'});
 if(u==='/subscribers'&&body.action==='charge')return response({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),session_id:101,message:'در حساب مشترک ثبت شد.'});
 if(u==='/accommodation'&&body.action==='search')return response({success:true,request_id:body.request_id,reservations:[{reservation_code:'SK-1',guest_name:'مهمان تست',room_names:['سیف'],check_in:'۱۴۰۵/۰۵/۱۵',check_out:'۱۴۰۵/۰۵/۱۶',charge_allowed:true}]});
 if(u==='/accommodation'&&body.action==='charge'){
   const transfer={id:91,session_id:101,table_id:1,table_name:'میز ۱',status:'pending',status_label:'در انتظار انتقال',needs_action:true,last_error_code:'internal_error',last_error:'نتیجه انتقال نامشخص است.',ambiguous:true,retry_allowed:true,can_detach:true,detached:false,guest_name_snapshot:'مهمان تست'};
   window.__hf.unresolved=[transfer];window.__hf.tables[0].accommodation_transfer=transfer;window.__hf.feedVersion++;
   return response({success:false,code:'internal_error',message:'نتیجه انتقال نامشخص است.',ambiguous:true,transfer,request_id:body.request_id},409);
 }
 if(u==='/accommodation'&&body.action==='retry')return response({success:false,code:'internal_error',message:'هنوز نامشخص است.',ambiguous:true,request_id:body.request_id},409);
 if(u==='/accommodation'&&body.action==='detach_followup'){
   const transfer={...window.__hf.unresolved[0],detached:true,can_detach:false,session_status:'followup'};window.__hf.unresolved=[transfer];
   Object.assign(window.__hf.tables[0],{active:false,session_id:null,bill_order_count:0,bill_subtotal:0,bill_final_total:0,bill_items:[],bill_orders:[],accommodation_transfer:null});window.__hf.feedVersion++;
   return response({success:true,persisted:true,request_id:body.request_id,message:'میز آزاد شد و حساب برای پیگیری محفوظ ماند.',transfer});
 }
 if(u==='/waiter'||u==='/bill')return response({success:true,message:'انجام شد'});
 return response({success:true,message:'انجام شد'});
};
'''
def confirm(page):
    page.wait_for_selector('#panelConfirmLayer:not(.hidden)')
    page.click('[data-panel-confirm-ok]')

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844});page.set_default_timeout(5000)
    page.set_content(html)
    for css in CSS: page.add_style_tag(path=str(css))
    page.evaluate('window.CUSTOMER_PRINT_CONFIGURED=false')
    page.evaluate(FETCH_FIXTURE)
    page.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'))
    page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'))
    page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
    page.wait_for_selector('.order-status-action[data-status="cancelled"]')

    # Current service work stays above collapsible persistent follow-ups.
    assert page.locator('.attention-current-v1301').count()==1
    assert page.locator('.attention-followups-v1301').count()==1
    assert page.locator('.attention-current-v1301').bounding_box()['y'] < page.locator('.attention-followups-v1301').bounding_box()['y']

    # Persisted stop state with an equal current revision is accepted without a false red error.
    page.click('[data-order-acceptance="kitchen"]');confirm(page)
    page.wait_for_function("document.querySelector('[data-acceptance-status=\"kitchen\"]')?.textContent==='متوقف'")
    assert 'تأیید نکرد' not in page.locator('#panelToast').inner_text()

    # A pending order is reachable from a full-width attention table card in one focused review step.
    page.click('#tabTables')
    card=page.locator('[data-select-table="2"]').locator('xpath=..')
    assert card.bounding_box()['width'] >= card.locator('xpath=..').bounding_box()['width']-2
    page.click('.table-card-review[data-review-order="20"]')
    page.wait_for_selector('#orderReviewModal:not(.hidden)')
    assert page.locator('#orderReviewBody').inner_text().find('لاته')>=0
    assert page.locator('#rejectReviewedOrder:not([disabled])').count()==1

    # RTL confirmation keeps destructive primary on the right and cancel on the left.
    page.click('#rejectReviewedOrder')
    page.wait_for_selector('#panelConfirmLayer:not(.hidden)')
    ok_box=page.locator('[data-panel-confirm-ok]').bounding_box(); cancel_box=page.locator('.panel-confirm-actions [data-panel-confirm-cancel]').bounding_box()
    assert ok_box['x'] > cancel_box['x']
    page.click('[data-panel-confirm-ok]')
    page.wait_for_function("!document.querySelector('.current-order-card[data-order-id=\"20\"]') && document.querySelector('#orderReviewModal').classList.contains('hidden')")

    # Subscriber search survives leaving and reopening the destination without refreshing the page.
    page.click('[data-select-table="1"]')
    account_text=page.locator('#tableDetailBody').inner_text()
    assert 'فاکتور جاری' in account_text
    assert '۱ نوبت سفارش' not in account_text and 'تفکیک نوبت' not in account_text
    assert 'جمع اقلام' not in account_text, 'Subtotal must stay hidden when no discount exists.'
    assert '۱ بیرون‌بر' in account_text, 'Takeaway remains visible as an operational exception in the current bill.'
    assert page.locator('.order-batches-disclosure-v13219').count()==0
    assert page.locator('.table-account-print-status-v13219').count()==1
    assert page.locator('.session-action[data-action="print_prebill"]').count()==0
    page.click('.open-settlement-action');page.click('[data-settlement="subscriber"]')
    page.fill('#subscriberSearch','مریم');page.click('#subscriberSearchButton');page.wait_for_selector('[data-subscriber-id="5"]')
    assert '۰۹۱۲۱۲۳۴۵۶۷' in page.locator('[data-subscriber-id="5"]').inner_text()
    page.click('#backSubscriberSettlement');assert page.locator('#checkoutModal').is_visible()
    page.click('[data-settlement="subscriber"]');page.fill('#subscriberSearch','مریم');page.click('#subscriberSearchButton');page.wait_for_selector('[data-subscriber-id="5"]')

    # Back navigation exists for accommodation too.
    page.click('#backSubscriberSettlement');page.click('[data-settlement="accommodation"]');page.click('#backAccommodationSettlement');assert page.locator('#checkoutModal').is_visible()

    # Ambiguous House error closes the transaction modal and exposes retry/detach in the table account.
    page.click('[data-settlement="accommodation"]');page.fill('#accommodationSearchQuery','مهمان');page.click('#accommodationSearchButton');page.wait_for_selector('[data-reservation-index="0"]');page.click('[data-reservation-index="0"]');page.click('#accommodationPostButton')
    page.wait_for_function("document.querySelector('#accommodationModal').classList.contains('hidden')")
    page.wait_for_selector('.table-account-alert-v1280 .accommodation-detach-action')
    assert page.locator('.table-account-alert-v1280 .accommodation-retry-action').count()==1

    # Detach frees the physical table while the same unresolved account remains in follow-up.
    page.click('.table-account-alert-v1280 .accommodation-detach-action');confirm(page)
    page.wait_for_function("!window.__hf.tables.find(t=>t.id===1).active")
    page.click('#tabAttention');page.locator('.attention-followups-v1301').evaluate('(el)=>el.open=true')
    page.wait_for_selector('.attention-followup-list-v1301 .accommodation-retry-action')
    assert 'حساب جداشده' in page.locator('.attention-followup-list-v1301').inner_text()

    # A long toast remains entirely inside the phone viewport.
    page.evaluate("CafeUI.toast('پیام بسیار طولانی برای بررسی نمایش صحیح در موبایل و جلوگیری از بیرون‌زدگی از سمت چپ یا راست صفحه','error',{sticky:true})")
    toast_box=page.locator('#panelToast').bounding_box()
    assert toast_box['x']>=-0.5 and toast_box['x']+toast_box['width']<=390.5
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    page.close()

    # Desktop: double print is one request and normal success feedback auto-dismisses.
    page=browser.new_page(viewport={'width':1366,'height':900});page.set_default_timeout(5000)
    page.set_content(html)
    for css in CSS: page.add_style_tag(path=str(css))
    page.evaluate(FETCH_FIXTURE)
    page.add_script_tag(path=str(ROOT/'assets/js/panel-core.js'));page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'));page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
    page.wait_for_selector('#tabTables');page.click('#tabTables');page.click('[data-select-table="1"]')
    button=page.locator('.session-action[data-action="print_prebill"]');button.dblclick(delay=10);page.wait_for_timeout(260)
    assert page.evaluate('window.__hf.printCalls')==1
    assert 'صورتحساب' in page.locator('#panelToast').inner_text()
    page.wait_for_timeout(5000)
    assert page.locator('#panelToast').is_hidden()
    browser.close()
print('Sokna 1.30.1-rc8 operator browser checks passed: queue priority, stop state, RTL dialog, table action, subscriber, accommodation follow-up, toast and print feedback.')
