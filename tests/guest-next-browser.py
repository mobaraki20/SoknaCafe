#!/usr/bin/env python3
from pathlib import Path
import base64
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
css_files = ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css']
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in css_files)
js=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
svg='<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="800" height="600" fill="#ded4c4"/><circle cx="400" cy="330" r="150" fill="#2f7566"/></svg>'
img='data:image/svg+xml;base64,' + base64.b64encode(svg.encode()).decode()
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="x"><style>:root{{--primary:#2f7566;--accent:#c88435;--app-bg:#f6f1e6;--on-primary:#fff;--on-accent:#222;--font-ui:Tahoma,Arial,sans-serif}}{css}
.menu-item-card img{{width:120px!important;max-width:40%!important;height:140px!important;object-fit:cover!important}}</style></head>
<body class="guest-menu-page theme-courtyard density-balanced menu-layout-editorial">
<header class="guest-header"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">س</div><div class="guest-brand-copy"><strong>کافه سکنا</strong><span>منوی میز شما</span></div></div><div class="guest-header-actions"><button class="guest-event-access"><span>رویدادها</span></button><div class="guest-table-label"><strong>میز ۱</strong></div></div></div></header>
<section class="recent-order" id="recentOrderBar"><div class="recent-order-heading"><small>سفارش شما</small><strong id="recentOrderCode">سفارش در انتظار تأیید</strong><span id="recentOrderMeta">۱ سفارش</span></div><span id="recentOrderStatus">کافه سفارش را بررسی می‌کند.</span><button type="button">مشاهده</button><div class="order-progress" aria-hidden="true"><i></i><i></i></div></section>
<main class="guest-main"><section class="guest-search-compact"><div class="guest-search-panel"><div class="menu-search-wrap-v12"><button class="search-focus-proxy" id="searchOpen">⌕</button><input id="menuSearch" placeholder="جست‌وجو در نام یا دسته‌بندی"><button id="searchClear" class="hidden">×</button></div><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><button id="searchBackToMenu"></button><div id="searchResultList"></div></div></div></section>
<div class="horizontal-rail-shell category-rail-shell"><nav class="category-orbit horizontal-rail"><a class="active"><span class="category-orb">☰</span><b>همه</b></a><a><span class="category-orb">●</span><b>خوراک روز</b></a><a><span class="category-orb">●</span><b>شربت‌خانه</b></a></nav></div>
<section class="featured-menu-zone"><div class="featured-menu-heading"><div><span>انتخاب‌های ویژه کافه</span><h2>پیشنهاد امروز</h2></div></div><div class="horizontal-rail-shell featured-rail-shell"><div class="featured-menu-rail horizontal-rail">
<article class="featured-menu-card" data-featured-item data-item-id="1"><img src="{img}" alt="زینگر"><div class="featured-menu-copy"><small>خوراک روز</small><h3>زینگر</h3><div><strong>۴۶۰٬۰۰۰ تومان</strong><div class="inline-qty"><button data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button data-add-item="1">+ افزودن</button></div></div></div></article>
<article class="featured-menu-card" data-featured-item data-item-id="2"><img src="{img}" alt="لیموناد"><div class="featured-menu-copy"><small>شربت‌خانه</small><h3>لیموناد</h3><div><strong>۱۸۰٬۰۰۰ تومان</strong><div class="inline-qty"><button data-inline-dec="2">−</button><span data-inline-count="2">۰</span><button data-add-item="2">+ افزودن</button></div></div></div></article>
</div></div></section>
<section data-category><article class="menu-item-card" data-menu-item data-item-id="3"><img src="{img}" alt="برگر"><h3>برگر گوشت</h3><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong><div class="inline-qty"><button data-inline-dec="3">−</button><span data-inline-count="3">۰</span><button data-add-item="3">+ افزودن</button></div></div></article></section></main>
<div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><small id="itemDetailCategory"></small><h2 id="itemDetailTitle"></h2><div id="itemDetailPrice" class="item-detail-price"></div><div id="itemDetailTags"></div><p id="itemDetailDescription"></p><div class="item-detail-option-slot" id="itemDetailOptionSlot"><details class="item-detail-note" id="itemDetailNoteBlock"><summary>+ افزودن یادداشت <small>اختیاری</small></summary><textarea id="itemDetailNote"></textarea></details><section id="itemDetailSuggestion" class="item-detail-suggestion hidden"><div class="item-detail-suggestion-media" id="itemDetailSuggestionMedia"></div><div><small>کنارش پیشنهاد می‌کنیم</small><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span></div><button id="itemDetailSuggestionAdd"></button></section></div></div></div><footer class="item-detail-footer"><div id="itemDetailActions" class="item-detail-actions"></div><button id="itemDetailPrimary" class="item-detail-primary"><span>افزودن به سفارش</span><strong id="itemDetailPrimaryTotal"></strong></button></footer></article></div>
<div class="guest-action-dock" id="guestActionDock"><button class="waiter-fab" id="waiterButton"><span id="waiterButtonText">خبر کردن گارسون</span></button>
<div class="cart-bar-v12 hidden" id="cartBar"><button id="openCart"><span class="cart-bar-icon">🛒</span><strong>دیدن سفارش</strong><b id="cartCount" class="cart-count-v12">۰</b></button><div class="cart-bar-summary"><small>جمع سبد</small><strong id="cartBarTotal"></strong></div></div></div>
<div class="drawer-backdrop" id="drawerBackdrop"></div><section class="cart-drawer" id="cartDrawer" aria-hidden="true"><div class="drawer-head"><div><small id="cartContext"></small><h2>مرور سفارش</h2><span id="cartSummaryCaption"></span></div><button id="closeCart">×</button></div><div class="drawer-content" id="cartDrawerContent"><div id="cartItems"></div><div id="cartScrollCue"></div><div id="cartStationWarning" class="hidden"></div><div id="cartSuggestion" class="cart-suggestion hidden"></div><details id="orderNoteDisclosure"><summary>+ افزودن یادداشت کلی سفارش</summary><textarea id="customerNote"></textarea></details></div><div class="drawer-foot"><div class="total-row"><span>جمع سفارش</span><span id="cartTotal"></span></div><button id="submitOrder" class="submit-order-button"><span id="submitOrderText">ثبت سفارش</span><strong id="submitOrderTotal" class="visually-hidden"></strong></button></div></section>
<div id="guestToast" class="hidden"></div><div id="orderLiveStatus"></div><span id="orderStatusText"></span>
<script>window.fetch=async()=>new Response(JSON.stringify({{success:true,orders:[],ordering_enabled:true,can_order:true,requires_operator_confirmation:false,station_state_hash:''}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});window.CAFE_MENU=[{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',price:460000,image:'{img}',available:1,suggested_item_id:2,tags:[]}},{{id:2,name:'لیموناد تازه',category:'شربت‌خانه',description:'',price:180000,image:'{img}',available:1,suggested_item_id:null,tags:[]}},{{id:3,name:'برگر گوشت',category:'خوراک روز',description:'',price:465000,image:'{img}',available:1,suggested_item_id:2,tags:[]}}];window.CAFE_TABLE={{token:'t',name:'میز ۱',code:'1'}};window.CAFE_SESSION={{token:'s'}};window.CAFE_CURRENCY='تومان';window.CAFE_CAN_ORDER=true;window.CAFE_ORDERING_ENABLED=true;window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_STATION_STATE_HASH='';window.CAFE_MESSAGES={{item_note_placeholder_enabled:true}};window.CAFE_ANALYTICS_ENABLED=false;window.CAFE_WAITER_ENABLED=false;</script><script>{js}</script></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width,height in ((390,844),(412,915),(1024,768)):
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html,wait_until='load')
        page.wait_for_timeout(100)
        event_box=page.locator('.guest-event-access').bounding_box(); table_box=page.locator('.guest-table-label').bounding_box()
        assert abs(event_box['width']-table_box['width'])<3 and abs(event_box['height']-table_box['height'])<3, (event_box,table_box)
        assert page.evaluate('document.documentElement.scrollWidth') <= width+1; search=page.locator('.menu-search-wrap-v12').bounding_box(); assert search['height']<=58
        featured=page.locator('.featured-menu-heading').bounding_box(); assert featured['y'] < height*0.78, (width,featured['y'])
        page.screenshot(path=f'/mnt/data/sokna-guest-main-{width}.png',full_page=False)
        page.locator('[data-add-item="1"]').click()
        page.wait_for_function("document.querySelector('[data-featured-item][data-item-id=\"1\"] .inline-qty')?.classList.contains('has-items')")
        boxes=page.evaluate('''() => { const m=document.querySelector('[data-featured-item][data-item-id="1"] [data-inline-dec="1"]').getBoundingClientRect(); const p=document.querySelector('[data-featured-item][data-item-id="1"] [data-add-item="1"]').getBoundingClientRect(); return {m:{x:m.x,right:m.right},p:{x:p.x,right:p.right}} }''')
        assert boxes['m']['x'] < boxes['p']['x'], boxes
        badge=page.locator('#cartCount').bounding_box(); icon=page.locator('.cart-bar-icon').bounding_box()
        badge_icon_overlap=max(0,min(badge['x']+badge['width'],icon['x']+icon['width'])-max(badge['x'],icon['x']))*max(0,min(badge['y']+badge['height'],icon['y']+icon['height'])-max(badge['y'],icon['y']))
        assert badge_icon_overlap==0, (badge,icon)
        page.locator('[data-featured-item][data-item-id="1"] h3').dispatch_event('click'); page.wait_for_timeout(80)
        assert not page.locator('#itemDetailModal').evaluate("e=>e.classList.contains('hidden')")
        media=page.locator('#itemDetailMedia').bounding_box(); assert media and abs(media['width']-media['height']) < 2, media
        assert page.locator('#itemDetailSuggestionTitle').inner_text()=='لیموناد تازه'
        if width<=560:
            assert page.locator('#itemDetailMedia img').evaluate("e=>getComputedStyle(e).objectFit")=='contain'
        assert page.locator('#waiterButton').evaluate("e=>getComputedStyle(e).visibility")=='hidden'
        page.screenshot(path=f'/mnt/data/sokna-guest-detail-{width}.png',full_page=False)
        # A click on the backdrop closes only this layer and must not open another item.
        if width>=1000:
            page.mouse.click(30,height//2)
        else:
            page.mouse.click(width//2,8)
        page.wait_for_timeout(80)
        assert page.locator('#itemDetailModal').evaluate("e=>e.classList.contains('hidden')")
        assert page.locator('#itemDetailTitle').inner_text()=='زینگر'
        page.locator('#openCart').click(); page.wait_for_function("() => { const e=document.querySelector('#cartDrawer'); if(!e || e.getAttribute('aria-hidden')!=='false') return false; const r=e.getBoundingClientRect(); return Math.abs(r.bottom-innerHeight)<16 && r.height>=360 && r.width<=Math.min(innerWidth,720)+2; }"); page.wait_for_timeout(40)
        assert page.locator('#cartDrawer').get_attribute('aria-hidden')=='false'
        assert page.locator('#waiterButton').evaluate("e=>getComputedStyle(e).visibility")=='hidden'
        assert page.locator('.drawer-foot .total-row').is_visible()
        assert 'میز ۱' in page.locator('#cartContext').inner_text()
        page.screenshot(path=f'/mnt/data/sokna-guest-cart-{width}.png',full_page=False)
        page.locator('#closeCart').click()
        page.close()
    browser.close()
print('Guest next browser checks passed at mobile, tall mobile, and desktop widths.')
