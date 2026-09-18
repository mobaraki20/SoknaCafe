#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
menu_js = (ROOT / 'assets/js/menu.js').read_text(encoding='utf-8')
html = '''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="test"><style>
body{direction:rtl}.hidden{display:none!important}.horizontal-rail{display:flex;gap:8px;width:360px;overflow-x:auto;direction:rtl}.horizontal-rail>a{flex:0 0 110px;height:40px;background:#eee}.search-result-card{display:block}.inline-qty{display:none}
</style></head><body class="guest-menu-page guest-mode-table menu-layout-editorial">
<div class="horizontal-rail-shell"><button id="railPrev" data-scroll-rail="categoryTabs" data-scroll-dir="-1">prev</button><nav id="categoryTabs" class="category-orbit horizontal-rail">''' + ''.join(f'<a href="#c{i}">آیتم {i}</a>' for i in range(10)) + '''</nav><button id="railNext" data-scroll-rail="categoryTabs" data-scroll-dir="1">next</button></div>
<section id="guestSearchZone"><div id="searchPanel"><button id="searchOpen"></button><input id="menuSearch"><button id="searchClear" class="hidden"></button><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><div id="searchResultList"></div><button id="searchBackToMenu"></button></div></div></section>
<section class="category-section-v12" id="c0"></section>
<script>window.CAFE_MESSAGES={};window.CAFE_MENU=[
{id:1,name:'سیب زمینی با سس قارچ',category:'سیب‌زمینی‌ها',search_text:'سیب زمینی با سس قارچ سیب زمینی ها',price:180000,available:1,station_busy:0},
{id:2,name:'دمنوش طلا',category:'دمنوش‌ها',description:'دارای عطر سیب',search_text:'دمنوش طلا دمنوش ها',price:170000,available:1,station_busy:0}
];window.CAFE_TABLE=null;window.CAFE_SESSION=null;window.CAFE_CAN_ORDER=false;window.CAFE_ORDER_ACCEPTANCE={cafe:false,kitchen:true,bar:true};window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_CURRENCY='تومان';window.CAFE_SESSIONS_ENABLED=true;</script>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 900, 'height': 700})
    page.set_content(html, wait_until='load')
    page.add_script_tag(content=(ROOT / 'assets/js/horizontal-rail.js').read_text(encoding='utf-8'))
    page.add_script_tag(content=(ROOT / 'assets/js/category-navigation.js').read_text(encoding='utf-8'))
    page.add_script_tag(content=menu_js)
    page.wait_for_timeout(150)

    rail = page.locator('#categoryTabs')
    assert rail.evaluate('(e)=>e.scrollWidth > e.clientWidth'), 'Desktop rail fixture does not overflow.'
    initial = rail.evaluate('(e)=>e.scrollLeft')
    page.locator('#railNext').click()
    page.wait_for_timeout(450)
    after_next = rail.evaluate('(e)=>e.scrollLeft')
    assert after_next != initial, f'RTL next arrow did not move the rail ({initial} -> {after_next}).'
    page.locator('#railPrev').click()
    page.wait_for_timeout(450)
    after_prev = rail.evaluate('(e)=>e.scrollLeft')
    assert after_prev != after_next, 'RTL previous arrow did not move the rail back.'

    search = page.locator('#menuSearch')
    search.fill('سیب')
    page.wait_for_timeout(100)
    results = page.locator('#searchResultList').inner_text()
    assert 'سیب زمینی با سس قارچ' in results, 'Exact item-name search result is missing.'
    assert 'دمنوش طلا' not in results, 'Description-only unrelated search result leaked into results.'
    assert page.locator('#searchResultsCount').inner_text().strip().startswith('۱'), 'Search result count is not one.'

    browser.close()
print('Browser UI regressions passed: RTL desktop rail controls and narrow Persian search work in Chromium.')
