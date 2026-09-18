#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
svg="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='640'%3E%3Crect width='640' height='640' fill='%23c8b28f'/%3E%3Ccircle cx='320' cy='370' r='150' fill='%236b4f2c'/%3E%3C/svg%3E"
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="guest-menu-page"><main class="guest-main"><section class="featured-menu-zone"><div class="featured-menu-rail"><article class="featured-menu-card" id="featured"><img id="featuredMedia" src="{svg}"><div class="featured-menu-copy" id="featuredCopy"><small>خوراک روز</small><h3>پاستا چیکن آلفردو</h3><div><strong>۵۸۵٬۰۰۰ تومان</strong></div></div></article></div></section><section><div class="items-list-v12"><article class="menu-item-v12" id="card"><div class="item-media-v12" id="media"><img src="{svg}"><span class="featured-chip">پیشنهاد کافه</span></div><div class="item-copy-v12" id="copy"><div><h3>برگر گوشت</h3><p>برگر گوشت، گوجه، خیارشور، کاهوپیچ، نان برگر و سس اختصاصی.</p></div><div class="item-action-row"><strong>۴۶۵٬۰۰۰ تومان</strong></div></div></article></div></section></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    for width,height in [(320,700),(390,844),(412,900),(768,900),(1024,900),(1366,900)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html,wait_until='load'); page.wait_for_timeout(40)
        data=page.evaluate('''() => {const card=document.getElementById('card').getBoundingClientRect(),media=document.getElementById('media').getBoundingClientRect(),copy=document.getElementById('copy').getBoundingClientRect(),f=document.getElementById('featured').getBoundingClientRect(),fm=document.getElementById('featuredMedia').getBoundingClientRect(),fc=document.getElementById('featuredCopy').getBoundingClientRect();return {card:[card.left,card.right,card.width],media:[media.left,media.right,media.width],copy:[copy.left,copy.right,copy.width],featured:[f.left,f.right],featuredMedia:[fm.left,fm.right],featuredCopy:[fc.left,fc.right],copyDirection:getComputedStyle(document.getElementById('copy')).direction,copyAlign:getComputedStyle(document.getElementById('copy')).textAlign,featuredDirection:getComputedStyle(document.getElementById('featuredCopy')).direction,overflow:document.documentElement.scrollWidth>innerWidth+1}}''')
        assert data['media'][0] < data['copy'][0], (width,data)
        assert data['media'][1] <= data['copy'][0] + 22, (width,data)
        assert data['featuredMedia'][0] < data['featuredCopy'][0], (width,data)
        assert data['copyDirection'] == 'rtl' and data['copyAlign'] in ('right','start'), (width,data)
        assert data['featuredDirection'] == 'rtl', (width,data)
        assert data['card'][0] >= -1 and data['card'][1] <= width+1, (width,data)
        assert not data['overflow'], (width,data)
        page.close()
    browser.close()
print('Guest product card direction checks passed: media left, Persian content right at all target widths.')
