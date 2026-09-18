#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
lines=''.join(f'''<div class="cart-line"><div class="cart-line-main"><div class="cart-line-heading"><h4>آیتم آزمایشی بسیار طولانی شماره {i}</h4><div class="cart-line-total">۳۶۰٬۰۰۰ تومان</div></div><div class="cart-line-price">۲ × ۱۸۰٬۰۰۰ تومان</div></div><div class="qty-control"><button>−</button><strong>۲</strong><button>+</button></div><details class="line-note-disclosure"><summary>یادداشت</summary></details></div>''' for i in range(12))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{css}</style></head><body class="guest-menu-page guest-mode-table has-cart"><section class="cart-drawer open"><div class="drawer-head"><div><small>سفارش برای میز ۳</small><h2>سبد سفارش</h2><span class="cart-summary-caption"></span></div><button class="icon-btn">×</button></div><div class="drawer-content" id="content"><div>{lines}</div><div class="cart-scroll-cue is-visible"></div><details class="order-note-field"><summary>+ افزودن یادداشت کلی سفارش</summary></details></div><div class="drawer-foot"><div class="total-row"><span>جمع انتخاب‌ها</span><span>۴٬۳۲۰٬۰۰۰ تومان</span></div><button class="btn btn-primary btn-block">ثبت سفارش</button></div></section></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    for width,height in [(320,640),(360,740),(390,844),(430,900),(768,900)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html, wait_until='load')
        data=page.evaluate('''() => {const d=document.querySelector('.cart-drawer'), c=document.querySelector('#content'), f=document.querySelector('.drawer-foot'); const dr=d.getBoundingClientRect(), fr=f.getBoundingClientRect(); return {drawerLeft:dr.left,drawerRight:dr.right,viewport:innerWidth,scroll:c.scrollHeight>c.clientHeight,footBottom:fr.bottom,drawerBottom:dr.bottom,bodyOverflow:document.documentElement.scrollWidth>innerWidth+1};}''')
        assert data['drawerLeft'] >= -1 and data['drawerRight'] <= width+1, (width,data)
        assert data['scroll'], (width,data)
        assert abs(data['footBottom']-data['drawerBottom']) < 2, (width,data)
        assert not data['bodyOverflow'], (width,data)
        page.close()
    browser.close()
print('Long-cart browser checks passed.')
