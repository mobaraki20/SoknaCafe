#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
 'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css','assets/css/items-management.css'
])

def no_overflow(pg,w):
    d=pg.evaluate('()=>({sw:document.documentElement.scrollWidth,bw:document.body.scrollWidth,cw:document.documentElement.clientWidth})')
    assert d['sw']<=w+1 and d['bw']<=w+1,(w,d)

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for w in (320,360,390,412,768,1366):
        pg=b.new_page(viewport={'width':w,'height':900})
        # Shared surfaces + balanced actions + QR compact rows
        pg.set_content(f'''<!doctype html><html dir="rtl" lang="fa"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content">
        <div class="panel-balanced-actions" id="actions"><a class="btn btn-primary">میز جدید</a><a class="btn btn-light">مدیریت QR</a></div>
        <div class="panel-surface-stack" id="stack"><section class="card" style="height:80px"></section><section class="card" style="height:80px"></section></div>
        <section class="card qr-table-list-card" style="margin-top:20px"><div class="qr-table-list"><a class="qr-table-row"><span class="qr-table-identity"><strong>میز ۱</strong><small>تراس</small></span><span class="panel-status-badge is-ok">فعال</span><span class="qr-table-open">‹</span></a></div></section>
        </main></body></html>''')
        no_overflow(pg,w)
        a=[pg.locator('#actions .btn').nth(i).bounding_box() for i in range(2)]
        assert all(a)
        if w<=340:
            assert a[1]['y']>a[0]['y']+20,(w,a)
        else:
            assert abs(a[0]['y']-a[1]['y'])<2 and abs(a[0]['width']-a[1]['width'])<2,(w,a)
            assert a[0]['height']>=47 and a[1]['height']>=47,(w,a)
        cards=[pg.locator('#stack .card').nth(i).bounding_box() for i in range(2)]
        assert cards[1]['y']-(cards[0]['y']+cards[0]['height'])>=16,(w,cards)
        qr=pg.locator('.qr-table-row').bounding_box();assert qr and qr['height']<90,(w,qr)
        pg.close()

    # Count screen: helper is compact, count rows are operationally dense, sticky actions one row at standard phone width.
    for w in (360,390,412):
        pg=b.new_page(viewport={'width':w,'height':820})
        pg.set_content(f'''<!doctype html><html dir="rtl" lang="fa"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content">
        <nav class="panel-subnav"><a>موجودی</a><a>گردش</a><a class="active">شمارش‌ها</a></nav>
        <div class="inventory-count-progress"><div class="inventory-count-progress-main"><div class="panel-copy-stack"><strong>شمارش ماهانه</strong><small>۳۰ از ۸۹ قلم</small></div><div class="inventory-count-meter"><span style="width:34%"></span></div></div><button class="btn btn-light">…</button></div>
        <div class="panel-helper-note inventory-count-helper"><span>ⓘ</span><div><strong>شمارش کور فعال است.</strong> موجودی سیستم نمایش داده نمی‌شود.</div></div>
        <section class="inventory-count-group"><h3>ملزومات مصرفی</h3><div class="inventory-count-group-list"><div class="inventory-count-line"><div class="inventory-count-copy"><strong>دستکش لاتکس لارج</strong></div><div class="inventory-count-entry"><input class="form-control" value="۵"><span class="inventory-count-unit">عدد</span></div></div><div class="inventory-count-line"><div class="inventory-count-copy"><strong>دستکش مشکی</strong></div><div class="inventory-count-entry"><input class="form-control"><span class="inventory-count-unit">عدد</span></div></div></div></section>
        <div class="inventory-sticky-actions inventory-count-actions"><button class="btn btn-primary">مرور شمارش</button><button class="btn btn-light">ذخیره و خروج</button></div>
        </main></body></html>''')
        no_overflow(pg,w)
        helper=pg.locator('.inventory-count-helper').bounding_box();assert helper and helper['height']<100,(w,helper)
        rows=pg.locator('.inventory-count-line');boxes=[rows.nth(i).bounding_box() for i in range(2)];assert all(boxes)
        assert max(x['height'] for x in boxes)<85,(w,boxes)
        acts=[pg.locator('.inventory-count-actions .btn').nth(i).bounding_box() for i in range(2)]
        assert abs(acts[0]['y']-acts[1]['y'])<2,(w,acts)
        pg.close()

    # Financial archive: one grouped surface, compact transaction rows.
    for w in (320,360,390,412):
        pg=b.new_page(viewport={'width':w,'height':850})
        pg.set_content(f'''<!doctype html><html dir="rtl" lang="fa"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><section class="invoice-result-list"><section class="invoice-day-group financial-index-surface"><header><strong>۲۳ مرداد ۱۴۰۵</strong></header><div class="invoice-day-list"><a class="invoice-transaction-row"><div class="invoice-transaction-main"><strong>میز ۳ · علی بهبودی</strong><span>حساب مشترک</span></div><div class="invoice-transaction-amount"><strong>۴۴۰٬۰۰۰ تومان</strong></div><div class="invoice-transaction-ref"><span>فاکتور ۵۱ · ۱۵:۱۳</span><span class="invoice-transaction-chevron">‹</span></div></a><a class="invoice-transaction-row"><div class="invoice-transaction-main"><strong>میز ۲ · نخلستون</strong><span>حساب اقامتگاه</span></div><div class="invoice-transaction-amount"><strong>۴۶۵٬۰۰۰ تومان</strong></div><div class="invoice-transaction-ref"><span>فاکتور ۵۰ · ۱۵:۱۲</span><span class="invoice-transaction-chevron">‹</span></div></a></div></section></section></main></body></html>''')
        no_overflow(pg,w)
        row=pg.locator('.invoice-transaction-row').first.bounding_box();assert row and row['height']<120,(w,row)
        assert pg.locator('.invoice-day-group').evaluate('e=>getComputedStyle(e).borderRadius')=='0px', 'Date groups are sections inside the shared financial shell, not independent rounded cards.'
        pg.close()

    # Mobile user editor focuses on editor instead of forcing the form below a long account list.
    pg=b.new_page(viewport={'width':390,'height':820})
    pg.set_content(f'''<!doctype html><html dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><div class="page-grid team-page-grid is-editor-mode"><section class="card team-accounts-card">list</section><section class="card team-editor-card"><a class="team-editor-back">بازگشت</a><input class="form-control"></section></div></main></body></html>''')
    assert pg.locator('.team-accounts-card').evaluate('e=>getComputedStyle(e).display')=='none'
    assert pg.locator('.team-editor-card').is_visible() and pg.locator('.team-editor-back').is_visible()
    no_overflow(pg,390);pg.close()

    # Subscriber list remains a continuous financial list on mobile, not card-per-record.
    pg=b.new_page(viewport={'width':390,'height':820})
    pg.set_content(f'''<!doctype html><html dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><section class="card"><div class="subscriber-card-list"><article class="subscriber-list-card"><div><a class="subscriber-name-link">علی</a><small>امروز</small></div><div><strong>۴۰۰٬۰۰۰ تومان</strong></div></article><article class="subscriber-list-card"><div><a class="subscriber-name-link">مریم</a></div><div><strong>۰ تومان</strong></div></article></div></section></main></body></html>''')
    st=pg.locator('.subscriber-list-card').first.evaluate('e=>({br:getComputedStyle(e).borderRadius,bt:getComputedStyle(e).borderTopStyle})')
    assert st['br']=='0px',st
    no_overflow(pg,390);pg.close()
    b.close()
print('1.32.15 hotfix browser passed: balanced actions, surface gaps, compact count workflow, financial history, focused team editor, and continuous subscriber list.')
