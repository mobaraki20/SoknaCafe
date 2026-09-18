#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'assets/js/category-navigation.js').read_text(encoding='utf-8')

def fixture():
    tabs='<a class="active" aria-current="true" href="#menuStart">همه</a>'+''.join(f'<a href="#category-{i}">دسته {i}</a>' for i in range(1,11))
    sections=''.join(f'<section class="category-section-v12" id="category-{i}" style="height:{260 if i%2 else 340}px"><h2>دسته {i}</h2></section>' for i in range(1,11))
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>html{{scroll-behavior:smooth}}body{{margin:0}}.guest-header{{height:50px}}.category-rail-shell{{position:sticky;top:0;height:60px;z-index:2}}.category-orbit{{height:60px;background:#fff}}.category-section-v12{{contain:layout style}}</style></head><body><div class="guest-header"></div><div class="category-rail-shell"><nav id="categoryTabs" class="category-orbit">{tabs}</nav></div><span id="menuStart"></span>{sections}</body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412):
        page=browser.new_page(viewport={'width':width,'height':700})
        errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(fixture()); page.add_script_tag(content=source); page.evaluate('SoknaCategoryNavigation.install()'); page.wait_for_timeout(50)
        # Simulate the same late geometry correction produced by content-visibility:
        # several categories above the destination gain their measured height while
        # the native smooth-scroll is already in flight.
        page.evaluate('''()=>{[120,520,980].forEach((ms,k)=>setTimeout(()=>{for(let i=1;i<=7;i++){const e=document.querySelector('#category-'+i);e.style.height=(parseFloat(e.style.height)+(k+1)*55)+'px';}},ms));}''')
        page.click('a[href="#category-8"]'); page.wait_for_timeout(2400)
        active=page.locator('#categoryTabs a.active').get_attribute('href')
        top=page.locator('#category-8').evaluate('(e)=>e.getBoundingClientRect().top')
        assert active=='#category-8',(width,active,top)
        assert 58 <= top <= 90,(width,top)
        assert not errors,(width,errors)
        # A new click must cancel the previous owner cleanly.
        page.click('a[href="#category-3"]'); page.wait_for_timeout(100); page.click('a[href="#category-6"]'); page.wait_for_timeout(3800)
        assert page.locator('#categoryTabs a.active').get_attribute('href')=='#category-6'
        top=page.locator('#category-6').evaluate('(e)=>e.getBoundingClientRect().top')
        assert 58 <= top <= 90,(width,top)
        page.close()
    browser.close()
print('Category navigation layout-shift regression passed at 320/360/390/412: live destination tracking and last-click ownership are stable.')
