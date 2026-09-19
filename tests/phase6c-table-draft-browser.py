#!/usr/bin/env python3
from pathlib import Path
import json
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
policy=(ROOT/'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')
quick_js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')

catalog={
    'success':True,
    'tables':[{
        'id':1,'name':'میز ۱','table_number':1,'code':'T-1','zone_label':'سالن','sort_order':1,
        'session_id':None,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,
        'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],
        'pending_order_count':0,'pending_orders':[]
    }],
    'menus':[{'id':1,'menu_key':'main','name':'منوی اصلی','status':'active'}],
    'selected_menu':{'id':1,'menu_key':'main','name':'منوی اصلی','status':'active'},
    'categories':[{'id':1,'name':'نوشیدنی','icon_key':''}],
    'items':[{'id':101,'category_id':1,'category_name':'نوشیدنی','name':'قهوه تست','price':100000,'order_available':1,'takeaway_allowed':1}],
}
server={'draft':None,'requests':[]}

def snapshot_items(rows):
    out=[]
    for idx,row in enumerate(rows):
        out.append({
            'id':int(row.get('id',0)),
            'name':'قهوه تست',
            'unit_price':100000,
            'expected_price':100000,
            'sellable_kind':'menu_item',
            'quantity':int(row.get('quantity',0)),
            'note':str(row.get('note','')),
            'fulfillment_mode':str(row.get('fulfillment_mode','dine_in')),
            'sort_order':idx,
        })
    return out

def fulfill_json(route,payload,status=200):
    route.fulfill(status=status,content_type='application/json; charset=utf-8',body=json.dumps(payload,ensure_ascii=False))

def route_handler(route):
    req=route.request
    parsed=urlparse(req.url)
    if parsed.path.endswith('/quick'):
        fulfill_json(route,catalog)
        return
    if not parsed.path.endswith('/draft'):
        route.continue_()
        return

    if req.method == 'GET':
        fulfill_json(route,{'success':True,'draft':server['draft']})
        return

    body=json.loads(req.post_data or '{}')
    action=str(body.get('action','save'))
    if action != 'save':
        fulfill_json(route,{'success':False,'code':'unsupported','message':'unsupported'},422)
        return

    expected=int(body.get('expected_version',0) or 0)
    current=int((server['draft'] or {}).get('version',0) or 0)
    server['requests'].append({'expected':expected,'current':current,'items':body.get('items',[])})
    if expected != current:
        fulfill_json(route,{
            'success':False,'code':'version_conflict',
            'message':'پیش‌نویس این میز توسط همکار دیگری تغییر کرده است؛ دوباره بارگذاری کنید.',
            'current_version':current,
        },409)
        return

    version=current+1
    server['draft']={
        'id':44,'table_id':1,'state':'active','version':version,'expected_session_id':0,
        'note':str(body.get('note','')),'created_by_user_id':7,'updated_by_user_id':7,
        'final_order_id':None,'created_at':'2026-09-19 12:00:00','updated_at':'2026-09-19 12:00:00',
        'items':snapshot_items(body.get('items',[])),
    }
    fulfill_json(route,{'success':True,'created':version==1,'draft':server['draft']})

markup=r'''
<div class="quick-order-page-shell" id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php" data-success-url="/operator/index.php?work=tables#tables" data-origin="table-panel" data-mode="normal">
<header class="quick-order-page-header"><button id="quickOrderBack"></button><div><strong id="quickOrderPageTitle">ثبت سفارش</strong><span class="hidden" id="quickOrderHeaderContext"><b id="quickOrderSelectedTableName"></b><small id="quickOrderSelectedTableMeta"></small></span></div><button class="hidden" id="quickOrderChangeTable">تغییر میز</button></header>
<main>
<section id="quickOrderTableStage"><header><div><h1 id="quickOrderTableTitle">انتخاب میز</h1><p></p></div><span id="quickOrderTableCount"></span></header><div id="quickOrderTableGroups"></div></section>
<section class="hidden" id="quickOrderWorkspace">
<input id="quickOrderTable" type="hidden">
<div class="quick-order-draft-status hidden" id="quickOrderDraftStatus"><span><strong>پیش‌نویس مشترک</strong><small id="quickOrderDraftStatusText"></small></span><button id="quickOrderDraftCancel"></button></div>
<button class="hidden" id="quickOrderMobilePendingBanner"><span><strong id="quickOrderMobilePendingCount"></strong></span></button>
<div class="hidden" id="quickOrderUncertainNotice"></div>
<nav class="hidden" id="quickOrderMenus"></nav>
<div class="quick-order-layout">
<nav class="quick-order-category-pane"><div><button id="quickOrderCategorySearch"></button></div><div id="quickOrderCategories"></div></nav>
<section class="quick-order-catalog-pane"><div><button id="quickOrderCategoryBack"></button><strong id="quickOrderCategoryTitle"></strong><button id="quickOrderSearchToggle"></button></div><label class="hidden" id="quickOrderSearchWrap"><input id="quickOrderSearch"><button id="quickOrderSearchClear"></button></label><div id="quickOrderItems"></div></section>
<aside id="quickOrderCart"><header class="quick-order-cart-head"><div><h2 id="quickOrderCartTitle"></h2></div><div><button class="hidden" id="quickOrderTakeawayTool"></button><details class="quick-order-cart-more"><summary></summary><div><button data-qo-global-note><span></span></button><button data-qo-clear-proxy><span></span></button></div></details><button id="quickOrderClear"></button><button id="quickOrderCartClose"></button></div></header>
<div><section class="hidden" id="quickOrderPending"></section><details class="hidden" id="quickOrderCurrentAccount"><summary><strong id="quickOrderCurrentTotal"></strong><b id="quickOrderCurrentCount"></b></summary><div id="quickOrderCurrentLines"></div></details><section class="hidden" id="quickOrderTakeawayMode"><button id="quickOrderTakeawayDone"></button><button id="quickOrderTakeawayAll"></button></section><div id="quickOrderCartLines"></div><div class="quick-order-note"><button id="quickOrderNoteToggle"><span></span></button><label class="hidden" id="quickOrderNoteWrap"><textarea id="quickOrderNote"></textarea></label></div></div>
<footer><div><div><small class="hidden" id="quickOrderFulfillmentSummary"></small><strong id="quickOrderTotal"></strong></div><div class="hidden" id="quickOrderPreviousRow"><strong id="quickOrderPreviousTotal"></strong></div><div class="hidden" id="quickOrderProjectedRow"><strong id="quickOrderProjectedTotal"></strong></div></div><button id="quickOrderSubmit"></button></footer>
</aside></div>
<button class="hidden" id="quickOrderCartBackdrop"></button><button id="quickOrderMobileCartBar"><span><strong id="quickOrderMobileCartCount"></strong><small><span id="quickOrderMobileCartTotal"></span></small></span></button>
</section></main></div><div id="panelToast" class="hidden"></div>
'''

def boot(browser,user_key):
    page=browser.new_page(viewport={'width':1280,'height':800})
    page.route('https://sokna.test/**',route_handler)
    page.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>.hidden{{display:none!important}}</style></head><body>{markup}<script>
window.STAFF_QUICK_ORDER_API='https://sokna.test/quick';
window.STAFF_TABLE_DRAFT_API='https://sokna.test/draft';
window.OPERATOR_STATUS_API='https://sokna.test/status';
window.QUICK_ORDER_USER_KEY={json.dumps(str(user_key))};
window.SOKNA_ICON_SPRITE='/sprite.svg';
window.CafeUI={{confirm:()=>Promise.resolve(false),toast:()=>{{}}}};
</script></body></html>''')
    page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {get length(){return m.size},getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})")
    page.add_script_tag(content=policy)
    page.add_script_tag(content=quick_js)
    page.wait_for_selector('[data-qo-add="101"]')
    return page

def qty_text(page):
    return page.locator('#quickOrderCartLines .quick-order-inline-qty span').first.inner_text()

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])

    first=boot(browser,7)
    first.locator('[data-qo-add="101"]').click()
    first.wait_for_timeout(500)
    assert server['draft'] and server['draft']['version']==1, server
    assert server['draft']['items'][0]['quantity']==1, server['draft']
    assert '۱' in qty_text(first)
    assert first.evaluate("sessionStorage.length") == 0, 'normal Table Draft must not use sessionStorage as authority'

    second=boot(browser,8)
    second.wait_for_timeout(120)
    assert '۱' in qty_text(second), qty_text(second)
    assert 'نسخه' in second.locator('#quickOrderDraftStatusText').inner_text()

    plus_first=first.locator('#quickOrderItems [data-qo-delta="1"][data-id="101"]')
    plus_first.click(); plus_first.click()
    first.wait_for_timeout(500)
    assert server['draft']['version']==2, server['draft']
    assert server['draft']['items'][0]['quantity']==3, server['draft']

    second.locator('#quickOrderItems [data-qo-delta="1"][data-id="101"]').click()
    second.wait_for_timeout(650)
    assert server['draft']['version']==2, 'stale editor must not advance server version'
    assert server['draft']['items'][0]['quantity']==3, 'stale editor must not overwrite newer quantity'
    assert [r['expected'] for r in server['requests']][-3:]==[0,1,1], server['requests']
    assert '۳' in qty_text(second), qty_text(second)
    assert second.evaluate("sessionStorage.length") == 0, 'shared normal draft must remain server-owned after conflict'

    first.close(); second.close(); browser.close()

print('Phase 6C browser PASS: two staff contexts share one server draft; stale version is rejected and refreshed without browser-owned normal draft state.')
