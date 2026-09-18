#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    raise RuntimeError('Playwright is required for panel experience release checks.') from exc
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/items-management.css'])

def no_overflow(page,w):
    d=page.evaluate('()=>({sw:document.documentElement.scrollWidth,bw:document.body.scrollWidth,cw:document.documentElement.clientWidth})')
    assert d['sw']<=w+1 and d['bw']<=w+1,(w,d)

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for w in (320,360,390,412,768,1366):
        pg=b.new_page(viewport={'width':w,'height':900})
        pg.set_content(f'''<!doctype html><html dir="rtl" lang="fa"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content">
        <div class="panel-surface-stack"><section class="card" id="c1" style="height:80px"></section><section class="card" id="c2" style="height:80px"></section></div>
        <div class="panel-detail-nav"><a class="panel-back-link">‹ بازگشت به فاکتورها</a></div>
        <section class="card invoice-detail-card"><div class="card-body invoice-receipt"><header class="invoice-receipt-summary"><div class="invoice-receipt-title"><div><h2>فاکتور ۴۹</h2><small>سال مالی ۱۴۰۵</small></div><div class="invoice-receipt-title-actions"><span class="badge badge-completed">ثبت‌شده</span><button class="panel-icon-action" aria-label="چاپ">⌁</button></div></div><div class="invoice-receipt-context"><div class="invoice-receipt-context-main"><strong>میز ۲</strong><span>حساب اقامتگاه</span></div><div class="invoice-receipt-party">مهران مبارکی · نخلستون</div></div><div class="invoice-receipt-time">۲۳ مرداد ۱۴۰۵ · ۱۵:۱۲ · عصر</div></header><section class="invoice-receipt-section"><div class="invoice-receipt-section-head"><h3>اقلام فاکتور</h3><span>مبالغ تومان</span></div><div class="invoice-receipt-lines"><div class="invoice-receipt-line"><div><strong>برگر گوشت</strong><small>۱ × ۴۶۵٬۰۰۰</small></div><strong>۴۶۵٬۰۰۰</strong></div></div></section><section class="invoice-receipt-totals"><div class="is-final"><span>مبلغ نهایی</span><strong>۴۶۵٬۰۰۰ تومان</strong></div></section><details class="panel-disclosure invoice-receipt-meta"><summary><span>اطلاعات ثبت</span><span>⌄</span></summary></details></div></section>
        </main></body></html>''')
        no_overflow(pg,w)
        c1=pg.locator('#c1').bounding_box();c2=pg.locator('#c2').bounding_box();assert c2['y']-(c1['y']+c1['height'])>=15,(w,c1,c2)
        icon=pg.locator('.panel-icon-action').bounding_box();assert icon and icon['width']>=43 and icon['height']>=43,(w,icon)
        assert pg.locator('.invoice-receipt-summary').count()==1
        pg.close()

    pg=b.new_page(viewport={'width':390,'height':900})
    pg.set_content(f'''<!doctype html><html dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><div class="financial-workspace subscriber-profile-workspace panel-page-flow"><div class="subscriber-profile-stack panel-page-flow"><section class="card subscriber-profile-head financial-record-hero"><div class="subscriber-profile-top"><div class="subscriber-profile-identity"><div class="subscriber-profile-name-row"><h2>علی بهبودی</h2><span class="panel-status-badge is-success">فعال</span></div><p>۰۹۱۷۷۷۷۷۷۷۷</p></div><a class="panel-icon-action">✎</a></div><div class="subscriber-balance-block"><span>مانده حساب</span><strong>۱۳٬۶۰۵٬۰۰۰ تومان</strong></div><div class="subscriber-profile-foot">آخرین فعالیت: ۲۳ مرداد · ۱۵:۱۳</div></section><details class="card panel-disclosure subscriber-payment-card"><summary><span><strong>ثبت پرداخت</strong><small>پرداخت کامل یا جزئی</small></span><span>⌄</span></summary></details></div></div><div class="financial-workspace subscriber-directory-workspace panel-page-flow"><section class="card subscriber-directory-card financial-index-surface"><div class="subscriber-card-list financial-list"><a class="subscriber-list-card financial-row"><div class="subscriber-list-identity financial-row-main"><strong>علی بهبودی</strong><span>۰۹۱۷۷۷۷۷۷۷۷</span><small>آخرین فعالیت: امروز</small></div><div class="subscriber-list-balance financial-row-amount"><strong>۱۳٬۶۰۵٬۰۰۰ تومان</strong></div><span class="subscriber-list-chevron">‹</span></a></div></section></div></main></body></html>''')
    no_overflow(pg,390)
    profile=pg.locator('.subscriber-profile-head').bounding_box();assert profile and profile['height']<210,profile
    top=pg.locator('.subscriber-profile-top').bounding_box();balance=pg.locator('.subscriber-balance-block').bounding_box();assert top and balance and balance['y']>=top['y']+top['height'],(top,balance)
    row=pg.locator('.subscriber-list-card').bounding_box();assert row and row['height']<105,row
    assert pg.locator('.subscriber-list-card .panel-status-badge.is-success').count()==0,'healthy list state must stay quiet'
    pg.close();b.close()
print('1.32.14 panel experience browser PASS at 320/360/390/412/768/1366.')
