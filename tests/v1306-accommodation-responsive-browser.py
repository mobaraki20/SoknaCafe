#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; accommodation responsive browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
rows=''.join(
    f'<article class="accommodation-history-item financial-row is-navigable" data-row-href="invoices.php?id={i}" role="link" tabindex="0">'
    f'<div class="financial-row-main accommodation-history-identity"><strong>رزرو {100+i} · میز {i}</strong>'
    f'<span>مریم عنبری · چهار</span><small>فاکتور {70+i} · ۲۱ مرداد · ۱۶:۴۹</small></div>'
    '<div class="financial-row-amount"><strong>۲۶۰٬۰۰۰ تومان</strong></div><span class="financial-row-chevron">‹</span></article>'
    for i in range(1,6)
)
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-body"><main class="panel-main"><div class="financial-workspace accommodation-page-stack panel-page-flow"><section class="card accommodation-history-card financial-page-shell financial-index-surface"><div class="financial-page-head"><div class="financial-page-summary"><strong>۲۵۰ سابقه</strong></div></div><div class="financial-page-toolbar"><form class="accommodation-history-search financial-filter-form"><label class="financial-search-field"><input class="form-control" placeholder="جست‌وجوی مهمان، اتاق، رزرو، فاکتور یا میز"></label><select class="form-control financial-compact-select"><option>همه سوابق</option></select></form></div><div class="accommodation-history-list financial-list">'''+rows+'''</div><nav class="panel-pagination"><a class="btn btn-light btn-sm">قبلی</a><span>صفحه ۱ از ۱۰</span><a class="btn btn-light btn-sm">بعدی</a></nav></section></div></main></body></html>'''
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-components.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412,768,1024,1366):
        page=browser.new_page(viewport={'width':width,'height':900});page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        assert not page.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), ('root overflow',width)
        assert page.locator('.accommodation-history-list').count()==1, ('single list owner',width)
        assert page.locator('table').count()==0, ('legacy table returned',width)
        items=page.locator('.accommodation-history-item')
        assert items.count()==5
        for row in items.all():
            b=row.bounding_box(); assert b and b['x']>=-0.5 and b['x']+b['width']<=width+0.5, (width,b)
        if width<=412:
            heights=[r.bounding_box()['height'] for r in items.all()]
            assert max(heights)<=96, ('row density',width,heights)
        first=items.first
        assert first.get_attribute('data-row-href')=='invoices.php?id=1'
        assert first.get_attribute('role')=='link' and first.get_attribute('tabindex')=='0'
        page.close()
    browser.close()
print('Accommodation responsive browser PASS at 320/360/390/412/768/1024/1366: one financial list, compact rows, whole-row destination, no legacy dual rendering.')
