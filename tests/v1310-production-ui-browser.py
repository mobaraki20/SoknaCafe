#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'] if (ROOT/p).exists())
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body>
<aside class="panel-sidebar" style="position:relative;width:280px;height:320px;background:var(--sokna-sidebar,#203b31)"><div class="sidebar-user"><div class="sidebar-user-identity"><div class="sidebar-avatar">م</div><span class="sidebar-user-copy"><strong id="userName">مدیر کافه</strong><small>مدیر سامانه</small></span></div><div class="sidebar-user-actions"><a>راهنما</a><a>خروج</a></div></div></aside>
<div class="invoice-detail-card"><section class="invoice-receipt-section"><div class="invoice-receipt-section-head"><h3>اقلام فاکتور</h3><span>مبالغ تومان</span></div><div class="invoice-receipt-lines"><div class="invoice-receipt-line" id="invoiceLine"><div><strong class="invoice-line-name">دمنوش آرام دل – سایز بزرگ</strong><small class="invoice-line-math"><bdi dir="ltr">۳ × ۲۲۰٬۰۰۰</bdi></small></div><strong class="invoice-receipt-line-total">۶۶۰٬۰۰۰</strong></div></div></section></div>
<div class="panel-confirm-layer" id="confirm"><div class="panel-confirm-backdrop"></div><section class="panel-confirm-card"><div class="panel-confirm-icon">!</div><div class="panel-confirm-copy"><h2>رد سفارش؟</h2><p>این سفارش از صف تأیید خارج می‌شود.</p></div><div class="panel-confirm-actions"><button class="btn btn-danger" id="ok">رد سفارش</button><button class="btn btn-light" id="cancel">انصراف</button></div></section></div>
</body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,768):
        pg=b.new_page(viewport={'width':width,'height':900}); pg.set_content(html)
        # Sidebar identity uses the dedicated high-contrast token.
        color=pg.locator('#userName').evaluate("e=>getComputedStyle(e).color")
        assert color=='rgb(237, 245, 240)',(width,color)
        # Confirm final action is visually on the right in RTL when actions are side-by-side.
        if width<=520:
            ok=pg.locator('#ok').bounding_box(); cancel=pg.locator('#cancel').bounding_box()
            assert ok and cancel and ok['x']>cancel['x'],(width,ok,cancel)
        # Invoice is readable without horizontal scrolling; mobile header is removed and all values stay visible.
        metrics=pg.evaluate("()=>({iw:innerWidth,sw:document.documentElement.scrollWidth,line:document.querySelector('#invoiceLine').getBoundingClientRect().width,wrap:document.querySelector('.invoice-receipt-lines').getBoundingClientRect().width})")
        assert metrics['sw']<=metrics['iw']+1,(width,metrics)
        if width<=720:
            assert metrics['line']<=metrics['wrap']+1,(width,metrics)
            for sel in ('.invoice-line-name','.invoice-line-math','.invoice-receipt-line-total'):
                assert pg.locator(sel).is_visible(),(width,sel)
        pg.close()
    b.close()
print('1.31.7 production UI browser passed: sidebar identity is readable, RTL confirm action is right-aligned, and current receipt rows need no horizontal scroll.')
