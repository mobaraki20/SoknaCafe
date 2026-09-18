#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; v1.32.14 remaining-mobile browser skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-components.css']
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-body"><main style="max-width:1100px;margin:auto;padding:12px;display:grid;gap:14px">
<section class="card team-accounts-card"><div class="card-head"><div><h2>اعضای تیم</h2></div></div><div class="team-account-list"><article class="team-account-row"><header><div><strong>مدیر کافه</strong><span class="team-account-username" dir="ltr">manager</span></div><span class="badge badge-posted">فعال</span></header><div class="team-account-summary"><span>۵ مسئولیت</span><span>آماده‌سازی: بار</span></div><details class="team-account-details"><summary>مشاهده مسئولیت‌ها</summary><div class="permission-chips"><span class="badge">سفارش</span><span class="badge">انبار</span><span class="badge">صندوق</span></div></details><div class="team-account-actions"><button class="btn btn-sm btn-light">ویرایش</button><button class="btn btn-sm btn-outline">غیرفعال‌کردن</button></div></article></div></section>
<div class="push-health-strip"><article><span>ثبت‌شده</span><strong>۳</strong></article><article><span>فعال</span><strong>۲</strong></article><article class="has-warning"><span>خطای اخیر</span><strong>۱</strong></article></div><section class="card push-device-card"><div class="push-device-list"><article class="push-device-row has-error"><header><div><strong>زری</strong><span>Galaxy · zari</span></div><span class="badge badge-posted">فعال</span></header><div class="push-device-health"><span>آخرین موفق: امروز</span><span class="text-danger">آخرین ارسال ناموفق: ۱۰:۲۰</span></div><details class="push-technical-detail"><summary>جزئیات آخرین خطا</summary><code dir="ltr">connection timeout while contacting endpoint</code></details><form class="push-device-action"><button class="btn btn-sm btn-outline">غیرفعال‌کردن اعلان</button></form></article></div></section>
<section class="card print5-section"><div class="print5-job-list"><article class="print5-job-row"><header><div><strong>آشپزخانه</strong><span>preparation · امروز ۱۰:۲۱</span></div><span class="badge badge-failed">ناموفق</span></header><div class="print5-job-meta"><span>تلاش ۲</span><span>order <b dir="ltr">921</b></span><span>خودکار</span></div><details class="print5-job-details"><summary>جزئیات خطا</summary><code>Printer offline</code></details><div class="print5-job-actions"><button class="btn btn-sm btn-light">تلاش مجدد</button><button class="btn btn-sm btn-outline">لغو Job</button></div></article></div></section>
<section class="card events-management-card"><table class="data-table mobile-card-table"><tbody><tr><td data-label="تصویر"><div class="thumb"></div></td><td data-label="رویداد"><strong>شب موسیقی</strong><br><small>حیاط</small></td><td data-label="زمان">۲۴ مرداد ۲۰:۰۰<br><small>تا ۲۳:۰۰</small></td><td data-label="ثبت‌نام">رزرو قبلی<br><small>ظرفیت ۳۰ نفر</small></td><td data-label="وضعیت"><span class="badge">پیش‌رو</span></td><td data-label="عملیات"><div class="row-action-menu"><button data-action-menu-trigger class="btn btn-light">عملیات</button></div></td></tr></tbody></table></section>
<div class="page-grid dashboard-main-grid"><section class="card table-card"><table class="data-table mobile-card-table"><tbody><tr><td data-label="سفارش">سفارش ۱۲۳</td><td data-label="میز">میز ۲</td><td data-label="مبلغ">۳۳۰٬۰۰۰ تومان</td><td data-label="وضعیت"><span class="badge">تأییدشده</span></td><td data-label="زمان">۳ دقیقه پیش</td></tr></tbody></table></section></div>
</main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':1200}); page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        assert not page.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), width
        if width<=760:
            assert page.locator('.team-account-actions .btn').first.bounding_box()['height'] >= 44, width
            assert page.locator('.print5-job-actions .btn').first.bounding_box()['height'] >= 44, width
            event=page.locator('.events-management-card tr').bounding_box(); assert event and event['height'] < 270, (width,event)
            recent=page.locator('.dashboard-main-grid tr').bounding_box(); assert recent and recent['height'] < 180, (width,recent)
        page.close()
    browser.close()
print('v1.32.14 remaining-mobile browser PASS at 320/360/390/412/768/1366 with compact team, push, print, event and dashboard surfaces.')
