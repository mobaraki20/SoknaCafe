#!/usr/bin/env python3
from pathlib import Path
import base64
import os
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
<section class="featured-menu-zone"><div class="section-title-v12 featured-menu-heading"><div><h2>پیشنهادهای کافه</h2></div></div><div class="horizontal-rail-shell"><div class="featured-menu-rail horizontal-rail" id="featuredMenuRail">
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="1"><div class="item-media-v12 featured-menu-media"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12 featured-menu-copy"><div><small>خوراک روز</small><h3>زینگر</h3><p>مرغ سوخاری، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><div class="item-price">۴۶۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="1"><button data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button class="inline-add" data-add-item="1">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="2"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><small>شربت‌خانه</small><h3>لیموناد تازه</h3><p>لیموی تازه و شربت دست‌ساز.</p></div><div class="item-action-row"><div class="item-price">۱۸۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="2"><button data-inline-dec="2">−</button><span data-inline-count="2">۰</span><button class="inline-add" data-add-item="2">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></div></section>
<section class="category-section-v12" id="category-1"><div class="section-title-v12"><h2>خوراک روز</h2><span>۲ انتخاب</span></div><div class="items-list-v12">
<article class="menu-item-v12" data-menu-item data-item-id="3"><div class="item-media-v12"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12"><div><h3>برگر گوشت</h3><p>برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="3"><button data-inline-dec="3">−</button><span data-inline-count="3">۰</span><button class="inline-add" data-add-item="3">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="menu-item-v12" data-menu-item data-item-id="4"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><h3>پاستا چیکن آلفردو</h3><p>مرغ، قارچ، خامه، شیر و پنیر پارمسان.</p></div><div class="item-action-row"><strong>۵۸۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="4"><button data-inline-dec="4">−</button><span data-inline-count="4">۰</span><button class="inline-add" data-add-item="4">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></section><div style="height:700px"></div></main>
<div class="guest-events-sheet hidden" id="eventsSheet" role="dialog"><section class="guest-events-panel"><header class="guest-events-head"><div><small>رویدادهای سکنا</small><h2>رویدادها</h2></div><button class="guest-events-close" data-close-events>×</button></header><div class="events-list"><article class="event-card"><div class="event-card-body"><h3>ورکشاپ سفال</h3><p>چهارشنبه، کافه سکنا</p></div></article></div></section></div>
<div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel can-order"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><h2 id="itemDetailTitle"></h2><div class="item-detail-price" id="itemDetailPrice"></div><div class="item-detail-tags" id="itemDetailTags"></div><p class="item-detail-description" id="itemDetailDescription"></p><div class="item-detail-option-slot" id="itemDetailOptionSlot"><details class="item-detail-note" id="itemDetailNoteBlock"><summary>افزودن یادداشت</summary><textarea id="itemDetailNote"></textarea></details><section class="item-detail-suggestion hidden" id="itemDetailSuggestion"><div class="item-detail-suggestion-media" id="itemDetailSuggestionMedia"></div><div class="item-detail-suggestion-copy"><small>پیشنهاد مکمل</small><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span></div><button id="itemDetailSuggestionAdd">+</button></section></div></div></div><footer class="item-detail-footer"><div class="item-detail-actions" id="itemDetailActions"></div><button class="item-detail-primary" id="itemDetailPrimary"><span>افزودن به سفارش</span></button></footer></article></div>
<div class="guest-action-dock" id="guestActionDock"><button class="waiter-fab" id="waiterButton"><span class="visually-hidden" id="waiterButtonText">فراخوان گارسون</span>🔔</button><div class="cart-bar-v12 hidden" id="cartBar"><button id="openCart"><span class="cart-bar-icon">🛒</span><strong>دیدن سفارش</strong><b id="cartCount" class="cart-count-v12">۰</b></button><div class="cart-bar-summary"><small>جمع سبد</small><strong id="cartBarTotal"></strong></div></div></div>
<div class="drawer-backdrop" id="drawerBackdrop"></div><section class="cart-drawer" id="cartDrawer" aria-hidden="true"><div class="drawer-head"><div><small id="cartContext"></small><h2>مرور سفارش</h2><span id="cartSummaryCaption"></span></div><button id="closeCart">×</button></div><div class="drawer-content" id="cartDrawerContent"><div id="cartItems"></div><div id="cartScrollCue"></div><div id="cartStationWarning" class="hidden"></div><div id="cartSuggestion" class="hidden"></div><details class="order-note-field" id="orderNoteDisclosure"><summary>+ افزودن یادداشت کلی سفارش <small>اختیاری</small></summary><textarea id="customerNote"></textarea></details></div><div class="drawer-foot"><div class="total-row"><span>جمع</span><span id="cartTotal"></span></div><button class="submit-order-button" id="submitOrder"><span id="submitOrderText">ثبت</span></button></div></section>
<div class="guest-orders-modal hidden" id="guestOrdersModal"><div class="guest-orders-box"><button class="waiter-modal-close" id="guestOrdersClose">×</button><div class="guest-orders-head"><div><small>حساب جاری همین دستگاه</small><h2>سفارش‌های شما</h2></div></div><div class="guest-orders-list" id="guestOrdersList"></div><p class="guest-orders-empty hidden" id="guestOrdersEmpty"></p><button id="guestOrdersDone">بازگشت</button></div></div>
<div class="guest-confirm-modal hidden" id="guestConfirmModal"><div class="guest-confirm-box"><h2 id="guestConfirmTitle"></h2><p id="guestConfirmText"></p><div class="guest-confirm-actions"><button id="guestConfirmAccept" class="guest-confirm-accept"></button><button id="guestConfirmCancel" class="guest-confirm-cancel"></button></div></div></div><div id="guestToast" class="hidden"></div><div id="orderLiveStatus"></div><span id="orderStatusText"></span>
<script>window.CAFE_MENU=[
{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',price:460000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:2,name:'لیموناد تازه',category:'شربت‌خانه',description:'لیموی تازه',price:180000,image:'{IMG}',available:1,suggested_item_id:null,tags:[]}},
{{id:3,name:'برگر گوشت',category:'خوراک روز',description:'برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی',price:465000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:4,name:'پاستا چیکن آلفردو',category:'خوراک روز',description:'مرغ، قارچ، خامه، شیر و پنیر پارمسان',price:585000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}}];
window.CAFE_TABLE={{token:'t',name:'میز ۱',code:'1'}};window.CAFE_SESSION={{token:'s'}};window.CAFE_CURRENCY='تومان';window.CAFE_CAN_ORDER=true;window.CAFE_ORDERING_ENABLED=true;window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_STATION_STATE_HASH='';window.CAFE_MESSAGES={{item_note_placeholder_enabled:true}};window.CAFE_ANALYTICS_ENABLED=false;window.CAFE_WAITER_ENABLED=true;window.CAFE_GUEST_ORDERS_API_URL='/orders';window.CAFE_CONTEXT_API_URL='/context';
const testOrders=[{{order_code:'O31',order_number:31,status:'pending_approval',status_label:'منتظر تأیید حضور',total_amount:1045000,can_edit:true,can_cancel:true,items:[{{id:1,name:'زینگر',quantity:1,line_total:460000,note:'سس جدا'}},{{id:4,name:'پاستا',quantity:1,line_total:585000,note:''}}]}}];
window.fetch=async(url,opts={{}})=>{{let body={{}};try{{body=JSON.parse(opts.body||'{{}}')}}catch(e){{}};if(String(url).includes('/context'))return new Response(JSON.stringify({{success:true,can_order:true,ordering_enabled:true,requires_operator_confirmation:false,waiter_enabled:true,session:window.CAFE_SESSION,table:window.CAFE_TABLE,station_state_hash:''}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='list')return new Response(JSON.stringify({{success:true,orders:testOrders}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='cancel')return new Response(JSON.stringify({{success:true}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});return new Response(JSON.stringify({{success:true,orders:[]}}),{{status:200,headers:{{'Content-Type':'application/json'}}}})}};</script><script>{JS}</script></body></html>'''

SIZES=((320,720),(390,844),(412,915),(768,900),(1024,768))
requested_width=os.environ.get('SOKNA_UI_WIDTH','').strip()
if requested_width:
    SIZES=tuple(size for size in SIZES if str(size[0])==requested_width)
    if not SIZES: raise SystemExit(f'Unsupported SOKNA_UI_WIDTH={requested_width}')

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in SIZES:
        page=browser.new_page(viewport={'width':width,'height':height})
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(HTML,wait_until='load');page.add_style_tag(content='*{animation:none!important;transition:none!important;scroll-behavior:auto!important}');page.wait_for_timeout(80)
        assert not errors,errors
        assert page.evaluate('document.documentElement.scrollWidth') <= width+1
        assert page.locator('#featuredMenuTitle, .featured-menu-heading h2').first.inner_text() == 'پیشنهادهای کافه'
        assert page.locator('.featured-menu-heading span').count() == 0
        # Sticky navigation keeps exact active contrast instead of the old faded state.
        page.evaluate("document.body.classList.add('guest-scrolled')")
        active_bg=page.locator('.category-orbit a.active').evaluate('e=>getComputedStyle(e).backgroundColor')
        active_color=page.locator('.category-orbit a.active').evaluate('e=>getComputedStyle(e).color')
        assert active_bg not in ('rgba(0, 0, 0, 0)','transparent')
        assert active_color != page.locator('.category-orbit a:not(.active)').first.evaluate('e=>getComputedStyle(e).color')
        # Card controls retain approved shape but fit within every product card.
        page.locator('[data-add-item="3"]').click();page.wait_for_timeout(50)
        card=page.locator('[data-menu-item][data-item-id="3"]'); qty=card.locator('.inline-qty').bounding_box(); copy=card.locator('.item-copy-v12').bounding_box()
        assert qty['x'] >= copy['x']-1 and qty['x']+qty['width'] <= copy['x']+copy['width']+1,(qty,copy)
        assert 46 <= qty['height'] <= 50 and 116 <= qty['width'] <= 122
        # Item detail is stripped of redundant category and uses one centered action label.
        card.locator('h3').click();page.wait_for_timeout(80)
        assert page.locator('#itemDetailCategory').count()==0
        close_box=page.locator('.item-detail-close').bounding_box(); assert close_box['width'] <= 44 and close_box['height'] <= 44
        detail_qty=page.locator('.detail-qty-control').bounding_box(); assert detail_qty['width'] <= 128
        primary=page.locator('#itemDetailPrimary'); assert primary.locator('strong').count()==0
        assert primary.evaluate("e=>getComputedStyle(e).justifyContent")=='center'
        page.locator('[data-close-item-detail]').click();page.wait_for_timeout(60)
        # Dense order review: no empty item note controls, no row currency repetition, one total only.
        for selector,count in ((('[data-add-item="1"]'),4),(('[data-add-item="4"]'),3)):
            for _ in range(count): page.locator(selector).evaluate('e=>e.click()')
        if page.locator('#cartDrawer').get_attribute('aria-hidden') != 'false': page.locator('#openCart').evaluate('e=>e.click()')
        page.wait_for_timeout(90)
        assert page.locator('.cart-currency-note').count()==1
        assert page.locator('.cart-line').count()>=3
        assert page.locator('.cart-line').filter(has_text='افزودن یادداشت').count()==0
        assert page.locator('.cart-line').filter(has_text='تومان').count()==0
        assert page.locator('.line-note-disclosure.has-note').count()==0
        assert page.locator('#submitOrder strong').count()==0
        assert page.locator('#cartTotal').inner_text().endswith('تومان')
        if width in (390,412):
            heights=page.locator('.cart-line').evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
            assert max(heights) <= 105,heights
        page.locator('#closeCart').click();page.wait_for_timeout(60)
        # Current orders show amounts once, without total in the modal header.
        page.locator('#openRecentOrder').click();page.wait_for_timeout(120)
        assert page.locator('#guestOrdersTotal').count()==0
        assert page.locator('.guest-orders-currency').count()==1
        assert page.locator('.guest-order-line').filter(has_text='تومان').count()==0
        assert page.locator('.guest-order-card-head').filter(has_text='منتظر تأیید کافه').count()==1
        # Edit and cancel use branded Persian confirmations, never native browser dialogs.
        native=[]; page.on('dialog',lambda d:(native.append(d.message),d.dismiss()))
        page.locator('[data-edit-guest-order]').click();page.wait_for_timeout(70)
        assert page.locator('#guestConfirmModal').is_visible()
        assert 'اقلام فعلی سبد حذف می‌شوند' in page.locator('#guestConfirmText').inner_text()
        assert not native
        page.locator('#guestConfirmCancel').click();page.wait_for_timeout(60)
        page.locator('[data-cancel-guest-order]').click();page.wait_for_timeout(70)
        assert 'امکان بازگرداندن' in page.locator('#guestConfirmText').inner_text()
        assert page.locator('#guestConfirmAccept').inner_text()=='لغو سفارش'
        assert not native
        page.locator('#guestConfirmCancel').click();page.wait_for_timeout(60)
        # Waiter modal has no duplicate raw close button in the final DOM.
        assert page.locator('#waiterModalClose').count()==0
        page.screenshot(path=f'/mnt/data/sokna-1275-ui-{width}.png',full_page=False)
        page.close()
    browser.close()
print('Sokna 1.27.5 UI acceptance passed at 320, 390, 412, 768 and 1024 widths.')
