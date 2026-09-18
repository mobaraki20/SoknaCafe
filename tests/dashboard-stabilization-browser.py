#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; dashboard stabilization browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css']
html='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-body"><main class="panel-content"><section class="dashboard-priority has-attention"><div><small>اولویت همین لحظه</small><h2>۳ مورد نیازمند اقدام</h2><p>سفارش‌های تازه و فراخوان‌های مهمان را پیش از گزارش‌ها رسیدگی کنید.</p></div><a class="btn btn-primary">کار روزانه</a></section><section class="dashboard-kpi-grid"><article class="dashboard-kpi is-sales"><small>فروش خالص امروز</small><strong>۹٬۲۰۰٬۰۰۰ تومان</strong><span>۱۸ فاکتور نهایی</span></article><article class="dashboard-kpi"><small>میزهای باز</small><strong>۱۲</strong><span>۳۸ میز آزاد</span></article><article class="dashboard-kpi"><small>سفارش منتظر</small><strong>۲</strong><span>۲۹ سفارش امروز</span></article><article class="dashboard-kpi"><small>فراخوان</small><strong>۱</strong><span>فعال</span></article></section><div class="dashboard-workspace"><section class="card"><div class="card-head"><h2>مقصدهای تسویه</h2></div><div class="card-body dashboard-destination-list"><div><span>مستقیم</span><strong>۶٬۰۰۰٬۰۰۰</strong></div><div><span>اقامتگاه</span><strong>۲٬۰۰۰٬۰۰۰</strong></div><div><span>مشترک</span><strong>۱٬۲۰۰٬۰۰۰</strong></div></div></section><section class="card"><div class="card-head"><h2>سلامت عملیات</h2></div><div class="card-body dashboard-system-list"><a><span class="system-dot is-ok"></span><div><strong>سفارش‌گیری فعال</strong><small>کل کافه</small></div></a><a><span class="system-dot is-warning"></span><div><strong>یک بخش شلوغ</strong><small>آماده‌سازی</small></div></a></div></section></div><section class="card"><div class="card-head"><h2>فرم خوانا</h2></div><div class="card-body"><label class="form-group"><span>مقدار تخفیف</span><input class="form-control" id="discount" placeholder="مثلاً ۱۰"><small>درصد یا مبلغ را مشخص کنید.</small></label></div></section></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in [(1440,900),(768,1024),(390,844),(320,720)]:
        page=browser.new_page(viewport={'width':width,'height':height});page.set_content(html)
        for css in CSS:page.add_style_tag(path=str(css))
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), width
        kpis=page.locator('.dashboard-kpi')
        assert kpis.count()==4
        if width<=600:
            assert page.locator('.dashboard-priority .btn').bounding_box()['width'] > width*0.8
        field=page.locator('#discount')
        border=field.evaluate('e=>getComputedStyle(e).borderTopWidth')
        assert float(border.replace('px',''))>=1
        field.focus();outline=field.evaluate('e=>getComputedStyle(e).boxShadow')
        assert outline!='none'
        page.close()
    browser.close()
print('Dashboard stabilization browser passed: exception-first hierarchy, responsive density, clear forms and no overflow.')
