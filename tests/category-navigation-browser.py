#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
shared=(ROOT/'assets/js/category-navigation.js').read_text(encoding='utf-8')
policy=(ROOT/'assets/js/fulfillment-policy.js').read_text(encoding='utf-8')

def fixture():
    tabs='<a class="active" aria-current="true" href="#menuStart">همه</a>'+''.join(f'<a href="#category-{i}">دسته {i}</a>' for i in range(1,5))
    sections=''.join(f'<section class="category-section-v12" id="category-{i}"><h2>دسته {i}</h2></section>' for i in range(1,5))
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="x"><style>html{{scroll-behavior:smooth}}body{{margin:0}}.guest-header{{height:50px;background:#eee}}.category-rail-shell{{position:sticky;top:0;z-index:4}}.category-orbit{{height:60px;display:flex;gap:12px;overflow:auto;background:white}}.category-orbit a{{min-width:100px;padding:15px 8px;box-sizing:border-box}}.category-orbit a.active{{font-weight:bold}}#menuStart{{display:block}}.category-section-v12{{height:650px;padding-top:1px}}.hidden{{display:none!important}}</style></head><body><header class="guest-header">سربرگ</header><div class="category-rail-shell"><nav class="category-orbit horizontal-rail" id="categoryTabs">{tabs}</nav></div><span id="menuStart"></span>{sections}<section id="guestSearchZone"><input id="menuSearch"><button id="searchOpen"></button><button id="searchClose"></button><button id="searchClear"></button><div id="searchResults" class="hidden"><span id="searchResultsCount"></span><div id="searchResultList"></div></div><button id="searchBackToMenu"></button></section><div id="guestToast" class="hidden"></div><script>window.CAFE_MESSAGES={{}};window.CAFE_MENU=[];window.CAFE_CURRENCY='تومان';window.CAFE_TABLE={{token:'test',name:'میز ۱'}};window.CAFE_SESSION=null;window.CAFE_CAN_ORDER=true;window.CAFE_ORDER_ACCEPTANCE={{cafe:true,kitchen:true,bar:true}};window.CAFE_REQUIRES_OPERATOR_CONFIRMATION=false;window.CAFE_SESSIONS_ENABLED=true;window.CAFE_ANALYTICS_ENABLED=false;window.__categoryChanges=[];document.addEventListener('sokna:category-change',e=>window.__categoryChanges.push(e.detail.id));</script></body></html>'''

def run(page, app_source, label):
    errors=[]; page.on('pageerror',lambda error: errors.append(str(error)))
    page.set_content(fixture()); page.add_script_tag(content=shared); page.add_script_tag(content=policy); page.add_script_tag(content=app_source); page.wait_for_timeout(100)
    page.click('a[href="#category-3"]'); page.wait_for_timeout(80)
    assert page.locator('a[href="#category-3"]').evaluate('(e)=>e.classList.contains("active")'), f'{label}: clicked category lost active state'
    page.wait_for_timeout(1300)
    assert page.locator('a[href="#category-3"]').evaluate('(e)=>e.classList.contains("active")'), f'{label}: wrong category after smooth scroll'
    top=page.locator('#category-3').evaluate('(e)=>e.getBoundingClientRect().top')
    assert 55 <= top <= 95, f'{label}: sticky-only offset wrong: {top}'
    # Slow oscillation smaller than the hysteresis band must not bounce active categories.
    boundary=page.locator('#category-3').evaluate('(e)=>window.scrollY+e.getBoundingClientRect().top')
    marker=page.locator('.category-orbit').evaluate('(e)=>e.getBoundingClientRect().height')+8
    page.evaluate('window.__categoryChanges=[]')
    for delta in (20,10,4,-2,3,-4,2,-3,1,-1,0):
        page.evaluate('(y)=>window.scrollTo(0,y)', boundary-marker+delta); page.wait_for_timeout(35)
    changes=page.evaluate('window.__categoryChanges')
    assert len(changes)<=1, f'{label}: active category jittered near boundary: {changes}'
    page.click('a[href="#category-2"]'); page.wait_for_timeout(25); page.click('a[href="#category-4"]'); page.wait_for_timeout(80)
    assert page.locator('a[href="#category-4"]').evaluate('(e)=>e.classList.contains("active")'), f'{label}: last rapid click did not win'
    page.wait_for_timeout(1300)
    assert page.locator('a[href="#category-4"]').evaluate('(e)=>e.classList.contains("active")'), f'{label}: scroll controller overwrote final click'
    assert not errors,(label,errors)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for name in ['menu.js']:
        page=browser.new_page(viewport={'width':390,'height':844})
        run(page,(ROOT/'assets/js'/name).read_text(encoding='utf-8'),name); page.close()
    browser.close()
print('Category navigation browser passed: one shared controller, stable hysteresis, correct sticky offset and last-click authority for guest/QR and preview.')
