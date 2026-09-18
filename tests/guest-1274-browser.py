#!/usr/bin/env python3
from pathlib import Path
import base64
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = '\n'.join((ROOT / p).read_text(encoding='utf-8') for p in (
    'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'
))
JS = (ROOT / 'assets/js/menu.js').read_text(encoding='utf-8')
SVG = '''<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="1000"><rect width="1000" height="1000" fill="#d9cfbd"/><rect y="0" width="1000" height="400" fill="#b9ab94"/><circle cx="510" cy="610" r="250" fill="#2f7566"/></svg>'''
IMG = 'data:image/svg+xml;base64,' + base64.b64encode(SVG.encode()).decode()

HTML = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="test"><style>:root{{--primary:#2f7566;--accent:#d99b3d;--app-bg:#f6f1e6;--on-primary:#fff;--on-accent:#2f2a24;--font-ui:Tahoma,Arial,sans-serif}}{CSS}</style></head>
<body class="guest-menu-page theme-courtyard density-balanced menu-layout-editorial has-waiter">
<header class="guest-header" id="guestHeader"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">س</div><div class="guest-brand-copy"><strong>کافه سکنا</strong><span>منوی میز شما</span></div></div><div class="guest-header-actions"><button class="guest-event-access" type="button" data-open-events><span>رویدادها</span></button><div class="guest-table-label"><strong>میز ۱</strong></div></div></div></header>
<div class="recent-order recent-order-compact" id="recentOrderBar"><button type="button" id="openRecentOrder"><span>مشاهده سفارش‌ها</span><b id="recentOrderBadge">۱</b></button></div>
<main class="guest-main"><section class="guest-search-compact" id="guestSearchZone"><div class="guest-search-panel"><div class="menu-search-wrap-v12"><button id="searchOpen" class="search-focus-proxy">⌕</button><input id="menuSearch" placeholder="جست‌وجو در نام یا دسته‌بندی"><button id="searchClear" class="hidden">×</button></div><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><button id="searchBackToMenu"></button><div id="searchResultList"></div></div></div></section>
<div class="horizontal-rail-shell category-rail-shell"><nav class="category-orbit horizontal-rail" id="categoryTabs"><a class="active" href="#menuStart"><span class="category-orb">☰</span><b>همه</b></a><a href="#category-1"><span class="category-orb">●</span><b>خوراک روز</b></a><a href="#category-2"><span class="category-orb">●</span><b>شربت‌خانه</b></a></nav></div><span id="menuStart"></span>
<section class="featured-menu-zone"><div class="section-title-v12 featured-menu-heading"><div><h2>پیشنهاد امروز</h2><span>انتخاب‌های ویژه کافه</span></div></div><div class="horizontal-rail-shell"><div class="featured-menu-rail horizontal-rail" id="featuredMenuRail">
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="1"><div class="item-media-v12 featured-menu-media"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12 featured-menu-copy"><div><small>خوراک روز</small><h3>زینگر</h3><p>مرغ سوخاری، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><div class="item-price">۴۶۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="1"><button data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button class="inline-add" data-add-item="1">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="2"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><small>شربت‌خانه</small><h3>لیموناد تازه</h3><p>لیموی تازه و شربت دست‌ساز.</p></div><div class="item-action-row"><div class="item-price">۱۸۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="2"><button data-inline-dec="2">−</button><span data-inline-count="2">۰</span><button class="inline-add" data-add-item="2">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></div></section>
<section class="category-section-v12" id="category-1"><div class="section-title-v12"><h2>خوراک روز</h2><span>۲ انتخاب</span></div><div class="items-list-v12">
<article class="menu-item-v12" data-menu-item data-item-id="3"><div class="item-media-v12"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12"><div><h3>برگر گوشت</h3><p>برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="3"><button data-inline-dec="3">−</button><span data-inline-count="3">۰</span><button class="inline-add" data-add-item="3">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="menu-item-v12" data-menu-item data-item-id="4"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><h3>پاستا چیکن آلفردو</h3><p>مرغ، قارچ، خامه، شیر و پنیر پارمسان.</p></div><div class="item-action-row"><strong>۵۸۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="4"><button data-inline-dec="4">−</button><span data-inline-count="4">۰</span><button class="inline-add" data-add-item="4">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></section><div style="height:700px"></div></main>
<div class="guest-events-sheet hidden" id="eventsSheet" role="dialog"><section class="guest-events-panel"><header class="guest-events-head"><div><small>رویدادهای سکنا</small><h2>رویدادها</h2></div><button class="guest-events-close" data-close-events>×</button></header><div class="events-list"><article class="event-card"><div class="event-card-body"><h3>ورکشاپ سفال</h3><p>چهارشنبه، کافه سکنا</p></div></article></div></section></div>
<div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel can-order"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><small id="itemDetailCategory"></small><h2 id="itemDetailTitle"></h2><div class="item-detail-price" id="itemDetailPrice"></div><div class="item-detail-tags" id="itemDetailTags"></div><p class="item-detail-description" id="itemDetailDescription"></p><div class="item-detail-option-slot" id="itemDetailOptionSlot"><details class="item-detail-note" id="itemDetailNoteBlock"><summary>افزودن یادداشت</summary><textarea id="itemDetailNote"></textarea></details><section class="item-detail-suggestion hidden" id="itemDetailSuggestion"><div class="item-detail-suggestion-media" id="itemDetailSuggestionMedia"></div><div class="item-detail-suggestion-copy"><small>پیشنهاد مکمل</small><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span></div><button id="itemDetailSuggestionAdd">+</button></section></div></div></div><footer class="item-detail-footer"><div class="item-detail-actions" id="itemDetailActions"></div><button class="item-detail-primary" id="itemDetailPrimary"><span>افزودن به سفارش</span><strong id="itemDetailPrimaryTotal"></strong></button></footer></article></div>
<div class="guest-action-dock" id="guestActionDock"><button class="waiter-fab" id="waiterButton"><span class="visually-hidden" id="waiterButtonText">فراخوان گارسون</span>🔔</button><div class="cart-bar-v12 hidden" id="cartBar"><button id="openCart"><span class="cart-bar-icon">🛒</span><strong>دیدن سفارش</strong><b id="cartCount" class="cart-count-v12">۰</b></button><div class="cart-bar-summary"><small>جمع سبد</small><strong id="cartBarTotal"></strong></div></div></div>
<div class="drawer-backdrop" id="drawerBackdrop"></div><section class="cart-drawer" id="cartDrawer" aria-hidden="true"><div class="drawer-head"><div><small id="cartContext"></small><h2>مرور سفارش</h2><span id="cartSummaryCaption"></span></div><button id="closeCart">×</button></div><div class="drawer-content" id="cartDrawerContent"><div id="cartItems"></div><div id="cartScrollCue"></div><div id="cartStationWarning" class="hidden"></div><div id="cartSuggestion" class="hidden"></div><details id="orderNoteDisclosure"><summary>یادداشت کلی</summary><textarea id="customerNote"></textarea></details></div><div class="drawer-foot"><div class="total-row"><span>جمع</span><span id="cartTotal"></span></div><button id="submitOrder"><span id="submitOrderText">ثبت</span><strong id="submitOrderTotal"></strong></button></div></section>
<div class="guest-orders-modal hidden" id="guestOrdersModal"><div class="guest-orders-box"><button class="waiter-modal-close" id="guestOrdersClose">×</button><div class="guest-orders-head"><div><small>حساب جاری همین دستگاه</small><h2>سفارش‌های شما</h2></div><strong id="guestOrdersTotal"></strong></div><div class="guest-orders-list" id="guestOrdersList"></div><p class="guest-orders-empty hidden" id="guestOrdersEmpty"></p><button id="guestOrdersDone">بازگشت</button></div></div>
<div id="guestToast" class="hidden"></div><div id="orderLiveStatus"></div><span id="orderStatusText"></span>
<script>window.CAFE_MENU=[
{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',price:460000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:2,name:'لیموناد تازه',category:'شربت‌خانه',description:'لیموی تازه',price:180000,image:'{IMG}',available:1,suggested_item_id:null,tags:[]}},
{{id:3,name:'برگر گوشت',category:'خوراک روز',description:'برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی',price:465000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:4,name:'پاستا چیکن آلفردو',category:'خوراک روز',description:'مرغ، قارچ، خامه، شیر و پنیر پارمسان',price:585000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}}];
window.CAFE_TABLE={{token:'t',name:'میز ۱',code:'1'}};window.CAFE_SESSION={{token:'s'}};window.CAFE_CURRENCY='تومان';window.CAFE_CAN_ORDER=true;window.CAFE_ORDERING_ENABLED=true;window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_STATION_STATE_HASH='';window.CAFE_MESSAGES={{item_note_placeholder_enabled:true}};window.CAFE_ANALYTICS_ENABLED=false;window.CAFE_WAITER_ENABLED=true;window.CAFE_GUEST_ORDERS_API_URL='/orders';window.CAFE_CONTEXT_API_URL='/context';
const testOrders=[{{order_code:'O31',order_number:31,status:'pending_approval',status_label:'منتظر تأیید',total_amount:1045000,can_edit:true,can_cancel:true,items:[{{id:1,name:'زینگر',quantity:1,line_total:460000,note:''}},{{id:4,name:'پاستا',quantity:1,line_total:585000,note:''}}]}}];
window.fetch=async(url,opts={{}})=>{{let body={{}};try{{body=JSON.parse(opts.body||'{{}}')}}catch(e){{}};if(String(url).includes('/context'))return new Response(JSON.stringify({{success:true,can_order:true,ordering_enabled:true,requires_operator_confirmation:false,waiter_enabled:true,session:window.CAFE_SESSION,table:window.CAFE_TABLE,station_state_hash:''}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='list')return new Response(JSON.stringify({{success:true,orders:testOrders}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});return new Response(JSON.stringify({{success:true,orders:[]}}),{{status:200,headers:{{'Content-Type':'application/json'}}}})}};</script><script>{JS}</script></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in ((320,720),(390,844),(412,915),(768,900),(1024,768)):
        page=browser.new_page(viewport={'width':width,'height':height})
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(HTML,wait_until='load');page.wait_for_timeout(180)
        assert not errors,errors
        assert page.evaluate('document.documentElement.scrollWidth') <= width+1
        assert page.locator('.guest-header-inner').bounding_box()['height'] <= (76 if width<=720 else 82)
        assert page.locator('.category-orbit a').first.bounding_box()['height'] <= 60
        assert page.locator('#recentOrderBar').bounding_box()['height'] <= 54
        # featured is a real product card, with integrated left image and right copy
        f=page.locator('.featured-menu-card').first; fm=f.locator('.item-media-v12').bounding_box(); fc=f.locator('.item-copy-v12').bounding_box()
        assert fm['x'] < fc['x'],(fm,fc)
        if width<=720: assert f.bounding_box()['width'] >= width-40
        # card control physical position and compact touch-safe shape
        page.locator('[data-add-item="3"]').click();page.wait_for_timeout(50)
        card=page.locator('[data-menu-item][data-item-id="3"]');qty=card.locator('.inline-qty').bounding_box();price=card.locator('.item-action-row>strong').bounding_box()
        assert qty['x'] > price['x'],(qty,price)
        assert 44 <= qty['height'] <= 50.5,qty
        assert 36 <= card.locator('[data-inline-dec]').bounding_box()['width'] <= 42
        assert card.locator('.inline-qty').evaluate("e=>getComputedStyle(e).borderRadius") in ('17px','17.0px')
        # badge belongs to cart button, never bell
        badge=page.locator('#cartCount').bounding_box();cartbtn=page.locator('#openCart').bounding_box();bell=page.locator('#waiterButton').bounding_box()
        assert badge and cartbtn and bell
        assert badge['x'] >= cartbtn['x']-12 and badge['x'] <= cartbtn['x']+cartbtn['width']+2
        assert not (bell['x'] <= badge['x'] <= bell['x']+bell['width'])
        assert 50 <= bell['width'] <= 56 and 50 <= bell['height'] <= 56
        # normal detail with suggestion does not scroll in common mobile sizes
        card.locator('h3').click();page.wait_for_timeout(100)
        assert page.locator('#itemDetailSuggestion').is_visible()
        footer_qty=page.locator('.item-detail-actions').bounding_box(); footer_cta=page.locator('#itemDetailPrimary').bounding_box(); assert footer_qty['x'] > footer_cta['x'],(footer_qty,footer_cta)
        assert not page.locator('#itemDetailNoteBlock').is_visible()
        if width in (390,412):
            scrollable=page.locator('.item-detail-scroll').evaluate('e=>e.scrollHeight>e.clientHeight+2')
            assert not scrollable, (width,page.locator('.item-detail-scroll').evaluate('e=>[e.scrollHeight,e.clientHeight]'))
        # add complementary product -> suggestion disappears, note appears
        page.locator('#itemDetailSuggestionAdd').click();page.wait_for_timeout(80)
        assert not page.locator('#itemDetailSuggestion').is_visible()
        assert page.locator('#itemDetailNoteBlock').is_visible()
        if width in (390,412): assert not page.locator('.item-detail-scroll').evaluate('e=>e.scrollHeight>e.clientHeight+2')
        # closing never moves the background card
        page.locator('[data-close-item-detail]').click();page.wait_for_timeout(100)
        target=page.locator('[data-menu-item][data-item-id="4"]');target.scroll_into_view_if_needed();page.wait_for_timeout(100)
        before=page.evaluate('window.scrollY');top=target.evaluate('e=>e.getBoundingClientRect().top')
        target.locator('h3').evaluate('e=>e.click()');page.wait_for_timeout(80);page.locator('[data-close-item-detail]').click();page.wait_for_timeout(220)
        assert abs(page.evaluate('window.scrollY')-before)<2
        assert abs(target.evaluate('e=>e.getBoundingClientRect().top')-top)<3
        # event sheet is the active top layer
        page.locator('[data-open-events]').click();page.wait_for_timeout(70)
        assert page.locator('#eventsSheet').evaluate('e=>getComputedStyle(e).position')=='fixed'
        assert int(page.locator('#eventsSheet').evaluate('e=>getComputedStyle(e).zIndex'))>=1300
        page.locator('[data-close-events]').click();page.wait_for_timeout(70)
        # compact order shortcut opens current safe order list
        page.locator('#openRecentOrder').click();page.wait_for_timeout(140)
        assert page.locator('.guest-order-card').count()==1
        assert page.locator('[data-edit-guest-order]').is_visible()
        page.screenshot(path=f'/mnt/data/sokna-1274-ui-{width}.png',full_page=False)
        page.close()
    browser.close()
print('Sokna 1.27.4 UI acceptance passed at 320, 390, 412, 768 and 1024 widths.')
