#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]

def css_text(paths):
    return '\n'.join((ROOT / p).read_text(encoding='utf-8') for p in paths)

bill_css = css_text(['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-components.css','assets/css/operator-live.css'])
bill_html = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{bill_css}</style></head><body>
<div class="success-modal bill-edit-modal" style="display:grid"><div class="success-box bill-edit-box-v1294 rc4-bill-edit"><div class="bill-edit-body-v1294 bill-edit-simple">
<div class="bill-edit-current bill-edit-control-row"><span>تعداد نهایی</span><div class="quantity-stepper bill-edit-final-stepper"><button class="is-minus">−</button><input value="۱"><button class="is-plus">+</button></div></div>
<p class="bill-edit-preview">۲ عدد از حساب حذف می‌شود.</p>
<div class="bill-edit-prepared-count bill-edit-control-row"><div><strong>از مقدار حذف‌شده، چند عدد آماده شده بود؟</strong><small>نمونه توضیح</small></div><div class="quantity-stepper bill-edit-prepared-stepper"><button class="is-minus">−</button><input value="۰"><button class="is-plus">+</button></div><p>اثر آماده‌سازی</p></div>
</div></div></div></body></html>'''

quick_css = css_text(['assets/css/tokens.css','assets/css/app.css','assets/css/quick-order.css'])
quick_html = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{quick_css}</style></head><body class="quick-order-page"><div class="quick-order-workspace"><div class="quick-order-layout">
<aside class="quick-order-category-pane"></aside><section class="quick-order-catalog-pane"><div class="quick-order-items"><article class="quick-order-item is-selected"><div class="quick-order-item-copy"><strong>قهوه</strong><small>۱۰۰</small></div><div class="quick-order-item-action"><div class="quick-order-inline-qty item-step"><button class="is-minus">−</button><span>۱</span><button class="is-plus">+</button></div></div></article></div></section>
<aside class="quick-order-cart"><div></div><div class="quick-order-cart-body"><div class="quick-order-cart-lines"><article class="quick-order-cart-line"><div class="quick-order-line-copy"><strong>قهوه</strong></div><div class="quick-order-line-actions"><div class="quick-order-inline-qty quick-order-line-qty cart-step"><button class="is-minus">−</button><span>۱</span><button class="is-plus">+</button></div><button class="quick-order-line-note-button"></button></div></article></div></div><div></div></aside>
</div></div></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in [320, 360, 390, 600, 1024, 1366]:
        page = browser.new_page(viewport={'width': width, 'height': 850})
        page.set_content(bill_html)
        m = page.evaluate('''() => {
          const a=document.querySelector('.bill-edit-final-stepper').getBoundingClientRect();
          const b=document.querySelector('.bill-edit-prepared-stepper').getBoundingClientRect();
          const ab=[...document.querySelectorAll('.bill-edit-final-stepper>*')].map(x=>x.getBoundingClientRect());
          const bb=[...document.querySelectorAll('.bill-edit-prepared-stepper>*')].map(x=>x.getBoundingClientRect());
          return {a:{x:a.x,w:a.width,h:a.height},b:{x:b.x,w:b.width,h:b.height},ab:ab.map(r=>({x:r.x,w:r.width})),bb:bb.map(r=>({x:r.x,w:r.width}))};
        }''')
        assert abs(m['a']['x'] - m['b']['x']) <= 1, (width, m)
        assert abs(m['a']['w'] - m['b']['w']) <= 1, (width, m)
        assert abs(m['a']['h'] - m['b']['h']) <= 1, (width, m)
        assert m['ab'][0]['x'] < m['ab'][1]['x'] < m['ab'][2]['x'], (width, m)  # − / value / + in LTR control
        assert m['bb'][0]['x'] < m['bb'][1]['x'] < m['bb'][2]['x'], (width, m)
        if width <= 600:
            assert m['ab'][0]['w'] >= 43.5 and m['ab'][2]['w'] >= 43.5, (width, m)
        page.close()

    for width in [1024, 1280, 1366, 1600]:
        page = browser.new_page(viewport={'width': width, 'height': 900})
        page.set_content(quick_html)
        m = page.evaluate('''() => {
          const item=document.querySelector('.item-step').getBoundingClientRect();
          const cart=document.querySelector('.cart-step').getBoundingClientRect();
          const tracks=getComputedStyle(document.querySelector('.cart-step')).gridTemplateColumns;
          const buttons=[...document.querySelectorAll('.cart-step button')].map(x=>x.getBoundingClientRect());
          return {item:{w:item.width,h:item.height},cart:{w:cart.width,h:cart.height},tracks,buttons:buttons.map(r=>({w:r.width,h:r.height}))};
        }''')
        assert abs(m['item']['w'] - 98) <= 1 and abs(m['item']['h'] - 32) <= 1, (width, m)
        assert abs(m['cart']['w'] - 132) <= 1 and abs(m['cart']['h'] - 44) <= 1, (width, m)
        assert m['tracks'] == '44px 44px 44px', (width, m)
        assert all(b['h'] >= 43.5 for b in m['buttons']), (width, m)
        page.close()
    browser.close()

print('dev19 stepper browser PASS: bill steppers share one fixed control column at 320–1366px and desktop Quick Order cart keeps 132x44 geometry while product controls remain compact.')
