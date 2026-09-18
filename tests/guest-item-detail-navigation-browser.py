#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
category_js=(ROOT/'assets/js/category-navigation.js').read_text(encoding='utf-8')
menu_js=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="test"><style>{css}.hidden{{display:none!important}}.category-section-v12{{min-height:700px}}</style></head><body class="guest-menu-page menu-layout-catalog"><div style="height:180px">سربرگ</div><div class="horizontal-rail-shell category-rail-shell"><nav class="category-orbit horizontal-rail" id="categoryTabs"><a class="active" href="#menuStart"><span>همه</span></a><a href="#category-1"><span>قهوه</span></a><a href="#category-2"><span>چای</span></a></nav></div><span id="menuStart"></span><section class="category-section-v12" id="category-1"><article class="menu-item-v12" data-menu-item data-item-id="1"><h3>لاته زعفرانی</h3><div class="inline-qty" data-inline-qty="1"><button data-inline-dec="1">−</button><span data-inline-count="1">۰</span><button class="inline-add" data-add-item="1">افزودن</button></div></article></section><section class="category-section-v12" id="category-2"><h2>چای‌ها</h2></section><section id="guestSearchZone"><button id="searchOpen"></button><input id="menuSearch"><button id="searchClear" class="hidden"></button><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><div id="searchResultList"></div><button id="searchBackToMenu"></button></div></section><div class="item-detail-modal hidden" id="itemDetailModal" role="dialog"><article class="item-detail-panel"><button data-close-item-detail>×</button><div class="item-detail-scroll"><div id="itemDetailMedia"></div><div class="item-detail-copy"><h2 id="itemDetailTitle"></h2><div id="itemDetailPrice"></div><div id="itemDetailTags"></div><p id="itemDetailDescription"></p><textarea id="itemDetailNote"></textarea><section id="itemDetailSuggestion" class="hidden"><h3 id="itemDetailSuggestionTitle"></h3><span id="itemDetailSuggestionPrice"></span><button id="itemDetailSuggestionAdd"></button></section></div></div><footer class="item-detail-footer"><div id="itemDetailActions"></div><button id="itemDetailPrimary"><span>افزودن به سفارش</span></button></footer></article></div><div id="guestToast" class="hidden"></div><script>window.CAFE_MESSAGES={{}};window.CAFE_MENU=[{{id:1,name:'لاته زعفرانی',category:'بار گرم',description:'اسپرسو، شیر تازه و زعفران',image:'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%2210%22/%3E',tags:[{{title:'پیشنهاد امروز',color_key:'accent'}}],price:185000,available:1,station_busy:0,search_text:'لاته زعفرانی بار گرم'}}];window.CAFE_TABLE={{token:'table-test',name:'میز ۱'}};window.CAFE_SESSION=null;window.CAFE_CAN_ORDER=true;window.CAFE_ORDER_ACCEPTANCE={{cafe:true,kitchen:true,bar:true}};window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_CURRENCY='تومان';window.CAFE_SESSIONS_ENABLED=true;window.CAFE_ANALYTICS_ENABLED=false;</script></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844})
    errors=[]; page.on('pageerror',lambda err: errors.append(str(err)))
    page.set_content(html,wait_until='load'); page.add_script_tag(content=category_js); page.add_script_tag(content=menu_js); page.wait_for_timeout(100)
    page.locator('[data-menu-item] h3').click(); page.wait_for_timeout(60)
    assert not page.locator('#itemDetailModal').evaluate('(e)=>e.classList.contains("hidden")'),'Item detail did not open.'
    assert page.locator('#itemDetailTitle').inner_text()=='لاته زعفرانی'
    assert '۱۸۵٬۰۰۰' in page.locator('#itemDetailPrice').inner_text()
    assert page.locator('#itemDetailActions .detail-qty-control strong').inner_text().strip()=='۱','Detail draft quantity did not initialize.'
    page.locator('#itemDetailPrimary').click(); page.wait_for_timeout(80)
    assert page.locator('[data-inline-count="1"]').inner_text().strip()=='۱','Confirmed detail quantity did not update the menu.'
    assert page.locator('#itemDetailModal').evaluate('(e)=>e.classList.contains("hidden")'),'Detail did not close after confirmation.'
    page.locator('a[href="#category-2"]').click(); page.wait_for_timeout(500)
    shell=page.locator('.category-rail-shell')
    shell_box=shell.bounding_box()
    sticky=shell.evaluate("(e)=>({position:getComputedStyle(e).position,top:getComputedStyle(e).top})")
    assert sticky['position']=='sticky' and sticky['top']=='0px',f'Category sticky owner is invalid: {sticky}'
    assert shell_box and shell_box['y']<=16,f'Category rail shell is not pinned near the viewport top: {shell_box}'
    active_style=page.locator('a[href="#category-2"]').evaluate("(e)=>({color:getComputedStyle(e).color,background:getComputedStyle(e).backgroundColor,opacity:getComputedStyle(e).opacity,filter:getComputedStyle(e).filter})")
    assert active_style['opacity']=='1' and active_style['filter']=='none',f'Active sticky category lost contrast: {active_style}'
    assert page.locator('a[href="#category-2"]').evaluate('(e)=>e.classList.contains("active")'),'Selected category did not become active.'
    assert not errors,errors
    browser.close()
print('Item detail and sticky category browser checks passed.')
