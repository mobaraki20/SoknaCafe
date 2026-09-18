#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel-components.css','assets/css/quick-order.css'])
policy=(ROOT/'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')
js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')

categories=[{'id':i,'name':name,'icon_key':''} for i,name in enumerate([
    'نوشیدنی گرم','نوشیدنی سرد','صبحانه','غذا','پیش غذا','کیک و دسر','دمنوش‌ها','شیک و اسموتی','سالاد','ساندویچ','قهوه','چای','خدمات','سایر'
],1)]
items=[]
for c in categories:
    for n in range(1,4):
        items.append({'id':c['id']*100+n,'category_id':c['id'],'category_name':c['name'],'name':f"{c['name']} نمونه {n}",'price':100000+c['id']*5000+n*1000,'order_available':1,'takeaway_allowed':1})
tables=[{'id':1,'name':'میز ۶','code':'6','zone_label':'سالن اصلی','sort_order':1,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]}]
payload={'success':True,'tables':tables,'menus':[{'id':1,'menu_key':'main','name':'کافه','status':'active'}],'selected_menu':{'id':1,'menu_key':'main','name':'کافه','status':'active'},'categories':categories,'items':items}

markup='''
<div class="quick-order-page-shell" id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php">
<header class="quick-order-page-header"><a class="quick-order-back" id="quickOrderBack" href="/operator/index.php">←</a><div class="quick-order-page-heading"><strong id="quickOrderPageTitle">ثبت سفارش</strong><span class="quick-order-table-context hidden" id="quickOrderHeaderContext"><b id="quickOrderSelectedTableName">—</b><small id="quickOrderSelectedTableMeta"></small></span></div><button class="quick-order-change-table hidden" id="quickOrderChangeTable" type="button">تغییر میز</button></header>
<main class="quick-order-page-main"><section class="quick-order-table-view" id="quickOrderTableStage"><header class="quick-order-table-stage-head"><div><h1 id="quickOrderTableTitle">انتخاب میز</h1></div><span id="quickOrderTableCount"></span></header><div class="quick-order-table-groups" id="quickOrderTableGroups"></div></section>
<section class="quick-order-workspace hidden" id="quickOrderWorkspace"><nav class="quick-order-menu-switcher hidden" id="quickOrderMenus" aria-label="انتخاب منو"></nav><input id="quickOrderTable" type="hidden"><button class="quick-order-mobile-pending-banner hidden" id="quickOrderMobilePendingBanner" type="button"><span><strong id="quickOrderMobilePendingCount"></strong></span><b>بررسی</b></button><div class="quick-order-uncertain hidden" id="quickOrderUncertainNotice"></div><div class="quick-order-layout">
<nav class="quick-order-category-pane"><div class="quick-order-pane-title"><strong>دسته‌بندی‌ها</strong><button class="quick-order-category-search" id="quickOrderCategorySearch" type="button">⌕</button></div><div class="quick-order-categories" id="quickOrderCategories"></div></nav>
<section class="quick-order-catalog-pane"><div class="quick-order-catalog-head"><button class="quick-order-category-back" id="quickOrderCategoryBack" type="button">دسته‌بندی‌ها</button><strong id="quickOrderCategoryTitle">دسته‌بندی‌ها</strong><button class="quick-order-search-toggle" id="quickOrderSearchToggle" type="button" aria-expanded="false">⌕</button></div><label class="quick-order-search is-collapsed" id="quickOrderSearchWrap"><input class="form-control" id="quickOrderSearch" type="search"><button class="hidden" id="quickOrderSearchClear" type="button">×</button></label><div class="quick-order-items" id="quickOrderItems"></div></section>
<aside class="quick-order-cart" id="quickOrderCart" aria-hidden="false"><header class="quick-order-cart-head"><div><small>سبد سفارش</small><h2 id="quickOrderCartTitle">هنوز آیتمی انتخاب نشده</h2></div><div class="quick-order-cart-head-actions"><button class="quick-order-takeaway-tool hidden" id="quickOrderTakeawayTool" type="button" aria-pressed="false">بیرون‌بر</button><details class="quick-order-cart-more"><summary aria-label="گزینه‌های بیشتر سبد">⋯</summary><div class="quick-order-cart-more-menu"><button type="button" data-qo-global-note><span>افزودن یادداشت کلی</span></button><button type="button" data-qo-clear-proxy><span>پاک‌کردن سبد</span></button></div></details><button class="quick-order-clear-icon" id="quickOrderClear" type="button" data-qo-clear-action="clear">پاک</button><button class="quick-order-cart-close" id="quickOrderCartClose" type="button">بستن</button></div></header>
<div class="quick-order-cart-body" id="quickOrderCartBody"><section class="quick-order-pending hidden" id="quickOrderPending"></section><details class="quick-order-current hidden" id="quickOrderCurrentAccount"><summary><div><small>حساب فعلی</small><strong id="quickOrderCurrentTotal">۰</strong></div><span><b id="quickOrderCurrentCount"></b></span></summary><div class="quick-order-current-lines" id="quickOrderCurrentLines"></div></details><section class="quick-order-takeaway-mode hidden" id="quickOrderTakeawayMode"><strong>موارد بیرون‌بر</strong><div><button id="quickOrderTakeawayDone">تأیید</button><button id="quickOrderTakeawayAll">همه</button></div></section><div class="quick-order-cart-lines" id="quickOrderCartLines"></div><div class="quick-order-note"><button class="quick-order-note-toggle" id="quickOrderNoteToggle" type="button" aria-expanded="false"><span>یادداشت کلی</span></button><label class="hidden" id="quickOrderNoteWrap"><textarea id="quickOrderNote"></textarea></label></div></div>
<footer class="quick-order-cart-footer"><div class="quick-order-totals"><div class="is-final"><span>جمع سفارش جدید <small class="quick-order-fulfillment-summary hidden" id="quickOrderFulfillmentSummary"></small></span><strong id="quickOrderTotal">۰</strong></div><div id="quickOrderPreviousRow" class="hidden"><strong id="quickOrderPreviousTotal">۰</strong></div><div id="quickOrderProjectedRow" class="hidden"><strong id="quickOrderProjectedTotal">۰</strong></div></div><button class="quick-order-submit" id="quickOrderSubmit" type="button" disabled>ثبت</button></footer></aside></div>
<button class="quick-order-cart-backdrop hidden" id="quickOrderCartBackdrop" type="button"></button><button class="quick-order-mobile-cartbar" id="quickOrderMobileCartBar" type="button" disabled><span><strong id="quickOrderMobileCartCount"></strong><small><span id="quickOrderMobileCartTotal">۰</span></small></span><b>سبد</b></button></section></main></div><div id="panelToast" class="panel-toast hidden"></div>
'''
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>.hidden{{display:none!important}}body{{margin:0;font-family:Tahoma}}{css}</style></head><body class="quick-order-page">{markup}<script>window.STAFF_QUICK_ORDER_API='https://sokna.test/quick';window.QUICK_ORDER_USER_KEY='7';window.SOKNA_ICON_SPRITE='/sprite.svg';window.CafeUI={{confirm:()=>Promise.resolve(false)}};window.fetch=async()=>new Response({json.dumps(json.dumps(payload,ensure_ascii=False))},{{status:200,headers:{{'Content-Type':'application/json'}}}});</script></body></html>'''

def boot(browser,width,height=844):
    page=browser.new_page(viewport={'width':width,'height':height})
    page.set_default_timeout(4000)
    page.set_content(html)
    page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})")
    page.add_script_tag(content=policy); page.add_script_tag(content=js)
    page.wait_for_selector('[data-qo-category="1"]')
    return page

def desktop(browser,width):
    page=boot(browser,width,850)
    assert page.locator('#quickOrderSearch').is_visible()
    assert page.locator('#quickOrderCategories .quick-order-category').count()==14
    boxes={k:page.locator(sel).bounding_box() for k,sel in {'cart':'#quickOrderCart','catalog':'.quick-order-catalog-pane','categories':'.quick-order-category-pane'}.items()}
    assert boxes['cart']['x'] < boxes['catalog']['x'] < boxes['categories']['x'], boxes
    assert 350 <= boxes['cart']['width'] <= 380, boxes['cart']
    assert 235 <= boxes['categories']['width'] <= 255, boxes['categories']
    # category selection is direct and title tracks the active category
    page.locator('[data-qo-category="2"]').click(); page.wait_for_timeout(20)
    assert 'نوشیدنی سرد' in page.locator('#quickOrderCategoryTitle').inner_text()
    # add an item; direct note action is one click and empty global-note UI stays absent
    page.locator('[data-qo-add="201"]').click(); page.wait_for_timeout(20)
    line=page.locator('#quickOrderCartLines .quick-order-cart-line').first
    assert line.is_visible() and line.locator('[data-qo-note-toggle]').count()==1
    assert not line.locator('.quick-order-line-more-v13').count()
    assert page.locator('.quick-order-note').evaluate("e=>getComputedStyle(e).display")=='none'
    # cart header actions align optically; takeaway may become visible after cart content exists
    takeaway=page.locator('#quickOrderTakeawayTool'); more=page.locator('.quick-order-cart-more>summary')
    if takeaway.is_visible():
        tb=takeaway.bounding_box(); mb=more.bounding_box(); assert abs(tb['height']-mb['height']) <= 1, (tb,mb)
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    page.close()

def mobile(browser):
    page=boot(browser,390,844)
    assert not page.locator('#quickOrderSearch').is_visible()
    assert page.locator('#quickOrderCategorySearch').is_visible()
    assert page.locator('.quick-order-cart-more').evaluate("e=>getComputedStyle(e).display")=='none'
    assert page.locator('#quickOrderClear').is_visible()
    page.locator('[data-qo-category="1"]').click(); page.locator('[data-qo-add="101"]').click(); page.wait_for_timeout(20)
    assert page.locator('#quickOrderMobileCartBar').is_enabled()
    page.locator('#quickOrderMobileCartBar').click(); page.wait_for_timeout(20)
    assert page.locator('#quickOrderCart').evaluate("e=>e.classList.contains('is-open')")
    assert page.locator('#quickOrderCartLines [data-qo-note-toggle]').count()==1
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    page.close()

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    mobile(browser)
    desktop(browser,1366)
    desktop(browser,1920)
    browser.close()
print('Quick Order production browser PASS: 390 mobile behavior preserved; 1366/1920 desktop categories, compact cart, note action, alignment and overflow are stable.')
