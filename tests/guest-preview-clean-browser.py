#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
js=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="x"><style>:root{{--primary:#2f7058;--accent:#b07148;--app-bg:#f7f3ec;--on-primary:#fff;--on-accent:#fff;--font-ui:Arial}}{css}</style></head><body class="guest-menu-page guest-mode-public is-menu-public has-waiter">
<header class="guest-header" id="guestHeader"><div class="guest-header-inner"><div class="guest-brand"><div class="guest-logo">س</div><div class="guest-brand-copy"><strong>کافه سکنا</strong><span>منوی کافه</span></div></div><div class="guest-header-actions"><button class="guest-event-access" data-open-events><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"></svg><span>رویدادها</span></button></div></div></header>
<main class="guest-main"><section class="guest-search-compact" id="guestSearchZone"><div class="guest-search-panel" id="searchPanel"><div class="guest-search-panel-head"><strong>جست‌وجوی منو</strong><button id="searchClose">×</button></div><div class="menu-search-wrap-v12"><button id="searchOpen">⌕</button><input id="menuSearch"><button class="search-clear hidden" id="searchClear">×</button></div><div class="search-results hidden" id="searchResults"><small id="searchResultsCount"></small><button id="searchBackToMenu">بازگشت</button><div id="searchResultList"></div></div></div></section>
<div class="horizontal-rail-shell"><button data-scroll-rail="categoryTabs" data-scroll-dir="-1"></button><nav class="category-orbit horizontal-rail" id="categoryTabs"><a class="active" href="#menuStart"><span class="category-orb"></span><b>همه</b></a><a href="#category-1"><span class="category-orb"></span><b>خوراک روز</b></a></nav><button data-scroll-rail="categoryTabs" data-scroll-dir="1"></button></div><span id="menuStart"></span>
<section class="featured-menu-zone"><div class="section-title-v12 featured-menu-heading"><div><h2>پیشنهادهای کافه</h2></div></div><div class="featured-menu-rail horizontal-rail" id="featuredMenuRail"><article class="featured-menu-card" data-featured-item data-item-id="1"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='800'%3E%3Crect width='600' height='800' fill='%23b9a17c'/%3E%3Ccircle cx='300' cy='460' r='170' fill='%23533'/%3E%3C/svg%3E"><div class="featured-menu-copy"><small>خوراک روز</small><h3>زینگر</h3><div><strong>۴۶۰٬۰۰۰ تومان</strong></div></div></article></div></section>
<section class="category-section-v12" id="category-1"><div class="section-title-v12"><h2>خوراک روز</h2><span>۱ انتخاب</span></div><div class="items-list-v12"><article class="menu-item-v12" id="underCard" data-menu-item data-item-id="2"><div class="item-media-v12"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='600'%3E%3Crect width='800' height='600' fill='%237ba'/%3E%3C/svg%3E"></div><div class="item-copy-v12"><div><h3>برگر گوشت</h3><p>توضیح کوتاه و خوانا</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong></div></div></article></div></section></main>
<div class="item-detail-modal hidden" id="itemDetailModal"><article class="item-detail-panel view-only"><div class="item-detail-grip"></div><button class="item-detail-close" data-close-item-detail>×</button><div class="item-detail-scroll"><div class="item-detail-media" id="itemDetailMedia"></div><div class="item-detail-copy"><h2 id="itemDetailTitle"></h2><div id="itemDetailPrice" class="item-detail-price"></div><div id="itemDetailTags" class="item-detail-tags"></div><p id="itemDetailDescription" class="item-detail-description"></p></div></div></article></div>
<button class="waiter-fab" id="waiterButton"><span id="waiterButtonText">فراخوان گارسون</span></button><div class="success-modal hidden" id="waiterModal"><div class="success-box"><p id="waiterModalText"></p><div id="publicTableQuick" class="public-table-quick hidden"><strong id="publicTableQuickName"></strong><button id="changePublicTable">تغییر میز</button></div><section id="publicTableSelectWrap" class="public-table-picker"><span id="publicTableSelection">هنوز انتخاب نشده</span><input type="hidden" id="publicWaiterTable"><div id="publicTableGrid" class="public-table-grid"></div></section><button id="confirmWaiter">فراخوان</button><button id="closeWaiter">بستن</button></div></div>
<section id="eventsSheet" class="success-modal hidden"><div class="success-box"><button data-close-events>×</button></div></section><div id="guestToast" class="guest-toast hidden"></div>
<script>window.CAFE_MENU=[{{id:1,name:'زینگر',category:'خوراک روز',description:'مرغ سوخاری، نان برگر و سس اختصاصی',image:"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='800'%3E%3Crect width='600' height='800' fill='%23b9a17c'/%3E%3Ccircle cx='300' cy='460' r='170' fill='%23533'/%3E%3C/svg%3E",price:460000,available:1,tags:[]}},{{id:2,name:'برگر گوشت',category:'خوراک روز',description:'برگر گوشت، گوجه، خیارشور و سس اختصاصی',image:"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='600'%3E%3Crect width='800' height='600' fill='%237ba'/%3E%3C/svg%3E",price:465000,available:1,tags:[]}}];window.CAFE_CURRENCY='تومان';window.CAFE_WAITER_API_URL='/fake';window.CAFE_PUBLIC_WAITER_TABLES=[{{id:1,name:'میز ۱',code:'1'}},{{id:2,name:'میز ۲',code:'2'}},{{id:12,name:'میز ۱۲',code:'12'}}];window.CAFE_MESSAGES={{waiter_button:'فراخوان گارسون'}};</script><script>{js}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width,height,name in [(360,800,'mobile-360'),(390,844,'mobile-390'),(412,915,'mobile-412'),(1366,900,'desktop-1366'),(1440,900,'desktop-1440'),(1920,1080,'desktop-1920')]:
        page=b.new_page(viewport={'width':width,'height':height})
        errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(html,wait_until='load')
        dims=page.evaluate('({iw:innerWidth,sw:document.documentElement.scrollWidth,bw:document.body.scrollWidth})')
        if dims['sw'] > dims['iw'] + 1:
            offenders=page.evaluate("()=>[...document.querySelectorAll('*')].map(e=>{const r=e.getBoundingClientRect();return {tag:e.tagName,cls:String(e.className||''),id:e.id,l:r.left,r:r.right,w:r.width}}).filter(x=>x.l<-.5||x.r>innerWidth+.5).sort((a,b)=>b.w-a.w).slice(0,12)")
            raise AssertionError(f'Horizontal overflow at {name}: {dims} {offenders}')
        assert page.locator('.guest-table-label').count()==0
        assert page.locator('.featured-menu-heading h2').inner_text()=='پیشنهادهای کافه'
        assert page.locator('.featured-menu-heading span').count()==0
        assert page.locator('#itemDetailCategory,#waiterModalClose').count()==0
        assert page.locator('[data-add-item],.cart-bar-v12').count()==0
        page.evaluate('window.scrollTo({top:260,behavior:"instant"})'); page.wait_for_timeout(40)
        page.locator('[data-featured-item]').click(); page.wait_for_timeout(80)
        open_scroll=page.evaluate('window.scrollY')
        assert page.locator('#itemDetailModal').is_visible()
        panel=page.locator('.item-detail-panel').bounding_box(); assert panel and panel['height'] < height*.94
        page.screenshot(path=f'/mnt/data/sokna-preview-{name}.png',full_page=True)
        # Backdrop click must close only; underlying card must not open.
        page.mouse.click(3, 6); page.wait_for_timeout(100)
        assert not page.locator('#itemDetailModal').is_visible()
        assert abs(page.evaluate('window.scrollY')-open_scroll)<=3
        assert page.locator('#itemDetailTitle').text_content()=='زینگر'
        page.locator('#waiterButton').click(); page.wait_for_timeout(50)
        assert page.locator('#publicTableGrid button').count()==3
        assert page.locator('#publicWaiterTableSearch').count()==0
        assert not errors, errors
        page.close()
    b.close()
print('Guest preview browser checks passed.')
