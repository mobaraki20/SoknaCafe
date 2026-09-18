#!/usr/bin/env python3
from pathlib import Path
import base64
import os
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
SCREENSHOT_DIR = Path(os.environ.get('SOKNA_SCREENSHOT_DIR', '/tmp'))
SCREENSHOT_DIR.mkdir(parents=True, exist_ok=True)
CSS = '\n'.join((ROOT / p).read_text(encoding='utf-8') for p in (
    'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'
))
SHEET = (ROOT / 'assets/js/guest-sheet.js').read_text(encoding='utf-8')
POLICY = (ROOT / 'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')
JS = (ROOT / 'assets/js/menu.js').read_text(encoding='utf-8')
SVG = '''<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="1000"><rect width="1000" height="1000" fill="#d9cfbd"/><rect y="0" width="1000" height="400" fill="#b9ab94"/><circle cx="510" cy="610" r="250" fill="#2f7566"/></svg>'''
IMG = 'data:image/svg+xml;base64,' + base64.b64encode(SVG.encode()).decode()

HTML = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="test"><style>:root{{--primary:#2f7566;--accent:#d99b3d;--app-bg:#f6f1e6;--on-primary:#fff;--on-accent:#2f2a24;--font-ui:Tahoma,Arial,sans-serif}}{CSS}</style></head>
<body class="guest-menu-page theme-courtyard density-balanced menu-layout-editorial has-waiter">
<header class="guest-header" id="guestHeader"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">س</div><div class="guest-brand-copy"><strong>کافه سکنا</strong><span>منوی میز شما</span></div></div><div class="guest-header-actions"><button class="guest-event-access" type="button" data-open-events><span>رویدادها</span></button><div class="guest-table-label"><strong>میز ۱</strong></div></div></div></header>
<div class="recent-order recent-order-compact" id="recentOrderBar"><button type="button" id="openRecentOrder"><span>مشاهده سفارش‌ها</span><b id="recentOrderBadge">۱</b></button></div>
<main class="guest-main"><section class="guest-search-compact" id="guestSearchZone"><div class="guest-search-panel"><div class="menu-search-wrap-v12"><button id="searchOpen" class="search-focus-proxy">⌕</button><input id="menuSearch" type="search" placeholder="چی دوست داری بخوری؟"><button id="searchClear" class="hidden">×</button></div><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><button id="searchBackToMenu"></button><div id="searchResultList"></div></div></div></section>
<div class="horizontal-rail-shell category-rail-shell"><nav class="category-orbit horizontal-rail" id="categoryTabs"><a class="active" href="#menuStart"><span class="category-orb">☰</span><b>همه</b></a><a href="#category-1"><span class="category-orb">●</span><b>خوراک روز</b></a><a href="#category-2"><span class="category-orb">●</span><b>شربت‌خانه</b></a></nav></div><span id="menuStart"></span>
<section class="featured-menu-zone"><div class="section-title-v12 featured-menu-heading"><div><h2>پیشنهادهای کافه</h2></div></div><div class="horizontal-rail-shell"><div class="featured-menu-rail horizontal-rail" id="featuredMenuRail">
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="1"><div class="item-media-v12 featured-menu-media"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12 featured-menu-copy"><div><small>خوراک روز</small><h3>زینگر</h3><p>مرغ سوخاری، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><div class="item-price">۴۶۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="1"><button data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button class="inline-add" data-add-item="1">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="featured-menu-card menu-item-v12" data-featured-item data-item-id="2"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><small>شربت‌خانه</small><h3>لیموناد تازه</h3><p>لیموی تازه و شربت دست‌ساز.</p></div><div class="item-action-row"><div class="item-price">۱۸۰٬۰۰۰ تومان</div><div class="inline-qty" data-inline-qty="2"><button data-inline-dec="2">−</button><span data-inline-count="2">۰</span><button class="inline-add" data-add-item="2">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></div></section>
<section class="category-section-v12" id="category-1"><div class="section-title-v12"><h2>خوراک روز</h2><span>۲ انتخاب</span></div><div class="items-list-v12">
<article class="menu-item-v12" data-menu-item data-item-id="3"><div class="item-media-v12"><img src="{IMG}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12"><div><h3>برگر گوشت</h3><p>برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="3"><button data-inline-dec="3">−</button><span data-inline-count="3">۰</span><button class="inline-add" data-add-item="3">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="menu-item-v12" data-menu-item data-item-id="4"><div class="item-media-v12"><img src="{IMG}"></div><div class="item-copy-v12"><div><h3>پاستا چیکن آلفردو</h3><p>مرغ، قارچ، خامه، شیر و پنیر پارمسان.</p></div><div class="item-action-row"><strong>۵۸۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="4"><button data-inline-dec="4">−</button><span data-inline-count="4">۰</span><button class="inline-add" data-add-item="4">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></section><div style="height:700px"></div></main>
<div class="guest-events-sheet hidden" id="eventsSheet" role="dialog"><section class="guest-events-panel"><header class="guest-events-head"><div><h2>رویدادهای سکنا</h2></div><button class="guest-events-close" data-close-events>×</button></header><p>برنامه‌های پیش‌روی کافه</p><div class="events-list"><article class="event-card"><img src="{IMG}"><div class="event-card-body"><div class="event-date-badge">سه‌شنبه ۲۰ مرداد · ساعت ۱۲</div><h3>دورهمی انگلیسی — نوبت جدید</h3><p>دورهمی انگلیسی هر هفته برگزار می‌شود.</p><div class="event-meta-v118"><div class="event-meta-row"><span class="ui-icon">⌖</span><div class="event-meta-copy"><small>محل برگزاری</small><strong>اتاق VIP سکنا</strong></div></div><div class="event-meta-row"><span class="ui-icon">◉</span><div class="event-meta-copy"><small>ظرفیت کل</small><strong>۱۵ نفر</strong></div></div><div class="event-meta-row"><span class="ui-icon">◫</span><div class="event-meta-copy"><small>هزینه</small><strong>۱۵۰٬۰۰۰ تومان</strong></div></div></div><button class="btn btn-primary">اطلاعات بیشتر</button></div></article></div></section></div>
<div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel can-order"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><h2 id="itemDetailTitle"></h2><div class="item-detail-price" id="itemDetailPrice"></div><div class="item-detail-tags" id="itemDetailTags"></div><p class="item-detail-description" id="itemDetailDescription"></p><div class="item-detail-option-slot" id="itemDetailOptionSlot"><details class="item-detail-note" id="itemDetailNoteBlock"><summary>افزودن یادداشت</summary><textarea id="itemDetailNote"></textarea></details><section class="item-detail-suggestion hidden" id="itemDetailSuggestion"><div class="item-detail-suggestion-media" id="itemDetailSuggestionMedia"></div><div class="item-detail-suggestion-copy"><small>پیشنهاد مکمل</small><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span></div><button id="itemDetailSuggestionAdd">+</button></section></div></div></div><footer class="item-detail-footer"><div class="item-detail-actions" id="itemDetailActions"></div><button class="item-detail-primary" id="itemDetailPrimary"><span>افزودن به سفارش</span></button></footer></article></div>
<div class="guest-action-dock" id="guestActionDock"><button class="waiter-fab" id="waiterButton"><span class="visually-hidden" id="waiterButtonText">فراخوان گارسون</span>🔔</button><div class="cart-bar-v12 hidden" id="cartBar"><button id="openCart"><span class="cart-bar-icon">🛒</span><strong>دیدن سفارش</strong><b id="cartCount" class="cart-count-v12">۰</b></button><div class="cart-bar-summary"><small>جمع سبد</small><strong id="cartBarTotal"></strong></div></div></div>
<div class="drawer-backdrop" id="drawerBackdrop"></div><section class="cart-drawer" id="cartDrawer" aria-hidden="true"><div class="drawer-head"><div><small id="cartContext"></small><h2>مرور سفارش</h2><div class="cart-mode-row hidden" id="cartModeRow"><span id="cartSummaryCaption"></span><button class="cart-edit-exit hidden" id="guestEditExit">انصراف از ویرایش</button></div></div><button id="closeCart">×</button></div><div class="drawer-content" id="cartDrawerContent"><div class="guest-order-conflict hidden" id="guestOrderConflict"><div><strong id="guestOrderConflictTitle">این سفارش تغییر کرده است.</strong><span id="guestOrderConflictText"></span></div><div class="guest-order-conflict-actions"><button type="button" id="guestOrderConflictPrimary"></button><button type="button" id="guestOrderConflictSecondary"></button></div></div><div id="cartItems"></div><button class="guest-takeaway-disclosure hidden" id="guestTakeawayDisclosure" type="button" aria-haspopup="dialog" aria-controls="guestTakeawayLayer"><span><strong id="guestTakeawayDisclosureTitle">بیرون‌بر هم دارید؟</strong><small id="guestTakeawayDisclosureCopy">در صورت نیاز، موارد بیرون‌بر را مشخص کنید.</small></span><b aria-hidden="true">‹</b></button><div id="cartScrollCue"></div><div id="cartStationWarning" class="hidden"></div><details class="order-note-field" id="orderNoteDisclosure"><summary><span>افزودن یادداشت سفارش</span> <small>اختیاری</small><b>‹</b></summary><textarea id="customerNote"></textarea></details><div id="cartSuggestion" class="cart-suggestion hidden"></div></div><div class="drawer-foot"><div class="total-row"><span><b id="cartTotalLabel">جمع سفارش</b> <small class="cart-fulfillment-summary hidden" id="cartFulfillmentSummary" role="status" aria-live="polite" aria-atomic="true"></small></span><span id="cartTotal"></span></div><button class="submit-order-button" id="submitOrder"><span id="submitOrderText">ثبت</span></button></div></section><div class="guest-takeaway-layer hidden" id="guestTakeawayLayer" aria-hidden="true"><button class="guest-takeaway-backdrop" id="guestTakeawayBackdrop" type="button" aria-label="انصراف"></button><section class="guest-takeaway-sheet" id="guestTakeawaySheet" role="dialog" aria-modal="true" aria-labelledby="guestTakeawayTitle"><div class="guest-takeaway-grip"></div><header><div><h3 id="guestTakeawayTitle">موارد بیرون‌بر</h3><p>بقیه اقلام داخل کافه سرو می‌شوند.</p></div><button id="guestTakeawayClose" type="button">×</button></header><div class="guest-takeaway-list" id="guestTakeawayList"></div><div class="guest-takeaway-bulk"><button class="guest-takeaway-all" id="guestTakeawayAll" type="button">همه بیرون‌بر</button></div><footer><div><button id="guestTakeawayConfirm" type="button">تأیید</button><button id="guestTakeawayCancel" type="button">انصراف</button></div></footer></section></div>
<div class="guest-orders-modal hidden" id="guestOrdersModal"><div class="guest-orders-box"><button class="waiter-modal-close" id="guestOrdersClose">×</button><div class="guest-orders-head"><div><small>حساب جاری همین دستگاه</small><h2>سفارش‌های شما</h2></div></div><div class="guest-orders-list" id="guestOrdersList"></div><p class="guest-orders-empty hidden" id="guestOrdersEmpty"></p><button id="guestOrdersDone">بازگشت</button></div></div>
<div class="guest-confirm-modal hidden" id="guestConfirmModal"><div class="guest-confirm-box"><h2 id="guestConfirmTitle"></h2><p id="guestConfirmText"></p><div class="guest-confirm-actions"><button id="guestConfirmAccept" class="guest-confirm-accept"></button><button id="guestConfirmCancel" class="guest-confirm-cancel"></button></div></div></div><div id="guestToast" class="hidden"></div><div id="orderLiveStatus"></div><span id="orderStatusText"></span>
<script>window.CAFE_MENU=[
{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',price:460000,image:'{IMG}',available:1,takeaway_allowed:1,suggested_item_id:2,tags:[]}},
{{id:2,name:'لیموناد تازه',category:'شربت‌خانه',description:'لیموی تازه',price:180000,image:'{IMG}',available:1,takeaway_allowed:1,suggested_item_id:null,tags:[]}},
{{id:3,name:'برگر گوشت',category:'خوراک روز',description:'برگر گوشت، گوجه، خیارشور، نان برگر و سس اختصاصی',price:465000,image:'{IMG}',available:1,takeaway_allowed:1,suggested_item_id:2,tags:[]}},
{{id:4,name:'پاستا چیکن آلفردو',category:'خوراک روز',description:'مرغ، قارچ، خامه، شیر و پنیر پارمسان',price:585000,image:'{IMG}',available:1,takeaway_allowed:0,suggested_item_id:2,tags:[]}}];
window.CAFE_TABLE={{token:'t',name:'میز ۱',code:'1'}};window.CAFE_SESSION={{token:'s'}};window.CAFE_CURRENCY='تومان';window.CAFE_CAN_ORDER=true;window.CAFE_ORDERING_ENABLED=true;window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_STATION_STATE_HASH='';window.CAFE_MESSAGES={{item_note_placeholder_enabled:true}};window.CAFE_ANALYTICS_ENABLED=false;window.CAFE_WAITER_ENABLED=true;window.CAFE_GUEST_ORDERS_API_URL='/orders';window.CAFE_CONTEXT_API_URL='/context';
const testOrders=[{{order_code:'O30',order_number:30,status:'accounted',status_label:'تأییدشده',total_amount:460000,can_edit:false,can_cancel:false,edit_signature:'sig-30',client_token:'c30',items:[{{id:1,name:'زینگر',quantity:1,unit_price:460000,line_total:460000,note:'',fulfillment_mode:'dine_in'}}]}},{{order_code:'O31',order_number:31,status:'new',status_label:'جدید',total_amount:1045000,can_edit:true,can_cancel:true,edit_signature:'sig-A',client_token:'c31',items:[{{id:1,name:'زینگر',quantity:1,unit_price:460000,line_total:460000,note:'سس جدا',fulfillment_mode:'dine_in'}},{{id:4,name:'پاستا',quantity:1,unit_price:585000,line_total:585000,note:'',fulfillment_mode:'dine_in'}}]}}];
window.forceEditConflict='';
window.fetch=async(url,opts={{}})=>{{let body={{}};try{{body=JSON.parse(opts.body||'{{}}')}}catch(e){{}};if(String(url).includes('/context'))return new Response(JSON.stringify({{success:true,can_order:true,ordering_enabled:true,requires_operator_confirmation:false,waiter_enabled:true,session:window.CAFE_SESSION,table:window.CAFE_TABLE,station_state_hash:''}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='list')return new Response(JSON.stringify({{success:true,orders:testOrders}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='update'&&window.forceEditConflict){{const order=testOrders.find(o=>o.order_code===body.order_code);if(window.forceEditConflict==='changed'){{order.edit_signature='sig-B';order.items[0].quantity=2;order.items[0].line_total=920000;order.total_amount=1505000;window.forceEditConflict='';return new Response(JSON.stringify({{success:false,code:'order_changed',message:'سفارش تغییر کرده است.'}}),{{status:409,headers:{{'Content-Type':'application/json'}}}})}}order.status='accounted';order.can_edit=false;order.can_cancel=false;order.edit_signature='sig-C';window.forceEditConflict='';return new Response(JSON.stringify({{success:false,code:'order_not_editable',message:'سفارش دیگر قابل ویرایش نیست.'}}),{{status:409,headers:{{'Content-Type':'application/json'}}}})}}if(String(url).includes('/orders')&&body.action==='update')return new Response(JSON.stringify({{success:true,order_code:body.order_code,order_number:31,status:'new',client_token:'c31'}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});if(String(url).includes('/orders')&&body.action==='cancel')return new Response(JSON.stringify({{success:true}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});return new Response(JSON.stringify({{success:true,order_code:'NEW',order_number:40,status:'new',client_token:'n40'}}),{{status:200,headers:{{'Content-Type':'application/json'}}}})}};</script><script>{SHEET}</script><script>{POLICY}</script><script>{JS}</script></body></html>'''

DEFAULT_SIZES=((320,720),(390,844),(412,915),(768,900),(1024,768))
requested_widths=os.environ.get('SOKNA_UI_WIDTHS', os.environ.get('SOKNA_UI_WIDTH','')).strip()
SIZES=DEFAULT_SIZES
if requested_widths:
    wanted={part.strip() for part in requested_widths.split(',') if part.strip()}
    SIZES=tuple(size for size in DEFAULT_SIZES if str(size[0]) in wanted)
    if not SIZES: raise SystemExit(f'Unsupported SOKNA_UI_WIDTHS={requested_widths}')

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in SIZES:
        page=browser.new_page(viewport={'width':width,'height':height})
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(HTML,wait_until='load');page.add_style_tag(content='*{animation:none!important;transition:none!important;scroll-behavior:auto!important}');page.wait_for_timeout(80)
        assert not errors,errors
        assert page.evaluate('document.documentElement.scrollWidth') <= width+1
        assert page.locator('#menuSearch').get_attribute('placeholder') == 'چی دوست داری بخوری؟'
        assert page.locator('.menu-search-wrap-v12').evaluate("e=>getComputedStyle(e).backgroundColor") != page.locator('body').evaluate("e=>getComputedStyle(e).backgroundColor")
        assert page.locator('.menu-search-wrap-v12 input').count()==1 and page.locator('#searchClear').count()==1
        header_h=page.locator('.guest-header-inner').bounding_box()['height']; assert header_h <= 72,header_h
        shell_h=page.locator('.category-rail-shell').bounding_box()['height']; assert shell_h <= 66,shell_h
        for tab in page.locator('.category-orbit a').all():
            label=tab.locator('b').bounding_box(); icon=tab.locator('.category-orb').bounding_box()
            assert abs((label['y']+label['height']/2)-(icon['y']+icon['height']/2)) <= 1.5,(label,icon)
        assert page.locator('#featuredMenuTitle, .featured-menu-heading h2').first.inner_text() == 'پیشنهادهای کافه'
        assert page.locator('.featured-menu-heading span').count() == 0
        # Sticky navigation keeps exact active contrast instead of the old faded state.
        active_before=page.locator('.category-orbit a.active').evaluate('e=>({bg:getComputedStyle(e).backgroundColor,color:getComputedStyle(e).color,opacity:getComputedStyle(e).opacity})')
        page.evaluate("window.scrollTo(0,900)");page.wait_for_timeout(60)
        active_bg=page.locator('.category-orbit a.active').evaluate('e=>getComputedStyle(e).backgroundColor')
        active_color=page.locator('.category-orbit a.active').evaluate('e=>getComputedStyle(e).color')
        assert active_bg not in ('rgba(0, 0, 0, 0)','transparent')
        assert active_bg == active_before['bg'] and active_color == active_before['color']
        assert page.locator('.category-orbit a.active').evaluate('e=>getComputedStyle(e).opacity') == '1'
        page.evaluate('window.scrollTo(0,0)');page.wait_for_timeout(40)
        assert active_color != page.locator('.category-orbit a:not(.active)').first.evaluate('e=>getComputedStyle(e).color')
        page.locator('[data-open-events]').click();page.wait_for_timeout(70)
        assert page.locator('.guest-events-head h2').inner_text()=='رویدادهای سکنا'
        assert page.locator('.guest-events-head small').count()==0
        event_image=page.locator('.event-card>img').bounding_box(); assert abs((event_image['width']/event_image['height'])-(16/9)) < .08,event_image
        assert page.locator('.event-meta-row').count()==3
        page.locator('[data-close-events]').click();page.wait_for_timeout(40)
        # Card controls retain approved shape but fit within every product card.
        page.locator('[data-add-item="3"]').click();page.wait_for_timeout(50)
        card=page.locator('[data-menu-item][data-item-id="3"]'); qty=card.locator('.inline-qty').bounding_box(); copy=card.locator('.item-copy-v12').bounding_box()
        assert qty['x'] >= copy['x']-1 and qty['x']+qty['width'] <= copy['x']+copy['width']+1,(qty,copy)
        assert 46 <= qty['height'] <= 50 and 116 <= qty['width'] <= 122
        minus=card.locator('[data-inline-dec]').bounding_box(); plus=card.locator('[data-add-item]').bounding_box(); assert abs(minus['width']-plus['width']) <= 1,(minus,plus)
        # The real full-card button is the canonical detail interaction owner; text beneath it is intentionally not a click target.
        card.locator('[data-open-item-detail]').click();page.wait_for_timeout(80)
        assert page.locator('#itemDetailCategory').count()==0
        close_box=page.locator('.item-detail-close').bounding_box(); assert close_box['width'] <= 44 and close_box['height'] <= 44
        detail_qty=page.locator('.detail-qty-control').bounding_box(); assert detail_qty['width'] <= 128
        detail_minus=page.locator('[data-detail-dec]').bounding_box();detail_plus=page.locator('[data-detail-inc]').bounding_box();assert abs(detail_minus['width']-detail_plus['width'])<=1,(detail_minus,detail_plus)
        primary=page.locator('#itemDetailPrimary'); assert primary.locator('strong').count()==0
        assert primary.evaluate("e=>getComputedStyle(e).justifyContent")=='center'
        page.locator('[data-close-item-detail]').click();page.wait_for_timeout(60)
        # Recommendation belongs to the source item; removing that source must not leave a stale upsell.
        page.locator('#openCart').evaluate('e=>e.click()');page.wait_for_timeout(50)
        assert page.locator('#cartSuggestion').is_visible() and 'لیموناد تازه' in page.locator('#cartSuggestion').inner_text()
        page.locator('.cart-line').filter(has_text='برگر گوشت').first.locator('[data-dec="3"]').click();page.wait_for_timeout(25)
        assert not page.locator('#cartSuggestion').is_visible(),'stale recommendation survived source removal'
        page.locator('#closeCart').evaluate('e=>e.click()');page.wait_for_timeout(30)
        page.locator('[data-add-item="3"]').evaluate('e=>e.click()');page.wait_for_timeout(20)
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
        # Operational note comes before upsell; large carts collapse the secondary suggestion.
        assert page.evaluate("document.querySelector('#orderNoteDisclosure').compareDocumentPosition(document.querySelector('#cartSuggestion')) & Node.DOCUMENT_POSITION_FOLLOWING")
        assert page.locator('#cartSuggestion .cart-suggestion-details').count()==1
        assert not page.locator('#cartSuggestion .cart-suggestion-details').get_attribute('open')
        if width in (390,412):
            heights=page.locator('.cart-line').evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
            assert max(heights) <= 92,heights
        # Quantity rerender must preserve keyboard focus without changing touch target geometry.
        if width == 390:
            focus_inc=page.locator('.cart-line .qty-control [data-inc]').first
            focus_id=focus_inc.get_attribute('data-inc')
            focus_inc.focus(); page.keyboard.press('Enter'); page.wait_for_timeout(35)
            assert page.locator(f'[data-inc="{focus_id}"]').evaluate('e=>document.activeElement===e'), 'cart quantity focus was lost after rerender'
        # Guest takeaway stays out of normal item rows and is staged in a focused bottom sheet.
        assert page.locator('#guestTakeawayDisclosure').is_visible()
        assert page.locator('#guestTakeawayDisclosureTitle').inner_text().strip()=='بیرون‌بر هم دارید؟'
        assert page.locator('.cart-line-takeaway-badge').count()==0
        page.locator('#guestTakeawayDisclosure').click(); page.wait_for_timeout(30)
        assert page.locator('#guestTakeawayLayer').is_visible()
        assert page.locator('#guestTakeawaySheet').is_visible()
        layer_box=page.locator('#guestTakeawayLayer').bounding_box(); viewport_h=page.evaluate('window.innerHeight'); assert layer_box and layer_box['y'] <= 1 and layer_box['height'] >= viewport_h-1,(width,layer_box,viewport_h)
        assert page.locator('#guestTakeawayLayer').evaluate("e=>e.parentElement===document.body")
        assert page.locator('.guest-takeaway-row').count()==page.locator('.cart-line').count()
        # The full-screen backdrop closes even above the cart drawer, and the header is the real swipe zone.
        page.locator('#guestTakeawayBackdrop').click(position={'x':8,'y':8}); page.wait_for_timeout(25)
        assert not page.locator('#guestTakeawayLayer').is_visible()
        page.locator('#guestTakeawayDisclosure').click(); page.wait_for_timeout(20)
        swipe_js = "h=>{const r=h.getBoundingClientRect(),x=r.left+r.width/2,y=r.top+20;for(const [type,cy] of [['pointerdown',y],['pointermove',y+150],['pointerup',y+150]])h.dispatchEvent(new PointerEvent(type,{bubbles:true,cancelable:true,pointerId:77,pointerType:'touch',button:0,clientX:x,clientY:cy}))}"
        page.locator('#guestTakeawaySheet>header').evaluate(swipe_js)
        page.wait_for_timeout(180)
        assert not page.locator('#guestTakeawayLayer').is_visible(),'header swipe-down did not dismiss takeaway sheet'
        page.locator('#guestTakeawayDisclosure').click(); page.wait_for_timeout(20)
        # Item 4 is intentionally not takeaway-eligible.
        ineligible=page.locator('.guest-takeaway-row').filter(has_text='پاستا چیکن آلفردو').first
        assert ineligible.locator('.guest-takeaway-ineligible').inner_text().strip()=='فقط داخل کافه'
        assert ineligible.locator('.guest-takeaway-stepper').count()==0
        # Staged changes are discarded by Cancel.
        page.locator('#guestTakeawayAll').click(); page.wait_for_timeout(20)
        assert 'همه داخل کافه' in page.locator('#guestTakeawayAll').inner_text()
        page.locator('#guestTakeawayCancel').click(); page.wait_for_timeout(20)
        assert not page.locator('#guestTakeawayLayer').is_visible()
        assert page.locator('.cart-line-takeaway-badge').count()==0
        assert page.locator('#guestTakeawayDisclosureTitle').inner_text().strip()=='بیرون‌بر هم دارید؟'
        # Re-open, make eligible lines takeaway, and confirm.
        page.locator('#guestTakeawayDisclosure').click(); page.wait_for_timeout(20)
        page.locator('#guestTakeawayAll').click(); page.wait_for_timeout(20)
        assert ineligible.locator('.guest-takeaway-ineligible').inner_text().strip()=='فقط داخل کافه'
        page.locator('#guestTakeawayConfirm').click(); page.wait_for_timeout(25)
        assert not page.locator('#guestTakeawayLayer').is_visible()
        assert page.locator('.cart-line-takeaway-badge').count()==page.locator('.cart-line').count()-1
        assert 'عدد بیرون‌بر' in page.locator('#cartFulfillmentSummary').inner_text()
        assert page.locator('#guestTakeawayDisclosureTitle').inner_text().strip()=='تغییر موارد بیرون‌بر'
        # Partial quantity is Bidi-safe and persists only after Confirm.
        page.locator('#guestTakeawayDisclosure').click(); page.wait_for_timeout(20)
        first_sheet=page.locator('.guest-takeaway-row').filter(has_text='زینگر').first
        minus=first_sheet.locator('[data-guest-takeaway-delta="-1"]')
        minus.click(); page.wait_for_timeout(15)
        ratio_text=first_sheet.locator('.fulfillment-ratio').text_content().replace('\n','').replace(' ','')
        assert ratio_text=='۳از۴', ratio_text
        # Escape is cancellation: normal cart state does not change.
        before=page.locator('.cart-line').filter(has_text='زینگر').first.locator('.cart-line-takeaway-badge').inner_text()
        page.keyboard.press('Escape'); page.wait_for_timeout(20)
        assert not page.locator('#guestTakeawayLayer').is_visible()
        after=page.locator('.cart-line').filter(has_text='زینگر').first.locator('.cart-line-takeaway-badge').inner_text()
        assert before==after
        # Mixed cart never leaks takeaway into a newly added line.
        page.locator('#cartDrawerContent').evaluate('e=>e.scrollTop=e.scrollHeight'); page.wait_for_timeout(15)
        page.locator('#closeCart').evaluate('e=>e.click()'); page.wait_for_timeout(30)
        if page.locator('[data-add-item="2"]').count(): page.locator('[data-add-item="2"]').evaluate('e=>e.click()')
        page.locator('#openCart').evaluate('e=>e.click()'); page.wait_for_timeout(50)
        assert page.locator('#cartDrawerContent').evaluate('e=>e.scrollTop') <= 2,'changed cart reopened at stale scroll position'
        new_line=page.locator('.cart-line').filter(has_text='لیموناد تازه').first
        if new_line.count(): assert new_line.locator('.cart-line-takeaway-badge').count()==0
        assert page.evaluate('document.documentElement.scrollWidth') <= width+1
        cart_minus=page.locator('.cart-line .qty-control [data-dec]').first.bounding_box();cart_plus=page.locator('.cart-line .qty-control [data-inc]').first.bounding_box();assert abs(cart_minus['width']-cart_plus['width'])<=1,(cart_minus,cart_plus)
        page.locator('#closeCart').click();page.wait_for_timeout(60)
        # Current orders show amounts once, without total in the modal header.
        page.locator('#openRecentOrder').click();page.wait_for_timeout(120)
        assert page.locator('#guestOrdersTotal').count()==0
        assert page.locator('.guest-orders-currency').count()==1
        assert page.locator('.guest-order-line').filter(has_text='تومان').count()==0
        assert page.locator('.guest-order-card').count()==2
        assert page.locator('.guest-order-card').filter(has_text='تأییدشده').locator('[data-edit-guest-order]').count()==0
        mutable_card=page.locator('.guest-order-card').filter(has_text='سفارش ۳۱')
        assert mutable_card.filter(has_text='منتظر تأیید کافه').count()==1
        assert mutable_card.locator('[data-edit-guest-order]').count()==1
        assert mutable_card.locator('[data-cancel-guest-order]').count()==1
        # Edit and cancel use branded Persian confirmations, never native browser dialogs.
        native=[]; page.on('dialog',lambda d:(native.append(d.message),d.dismiss()))
        mutable_card.locator('[data-edit-guest-order]').click();page.wait_for_timeout(70)
        assert page.locator('#guestConfirmModal').is_visible()
        assert 'اقلام فعلی سبد حذف می‌شوند' in page.locator('#guestConfirmText').inner_text()
        assert not native
        page.locator('#guestConfirmCancel').click();page.wait_for_timeout(60)
        mutable_card.locator('[data-cancel-guest-order]').click();page.wait_for_timeout(70)
        assert 'امکان بازگرداندن' in page.locator('#guestConfirmText').inner_text()
        assert page.locator('#guestConfirmAccept').inner_text()=='لغو سفارش'
        assert not native
        page.locator('#guestConfirmCancel').click();page.wait_for_timeout(60)
        # Explicit edit is a distinct state and stale server versions never fall through into a new/append order.
        if width == 390:
            mutable_card.locator('[data-edit-guest-order]').click();page.wait_for_timeout(50)
            page.locator('#guestConfirmAccept').click();page.wait_for_timeout(70)
            assert page.locator('#cartDrawer').get_attribute('aria-hidden') == 'false'
            assert page.locator('#cartModeRow').is_visible()
            assert 'ذخیره تغییرات سفارش ۳۱' in page.locator('#submitOrderText').inner_text()
            page.evaluate("window.forceEditConflict='changed'")
            page.locator('#submitOrder').click();page.wait_for_timeout(140)
            assert page.locator('#guestOrderConflict').is_visible()
            assert page.locator('#submitOrder').is_disabled()
            assert page.locator('#guestOrderConflictPrimary').inner_text() == 'بارگذاری آخرین نسخه'
            assert 'ابتدا وضعیت سفارش' in page.locator('#submitOrderText').inner_text()
            page.locator('#guestOrderConflictPrimary').click();page.wait_for_timeout(70)
            assert not page.locator('#guestOrderConflict').is_visible()
            assert 'ذخیره تغییرات سفارش ۳۱' in page.locator('#submitOrderText').inner_text()
            # A terminal transition stays locked; it must never silently become «افزودن به سفارش میز».
            page.evaluate("window.forceEditConflict='locked'")
            page.locator('#submitOrder').click();page.wait_for_timeout(140)
            assert page.locator('#guestOrderConflict').is_visible()
            assert page.locator('#guestOrderConflictPrimary').inner_text() == 'مشاهده سفارش‌ها'
            assert page.locator('#submitOrder').is_disabled()
            assert 'افزودن به' not in page.locator('#submitOrderText').inner_text()
            assert 'ثبت سفارش' not in page.locator('#submitOrderText').inner_text()
            page.locator('#guestOrderConflictSecondary').click();page.wait_for_timeout(50)
            assert page.locator('#guestConfirmModal').is_visible()
            page.locator('#guestConfirmCancel').click();page.wait_for_timeout(30)
        # Waiter modal has no duplicate raw close button in the final DOM.
        assert page.locator('#waiterModalClose').count()==0
        page.screenshot(path=str(SCREENSHOT_DIR / f'sokna-1276-ui-{width}.png'),full_page=False)
        page.close()
    browser.close()
print('Guest review/takeaway acceptance PASS at ' + ', '.join(str(width) for width,_ in SIZES) + ' widths.')
