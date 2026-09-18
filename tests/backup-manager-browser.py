#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'] if (ROOT/p).exists())
rows=''.join(f'''<article class="backup-version-row"><div class="backup-version-copy"><strong>۱۸ مرداد ۱۴۰۵، ۰{i}:۳۰</strong><small>۵٫۲ مگابایت · سالم و آماده بازیابی</small></div><button class="btn btn-sm btn-outline">بازگرداندن اطلاعات</button></article>''' for i in range(7))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content">
<div class="alert alert-warning backup-export-reminder"><div><strong>زمان ذخیره یک نسخه خارج از سرور رسیده است.</strong><span>بیش از ۷ روز از آخرین دانلود امن گذشته است.</span></div><button class="btn btn-sm btn-primary" data-secure-export="sokna-backup-test.tar.gz">دانلود امن آخرین پشتیبان</button></div>
<section class="card backup-summary is-ok"><div class="backup-summary-head"><div><small>وضعیت حفاظت از اطلاعات</small><h2>اطلاعات شما پشتیبان سالم و به‌روز دارد</h2><p>پشتیبان‌های اطلاعات به‌صورت روزانه نگهداری می‌شوند.</p></div><form><button class="btn btn-primary">گرفتن پشتیبان جدید</button></form></div><div class="backup-summary-grid"><div><span>آخرین پشتیبان سالم</span><strong>امروز، ۰۱:۳۰</strong><small>آماده بازیابی</small></div><div><span>نسخه خارج از سرور</span><strong>۷ روز قبل</strong><small>نیازمند دانلود</small></div><div><span>نگهداری روی سرور</span><strong>۷ نسخه اخیر</strong><small>پشتیبان روزانه</small></div></div></section>
<div class="maintenance-manager-grid"><section class="card offserver-card"><div class="card-head"><div><h2>یک نسخه خارج از سرور نگه دارید</h2><small>حداقل هفته‌ای یک‌بار دانلود کنید.</small></div></div><div class="card-body"><div class="offserver-status-row"><div><span>آخرین دانلود ثبت‌شده</span><strong>۷ روز قبل</strong></div><span class="badge badge-warning">زمان دانلود رسیده</span></div><button class="btn btn-light" data-secure-export="sokna-backup-test.tar.gz">دانلود امن آخرین پشتیبان سالم</button></div></section><section class="card"><div class="card-head"><h2>نسخه برنامه</h2></div><div class="card-body"><a class="btn btn-light">مرکز به‌روزرسانی و بازیابی</a></div></section></div>
<section class="card restore-list-card"><div class="card-head"><div><h2>بازیابی اطلاعات</h2><small>نسخه‌های سالم و سازگار</small></div></div><div class="card-body backup-version-list">{rows}</div></section>
<details class="card maintenance-technical" open><summary><span><strong>جزئیات و ابزارهای نگهداری</strong><small>برای بررسی فنی</small></span></summary><div class="maintenance-technical-body"><section class="maintenance-health-strip"><div><span>آخرین بررسی سلامت</span><strong>سالم</strong></div><div><span>فضای آزاد سرور</span><strong>۱ گیگابایت</strong></div><div><span>وضعیت عملیات</span><strong>آماده</strong></div><form><button class="btn btn-sm btn-light">اجرای بررسی سلامت</button></form></section><div class="data-table-wrap"><table class="data-table mobile-card-table maintenance-archive-table"><thead><tr><th>زمان</th><th>نوع</th><th>نسخه</th><th>حجم</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><tr><td data-label="زمان">امروز</td><td data-label="نوع">پشتیبان اطلاعات</td><td>1.30.5</td><td>5 MB</td><td>سالم</td><td><button class="btn btn-sm btn-light">دانلود فایل</button></td></tr></tbody></table></div></div></details>
<dialog class="panel-action-dialog" id="secureExportDialog" open><form><div class="panel-action-dialog-head"><small>نسخه خارج از سرور</small><h3>ساخت فایل پشتیبان رمزگذاری‌شده</h3></div><div class="alert alert-warning">اگر این رمز را گم کنید، فایل .skb قابل بازیابی نخواهد بود.</div><label class="form-group"><span>رمز بازیابی</span><input class="form-control" type="password" minlength="12" maxlength="256"></label><label class="form-group"><span>تکرار رمز بازیابی</span><input class="form-control" type="password" minlength="12" maxlength="256"></label><div class="actions"><button class="btn btn-light" type="button">انصراف</button><button class="btn btn-primary" type="submit">ساخت و دانلود فایل امن</button></div></form></dialog>
</div></main></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,360,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':900})
        page.set_content(html)
        page.wait_for_timeout(40)
        metrics=page.evaluate('''() => ({
            inner: innerWidth,
            scroll: document.documentElement.scrollWidth,
            grid: getComputedStyle(document.querySelector('.backup-summary-grid')).gridTemplateColumns,
            buttons: [...document.querySelectorAll('.backup-version-row .btn')].map(b=>b.getBoundingClientRect().height),
            tech: document.querySelector('.maintenance-technical').getBoundingClientRect(),
            wrap: document.querySelector('.maintenance-technical .data-table-wrap').getBoundingClientRect(),
            dialog: document.querySelector('#secureExportDialog').getBoundingClientRect(),
            passwordWidths: [...document.querySelectorAll('#secureExportDialog input[type=password]')].map(x=>x.getBoundingClientRect().width),
            dialogButtons: [...document.querySelectorAll('#secureExportDialog .btn')].map(x=>x.getBoundingClientRect().height)
        })''')
        assert metrics['scroll'] <= width+1, (width, metrics)
        assert all(h >= 43.5 for h in metrics['buttons']), (width, metrics['buttons'])
        assert metrics['tech']['left'] >= -1 and metrics['tech']['right'] <= width+1, (width, metrics['tech'])
        assert metrics['wrap']['right'] <= width+1, (width, metrics['wrap'])
        assert metrics['dialog']['left'] >= -1 and metrics['dialog']['right'] <= width+1, (width, metrics['dialog'])
        assert all(w > 0 and w <= width for w in metrics['passwordWidths']), (width, metrics['passwordWidths'])
        assert all(h >= 43.5 for h in metrics['dialogButtons']), (width, metrics['dialogButtons'])
        if width <= 760:
            assert len(metrics['grid'].split()) == 1, (width, metrics['grid'])
        else:
            assert len(metrics['grid'].split()) == 3, (width, metrics['grid'])
        page.close()
    browser.close()
print('Backup manager browser passed at 320/360/390/412/768/1366: no root overflow, secure-export dialog is bounded/touch-safe, responsive summary and technical table stay local.')
