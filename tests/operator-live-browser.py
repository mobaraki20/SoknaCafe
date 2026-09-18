#!/usr/bin/env python3
from pathlib import Path
import os
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; current operator browser check skipped.')
    raise SystemExit(0)

ROOT = Path(__file__).resolve().parents[1]
CSS = [ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
JS = ROOT/'assets/js/operator.js'
MENU_JS = ROOT/'assets/js/panel-menus.js'
HTML = r'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="test"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sokna 1.29</title></head><body class="panel-body panel-section-operator"><main class="panel-content">
<section class="operator-live-head-v1280"><div class="operator-work-tabs-v1280 panel-primary-tabs" role="tablist"><button id="tabAttention" class="is-active" data-work-tab="attention" aria-selected="true">نیازمند اقدام <span id="attentionTabCount" class="hidden"></span></button><button id="tabTables" data-work-tab="tables" aria-selected="false">میزها</button><button id="tabItems" data-work-tab="items" aria-selected="false">جمع اقلام</button></div><div id="liveIndicator" class="operator-connection-v1280"><span class="live-dot"></span><span id="liveStatus"></span><small id="lastSync"></small></div></section>
<section id="printStatusStrip" class="print-status-strip hidden"><strong id="printStatusTitle"></strong><span id="printStatusText"></span></section><section id="accommodationAlertStrip" class="accommodation-alert-strip hidden"><strong id="accommodationAlertTitle"></strong><span id="accommodationAlertText"></span></section>
<section id="attentionPanel" data-work-panel="attention"><strong id="attentionCount" class="hidden"></strong><div id="attentionBoard"></div></section>
<section id="tablesPanel" data-work-panel="tables" class="hidden"><button type="button" class="is-active" data-table-filter="all" aria-pressed="true" id="tableAllCount"></button><button type="button" data-table-filter="open" aria-pressed="false" id="tableOpenCount"></button><button type="button" data-table-filter="free" aria-pressed="false" id="tableFreeCount"></button><div class="table-sort-control-v1306"><button type="button" data-table-sort="layout" aria-pressed="true">چیدمان سالن</button><button type="button" data-table-sort="oldest" aria-pressed="false">قدیمی‌ترین</button><button type="button" data-table-sort="newest" aria-pressed="false">تازه‌ترین</button></div><div id="liveTablesLayout" class="live-tables-layout-v1280"><aside id="tableDetailShell" class="table-account-panel-v1280" aria-hidden="true"><header class="table-account-head-v1280"><div><button id="tableDetailTitle" class="table-name-action-v1280"></button><span id="tableDetailMeta"></span></div><button id="closeTableDetail">×</button></header><div id="tableDetailBody" class="table-detail-body-v1190"></div><footer id="tableDetailFooter" class="table-account-footer-v1301 hidden"></footer></aside><div class="table-overview-pane-v1280"><div id="tablesBoard"></div></div></div><button id="tableDetailBackdrop"></button></section>
<section id="itemsPanel" data-work-panel="items" class="hidden"><div id="itemTotalsBoard"></div></section>
<button id="openTodaySettlements" type="button">تسویه‌های امروز</button>
<div class="success-modal hidden today-settlements-modal" id="todaySettlementsModal" role="dialog" aria-modal="true"><div class="success-box today-settlements-box"><div class="today-settlements-head"><div><small id="todaySettlementsSummary"></small><h2>تسویه‌های امروز</h2><p>مشاهده و عملیات مالی</p></div><div class="today-settlements-head-actions"><button id="refreshTodaySettlements">↻</button><button id="closeTodaySettlements">×</button></div></div><div id="todaySettlementsList"></div></div></div>
<div class="success-modal hidden" id="settlementVoidModal"><div class="success-box"><input id="settlementVoidId"><input id="settlementVoidReason"><label id="settlementVoidTargetWrap" class="hidden"><select id="settlementVoidTarget"></select></label><button id="confirmSettlementVoid"></button><button id="cancelSettlementVoid"></button><button id="closeSettlementVoid"></button></div></div>
<details id="operatorControls" open><span id="operationsSummaryText"></span><span id="operationsSummaryState"></span>
<div data-acceptance-row="cafe"><span data-acceptance-status="cafe"></span><button data-order-acceptance="cafe"></button></div>
<div data-acceptance-row="kitchen"><span data-acceptance-status="kitchen"></span><button data-order-acceptance="kitchen"></button></div>
<div data-acceptance-row="bar"><span data-acceptance-status="bar"></span><button data-order-acceptance="bar"></button></div>
<button id="waiterToggle"></button><div id="stationControls"><div data-station-card="kitchen"><small data-station-save-state></small><button data-station-choice="kitchen" data-busy="0">عادی</button><button data-station-choice="kitchen" data-busy="1">شلوغ</button></div><div data-station-card="bar"><small data-station-save-state></small><button data-station-choice="bar" data-busy="0">عادی</button><button data-station-choice="bar" data-busy="1">شلوغ</button></div></div></details>
<div id="billItemModal" class="success-modal hidden"><input id="billItemId"><input id="billItemQuantity"><input id="billItemReason"><p id="billItemDescription"></p><button id="closeBillItem"></button><button id="saveBillItem"></button></div>
<div id="moveTableModal" class="success-modal hidden"><div class="success-box"><p id="moveTableSummary"></p><div id="moveTableChoices"></div><button id="confirmMoveTable" disabled></button><button id="cancelMoveTable"></button><button id="closeMoveTable"></button></div></div>
<div id="checkoutModal" class="success-modal hidden"><div class="success-box"><p id="checkoutModalSummary"></p><input id="checkoutTableId"><input id="checkoutSessionId"><input id="checkoutExpectedTotal"><input id="checkoutExpectedSignature"><div class="settlement-destinations-v1280"><button data-settlement="direct"><strong id="directSettlementChoiceLabel">تسویه مستقیم</strong></button><button data-settlement="itemized">پرداخت جداگانه</button><button data-settlement="accommodation">ثبت در حساب اقامتگاه</button><button data-settlement="subscriber">ثبت در حساب مشترک</button></div><p id="checkoutItemizedLockNote" class="hidden"></p><input type="checkbox" id="checkoutPrintFinal"><button id="closeCheckoutModal"></button></div></div>
<div id="itemizedSettlementModal" class="success-modal hidden settlement-modal itemized-settlement-modal"><div class="success-box settlement-shell itemized-settlement-box"><div class="modal-head-v1280"><p id="itemizedSettlementSubtitle"></p><button id="closeItemizedSettlement"></button></div><div class="settlement-content"><div id="itemizedFlowNotice" class="itemized-flow-notice hidden"></div><div id="itemizedAccountSummary" class="itemized-account-summary"></div><section id="itemizedSelectionStep"><div class="itemized-selection-head"><strong>اقلام باقی‌مانده</strong><div class="itemized-selection-actions"><button class="btn btn-light btn-sm hidden" id="addMissedItemizedItem">افزودن قلم جاافتاده</button><button class="btn btn-light btn-sm" id="selectAllItemizedRemaining">انتخاب همه مانده</button></div></div><div id="itemizedSelectionList" class="itemized-selection-list"></div><div class="itemized-selection-total"><span id="itemizedSelectionCount"></span><div class="itemized-selection-payable"><small id="itemizedSelectionBreakdown" class="hidden"></small><span id="itemizedSelectionAmountLabel">جمع انتخاب‌شده</span><strong id="itemizedSelectionGross"></strong></div></div><p id="itemizedSelectionError" class="hidden"></p></section></div><div class="modal-actions settlement-footer"><button id="submitItemizedSettlement">حداقل یک قلم انتخاب کنید</button><button id="backItemizedSettlement">بازگشت</button></div></div></div>
<div id="directSettlementModal" class="success-modal hidden"><div class="success-box"><span id="directSettlementTable"></span><strong id="directSettlementAmount"></strong><p id="directSettlementError" class="hidden"></p><button id="confirmDirectSettlement">تأیید تسویه</button><button id="backDirectSettlement">بازگشت</button><button id="closeDirectSettlement"></button></div></div>
<div id="subscriberModal" class="success-modal hidden"><div class="success-box"><p id="subscriberAmountSummary"></p><input id="subscriberSearch"><div id="subscriberSearchResult"></div><div id="subscriberConfirm" class="hidden"></div><button id="closeSubscriberModal"></button></div></div>
<div id="accommodationModal" class="success-modal hidden"><div class="success-box"><input id="accommodationTableId"><input id="accommodationAmount"><input id="accommodationSearchQuery"><button id="accommodationSearchButton"></button><div id="accommodationSearchResult"></div><div id="accommodationConfirm" class="hidden"><span id="accommodationGuest"></span><span id="accommodationRoom"></span><span id="accommodationDates"></span><span id="accommodationCode"></span><span id="accommodationFinalAmount"></span><span id="accommodationExternalId"></span><button id="accommodationPostButton"></button><button id="accommodationBackButton"></button></div><button id="closeAccommodationModal"></button></div></div>
</main><script>
window.OPERATOR_API='/feed';window.OPERATOR_INVOICES_URL='/invoices';window.OPERATOR_STATUS_API='/status';window.OPERATOR_SESSION_API='/session';window.OPERATOR_BILL_API='/bill';window.OPERATOR_SUBSCRIBERS_API='/subscribers';window.OPERATOR_SETTLEMENTS_API='/settlements';window.OPERATOR_CONTROLS_API='/controls';window.OPERATOR_WAITER_API='/waiter';window.ACCOMMODATION_API='/accommodation';window.OPERATOR_PERMISSIONS={orders_floor:true,cashier_accounts:true,shift_supervision:true};window.CUSTOMER_PRINT_CONFIGURED=true;window.PREP_PRINT_CONFIGURED=true;window.CHECKOUT_PRINT_DEFAULT=true;window.CAFE_CURRENCY='تومان';window.CafeUI={toast:()=>{},confirm:async()=>true,runAction:async({button,loadingText,successText,request,onSuccess})=>{const old=button?.textContent||'';if(button){button.disabled=true;button.textContent=loadingText||old;}try{const result=await request();if(button&&successText)button.textContent=successText;if(onSuccess)await onSuccess(result);return result;}finally{if(button){button.disabled=false;}}}};
const table=(id,name,zone,extra={})=>({id,name,zone_label:zone,sort_order:id,active:false,session_id:null,duration:null,bill_order_count:0,unconfirmed_order_count:0,bill_subtotal:0,bill_discount:0,bill_final_total:0,bill_items:[],bill_orders:[],...extra});
let acceptanceRevision=1;const acceptance={cafe:true,kitchen:true,bar:true};const feed={success:true,unchanged:false,snapshot:'s1',new_ids:['20'],call_ids:['31'],order_acceptance:acceptance,waiter_enabled:true,station_states:{kitchen:false,bar:false},printing:{pending:0,problem:0,agent_online:true},accommodation:{enabled:true,unresolved:[]},orders:[{id:20,order_number:20,table_id:2,table_name:'میز ۲',zone_label:'حیاط',status:'new',waiting_minutes:4,time_ago:'۴ دقیقه پیش',created_time:'۱۰:۰۰',total_amount:320000,allowed_statuses:['accounted','cancelled'],items:[{item_name:'لاته',quantity:2,line_total:320000,item_note:''}]}],calls:[{id:31,table_id:3,table_name:'میز ۳',zone_label:'سالن',status:'new',waiting_minutes:3,time_ago:'۳ دقیقه پیش'}],item_totals:[{station_label:'آشپزخانه',item_name:'پاستا',confirmed:8,pending:2},{station_label:'بار',item_name:'لاته',confirmed:4,pending:0}],tables:[
table(1,'میز ۱','سالن',{active:true,session_id:101,started_at:'2026-08-09 09:00:00',duration:'۳۵ دقیقه',last_order_ago:'۱۲ دقیقه پیش',bill_order_count:2,bill_subtotal:610000,bill_final_total:610000,pending_preparation_adjustments:1,bill_items:[{item_name:'قهوه دمی',quantity:1,unit_price:210000,line_total:210000,item_note:'',source_lines:[{id:111,ordered_quantity:1,quantity:1}]},{item_name:'کیک شکلاتی',quantity:2,unit_price:200000,line_total:400000,item_note:'',source_lines:[{id:112,ordered_quantity:2,quantity:2}]}],bill_orders:[{id:11,order_number:11,status:'accounted',allowed_statuses:[],guest_label:'مهمان',created_time:'۰۹:۳۰',items:[{id:111,item_name:'قهوه دمی',ordered_quantity:1,quantity:1,unit_price:210000,line_total:210000,item_note:''}]},{id:12,order_number:12,status:'accounted',allowed_statuses:[],guest_label:'مهمان',created_time:'۰۹:۴۵',items:[{id:112,item_name:'کیک شکلاتی',ordered_quantity:2,quantity:2,unit_price:200000,line_total:400000,item_note:''}]}]}),
table(2,'میز ۲','حیاط',{active:true,session_id:102,started_at:'2026-08-09 10:30:00',duration:'۵ دقیقه',unconfirmed_order_count:1,bill_orders:[{id:20,order_number:20,status:'new',allowed_statuses:['accounted','cancelled'],guest_label:'مهمان',created_time:'۱۰:۰۰',items:[{id:201,item_name:'لاته',quantity:2,line_total:320000}]}]}),
table(3,'میز ۳','سالن',{active:true,session_id:103,started_at:'2026-08-09 10:00:00',duration:'۱۲ دقیقه'}),table(4,'میز ۴','حیاط',{}),table(5,'میز ۵','پشت‌بام',{}),table(6,'میز ۶','',{})]};
window.__requests=[];window.fetch=async(url,options={})=>{const u=String(url);if(u.startsWith('/feed')){const unchanged=u.includes('snapshot=s1');const payload=unchanged?{success:true,unchanged:true,snapshot:'s1',new_ids:feed.orders.map(o=>String(o.id)),call_ids:feed.call_ids,item_totals:feed.item_totals,order_acceptance:{...acceptance},order_acceptance_revision:acceptanceRevision,waiter_enabled:true,station_states:{kitchen:false,bar:false},printing:feed.printing,accommodation:feed.accommodation}:{...feed,orders:[...feed.orders],new_ids:feed.orders.map(o=>String(o.id)),order_acceptance:{...acceptance},order_acceptance_revision:acceptanceRevision};return new Response(JSON.stringify(payload),{status:200,headers:{'Content-Type':'application/json'}});}if(String(u).startsWith('/controls')&&(!options.method||options.method==='GET'))return new Response(JSON.stringify({success:true,order_acceptance:{...acceptance},order_acceptance_revision:acceptanceRevision,waiter_enabled:true,station_states:{kitchen:false,bar:false}}),{status:200,headers:{'Content-Type':'application/json'}});if(u==='/settlements'&&(!options.method||options.method==='GET'))return new Response(JSON.stringify({success:true,can_void:true,items:[{id:90,invoice_number:'S-1405-000090',table_name:'میز ۹',destination:'subscriber',destination_label:'حساب مشترک',subscriber_name:'سعید عبداللهی',total:430000,status:'completed',status_label:'ثبت‌شده',settled_at_label:'۱۴ مرداد ۱۴۰۵ · ساعت ۰۸:۴۲',actor_name:'مدیر کافه',print_status_label:'در انتظار چاپ'}]}) ,{status:200,headers:{'Content-Type':'application/json'}});if(u.startsWith('/subscribers')&&(!options.method||options.method==='GET'))return new Response(JSON.stringify({success:true,items:[{id:7,name:'شرکت نمونه',mobile:'09120000000',balance:900000}]}),{status:200,headers:{'Content-Type':'application/json'}});let body={};try{body=JSON.parse(options.body||'{}')}catch(e){}window.__requests.push({url:u,...body});if(u==='/controls'&&body.action==='set_order_acceptance'){acceptance[body.scope]=Boolean(body.enabled);acceptanceRevision++;return new Response(JSON.stringify({success:true,persisted:true,request_id:body.request_id,message:'ذخیره شد',order_acceptance:{...acceptance},order_acceptance_revision:acceptanceRevision,waiter_enabled:true,station_states:{kitchen:false,bar:false}}),{status:200,headers:{'Content-Type':'application/json'}});}if(u==='/controls'&&body.action==='set_station')return new Response(JSON.stringify({success:true,message:'ذخیره شد',station:body.station,busy:Boolean(body.busy)}),{status:200,headers:{'Content-Type':'application/json'}});if(u==='/status'){feed.orders=feed.orders.filter(o=>Number(o.id)!==Number(body.order_id));return new Response(JSON.stringify({success:true,persisted:true,request_id:body.request_id,current_status:body.status,status:body.status,message:'انجام شد'}),{status:200,headers:{'Content-Type':'application/json'}});}if(u==='/session'){return new Response(JSON.stringify({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),source_table_id:Number(body.table_id),target_table_id:Number(body.target_table_id||0),message:'انجام شد',pending_preparation_adjustments:1,print_job:{id:501}}),{status:200,headers:{'Content-Type':'application/json'}});}if(u==='/subscribers'&&body.action==='charge')return new Response(JSON.stringify({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),message:'انجام شد'}),{status:200,headers:{'Content-Type':'application/json'}});if(u==='/accommodation'&&body.action==='search')return new Response(JSON.stringify({success:true,reservations:[],request_id:body.request_id}),{status:200,headers:{'Content-Type':'application/json'}});if(u==='/accommodation'&&body.action==='charge')return new Response(JSON.stringify({success:true,persisted:true,request_id:body.request_id,table_id:Number(body.table_id),message:'انجام شد'}),{status:200,headers:{'Content-Type':'application/json'}});return new Response(JSON.stringify({success:true,message:'انجام شد'}),{status:200,headers:{'Content-Type':'application/json'}})};
</script></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1440,'height':980})
    page.set_content(HTML)
    for css in CSS: page.add_style_tag(path=str(css))
    page.add_script_tag(path=str(MENU_JS))
    page.add_script_tag(path=str(JS))
    page.wait_for_selector('[data-select-table="1"]', state='attached')
    assert page.locator('#tabAttention').get_attribute('aria-selected')=='true'
    assert page.locator('[data-acceptance-status="kitchen"]').inner_text()=='فعال'
    assert 'توقف آنلاین آشپزخانه' in page.locator('[data-order-acceptance="kitchen"]').inner_text()
    page.click('[data-order-acceptance="kitchen"]');page.wait_for_timeout(80)
    assert page.locator('[data-acceptance-status="kitchen"]').inner_text()=='متوقف'
    assert page.locator('[data-order-acceptance="kitchen"]').inner_text()=='فعال‌سازی آنلاین'
    assert any(r.get('action')=='set_order_acceptance' and r.get('scope')=='kitchen' and r.get('enabled') is False for r in page.evaluate('window.__requests'))
    reject=page.locator('.order-status-action[data-status="cancelled"]')
    assert reject.count()==1
    page.click('.order-status-action[data-status="cancelled"]');page.wait_for_timeout(100)
    assert any(r.get('url')=='/status' and r.get('order_id')==20 and r.get('status')=='cancelled' for r in page.evaluate('window.__requests'))
    page.click('[data-station-choice="bar"][data-busy="1"]');page.wait_for_timeout(80)
    assert page.locator('[data-station-choice="bar"][data-busy="1"]').evaluate('(e)=>e.classList.contains("is-selected")')
    assert any(r.get('action')=='set_station' and r.get('station')=='bar' and r.get('busy') is True for r in page.evaluate('window.__requests'))
    page.click('#openTodaySettlements');page.wait_for_timeout(80)
    settlement_text=page.locator('#todaySettlementsList').inner_text()
    assert 'S-1405-000090' in settlement_text and '۱۴ مرداد ۱۴۰۵' in settlement_text and 'در انتظار چاپ' in settlement_text
    assert 'pending' not in settlement_text
    assert page.locator('.settlement-void-action').count()==1 and page.locator('.settlement-reprint-action').count()==1
    assert page.locator('.settlement-void-action').evaluate("e=>e.classList.contains('btn-outline-danger')")
    page.click('#closeTodaySettlements')
    page.click('#tabTables')
    assert page.locator('#tabTables').get_attribute('aria-selected')=='true'
    assert page.locator('#tabAttention').get_attribute('aria-selected')=='false'
    assert 'is-active' not in (page.locator('#tabAttention').get_attribute('class') or '')
    assert page.locator('#orderSearch').count()==0 and page.locator('#tableViewFilter').count()==0
    text=page.locator('#tablesBoard').inner_text()
    for zone in ('سالن','حیاط','پشت‌بام','بدون دسته‌بندی'): assert zone in text
    assert page.locator('[data-select-table]').count()==6
    page.click('[data-table-filter="open"]');page.wait_for_timeout(30)
    assert page.locator('[data-select-table]').count()==3 and page.locator('[data-table-filter="open"]').get_attribute('aria-pressed')=='true'
    page.click('[data-table-filter="free"]');page.wait_for_timeout(30)
    assert page.locator('[data-select-table]').count()==3 and page.locator('[data-table-filter="free"]').get_attribute('aria-pressed')=='true'
    page.click('[data-table-filter="all"]');page.wait_for_timeout(30)
    assert page.locator('[data-select-table]').count()==6
    page.click('[data-table-sort="oldest"]');page.wait_for_timeout(30)
    oldest=page.locator('[data-select-table]').evaluate_all("els=>els.map(e=>Number(e.dataset.selectTable))")
    assert oldest==[1,3,2,4,5,6], oldest
    page.click('[data-table-sort="newest"]');page.wait_for_timeout(30)
    newest=page.locator('[data-select-table]').evaluate_all("els=>els.map(e=>Number(e.dataset.selectTable))")
    assert newest==[2,3,1,4,5,6], newest
    page.click('[data-table-sort="layout"]');page.wait_for_timeout(30)
    assert page.locator('[data-table-sort="layout"]').get_attribute('aria-pressed')=='true'
    page.click('[data-select-table="1"]');page.wait_for_timeout(50)
    panel=page.locator('#tableDetailShell').bounding_box(); board=page.locator('.table-overview-pane-v1280').bounding_box()
    assert panel and board and panel['x'] < board['x'], (panel,board)
    assert panel['width'] >= 515, panel
    detail=page.locator('#tableDetailShell').inner_text()
    assert 'قهوه دمی' in detail and 'تسویه حساب' in detail and 'تخفیف' in detail
    assert 'دلیل تخفیف' not in detail
    page.click('#tableDetailTitle');page.wait_for_timeout(30)
    assert page.locator('#moveTableModal').is_visible()
    targets=page.locator('[data-move-target]')
    assert targets.count()==3 and page.locator('[data-move-target="4"]').count()==1 and page.locator('[data-move-target="5"]').count()==1 and page.locator('[data-move-target="6"]').count()==1
    page.click('#closeMoveTable')
    # Target the order-history disclosure explicitly: polling may replace sibling disclosures.
    page.locator('.order-batches-disclosure-v13219').evaluate('e=>e.open=true')
    page.wait_for_selector('.bill-batch-v1280', state='attached')
    page.locator('.bill-batch-v1280').first.evaluate('e=>e.open=true')
    # The polling fixture can replace the detail DOM between pointer phases; this test
    # validates the reprint contract while menu mechanics are covered separately.
    page.locator('.bill-batch-action-trigger:visible').first.evaluate('e=>e.click()')
    page.locator('.prep-reprint-action:visible').first.evaluate('e=>e.click()');page.wait_for_timeout(50)
    assert any(r.get('action')=='reprint_prep' and r.get('order_id')==11 for r in page.evaluate('window.__requests'))
    page.click('.open-settlement-action');
    assert page.locator('[data-settlement]').count()==4 and page.locator('[data-settlement="itemized"]').count()==1
    page.click('[data-settlement="subscriber"]');page.fill('#subscriberSearch','شر');page.wait_for_selector('[data-subscriber-id="7"]');page.click('[data-subscriber-id="7"]');page.click('#confirmSubscriberCharge');page.wait_for_timeout(60)
    subscriber=[r for r in page.evaluate('window.__requests') if r.get('url')=='/subscribers' and r.get('action')=='charge']
    assert subscriber and subscriber[-1].get('subscriber_id')==7 and subscriber[-1].get('table_id')==1 and subscriber[-1].get('print_final') is True and 'payment_method' not in subscriber[-1]
    page.click('[data-select-table="1"]');page.click('.open-settlement-action');page.wait_for_timeout(20)
    assert 'اصلاحیه آماده‌سازی هنوز باز است' in page.locator('#checkoutModalSummary').inner_text()
    page.click('[data-settlement="direct"]');page.click('#confirmDirectSettlement');page.wait_for_timeout(80)
    direct=[r for r in page.evaluate('window.__requests') if r.get('action')=='checkout_direct']
    assert len(direct)>=1 and direct[-1].get('table_id')==1 and direct[-1].get('print_final') is True and 'accept_preparation_adjustment' not in direct[-1] and 'payment_method' not in direct[-1]
    page.click('#tabItems');
    assert 'پاستا' in page.locator('#itemTotalsBoard').inner_text() and '۸ تأییدشده' in page.locator('#itemTotalsBoard').inner_text() and '۲ منتظر تأیید' in page.locator('#itemTotalsBoard').inner_text()
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    shot=Path(os.getenv('SOKNA_SCREENSHOT_DIR','/mnt/data'))/'sokna-1280-operator-desktop.png';page.screenshot(path=str(shot),full_page=True)

    mobile=browser.new_page(viewport={'width':390,'height':844});mobile.set_content(HTML)
    for css in CSS: mobile.add_style_tag(path=str(css))
    mobile.add_script_tag(path=str(MENU_JS));mobile.add_script_tag(path=str(JS));mobile.wait_for_selector('[data-select-table="1"]', state='attached');mobile.click('#tabTables');
    assert 'آخرین سفارش ۱۲ دقیقه پیش' in mobile.locator('#tablesBoard').inner_text()
    mobile.click('[data-select-table="1"]');mobile.wait_for_timeout(60)
    assert mobile.locator('#tableDetailShell').evaluate("e=>e.classList.contains('is-open')")
    assert 'آخرین سفارش ۱۲ دقیقه پیش' in mobile.locator('#tableDetailMeta').inner_text()
    mobile.go_back();mobile.wait_for_timeout(80)
    assert not mobile.locator('#tableDetailShell').evaluate("e=>e.classList.contains('is-open')")
    assert mobile.locator('[data-table-sort="layout"]').get_attribute('aria-pressed')=='true'
    assert mobile.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    mobile.screenshot(path='/mnt/data/sokna-1280-operator-mobile.png',full_page=True)
    browser.close()
print('Current operator browser checks passed: real tabs, dynamic areas, left desktop invoice, discounts, move-by-table-name, prep reprint, four settlement destinations including itemized payment, item totals, independent acceptance controls and immediate station state.')
