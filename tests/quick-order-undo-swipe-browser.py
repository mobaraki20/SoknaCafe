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
<main class="quick-order-page-main" id="quickOrderMain"><section class="quick-order-table-view" id="quickOrderTableStage"><header class="quick-order-table-stage-head"><div><h1 id="quickOrderTableTitle">انتخاب میز</h1><p>میز موردنظر را انتخاب کنید.</p></div><span id="quickOrderTableCount"></span></header><div class="quick-order-table-groups" id="quickOrderTableGroups"></div></section>
<section class="quick-order-workspace hidden" id="quickOrderWorkspace"><input id="quickOrderTable" type="hidden"><nav class="quick-order-menu-switcher hidden" id="quickOrderMenus" aria-label="انتخاب منو"></nav><button class="quick-order-mobile-pending-banner hidden" id="quickOrderMobilePendingBanner" type="button"><span><strong id="quickOrderMobilePendingCount"></strong><small></small></span><b>بررسی سفارش</b></button><div class="quick-order-uncertain hidden" id="quickOrderUncertainNotice"><strong></strong><span></span></div><div class="quick-order-layout">
<nav class="quick-order-category-pane"><div class="quick-order-pane-title"><strong>دسته‌بندی‌ها</strong></div><div class="quick-order-categories" id="quickOrderCategories"></div></nav>
<section class="quick-order-catalog-pane"><div class="quick-order-catalog-head"><button class="quick-order-category-back" id="quickOrderCategoryBack" type="button">دسته‌بندی‌ها</button><strong id="quickOrderCategoryTitle">دسته‌بندی‌ها</strong><button class="quick-order-search-toggle" id="quickOrderSearchToggle" type="button" aria-expanded="false">⌕</button></div><label class="quick-order-search is-collapsed" id="quickOrderSearchWrap"><input class="form-control" id="quickOrderSearch" type="search"><button class="hidden" id="quickOrderSearchClear" type="button">×</button></label><div class="quick-order-items" id="quickOrderItems"></div></section>
<aside class="quick-order-cart" id="quickOrderCart" aria-hidden="false"><header class="quick-order-cart-head"><div><small>سبد سفارش</small><h2 id="quickOrderCartTitle">هنوز آیتمی انتخاب نشده</h2><span class="quick-order-cart-total-head"><span>مبلغ کل</span><strong id="quickOrderCartHeadTotal">۰</strong></span></div><div class="quick-order-cart-head-actions"><details class="quick-order-cart-more"><summary aria-label="گزینه‌های بیشتر سبد" title="گزینه‌های بیشتر">⋯</summary><div class="quick-order-cart-more-menu"><button type="button" data-qo-clear-proxy><span>پاک‌کردن سبد</span></button></div></details><button class="quick-order-clear-icon" id="quickOrderClear" type="button" data-qo-clear-action="clear" aria-label="پاک‌کردن سبد" title="پاک‌کردن سبد"></button><button class="quick-order-cart-close" id="quickOrderCartClose" type="button">×</button></div></header><div class="quick-order-cart-body" id="quickOrderCartBody"><section class="quick-order-pending hidden" id="quickOrderPending"></section><details class="quick-order-current hidden" id="quickOrderCurrentAccount"><summary><div><small>حساب فعلی میز</small><strong id="quickOrderCurrentTotal">۰</strong></div><span><b id="quickOrderCurrentCount"></b><i>مشاهده</i></span></summary><div class="quick-order-current-lines" id="quickOrderCurrentLines"></div></details><div class="quick-order-cart-lines" id="quickOrderCartLines"></div><div class="quick-order-note"><button id="quickOrderNoteToggle" type="button" aria-expanded="false">یادداشت کلی</button><label class="hidden" id="quickOrderNoteWrap"><textarea class="form-control" id="quickOrderNote"></textarea></label></div><div class="quick-order-totals"><div><strong id="quickOrderTotal">۰</strong></div><div id="quickOrderPreviousRow" class="hidden"><strong id="quickOrderPreviousTotal">۰</strong></div><div id="quickOrderProjectedRow" class="hidden"><strong id="quickOrderProjectedTotal">۰</strong></div></div></div><button class="btn btn-primary quick-order-submit" id="quickOrderSubmit" type="button" disabled>ثبت</button></aside>
</div><button class="quick-order-cart-backdrop hidden" id="quickOrderCartBackdrop" type="button"></button><button class="quick-order-mobile-cartbar" id="quickOrderMobileCartBar" type="button" disabled><span><strong id="quickOrderMobileCartCount"></strong><small><span id="quickOrderMobileCartTotal"></span></small></span><b>مشاهده سبد</b></button></section></main></div><div id="panelToast" class="panel-toast hidden"></div>
'''

categories=[{'id':10,'name':'قهوه','icon_key':'coffee'},{'id':20,'name':'غذا','icon_key':'food'}]
items=[
 {'id':101,'category_id':10,'category_name':'قهوه','name':'آفوگاتو','price':245000,'order_available':1},
 {'id':102,'category_id':10,'category_name':'قهوه','name':'امریکانو — سایز بزرگ','price':185000,'order_available':1},
 {'id':103,'category_id':10,'category_name':'قهوه','name':'لاته','price':210000,'order_available':1},
] + [{'id':item_id,'category_id':10,'category_name':'قهوه','name':f'آیتم تست {item_id}','price':150000+item_id*100,'order_available':1} for item_id in range(104,116)]
payload={'success':True,'menus':[{'id':1,'menu_key':'main','name':'کافه','status':'active'}],'selected_menu':{'id':1,'menu_key':'main','name':'کافه','status':'active'},'tables':[{'id':1,'name':'میز ۲','code':'2','zone_label':'آزاد','sort_order':1,'is_open':False,'current_total':0,'current_final_total':0,'current_order_count':0,'discount_type':'','discount_value':0,'current_quantity':0,'current_items':[],'pending_order_count':0,'pending_orders':[]}], 'categories':categories,'items':items}
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"><style>.hidden{{display:none!important}}body{{font-family:Tahoma,sans-serif;margin:0}}{css}</style></head><body class="quick-order-page">{markup}<script>window.STAFF_QUICK_ORDER_API='https://sokna.test/quick';window.SOKNA_ICON_SPRITE='/sprite.svg';window.QUICK_ORDER_USER_KEY='7';window.fetch=async()=>new Response({json.dumps(json.dumps(payload,ensure_ascii=False))},{{status:200,headers:{{'Content-Type':'application/json'}}}});</script></body></html>'''

def box_signature(page, selector):
    return page.locator(selector).evaluate('''e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return {w:r.width,h:r.height,p:s.padding,b:s.borderRadius,bg:s.backgroundColor,c:s.color}}''')

def setup(page):
    page.set_content(html)
    page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})")
    page.add_script_tag(content=policy)
    page.add_script_tag(content=js)
    page.wait_for_selector('[data-qo-category="10"]')
    page.click('[data-qo-category="10"]')
    page.wait_for_selector('[data-qo-add="101"]')

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])

    page=browser.new_page(viewport={'width':390,'height':844})
    setup(page)
    page.click('[data-qo-add="101"]'); page.click('[data-qo-add="101"]'); page.click('[data-qo-add="102"]')
    page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(220)
    assert page.locator('#quickOrderCart').evaluate('e=>e.classList.contains("is-open")')

    # Preserve exact cart state, including per-line and global notes.
    page.click('[data-qo-note-toggle="101"]')
    page.fill('[data-qo-note-input="101"]','کم‌شیرین')
    page.click('#quickOrderNoteToggle')
    page.fill('#quickOrderNote','اول قهوه‌ها')
    before=box_signature(page,'#quickOrderClear')
    cart_before=box_signature(page,'#quickOrderCart')

    page.click('#quickOrderClear'); page.wait_for_timeout(30)
    after_delete=box_signature(page,'#quickOrderClear')
    assert before == after_delete, (before, after_delete)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'undo'
    assert page.locator('#quickOrderClear').get_attribute('aria-label') == 'بازگردانی حذف سبد'
    assert 'هنوز آیتمی انتخاب نشده' in page.locator('#quickOrderCartTitle').inner_text()
    assert page.input_value('#quickOrderNote') == ''
    assert box_signature(page,'#quickOrderCart') == cart_before

    page.click('#quickOrderClear'); page.wait_for_timeout(30)
    after_undo=box_signature(page,'#quickOrderClear')
    assert before == after_undo, (before, after_undo)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'clear'
    assert '۲ قلم' in page.locator('#quickOrderCartTitle').inner_text() and '۳ عدد' in page.locator('#quickOrderCartTitle').inner_text()
    assert page.input_value('#quickOrderNote') == 'اول قهوه‌ها'
    assert page.input_value('[data-qo-note-input="101"]') == 'کم‌شیرین'

    # A real cart mutation invalidates stale Undo instead of restoring an old snapshot.
    page.click('#quickOrderClear'); page.wait_for_timeout(20)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'undo'
    page.click('#quickOrderCartClose'); page.wait_for_timeout(220)
    page.click('[data-qo-add="103"]'); page.wait_for_timeout(30)
    page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(220)
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'clear'
    assert '۱ قلم' in page.locator('#quickOrderCartTitle').inner_text()

    # Fill a genuinely long cart; the body remains the only scroll owner and must not compete with sheet dismissal.
    page.click('#quickOrderCartClose'); page.wait_for_timeout(220)
    for item_id in range(104,113):
        page.click(f'[data-qo-add="{item_id}"]')
    page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(220)
    metrics=page.locator('#quickOrderCartBody').evaluate('e=>({scroll:e.scrollHeight,client:e.clientHeight})')
    assert metrics['scroll'] > metrics['client'], metrics
    page.locator('#quickOrderCartBody').evaluate('e=>{e.scrollTop=e.scrollHeight}')
    assert page.locator('#quickOrderCartBody').evaluate('e=>e.scrollTop') > 0

    # A short pull returns to the same geometry; a deliberate downward pull dismisses.
    head=page.locator('.quick-order-cart-head').bounding_box(); assert head
    x=head['x'] + head['width']*0.50; y=head['y'] + min(24,head['height']*0.45)
    page.mouse.move(x,y); page.mouse.down(); page.mouse.move(x,y+36,steps=4); page.mouse.up(); page.wait_for_timeout(230)
    assert page.locator('#quickOrderCart').evaluate('e=>e.classList.contains("is-open")')
    assert box_signature(page,'#quickOrderCart') == cart_before

    head=page.locator('.quick-order-cart-head').bounding_box(); x=head['x']+head['width']*0.50; y=head['y']+min(24,head['height']*0.45)
    page.mouse.move(x,y); page.mouse.down(); page.mouse.move(x,y+150,steps=8); page.mouse.up(); page.wait_for_timeout(260)
    assert not page.locator('#quickOrderCart').evaluate('e=>e.classList.contains("is-open")')
    assert page.locator('#quickOrderCartBackdrop').evaluate('e=>e.classList.contains("hidden")')
    assert '۱۰ قلم' in page.locator('#quickOrderMobileCartCount').inner_text(), 'Dismiss must never clear the cart.'

    # Dragging in the scroll/content region must not accidentally dismiss the sheet.
    page.click('#quickOrderMobileCartBar'); page.wait_for_timeout(220)
    body=page.locator('#quickOrderCartBody').bounding_box(); assert body
    bx=body['x']+body['width']*0.5; by=body['y']+min(80,body['height']*0.25)
    page.mouse.move(bx,by); page.mouse.down(); page.mouse.move(bx,by+150,steps=8); page.mouse.up(); page.wait_for_timeout(220)
    assert page.locator('#quickOrderCart').evaluate('e=>e.classList.contains("is-open")'), 'Content gestures must remain scroll-safe.'
    page.close()

    # Desktop keeps the identical clear-button geometry and gains the same in-place Undo behavior.
    page=browser.new_page(viewport={'width':1366,'height':850})
    setup(page)
    page.click('[data-qo-add="101"]')
    desktop_before=box_signature(page,'#quickOrderClear')
    assert not page.locator('#quickOrderClear').is_visible(), 'Desktop clear owner is intentionally hidden behind the cart overflow menu.'
    page.locator('.quick-order-cart-more>summary').click()
    proxy=page.locator('[data-qo-clear-proxy]')
    assert proxy.is_visible()
    proxy.click(); page.wait_for_timeout(20)
    assert box_signature(page,'#quickOrderClear') == desktop_before
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'undo'
    assert proxy.get_attribute('data-qo-clear-action') == 'undo'
    assert proxy.get_attribute('aria-label') == 'بازگردانی حذف سبد'
    page.locator('.quick-order-cart-more>summary').click()
    assert 'بازگردانی حذف سبد' in (proxy.text_content() or '')
    proxy.click(); page.wait_for_timeout(20)
    assert box_signature(page,'#quickOrderClear') == desktop_before
    assert page.locator('#quickOrderClear').get_attribute('data-qo-clear-action') == 'clear'
    assert proxy.get_attribute('data-qo-clear-action') == 'clear'
    assert proxy.get_attribute('aria-label') == 'پاک‌کردن سبد'
    assert '۱ قلم' in page.locator('#quickOrderCartTitle').inner_text()
    page.close()

    browser.close()
print('Quick Order Undo + swipe browser regression passed: in-place geometry preserved, exact cart snapshot restored, stale undo invalidated, deliberate header swipe dismisses, content drag does not dismiss, cart survives close, desktop unaffected.')
