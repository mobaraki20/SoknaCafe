#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
def load(*files): return '\n'.join((ROOT/f).read_text(encoding='utf-8') for f in files)
quick=load('assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/quick-order.css')
purchase=load('assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css')
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,412,768):
        page=browser.new_page(viewport={'width':width,'height':844})
        page.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{quick}</style></head><body><article class="quick-order-cart-line is-takeaway-editing has-takeaway-stepper"><div class="quick-order-line-copy"><strong>چای مراکش - بزرگ</strong></div><div class="quick-order-line-actions"><span class="quick-order-line-qty-readonly"><small>تعداد</small><strong>۳</strong></span><div class="quick-order-takeaway-stepper"><button>−</button><span class="fulfillment-ratio">۲ از ۳</span><button>+</button></div><button class="icon-button">یادداشت</button></div></article></body></html>''')
        metrics=page.evaluate('''()=>{const s=document.querySelector('.quick-order-takeaway-stepper'),a=document.querySelector('.quick-order-line-actions');return {sw:s.getBoundingClientRect().width,aw:a.getBoundingClientRect().width,overflow:document.documentElement.scrollWidth-innerWidth}}''')
        assert 118 <= metrics['sw'] <= 142, (width,metrics)
        assert metrics['sw'] < metrics['aw'] - 20 or width==320, (width,metrics)
        assert metrics['overflow'] <= 1, (width,metrics)
        page.close()
    for width in (320,390,768,1366):
        page=browser.new_page(viewport={'width':width,'height':844})
        page.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{purchase}</style></head><body><header class="purchase-page-head inventory-toolbar"><div class="panel-copy-stack"><strong>خرید و تحویل</strong></div><div class="inventory-toolbar-actions"><a class="btn btn-light">درخواست خرید</a><button class="btn btn-light purchase-share-btn"><svg class="ui-icon"></svg><span>اشتراک</span></button></div></header></body></html>''')
        m=page.evaluate('''()=>{const a=document.querySelector('.inventory-toolbar-actions a'),s=document.querySelector('.purchase-share-btn');return {aw:a.getBoundingClientRect().width,sw:s.getBoundingClientRect().width,sh:s.getBoundingClientRect().height,overflow:document.documentElement.scrollWidth-innerWidth}}''')
        assert 44 <= m['sh'], (width,m)
        assert m['sw'] <= 125, (width,m)
        if width<=760: assert m['aw'] >= m['sw'], (width,m)
        assert m['overflow'] <= 1, (width,m)
        page.close()
    browser.close()
print('PASS v1363 UI browser: takeaway stepper stays content-width and purchase Share stays compact without overflow.')
