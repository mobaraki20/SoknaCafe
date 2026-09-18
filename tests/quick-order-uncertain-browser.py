#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel-components.css','assets/css/quick-order.css'])
policy=(ROOT/'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')
js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')
payload={'success':True,'tables':[{'id':1,'name':'میز ۱','code':'1','zone_label':'سالن','sort_order':1,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]}], 'categories':[{'id':10,'name':'قهوه','icon_key':'coffee'}], 'items':[{'id':101,'category_id':10,'category_name':'قهوه','name':'لاته','price':120000,'order_available':1}]}
markup='''
<div class="quick-order-page-shell" id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php">
<header class="quick-order-page-header"><a class="quick-order-back" id="quickOrderBack" href="/operator/index.php">←</a><div class="quick-order-page-heading"><strong id="quickOrderPageTitle">ثبت سفارش</strong><span class="quick-order-table-context hidden" id="quickOrderHeaderContext"><b id="quickOrderSelectedTableName">—</b><small id="quickOrderSelectedTableMeta"></small></span></div><button class="quick-order-change-table hidden" id="quickOrderChangeTable" type="button">تغییر میز</button></header>
<main class="quick-order-page-main" id="quickOrderMain"><section class="quick-order-table-view" id="quickOrderTableStage"><header><h1 id="quickOrderTableTitle">انتخاب میز</h1><span id="quickOrderTableCount"></span></header><div id="quickOrderTableGroups"></div></section>
<section class="quick-order-workspace hidden" id="quickOrderWorkspace"><input id="quickOrderTable" type="hidden"><button class="quick-order-mobile-pending hidden" id="quickOrderMobilePendingBanner" type="button"><span id="quickOrderMobilePendingCount"></span></button><div class="quick-order-uncertain hidden" id="quickOrderUncertainNotice" role="status"><strong>نتیجه ثبت قبلی هنوز مشخص نیست.</strong><span>سبد تا تعیین نتیجه ثابت می‌ماند.</span></div><div class="quick-order-layout">
<nav class="quick-order-category-pane"><div id="quickOrderCategories"></div></nav><section class="quick-order-catalog-pane"><div><button id="quickOrderCategoryBack" type="button">دسته‌ها</button><strong id="quickOrderCategoryTitle"></strong><button id="quickOrderSearchToggle" type="button" aria-expanded="false">جست‌وجو</button></div><label class="quick-order-search is-collapsed" id="quickOrderSearchWrap"><input id="quickOrderSearch" type="search"><button class="hidden" id="quickOrderSearchClear" type="button">×</button></label><div id="quickOrderItems"></div></section>
<aside class="quick-order-cart" id="quickOrderCart" aria-hidden="false"><header><h2 id="quickOrderCartTitle"></h2><strong id="quickOrderCartHeadTotal"></strong><button id="quickOrderClear" type="button">حذف</button><button id="quickOrderCartClose" type="button">بستن</button></header><div class="quick-order-cart-body" id="quickOrderCartBody"><section class="quick-order-pending hidden" id="quickOrderPending"></section><details class="quick-order-current hidden" id="quickOrderCurrentAccount"><summary><strong id="quickOrderCurrentTotal"></strong><b id="quickOrderCurrentCount"></b></summary><div id="quickOrderCurrentLines"></div></details><div id="quickOrderCartLines"></div><button id="quickOrderNoteToggle" type="button" aria-expanded="false">یادداشت</button><label class="hidden" id="quickOrderNoteWrap"><textarea id="quickOrderNote"></textarea></label><strong id="quickOrderTotal"></strong><div id="quickOrderPreviousRow" class="hidden"><strong id="quickOrderPreviousTotal"></strong></div><div id="quickOrderProjectedRow" class="hidden"><strong id="quickOrderProjectedTotal"></strong></div></div><button id="quickOrderSubmit" type="button" disabled>ثبت</button></aside></div><button id="quickOrderCartBackdrop" type="button" class="hidden"></button><button id="quickOrderMobileCartBar" type="button" disabled><strong id="quickOrderMobileCartCount"></strong><span id="quickOrderMobileCartTotal"></span></button></section></main></div><div id="panelToast" class="hidden"></div>'''
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="csrf"><style>.hidden{{display:none!important}}{css}</style></head><body class="quick-order-page">{markup}<script>window.STAFF_QUICK_ORDER_API='/quick';window.SOKNA_ICON_SPRITE='/sprite.svg';window.QUICK_ORDER_USER_KEY='7';</script></body></html>'''
posts=[]
STORAGE_STUB="""(()=>{const make=(seed={})=>{const m=new Map(Object.entries(seed));return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}};Object.defineProperty(window,'sessionStorage',{value:make(),configurable:true});Object.defineProperty(window,'localStorage',{value:make(window.__LOCAL_SEED||{}),configurable:true});})()"""
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    # First load: submit reaches ambiguous network failure.
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(html)
    page.evaluate(STORAGE_STUB)
    page.evaluate(f"""window.__posts=[];window.fetch=async(url,opts={{}})=>{{if(!opts.method)return new Response({json.dumps(json.dumps(payload,ensure_ascii=False))},{{status:200,headers:{{'Content-Type':'application/json'}}}});window.__posts.push(JSON.parse(opts.body));throw new TypeError('Failed to fetch')}};""")
    page.add_script_tag(content=policy); page.add_script_tag(content=js); page.wait_for_selector('[data-qo-category="10"]')
    page.click('[data-qo-category="10"]'); page.click('[data-qo-add="101"]'); page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(30)
    page.click('#quickOrderSubmit'); page.wait_for_timeout(100)
    first_posts=page.evaluate('window.__posts'); assert len(first_posts)==1 and first_posts[0]['request_token']
    token=first_posts[0]['request_token']; assert first_posts[0].get('expected_session_id')==0
    assert page.locator('#quickOrderUncertainNotice').is_visible()
    assert 'بررسی و تلاش دوباره' in page.locator('#quickOrderSubmit').inner_text()
    stored=page.evaluate("localStorage.getItem('sokna.quick-order.uncertain.v2.7.1')")
    assert stored and token in stored
    page.close()
    # Simulated PWA reopen: local unresolved record is restored; retry sends same token.
    page=browser.new_page(viewport={'width':390,'height':844}); page.set_content(html)
    page.evaluate("v=>window.__LOCAL_SEED={'sokna.quick-order.uncertain.v2.7.1':v}", stored); page.evaluate(STORAGE_STUB)
    page.evaluate(f"""window.__posts=[];window.fetch=async(url,opts={{}})=>{{if(!opts.method)return new Response({json.dumps(json.dumps(payload,ensure_ascii=False))},{{status:200,headers:{{'Content-Type':'application/json'}}}});window.__posts.push(JSON.parse(opts.body));return new Response(JSON.stringify({{success:false,message:'known failure'}}),{{status:400,headers:{{'Content-Type':'application/json'}}}})}};""")
    page.add_script_tag(content=policy); page.add_script_tag(content=js); page.wait_for_selector('#quickOrderUncertainNotice',state='visible')
    assert 'لاته' in page.locator('#quickOrderCartLines').inner_text()
    page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(30)
    page.click('#quickOrderSubmit'); page.wait_for_timeout(100)
    second_posts=page.evaluate('window.__posts'); assert len(second_posts)==1 and second_posts[0]['request_token']==token and second_posts[0].get('expected_session_id')==0, (second_posts,token)
    page.close(); browser.close()
print('Quick Order uncertain-result browser passed: persistent unresolved state, simulated reopen recovery, same-token/session retry.')
