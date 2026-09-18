#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]

def css(*paths):
    return '\n'.join((ROOT / p).read_text(encoding='utf-8') for p in paths)

guest_css = css('assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css')
purchase_css = css('assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css')

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    page = browser.new_page(viewport={'width':390,'height':844})
    page.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{guest_css}</style></head><body class="guest-menu-page"><div class="drawer-content"><button class="guest-takeaway-disclosure"><span><strong>بیرون‌بر هم دارید؟</strong><small>در صورت نیاز، موارد بیرون‌بر را مشخص کنید.</small></span><b>‹</b></button><details class="order-note-field"><summary><span>افزودن یادداشت سفارش</span><small>اختیاری</small><b>‹</b></summary><textarea class="order-note"></textarea></details></div></body></html>''')
    guest = page.evaluate('''()=>{const t=document.querySelector('.guest-takeaway-disclosure'), n=document.querySelector('.order-note-field'), s=n.querySelector('summary');return {noteHeight:n.getBoundingClientRect().height,summaryHeight:s.getBoundingClientRect().height,takeawayBg:getComputedStyle(t).backgroundColor,noteBg:getComputedStyle(n).backgroundColor,overflow:document.documentElement.scrollWidth-innerWidth}}''')
    assert 43 <= guest['summaryHeight'] <= 45.5, guest
    assert guest['noteHeight'] <= 47, guest
    assert guest['takeawayBg'] != guest['noteBg'], guest
    assert guest['overflow'] <= 1, guest
    page.close()

    for width in (390,768,1024):
        page = browser.new_page(viewport={'width':width,'height':844})
        page.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{purchase_css}</style></head><body><main class="purchase-page"><section class="card purchase-waiting-card"><div class="card-head"><div><h2>در انتظار خرید · ۱ قلم</h2></div></div><div class="purchase-need-list"><article class="purchase-need-row"><div class="purchase-need-main"><div class="purchase-need-title"><strong>پپسی</strong></div><small>نیاز: <b>۲ عدد</b> · آشپزخانه ۲ عدد</small><small class="muted">موجودی انبار: ۳۳ عدد</small></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm">شروع خرید</button><button class="btn btn-light btn-sm">بیشتر</button></div><div class="purchase-need-more hidden"></div></article></div></section><section class="card purchase-preparing-card"><div class="purchase-need-list"><article class="purchase-need-row"><div class="purchase-need-main"><div class="purchase-need-title"><strong>آویشن خشک</strong></div><small><b>۲ کیلوگرم</b> · آشپزخانه</small><small class="muted">مدیر کافه · ۲ شهریور</small></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm">ثبت تحویل</button><button class="btn btn-light btn-sm">بیشتر</button></div></article></div></section></main></body></html>''')
        result = page.evaluate('''()=>{const r=document.querySelector('.purchase-waiting-card .purchase-need-row'), p=document.querySelector('.purchase-preparing-card .btn-primary'), cs=getComputedStyle(r);return {rowHeight:r.getBoundingClientRect().height,rowRadius:cs.borderRadius,rowBg:cs.backgroundColor,prepWidth:p.getBoundingClientRect().width,overflow:document.documentElement.scrollWidth-innerWidth}}''')
        assert result['rowHeight'] <= 105, (width,result)
        assert result['rowRadius'] == '0px', (width,result)
        assert result['rowBg'] == 'rgba(0, 0, 0, 0)', (width,result)
        assert result['overflow'] <= 1, (width,result)
        page.close()

    browser.close()

print('UI density root-fix browser gate passed: compact guest note, distinct takeaway disclosure, flat purchase rows and no overflow at 390/768/1024.')
