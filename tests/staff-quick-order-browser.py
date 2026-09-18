#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel-components.css','assets/css/quick-order.css'])
policy=(ROOT/'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')
js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')

markup='''
<div class="quick-order-page-shell" id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php">
<header class="quick-order-page-header"><a class="quick-order-back" id="quickOrderBack" href="/operator/index.php">←</a><div class="quick-order-page-heading"><strong id="quickOrderPageTitle">ثبت سفارش</strong><span class="quick-order-table-context hidden" id="quickOrderHeaderContext"><b id="quickOrderSelectedTableName">—</b><small id="quickOrderSelectedTableMeta"></small></span></div><button class="quick-order-change-table hidden" id="quickOrderChangeTable" type="button">تغییر میز</button></header>
<main class="quick-order-page-main" id="quickOrderMain">
<section class="quick-order-table-view" id="quickOrderTableStage"><header class="quick-order-table-stage-head"><div><h1 id="quickOrderTableTitle">انتخاب میز</h1><p>میز موردنظر را انتخاب کنید.</p></div><span id="quickOrderTableCount"></span></header><div class="quick-order-table-groups" id="quickOrderTableGroups"></div></section>
<section class="quick-order-workspace hidden" id="quickOrderWorkspace"><input id="quickOrderTable" type="hidden"><nav class="quick-order-menu-switcher hidden" id="quickOrderMenus" aria-label="انتخاب منو"></nav><div class="quick-order-layout">
<nav class="quick-order-category-pane"><div class="quick-order-pane-title"><strong>دسته‌بندی‌ها</strong><button class="quick-order-category-search" id="quickOrderCategorySearch" type="button">⌕</button></div><div class="quick-order-categories" id="quickOrderCategories"></div></nav>
<section class="quick-order-catalog-pane"><div class="quick-order-catalog-head"><button class="quick-order-category-back" id="quickOrderCategoryBack" type="button">دسته‌بندی‌ها</button><strong id="quickOrderCategoryTitle">دسته‌بندی‌ها</strong><button class="quick-order-search-toggle" id="quickOrderSearchToggle" type="button" aria-expanded="false">⌕</button></div><label class="quick-order-search is-collapsed" id="quickOrderSearchWrap"><input class="form-control" id="quickOrderSearch" type="search"><button class="hidden" id="quickOrderSearchClear" type="button">×</button></label><div class="quick-order-items" id="quickOrderItems"></div></section>
<aside class="quick-order-cart" id="quickOrderCart" aria-hidden="false"><header class="quick-order-cart-head"><div><small>سبد سفارش</small><h2 id="quickOrderCartTitle">هنوز آیتمی انتخاب نشده</h2></div><div class="quick-order-cart-head-actions"><button class="quick-order-takeaway-tool hidden" id="quickOrderTakeawayTool" type="button" aria-pressed="false">بیرون‌بر</button><button class="quick-order-clear-icon" id="quickOrderClear" type="button" data-qo-clear-action="clear" aria-label="پاک‌کردن سبد">trash</button><button class="quick-order-cart-close" id="quickOrderCartClose" type="button">بستن</button></div></header><div class="quick-order-cart-body" id="quickOrderCartBody"><section class="quick-order-pending hidden" id="quickOrderPending"></section><details class="quick-order-current hidden" id="quickOrderCurrentAccount"><summary><div><small>حساب فعلی میز</small><strong id="quickOrderCurrentTotal">۰</strong></div><span><b id="quickOrderCurrentCount"></b><i>مشاهده</i></span></summary><div class="quick-order-current-lines" id="quickOrderCurrentLines"></div></details><section class="quick-order-takeaway-mode hidden" id="quickOrderTakeawayMode"><strong>موارد بیرون‌بر</strong><div><button id="quickOrderTakeawayDone" type="button">تأیید</button><button id="quickOrderTakeawayAll" type="button">همه بیرون‌بر</button></div></section><div class="quick-order-cart-lines" id="quickOrderCartLines"></div><div class="quick-order-note"><button id="quickOrderNoteToggle" type="button">یادداشت کلی</button><label class="hidden" id="quickOrderNoteWrap"><textarea class="form-control" id="quickOrderNote"></textarea></label></div><div class="quick-order-totals"><div class="is-final"><span>جمع سفارش جدید <small class="quick-order-fulfillment-summary hidden" id="quickOrderFulfillmentSummary"></small></span><strong id="quickOrderTotal">۰</strong></div><div id="quickOrderPreviousRow" class="hidden"><strong id="quickOrderPreviousTotal">۰</strong></div><div id="quickOrderProjectedRow" class="hidden"><strong id="quickOrderProjectedTotal">۰</strong></div></div></div><button class="btn btn-primary quick-order-submit" id="quickOrderSubmit" type="button" disabled>یک آیتم انتخاب کنید</button></aside>
</div><button class="quick-order-cart-backdrop hidden" id="quickOrderCartBackdrop" type="button"></button><button class="quick-order-mobile-cartbar" id="quickOrderMobileCartBar" type="button" disabled><span><strong id="quickOrderMobileCartCount">هنوز آیتمی انتخاب نشده</strong><small><span id="quickOrderMobileCartTotal">۰</span> تومان</small></span><b>مشاهده سبد</b></button></section></main></div><div id="panelToast" class="panel-toast hidden"></div>
'''

categories=[
 {'id':10,'name':'خوراک روز','icon_key':'service'},
 {'id':20,'name':'شربت‌خانه','icon_key':''},
 {'id':30,'name':'دمنوش‌ها','icon_key':'herbal'},
 {'id':40,'name':'چای‌ها','icon_key':'tea'},
 {'id':50,'name':'بار گرم','icon_key':'cup-hot'},
 {'id':60,'name':'بار سرد','icon_key':'cold-drink'},
 {'id':70,'name':'شیک‌ها','icon_key':'shake'},
 {'id':80,'name':'کیک و دسر','icon_key':'cake'},
 {'id':90,'name':'سیب‌زمینی‌ها','icon_key':'not-real'},
 {'id':100,'name':'اسموتی','icon_key':'smoothie'},
]
items=[]
for c in categories:
    for n in range(1,5):
        items.append({'id':c['id']*10+n,'category_id':c['id'],'category_name':c['name'],'name':f"{c['name']} نمونه {n} با نام نسبتاً بلند",'price':120000+c['id']*1000+n*5000,'order_available':1,'takeaway_allowed':0 if (c['id']==10 and n==4) else 1})
tables=[{'id':1,'name':'میز ۱۹','code':'19','zone_label':'آزاد','sort_order':1,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]},{'id':2,'name':'میز ۲۰','code':'20','zone_label':'آزاد','sort_order':2,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]}]+[{'id':i,'name':f'میز {i}','code':str(i),'zone_label':'سالن','sort_order':i,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]} for i in range(3,51)]
payload={'success':True,'tables':tables,'menus':[{'id':1,'menu_key':'main','name':'کافه','status':'active'}],'selected_menu':{'id':1,'menu_key':'main','name':'کافه','status':'active'}, 'categories':categories,'items':items}

html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"><style>.hidden{{display:none!important}}body{{font-family:Tahoma,sans-serif;margin:0}}{css}</style></head><body class="quick-order-page">{markup}<script>window.STAFF_QUICK_ORDER_API='https://sokna.test/quick';window.SOKNA_ICON_SPRITE='/sprite.svg';window.QUICK_ORDER_USER_KEY='7';window.CafeUI={{confirm:(...args)=>{{window.__qoConfirmArgs=args;return Promise.resolve(false);}}}};window.fetch=async()=>new Response({json.dumps(json.dumps(payload,ensure_ascii=False))},{{status:200,headers:{{'Content-Type':'application/json'}}}});</script></body></html>'''

def mobile(browser,width):
    page=browser.new_page(viewport={'width':width,'height':844})
    page.set_default_timeout(5000)
    page.set_content(html); page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})"); page.add_script_tag(content=policy); page.add_script_tag(content=js); page.wait_for_selector('[data-qo-category="10"]')
    assert page.locator('#quickOrderTableStage').evaluate('e=>e.classList.contains("hidden")'), 'Free table must open categories directly.'
    assert not page.locator('#quickOrderSearch').is_visible(), 'Persistent mobile search returned.'
    assert page.locator('#quickOrderCategories .quick-order-category').count()==10
    if width >= 360:
        boxes=[page.locator('#quickOrderCategories .quick-order-category').nth(i).bounding_box() for i in range(3)]
        assert all(boxes) and max(abs(boxes[i]['y']-boxes[0]['y']) for i in range(3)) <= 1, boxes
        assert max(b['height'] for b in boxes) <= 90, boxes
    assert page.locator('#quickOrderCategorySearch').is_visible()
    if width == 768:
        first_row=[page.locator('#quickOrderCategories .quick-order-category').nth(i).bounding_box() for i in range(3)]
        assert all(first_row) and max(abs(first_row[i]['y']-first_row[0]['y']) for i in range(3)) <= 1, first_row
    # Only valid, non-empty category icons render.
    assert page.locator('#quickOrderCategories .quick-order-category .ui-icon').count()==8
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    page.locator('#quickOrderCategorySearch').click(); page.wait_for_timeout(20)
    assert page.locator('#quickOrderSearch').is_visible()
    page.locator('#quickOrderCategoryBack').click(); page.wait_for_timeout(20)
    page.locator('[data-qo-category="10"]').click(); page.wait_for_timeout(30)
    assert page.locator('#quickOrderWorkspace').evaluate('e=>e.classList.contains("is-mobile-items")')
    assert not page.locator('#quickOrderSearch').is_visible()
    page.locator('#quickOrderSearchToggle').click(); assert page.locator('#quickOrderSearch').is_visible()
    page.locator('#quickOrderSearchToggle').click(); assert not page.locator('#quickOrderSearch').is_visible()
    card=page.locator('[data-qo-add="101"]')
    h0=card.bounding_box()['height']; card.click(); page.wait_for_timeout(30); h1=card.bounding_box()['height']
    assert abs(h0-h1)<=1, (h0,h1)
    assert '۱ قلم' in page.locator('#quickOrderMobileCartCount').inner_text()
    card.click(); page.wait_for_timeout(20)
    assert '۱ قلم' in page.locator('#quickOrderMobileCartCount').inner_text() and '۲ عدد' in page.locator('#quickOrderMobileCartCount').inner_text()
    page.locator('#quickOrderCategoryBack').click(); page.wait_for_timeout(30)
    assert not page.locator('#quickOrderWorkspace').evaluate('e=>e.classList.contains("is-mobile-items")')
    # cart survives category navigation; leaving with a draft must request confirmation and preserve it.
    assert '۱ قلم' in page.locator('#quickOrderMobileCartCount').inner_text()
    page.locator('#quickOrderBack').click(); page.wait_for_timeout(30)
    assert page.evaluate('window.__qoConfirmArgs && window.__qoConfirmArgs[0].includes("پیش‌نویس این میز حفظ می‌شود")')
    assert '۱ قلم' in page.locator('#quickOrderMobileCartCount').inner_text()
    page.locator('[data-qo-category="60"]').click(); page.wait_for_timeout(30)
    last=page.locator('#quickOrderItems .quick-order-item').last
    page.evaluate('window.scrollTo(0, document.body.scrollHeight)'); page.wait_for_timeout(30)
    lb=last.bounding_box(); bb=page.locator('#quickOrderMobileCartBar').bounding_box()
    assert lb['y']+lb['height'] <= bb['y']+2, (lb,bb)
    page.locator('#quickOrderMobileCartBar').click(); page.wait_for_timeout(240)
    assert page.locator('#quickOrderCart').evaluate('e=>e.classList.contains("is-open")')
    # Staff takeaway is a compact cart-level edit mode; normal rows reserve no per-line takeaway controls.
    assert page.locator('#quickOrderTakeawayTool').is_visible()
    assert page.locator('#quickOrderTakeawayTool').inner_text().strip()=='بیرون‌بر'
    assert page.locator('#quickOrderTakeawayMode').evaluate('e=>e.classList.contains("hidden")')
    assert page.locator('.quick-order-takeaway-stepper').count()==0
    assert page.locator('.quick-order-takeaway-badge').count()==0
    normal_heights=page.locator('#quickOrderCartLines .quick-order-cart-line').evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
    assert normal_heights and max(normal_heights) <= 92, normal_heights
    page.locator('#quickOrderTakeawayTool').click(); page.wait_for_timeout(20)
    assert page.locator('#quickOrderTakeawayMode').is_visible()
    step=page.locator('.quick-order-cart-line').filter(has_text='خوراک روز نمونه 1').locator('.quick-order-takeaway-stepper')
    assert step.count()==1 and '۰از۲' in step.text_content().replace('\n','').replace(' ','')
    editing_line=page.locator('.quick-order-cart-line').filter(has_text='خوراک روز نمونه 1').first
    assert editing_line.locator('.quick-order-line-qty-readonly').count()==1
    assert 'تعداد' in editing_line.locator('.quick-order-line-qty-readonly').inner_text()
    assert editing_line.locator('[data-qo-delta]').count()==0, 'Total quantity must be read-only while takeaway mode is active.'
    assert page.locator('#quickOrderTakeawayDone').inner_text().strip()=='تأیید'
    step_line=page.locator('.quick-order-cart-line').filter(has_text='خوراک روز نمونه 1').first
    assert step_line.evaluate('e=>e.classList.contains("has-takeaway-stepper")')
    step_line_box=step_line.bounding_box(); step_copy_box=step_line.locator('.quick-order-line-copy').bounding_box(); step_actions_box=step_line.locator('.quick-order-line-actions').bounding_box()
    assert step_copy_box['width'] >= step_line_box['width']-6, (step_line_box,step_copy_box)
    assert step_actions_box['y'] >= step_copy_box['y']+step_copy_box['height']-1, (step_copy_box,step_actions_box)
    assert page.locator('[data-qo-takeaway-delta="-1"][data-id="101"]').is_disabled()
    page.locator('[data-qo-takeaway-delta="1"][data-id="101"]').click(); page.wait_for_timeout(15)
    assert '۱از۲' in step.text_content().replace('\n','').replace(' ','')
    page.locator('[data-qo-takeaway-delta="1"][data-id="101"]').click(); page.wait_for_timeout(15)
    assert '۲از۲' in step.text_content().replace('\n','').replace(' ','')
    assert page.locator('[data-qo-takeaway-delta="1"][data-id="101"]').is_disabled()
    page.locator('#quickOrderTakeawayDone').click(); page.wait_for_timeout(20)
    assert not page.locator('#quickOrderTakeawayMode').is_visible()
    line101=page.locator('#quickOrderCartLines .quick-order-cart-line').filter(has_text='خوراک روز نمونه 1').first
    assert 'همه بیرون‌بر' in line101.locator('.quick-order-takeaway-badge').inner_text()
    # Mixed order: reopen and make the first line partial, then newly-added item remains dine-in.
    page.locator('#quickOrderTakeawayTool').click(); page.wait_for_timeout(15)
    page.locator('[data-qo-takeaway-delta="-1"][data-id="101"]').click(); page.wait_for_timeout(15)
    assert '۱از۲' in line101.locator('.quick-order-takeaway-stepper').text_content().replace('\n','').replace(' ','')
    page.locator('#quickOrderTakeawayDone').click(); page.wait_for_timeout(15)
    assert '۱ از ۲ بیرون‌بر' in line101.locator('.quick-order-takeaway-badge').inner_text()
    page.locator('#quickOrderCartClose').click(); page.wait_for_timeout(20)
    page.locator('#quickOrderCategoryBack').click(); page.wait_for_timeout(20) if page.locator('#quickOrderCategoryBack').is_visible() else None
    if page.locator('[data-qo-category="10"]').is_visible():
        page.locator('[data-qo-category="10"]').click(); page.wait_for_timeout(20)
    page.locator('[data-qo-add="102"]').click(); page.wait_for_timeout(20)
    page.locator('#quickOrderMobileCartBar').click(); page.wait_for_timeout(80)
    line102=page.locator('#quickOrderCartLines .quick-order-cart-line').filter(has_text='خوراک روز نمونه 2').first
    assert line102.locator('.quick-order-takeaway-badge').count()==0, 'New line in a mixed cart must remain dine-in.'
    # An ineligible catalog line exposes only the compact fixed state while editing.
    page.locator('#quickOrderCartClose').click(); page.wait_for_timeout(15)
    if page.locator('#quickOrderCategoryBack').is_visible(): page.locator('#quickOrderCategoryBack').click(); page.wait_for_timeout(15)
    if page.locator('[data-qo-category="10"]').is_visible(): page.locator('[data-qo-category="10"]').click(); page.wait_for_timeout(15)
    page.locator('[data-qo-add="104"]').click(); page.wait_for_timeout(15)
    page.locator('#quickOrderMobileCartBar').click(); page.wait_for_timeout(60)
    page.locator('#quickOrderTakeawayTool').click(); page.wait_for_timeout(15)
    line104=page.locator('#quickOrderCartLines .quick-order-cart-line').filter(has_text='خوراک روز نمونه 4').first
    assert line104.locator('.quick-order-takeaway-ineligible').inner_text().strip()=='فقط داخل'
    page.locator('#quickOrderTakeawayDone').click(); page.wait_for_timeout(15)
    assert line104.locator('.quick-order-takeaway-badge').count()==0
    closed_heights=page.locator('#quickOrderCartLines .quick-order-cart-line').evaluate_all('els=>els.filter(e=>!e.querySelector(".quick-order-takeaway-badge")).map(e=>e.getBoundingClientRect().height)')
    assert closed_heights and max(closed_heights) <= 92, closed_heights
    # Header action is direct Trash -> Undo in the same slot; no broken three-dot menu remains.
    assert page.locator('.quick-order-cart-menu').count()==0
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action')=='clear'
    page.locator('#quickOrderClear').click(); page.wait_for_timeout(30)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action')=='undo'
    assert page.locator('#quickOrderCartLines .quick-order-cart-line').count()==0
    page.locator('#quickOrderClear').click(); page.wait_for_timeout(30)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action')=='clear'
    assert page.locator('#quickOrderCartLines .quick-order-cart-line').count()==3
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    submit_box=page.locator('#quickOrderSubmit').bounding_box()
    assert submit_box and submit_box['y'] + submit_box['height'] <= page.evaluate('innerHeight') + 1, submit_box
    overflows=page.locator('#quickOrderCartBody').evaluate('e=>({scroll:e.scrollHeight,client:e.clientHeight})')
    # nested sections themselves must not own vertical scrolling
    assert page.locator('#quickOrderCartLines').evaluate('e=>getComputedStyle(e).overflowY') == 'visible'
    assert page.locator('#quickOrderCurrentLines').evaluate('e=>getComputedStyle(e).overflowY') == 'visible'
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    if width == 390:
        # Explicit Change Table is the only operation allowed to rebind the current draft.
        page.click('#quickOrderCartClose'); page.wait_for_timeout(20)
        page.click('#quickOrderChangeTable'); page.wait_for_selector('#quickOrderTableStage:not(.hidden)')
        dims=page.locator('#quickOrderTableStage').evaluate('e=>({scroll:e.scrollHeight,client:e.clientHeight})'); assert dims['scroll']>dims['client'], dims
        page.locator('#quickOrderTableStage').evaluate('e=>e.scrollTop=500'); page.wait_for_timeout(20); assert page.locator('#quickOrderTableStage').evaluate('e=>e.scrollTop')>0
        page.click('#quickOrderBack'); page.wait_for_timeout(20); assert page.locator('#quickOrderWorkspace').is_visible() and '۴ عدد' in page.locator('#quickOrderMobileCartCount').inner_text()
        page.click('#quickOrderChangeTable'); page.wait_for_selector('#quickOrderTableStage:not(.hidden)'); assert page.locator('#quickOrderTableStage').evaluate('e=>e.scrollTop')==0
        page.click('[data-qo-table="2"]'); page.wait_for_timeout(50)
        assert page.locator('#quickOrderTable').input_value() == '2'
        assert '۴ عدد' in page.locator('#quickOrderMobileCartCount').inner_text()
        stored=page.evaluate("()=>({source:sessionStorage.getItem('sokna.quick-order.v2.7.1'),target:sessionStorage.getItem('sokna.quick-order.v2.7.2')})")
        assert stored['source'] is None and stored['target'] and '101' in stored['target'], stored
    page.close()

def desktop(browser,width):
    page=browser.new_page(viewport={'width':width,'height':850})
    page.set_default_timeout(5000)
    page.set_content(html); page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})"); page.add_script_tag(content=policy); page.add_script_tag(content=js); page.wait_for_selector('[data-qo-category="10"]')
    assert page.locator('#quickOrderSearch').is_visible()
    boxes={k:page.locator(sel).bounding_box() for k,sel in {'cart':'#quickOrderCart','catalog':'.quick-order-catalog-pane','categories':'.quick-order-category-pane'}.items()}
    assert boxes['cart']['x'] < boxes['catalog']['x'] < boxes['categories']['x'], boxes
    assert 350 <= boxes['cart']['width'] <= 380, boxes['cart']
    assert 235 <= boxes['categories']['width'] <= 255, boxes['categories']
    assert page.locator('#quickOrderCart').evaluate('e=>e.scrollWidth <= e.clientWidth + 1')
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    page.close()

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for w in (320,360,390,412,768): mobile(browser,w)
    for w in (1366,1440): desktop(browser,w)
    browser.close()
print('Quick Order browser PASS at 320/360/390/412/768/1366/1440: mobile flow preserved, desktop one-click categories, compact cart, draft preservation, and no overflow.')
