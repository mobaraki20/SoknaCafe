#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="guest-menu-page has-waiter has-cart"><main class="guest-main"><article class="menu-item-v12" id="lastCard"><div class="item-media-v12"></div><div class="item-copy-v12"><h3>آخرین محصول</h3><p>این کارت نباید زیر نوار اقدام قرار بگیرد.</p></div></article></main><div class="guest-action-dock" id="dock"><button class="waiter-fab" id="waiter"><svg class="ui-icon" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/></svg><span class="visually-hidden">فراخوان گارسون</span></button><div class="cart-bar-v12" id="bar"><button><span>🛒</span><strong>مشاهده سبد سفارش</strong></button><div class="cart-bar-summary"><small>جمع سبد</small><strong>۹۹۹٬۹۹۹٬۹۹۹ تومان</strong></div></div></div></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    for width,height in [(320,640),(360,740),(390,844),(430,900),(1024,800)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html,wait_until='load'); page.wait_for_timeout(50)
        d=page.evaluate('''() => {const w=document.getElementById('waiter').getBoundingClientRect(), b=document.getElementById('bar').getBoundingClientRect(), dock=document.getElementById('dock').getBoundingClientRect(), i=document.querySelector('#waiter svg').getBoundingClientRect(), card=document.getElementById('lastCard').getBoundingClientRect();return {gap:b.left-w.right,bottomDelta:Math.abs(w.bottom-b.bottom),dockLeft:dock.left,dockRight:dock.right,waiterLeft:w.left,waiterRight:w.right,barLeft:b.left,barRight:b.right,waiterCenter:[w.left+w.width/2,w.top+w.height/2],iconCenter:[i.left+i.width/2,i.top+i.height/2],cardBottom:card.bottom,dockTop:dock.top,mainPadding:parseFloat(getComputedStyle(document.querySelector('.guest-main')).paddingBottom),overflow:document.documentElement.scrollWidth>innerWidth+1}}''')
        assert d['gap'] >= 7 and d['bottomDelta'] < 3, (width,d)
        assert d['dockLeft'] >= -1 and d['dockRight'] <= width+1, (width,d)
        assert d['waiterLeft'] >= d['dockLeft']-1 and d['barRight'] <= d['dockRight']+1, (width,d)
        assert abs(d['waiterCenter'][0]-d['iconCenter'][0]) < 1 and abs(d['waiterCenter'][1]-d['iconCenter'][1]) < 1, (width,d)
        assert d['mainPadding'] >= 94, (width,d)
        assert not d['overflow'], (width,d)
        page.close()
    browser.close()
print('Waiter/cart dock browser checks passed: one reserved bottom dock with no independent floating control.')
