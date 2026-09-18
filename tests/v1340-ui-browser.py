#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
css='\n'.join((R/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-content">
<section class="card"><div class="card-body"><div class="form-group full"><label>مسئولیت‌های کاری</label><div class="responsibility-grid">
{''.join(f'<label class="responsibility-card"><input type="checkbox" {"checked" if i in (0,3) else ""}><span><strong>{name}</strong><small>توضیح کوتاه مسئولیت کاری در شیفت</small></span></label>' for i,name in enumerate(['سالن و سفارش','آماده‌سازی','صندوق و حساب','انبار و خرید','سرپرستی و مدیریت']))}
</div></div><div class="form-group full inventory-special-access"><label>اختیار ویژه</label><label class="special-access-row"><input type="checkbox"><span><strong>مشاهده اطلاعات مالی انبار</strong><small>بهای خرید و ارزش موجودی</small></span></label></div></div></section>
<div class="panel-surface-stack supply-needs-page"><section class="card"><div class="card-body supply-builder"><label class="supply-search"><span>جست‌وجوی ماده یا کالا</span><input class="form-control" value="سینه مرغ"></label><div class="supply-draft-form"><div class="supply-draft-head"><div><strong>نیازهای این ثبت</strong><small>۳ مورد آماده ثبت</small></div></div><div class="supply-draft-list"><div class="supply-draft-row"><div class="supply-draft-copy"><strong>سینه مرغ</strong><small>موجودی سیستم: ۱٫۳ کیلوگرم</small></div><label class="supply-qty-field"><span>مقدار موردنیاز</span><span class="supply-qty-control"><input class="form-control" value="4"><em>کیلوگرم</em></span></label><button class="supply-row-remove">×</button></div><div class="supply-draft-row is-free"><div class="supply-draft-copy"><strong>فلفل هالوپینو</strong><small>خارج از فهرست · فقط اگر خرید شد به انبار متصل یا ساخته می‌شود</small></div><label class="supply-qty-field"><span>مقدار موردنیاز</span><span class="supply-qty-control"><input class="form-control" value="2"><select class="form-control supply-unit-select"><option>کیلوگرم</option></select></span></label><button class="supply-row-remove">×</button></div></div><div class="supply-submit-bar"><button class="btn btn-primary">ثبت ۲ نیاز</button></div></div></div></section></div>
<div class="panel-surface-stack purchase-page purchase-v1350"><section class="card"><div class="card-head"><div><h2>نیازهای خرید · ۲</h2><small>نیازهای واقعی اعلام‌شده توسط تیم</small></div></div><div class="purchase-need-list"><article class="purchase-need-row"><div class="purchase-need-main"><div class="purchase-need-title"><strong>سینه مرغ</strong></div><small>آشپزخانه · امیر · نیاز: <b>۴ کیلوگرم</b></small><small class="muted">موجودی سیستم: ۱٫۳ کیلوگرم</small></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm">ثبت خرید و ورود</button><button class="btn btn-light btn-sm">بیشتر</button></div></article><article class="purchase-need-row"><div class="purchase-need-main"><div class="purchase-need-title"><strong>فلفل هالوپینو</strong><span class="status-chip status-chip-warning">خارج از فهرست</span></div><small>آشپزخانه · نیاز: <b>۲ کیلوگرم</b></small></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm">خرید، ساخت کالا و ورود</button><button class="btn btn-light btn-sm">بیشتر</button></div></article></div></section><section class="card purchase-low-stock"><div class="card-head"><div><h2>موجودی کم · ۱</h2><small>فقط پیشنهاد سیستم؛ اگر لازم نیست هیچ کاری نکن.</small></div></div><div class="purchase-low-list"><form class="purchase-low-row"><div><strong>خامه</strong><small>موجودی ۲ عدد · حد هشدار ۴ عدد</small></div><label><span>مقدار برای خرید</span><span class="purchase-inline-qty"><input class="form-control" value="2"><em>عدد</em></span></label><button class="btn btn-light btn-sm">+ افزودن</button></form></div></section></div>
</main></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,390,412,768,1366):
        page=b.new_page(viewport={'width':width,'height':1000}); page.set_content(html); page.wait_for_timeout(20)
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), width
        cards=page.locator('.responsibility-card'); assert cards.count()==5
        cols=page.evaluate("getComputedStyle(document.querySelector('.responsibility-grid')).gridTemplateColumns.split(' ').length")
        assert cols==(1 if width<=620 else 2),(width,cols)
        row=page.locator('.supply-draft-row').first.bounding_box(); assert row and row['width']<=width-16,(width,row)
        need=page.locator('.purchase-need-row').first.bounding_box(); assert need and need['width']<=width-16,(width,need)
        if width<=760:
            main=page.locator('.purchase-need-row .purchase-need-main').first.bounding_box(); actions=page.locator('.purchase-need-row .purchase-need-actions').first.bounding_box()
            if width<=360:
                assert actions['y']>=main['y']+main['height']-1,(width,main,actions)
            else:
                assert actions['x']+actions['width']<=main['x']+1,(width,main,actions)
        for sel in ('.purchase-need-actions .btn','.supply-submit-bar .btn'):
            for el in page.locator(sel).all():
                box=el.bounding_box(); assert box and box['height']>=34,(width,sel,box)
        page.close()
    b.close()
print('Responsibility/supply/purchase UI browser PASS at 320/390/412/768/1366: compact bundles, multi-item needs, direct receive, narrow stacking plus compact 390–760 rows, no horizontal overflow.')
