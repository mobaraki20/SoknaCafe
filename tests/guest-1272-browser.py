#!/usr/bin/env python3
from pathlib import Path
import base64
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = '\n'.join((ROOT / p).read_text(encoding='utf-8') for p in (
    'assets/css/tokens.css', 'assets/css/app.css', 'assets/css/responsive.css', 'assets/css/guest-menu.css'
))
JS = (ROOT / 'assets/js/menu.js').read_text(encoding='utf-8')
SVG = '''<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="800" height="600" fill="#dcd1bf"/><rect x="0" y="0" width="800" height="300" fill="#b8a991"/><circle cx="400" cy="400" r="150" fill="#2f7566"/></svg>'''
IMG = 'data:image/svg+xml;base64,' + base64.b64encode(SVG.encode()).decode()

HTML = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="test"><style>:root{{--primary:#2f7566;--accent:#c88435;--app-bg:#f6f1e6;--on-primary:#fff;--on-accent:#222;--font-ui:Tahoma,Arial,sans-serif}}{CSS}</style></head>
<body class="guest-menu-page theme-courtyard density-balanced menu-layout-editorial">
<header class="guest-header"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">س</div><div class="guest-brand-copy"><strong>کافه سکنا</strong><span>منوی میز شما</span></div></div><div class="guest-header-actions"><button class="guest-event-access" type="button" data-open-events><span>رویدادها</span></button><div class="guest-table-label"><strong>میز ۱</strong></div></div></div></header>
<section class="recent-order" id="recentOrderBar"><div class="recent-order-heading"><small>وضعیت سفارش</small><strong id="recentOrderCode">سفارش شما در انتظار تأیید</strong><span id="recentOrderMeta">۱ نوبت</span></div><span id="recentOrderStatus">کافه سفارش را بررسی می‌کند.</span><button type="button" id="openRecentOrder">مشاهده</button><div class="order-progress"><i></i><i></i></div></section>
<main class="guest-main">
<section class="guest-search-compact" id="guestSearchZone"><div class="guest-search-panel"><div class="menu-search-wrap-v12"><button id="searchOpen" class="search-focus-proxy">⌕</button><input id="menuSearch" placeholder="جست‌وجو در نام یا دسته‌بندی"><button id="searchClear" class="hidden">×</button></div><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><button id="searchBackToMenu"></button><div id="searchResultList"></div></div></div></section>
<div class="horizontal-rail-shell category-rail-shell"><nav class="category-orbit horizontal-rail"><a class="active"><span class="category-orb">☰</span><b>همه</b></a><a><span class="category-orb">●</span><b>خوراک روز</b></a><a><span class="category-orb">●</span><b>شربت‌خانه</b></a></nav></div>
<section class="featured-menu-zone"><div class="featured-menu-heading"><div><span>انتخاب‌های ویژه کافه</span><h2>پیشنهاد امروز</h2></div></div><div class="horizontal-rail-shell featured-rail-shell"><div class="featured-menu-rail horizontal-rail">
<article class="featured-menu-card" data-featured-item data-item-id="1"><img src="{IMG}" alt="زینگر"><div class="featured-menu-copy"><small>خوراک روز</small><h3>زینگر</h3><div><strong>۴۶۰٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="1"><button type="button" data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button class="inline-add" type="button" data-add-item="1">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="featured-menu-card" data-featured-item data-item-id="2"><img src="{IMG}" alt="لیموناد"><div class="featured-menu-copy"><small>شربت‌خانه</small><h3>لیموناد تازه</h3><div><strong>۱۸۰٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="2"><button type="button" data-inline-dec="2">−</button><span data-inline-count="2">۰</span><button class="inline-add" type="button" data-add-item="2">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></div></section>
<section class="category-section-v12" data-category><div class="section-title-v12"><h2>خوراک روز</h2><span>۲ انتخاب</span></div><div class="items-list-v12">
<article class="menu-item-v12 featured" data-menu-item data-item-id="3"><div class="item-media-v12"><img src="{IMG}" alt="برگر گوشت"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12"><div><h3>برگر گوشت</h3><p>برگر گوشت ۱۵۰–۱۸۰ گرم، گوجه، خیارشور، کاهوپیچ، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="3"><button type="button" data-inline-dec="3">−</button><span data-inline-count="3">۰</span><button class="inline-add" type="button" data-add-item="3">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
<article class="menu-item-v12" data-menu-item data-item-id="4"><div class="item-media-v12"><img src="{IMG}" alt="پاستا"></div><div class="item-copy-v12"><div><h3>پاستا چیکن آلفردو</h3><p>مرغ، قارچ، خامه، شیر، پنیر پارمسان و جعفری خشک.</p></div><div class="item-action-row"><strong>۵۸۵٬۰۰۰ تومان</strong><div class="inline-qty" data-inline-qty="4"><button type="button" data-inline-dec="4">−</button><span data-inline-count="4">۰</span><button class="inline-add" type="button" data-add-item="4">+<span class="inline-add-label">افزودن</span></button></div></div></div></article>
</div></section><div style="height:650px" aria-hidden="true"></div></main>

<div class="guest-events-sheet hidden" id="eventsSheet" role="dialog"><section class="guest-events-panel"><header class="guest-events-head"><div><small>رویدادهای سکنا</small><h2>رویدادها</h2></div><button class="guest-events-close" data-close-events>×</button></header><div class="events-list"><article class="event-card"><div class="event-card-body"><h3>ورکشاپ سفال</h3><p>چهارشنبه، کافه سکنا</p></div></article></div></section></div>
<div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel can-order"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><small id="itemDetailCategory"></small><h2 id="itemDetailTitle"></h2><div class="item-detail-price" id="itemDetailPrice"></div><div class="item-detail-tags" id="itemDetailTags"></div><p class="item-detail-description" id="itemDetailDescription"></p><details class="item-detail-note"><summary>+ افزودن یادداشت <small>اختیاری</small></summary><textarea id="itemDetailNote"></textarea></details><section class="item-detail-suggestion hidden" id="itemDetailSuggestion"><div><small>کنارش پیشنهاد می‌کنیم</small><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span></div><button id="itemDetailSuggestionAdd"></button></section></div></div><footer class="item-detail-footer"><div class="item-detail-actions" id="itemDetailActions"></div><button class="item-detail-primary" id="itemDetailPrimary"><span>افزودن به سفارش</span><strong id="itemDetailPrimaryTotal"></strong></button></footer></article></div>
<div class="guest-action-dock" id="guestActionDock"><button class="waiter-fab" id="waiterButton"><span id="waiterButtonText">فراخوان گارسون</span></button><div class="cart-bar-v12 hidden" id="cartBar"><button id="openCart"><span class="cart-bar-icon">🛒</span><strong>دیدن سفارش</strong><b id="cartCount" class="cart-count-v12">۰</b></button><div class="cart-bar-summary"><small>جمع سبد</small><strong id="cartBarTotal"></strong></div></div></div>
<div class="drawer-backdrop" id="drawerBackdrop"></div><section class="cart-drawer" id="cartDrawer" aria-hidden="true"><div class="drawer-head"><div><small id="cartContext"></small><h2>مرور سفارش</h2><span id="cartSummaryCaption"></span></div><button id="closeCart">×</button></div><div class="drawer-content" id="cartDrawerContent"><div id="cartItems"></div><div id="cartScrollCue"></div><div id="cartStationWarning" class="hidden"></div><div id="cartSuggestion" class="cart-suggestion hidden"></div><details id="orderNoteDisclosure"><summary>+ افزودن یادداشت کلی سفارش</summary><textarea id="customerNote"></textarea></details></div><div class="drawer-foot"><div class="total-row"><span>جمع سفارش</span><span id="cartTotal"></span></div><button id="submitOrder"><span id="submitOrderText">ثبت سفارش</span><strong id="submitOrderTotal"></strong></button></div></section>
<div class="guest-orders-modal hidden" id="guestOrdersModal" role="dialog"><div class="guest-orders-box"><button class="waiter-modal-close" id="guestOrdersClose">×</button><div class="guest-orders-head"><div><small>حساب جاری همین دستگاه</small><h2>سفارش‌های شما</h2></div><strong id="guestOrdersTotal"></strong></div><div class="guest-orders-list" id="guestOrdersList"></div><p class="guest-orders-empty hidden" id="guestOrdersEmpty"></p><button id="guestOrdersDone">بازگشت به منو</button></div></div>
<div id="guestToast" class="hidden"></div><div id="orderLiveStatus"></div><span id="orderStatusText"></span>
<script>
window.CAFE_MENU=[
{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',price:460000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:2,name:'لیموناد تازه',category:'شربت‌خانه',description:'لیموی تازه',price:180000,image:'{IMG}',available:1,suggested_item_id:null,tags:[]}},
{{id:3,name:'برگر گوشت',category:'خوراک روز',description:'برگر گوشت ۱۵۰–۱۸۰ گرم، گوجه، خیارشور، کاهوپیچ، نان برگر و سس اختصاصی.',price:465000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}},
{{id:4,name:'پاستا چیکن آلفردو',category:'خوراک روز',description:'مرغ، قارچ، خامه، شیر، پنیر پارمسان و جعفری خشک.',price:585000,image:'{IMG}',available:1,suggested_item_id:2,tags:[]}}
];
window.CAFE_TABLE={{token:'table-test',name:'میز ۱',code:'1'}};window.CAFE_SESSION={{token:'session-test'}};window.CAFE_CURRENCY='تومان';window.CAFE_CAN_ORDER=true;window.CAFE_ORDERING_ENABLED=true;window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_STATION_STATE_HASH='';window.CAFE_MESSAGES={{item_note_placeholder_enabled:true}};window.CAFE_ANALYTICS_ENABLED=false;window.CAFE_WAITER_ENABLED=true;window.CAFE_GUEST_ORDERS_API_URL='/orders';
const testOrders=[{{order_code:'O31',order_number:31,status:'pending_approval',status_label:'منتظر تأیید اپراتور',total_amount:1445000,can_edit:true,can_cancel:true,items:[{{id:1,name:'زینگر',quantity:1,unit_price:460000,line_total:460000,note:''}},{{id:3,name:'برگر گوشت',quantity:1,unit_price:465000,line_total:465000,note:'بدون خیارشور'}},{{id:4,name:'پاستا چیکن آلفردو',quantity:1,unit_price:520000,line_total:520000,note:''}}]}}];
window.fetch=async(url,opts={{}})=>{{let body={{}};try{{body=JSON.parse(opts.body||'{{}}')}}catch(e){{}};if(String(url).includes('/orders')&&body.action==='list')return new Response(JSON.stringify({{success:true,orders:testOrders}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});return new Response(JSON.stringify({{success:true,orders:[],ordering_enabled:true,can_order:true,requires_operator_confirmation:false,station_state_hash:''}}),{{status:200,headers:{{'Content-Type':'application/json'}}}})}};
</script><script>{JS}</script></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width, height in ((320, 720), (390, 844), (412, 915), (1024, 768)):
        page = browser.new_page(viewport={'width': width, 'height': height})
        errors=[]; page.on('pageerror',lambda err: errors.append(str(err)))
        page.set_content(HTML, wait_until='load'); page.wait_for_timeout(120)
        assert not errors, errors
        assert page.evaluate('document.documentElement.scrollWidth') <= width + 1
        # Cards: image is physically left, text physically right, and mobile image keeps a stable readable proportion.
        card = page.locator('.menu-item-v12').first
        media = card.locator('.item-media-v12').bounding_box(); copy = card.locator('.item-copy-v12').bounding_box(); cardbox=card.bounding_box()
        assert media['x'] < copy['x'], (media,copy)
        if width <= 560:
            ratio=media['width']/cardbox['width']
            assert .28 <= ratio <= .34, ratio
        # Quantity control stays compact but preserves an accessible >=44px touch target and physical order.
        page.locator('[data-add-item="3"]').click(); page.wait_for_timeout(40)
        qty=page.locator('[data-inline-qty="3"]').bounding_box(); minus=page.locator('[data-inline-dec="3"]').bounding_box(); plus=page.locator('[data-add-item="3"]').bounding_box()
        assert 116 <= qty['width'] <= 126 and 44 <= qty['height'] <= 50.5, qty
        assert 36 <= minus['width'] <= 42 and 36 <= plus['width'] <= 42
        assert minus['x'] < plus['x'], (minus,plus)
        assert minus['x'] >= qty['x']-1 and plus['x']+plus['width'] <= qty['x']+qty['width']+1
        # Detail: responsive layout and no clipped footer controls; do not freeze historical pixel sizes.
        page.locator('[data-menu-item][data-item-id="3"] h3').click(); page.wait_for_timeout(70)
        panel=page.locator('.item-detail-panel').bounding_box(); dmedia=page.locator('.item-detail-media').bounding_box(); dcopy=page.locator('.item-detail-copy').bounding_box(); dqty=page.locator('.detail-qty-control').bounding_box()
        assert not page.locator('#itemDetailModal').evaluate('e=>e.classList.contains("hidden")')
        if width <= 560:
            assert dmedia['y'] < dcopy['y']
            assert abs(dmedia['height']-dmedia['width']) <= 1 and .65 <= dmedia['width']/width <= .78, (width,dmedia)
        else:
            assert dmedia['x'] < dcopy['x']
            assert panel['width'] <= min(900, width), panel
        assert dqty['x'] >= panel['x'] and dqty['x']+dqty['width'] <= panel['x']+panel['width']+1, (dqty,panel)
        page.screenshot(path=f'/mnt/data/sokna-1272-detail-{width}.png', full_page=False)
        # Close once, move to a genuinely deep scroll position, then open again.
        # This reproduces the Android regression that used to paint the page from
        # the top before returning to the selected item.
        page.locator('[data-close-item-detail]').click(); page.wait_for_timeout(120)
        target=page.locator('[data-menu-item][data-item-id="3"]')
        target.scroll_into_view_if_needed(); page.wait_for_timeout(420); page.evaluate('window.scrollBy({top:-110,behavior:"instant"})'); page.wait_for_timeout(80)
        before_top=target.evaluate('e=>e.getBoundingClientRect().top')
        before_scroll=page.evaluate('window.scrollY')
        target.locator('h3').click(); page.wait_for_timeout(70)
        during_top=target.evaluate('e=>e.getBoundingClientRect().top')
        assert abs(during_top-before_top) < 2, (before_top,during_top)
        page.evaluate("""() => { window.__closeSamples=[]; const card=document.querySelector('[data-menu-item][data-item-id=\"3\"]'); let n=0; const tick=()=>{window.__closeSamples.push(card.getBoundingClientRect().top); if(++n<20)requestAnimationFrame(tick)}; requestAnimationFrame(tick); }""")
        if width <= 560: page.mouse.click(width//2, 6)
        else: page.mouse.click(20, height//2)
        page.wait_for_timeout(420)
        assert page.locator('#itemDetailModal').evaluate('e=>e.classList.contains("hidden")')
        samples=page.evaluate('window.__closeSamples')
        assert samples and max(samples)-min(samples) < 3, samples
        assert abs(page.evaluate('window.scrollY')-before_scroll) < 2
        assert abs(target.evaluate('e=>e.getBoundingClientRect().top')-before_top) < 2
        # Events must be the active top-level layer, not an unstyled panel behind the menu.
        page.locator('[data-open-events]').click(); page.wait_for_timeout(70)
        sheet=page.locator('#eventsSheet'); epanel=page.locator('.guest-events-panel')
        assert not sheet.evaluate('e=>e.classList.contains("hidden")')
        assert sheet.evaluate('e=>getComputedStyle(e).position')=='fixed'
        assert int(sheet.evaluate('e=>getComputedStyle(e).zIndex')) >= 1300
        eb=epanel.bounding_box(); assert eb and eb['height']>100
        top_element=page.evaluate('''() => {const p=document.querySelector('.guest-events-panel').getBoundingClientRect(); const e=document.elementFromPoint(p.left+p.width/2,p.top+50); return !!e?.closest('.guest-events-panel')}''')
        assert top_element
        page.screenshot(path=f'/mnt/data/sokna-1272-events-{width}.png', full_page=False)
        page.locator('[data-close-events]').click(); page.wait_for_timeout(90)
        assert sheet.evaluate('e=>e.classList.contains("hidden")')
        # Registered orders restore the 1.26 card hierarchy while retaining edit/cancel data attributes.
        page.locator('#openRecentOrder').click(); page.wait_for_timeout(150)
        assert not page.locator('#guestOrdersModal').evaluate('e=>e.classList.contains("hidden")')
        order_card=page.locator('.guest-order-card')
        assert order_card.count()==1 and page.locator('.guest-order-line').count()==3
        assert page.locator('[data-edit-guest-order]').is_visible() and page.locator('[data-cancel-guest-order]').is_visible()
        assert '۳ قلم' in page.locator('.guest-order-card-head small').inner_text()
        page.screenshot(path=f'/mnt/data/sokna-1272-orders-{width}.png', full_page=False)
        page.locator('#guestOrdersClose').click(); page.wait_for_timeout(90)
        page.close()
    browser.close()
print('Sokna 1.27.2 visual and interaction checks passed at 320, 390, 412 and 1024 widths.')
