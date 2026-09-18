#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
HTML=r'''<!doctype html><html lang="fa" dir="rtl"><body><main style="display:grid;gap:28px;padding:10px">
<div class="financial-workspace financial-invoice-workspace panel-page-flow" id="invoiceArchive"><section class="card financial-page-shell financial-index-surface"><div class="financial-page-head"><div class="financial-page-summary"><strong>۵۱ فاکتور</strong></div></div><div class="financial-page-toolbar"><form class="financial-search-form"><label class="financial-search-field"><input class="form-control" placeholder="جست‌وجوی فاکتور، میز، مشترک، مهمان یا اتاق"></label></form><button class="financial-filter-trigger"><span>فیلترها</span><b>۲</b></button><div class="financial-filter-state"><div class="financial-filter-chips"><span class="financial-filter-chip">سال مالی ۱۴۰۵</span><span class="financial-filter-chip">حساب مشترک</span></div><a class="panel-clear-filter">پاک‌کردن</a></div></div><div class="invoice-result-list financial-list"><section class="invoice-day-group"><header><strong>۲۳ مرداد ۱۴۰۵</strong></header><div class="invoice-day-list">''' + ''.join(f'''<a class="invoice-transaction-row financial-row"><div class="invoice-transaction-main financial-row-main"><strong>فاکتور {50-i}</strong><span>میز {i} · علی بهبودی</span><small>حساب مشترک · ۱۵:{10+i}</small></div><div class="invoice-transaction-amount financial-row-amount"><strong>{440+i}٬۰۰۰ تومان</strong></div><span class="financial-row-chevron">‹</span></a>''' for i in range(1,5)) + r'''</div></section></div></section></div>
<div class="financial-filter-layer hidden" id="invoiceFilterLayer" role="dialog"><button class="financial-filter-backdrop"></button><form class="financial-filter-sheet"><div class="financial-filter-sheet-head"><span class="financial-sheet-grip"></span><div><small>فاکتورها</small><h2>فیلترها</h2></div><button class="panel-icon-action">×</button></div><div class="financial-filter-sheet-body"><label class="form-group"><span>سال مالی</span><select class="form-control"><option>همه</option></select></label><label class="form-group"><span>مقصد</span><select class="form-control"><option>همه</option></select></label><label class="form-group"><span>وضعیت</span><select class="form-control"><option>همه</option></select></label><label class="form-group"><span>از تاریخ</span><div class="jalali-date-control"><input class="form-control"><button class="jalali-date-button">□</button></div></label><label class="form-group"><span>تا تاریخ</span><div class="jalali-date-control"><input class="form-control"><button class="jalali-date-button">□</button></div></label></div><div class="financial-filter-sheet-actions"><button class="btn btn-primary">اعمال فیلترها</button></div></form></div>

<div class="financial-workspace subscriber-directory-workspace panel-page-flow" id="subscriberDirectory"><section class="card subscriber-directory-card financial-page-shell financial-index-surface"><div class="financial-page-head"><div class="financial-page-summary"><span>مانده کل بدهکاران</span><strong>۲۸٬۳۵۵٬۰۰۰ تومان</strong><small>۴ مشترک</small></div><a class="financial-head-action">+ مشترک جدید</a></div><div class="financial-page-toolbar"><form class="subscriber-search-form financial-filter-form"><label class="financial-search-field"><input class="form-control" placeholder="جست‌وجوی نام یا موبایل"></label><div class="subscriber-filter-row financial-filter-inline"><select class="form-control financial-compact-select"><option>همه وضعیت‌ها</option></select><label class="financial-filter-toggle"><input type="checkbox"><span>فقط بدهکار</span></label></div></form></div><div class="subscriber-card-list financial-list">''' + ''.join(f'''<a class="subscriber-list-card financial-row"><div class="subscriber-list-identity financial-row-main"><strong>مشترک نمونه {i}</strong><span>۰۹۱۷۷۷۷۷۷۷{i}</span><small>۲۳ مرداد · ۱۵:{10+i}</small></div><div class="subscriber-list-balance financial-row-amount"><strong>{13+i}٬۶۰۵٬۰۰۰ تومان</strong></div><span class="subscriber-list-chevron">‹</span></a>''' for i in range(1,5)) + r'''</div></section></div>

<div class="financial-workspace subscriber-profile-workspace panel-page-flow" id="subscriberProfile"><div class="subscriber-profile-stack panel-page-flow"><section class="card subscriber-profile-head financial-record-hero"><div class="subscriber-profile-top"><div class="subscriber-profile-identity"><div class="subscriber-profile-name-row"><h2>علی بهبودی</h2></div><p>۰۹۱۷۷۷۷۷۷۷۷</p></div></div><div class="subscriber-balance-block"><span>مانده حساب</span><strong>۱۳٬۶۰۵٬۰۰۰ تومان</strong></div><div class="subscriber-profile-foot">آخرین فعالیت: ۲۳ مرداد · ۱۵:۱۳</div></section><section class="card subscriber-ledger-card financial-record-section"><div class="subscriber-ledger-head"><div><h2>گردش حساب</h2><small>۹ سند</small></div><form class="subscriber-ledger-filter"><label><select class="form-control"><option>همه اسناد</option></select></label></form></div><div class="subscriber-timeline">''' + ''.join(f'''<article class="subscriber-ledger-entry financial-history-row is-invoice"><details class="subscriber-ledger-disclosure"><summary><div class="subscriber-ledger-entry-main"><h3>فاکتور {50-i}</h3><div class="subscriber-ledger-entry-context"><span>میز {i}</span><span>۲۳ مرداد · ۱۵:{10+i}</span></div></div><div class="subscriber-ledger-entry-amount"><strong>{440+i}٬۰۰۰ تومان</strong><small>مانده بعد: ۱۳٬۱۶۵٬۰۰۰ تومان</small></div><span class="subscriber-ledger-chevron">⌄</span></summary><div class="subscriber-ledger-detail"><div class="financial-receipt-preview"><div class="financial-receipt-row"><div><strong>شربت شاه‌توت</strong><small>۱ × ۲۴۰٬۰۰۰</small></div><strong>۲۴۰٬۰۰۰</strong></div></div><a class="document-open-link subscriber-ledger-open-document">مشاهده فاکتور ‹</a></div></details></article>''' for i in range(1,5)) + r'''</div></section></div></div>

<div class="financial-workspace accommodation-page-stack panel-page-flow" id="accommodationHistory"><section class="card accommodation-history-card financial-page-shell financial-index-surface"><div class="financial-page-head"><div class="financial-page-summary"><strong>۱۶ سابقه</strong></div><div class="panel-utility-actions"><a class="panel-icon-action">⚙</a></div></div><div class="financial-page-toolbar"><form class="accommodation-history-search financial-filter-form"><label class="financial-search-field"><input class="form-control" placeholder="جست‌وجوی مهمان، اتاق، رزرو، فاکتور یا میز"></label><select class="form-control financial-compact-select"><option>همه سوابق</option></select></form></div><div class="accommodation-history-list financial-list">''' + ''.join(f'''<article class="accommodation-history-item financial-row is-navigable" data-row-href="invoices.php?id={i}" role="link" tabindex="0"><div class="financial-row-main accommodation-history-identity"><strong>رزرو {8+i} · میز ۲</strong><span>مهران مبارکی · نخلستان</span><small>فاکتور {49-i} · ۲۳ مرداد · ۱۵:{10+i}</small></div><div class="financial-row-amount"><strong>{465+i}٬۰۰۰ تومان</strong></div><span class="financial-row-chevron">‹</span></article>''' for i in range(1,5)) + r'''</div></section></div>

<div class="financial-workspace financial-period-workspace panel-page-flow" id="financialPeriods"><section class="card financial-period-current financial-record-hero"><div class="financial-period-current-head"><div class="panel-copy-stack"><small class="muted">دوره جاری</small><div class="financial-period-title-row"><h2>سال مالی ۱۴۰۵</h2><span class="panel-status-badge is-success">باز</span></div><p class="muted">۱۴۰۵/۰۱/۰۱ تا ۱۴۰۵/۱۲/۲۹</p></div><a class="btn btn-light btn-sm">فاکتورهای این دوره</a></div><div class="financial-metric-strip"><div><span>فروش خالص</span><strong>۱۲٬۴۵۰٬۰۰۰ تومان</strong></div><div><span>فاکتور</span><strong>۸۴</strong></div><div><span>تخفیف</span><strong>۴۵۰٬۰۰۰ تومان</strong></div><div><span>اسناد برگشت</span><strong>۳</strong></div></div></section><details class="financial-context-strip financial-info-disclosure"><summary><span>نحوه اتصال اسناد به دوره مالی</span><span>⌄</span></summary><div class="financial-info-copy">هر فاکتور در زمان تسویه به دوره همان تاریخ متصل می‌شود.</div></details></div>

<section class="card invoice-detail-card financial-record" id="invoiceDetail"><div class="card-body invoice-receipt"><header class="invoice-receipt-summary"><div class="invoice-receipt-title"><div><h2>فاکتور ۴۹</h2></div></div><div class="invoice-receipt-context"><div class="invoice-receipt-context-main"><strong>میز ۲ · تسویه مستقیم</strong></div><div class="invoice-receipt-party">کیمی احمدی</div></div><div class="invoice-receipt-time">۲۷ مرداد · ۱۵:۱۲</div></header><div class="invoice-related-document is-voided"><strong>سند برگشت ۲</strong><span>دلیل برگشت: تست</span><a>مشاهده سند برگشت</a></div><section class="invoice-receipt-section"><div class="invoice-receipt-lines"><div class="invoice-receipt-line"><div><strong>املت گوجه</strong><small>۱ × ۲۲۰٬۰۰۰</small></div><strong>۲۲۰٬۰۰۰</strong></div></div></section><section class="invoice-receipt-totals"><div class="is-final"><span>مبلغ نهایی</span><strong>۲۲۰٬۰۰۰ تومان</strong></div></section><details open class="panel-disclosure invoice-receipt-meta"><summary><span>اطلاعات ثبت</span></summary><dl><div><dt>شناسه سند</dt><dd>I-1405-000049</dd></div><div><dt>ثبت‌کننده</dt><dd>مدیر کافه</dd></div><div><dt>دوره مالی</dt><dd>سال مالی ۱۴۰۵</dd></div></dl></details></div></section>
</main></body></html>'''
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-components.css']

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':2200})
        page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        assert not page.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), ('root overflow',width)

        # Cross-page family: search controls and row amounts share actual geometry/typography.
        search_heights=[]
        for selector in ('#invoiceArchive .financial-search-field .form-control','#subscriberDirectory .financial-search-field .form-control','#accommodationHistory .financial-search-field .form-control'):
            box=page.locator(selector).bounding_box(); assert box; search_heights.append(round(box['height'],1))
        assert max(search_heights)-min(search_heights) <= 1.5, ('search control drift',width,search_heights)
        amount_sizes=[page.locator(sel).first.evaluate('el=>parseFloat(getComputedStyle(el).fontSize)') for sel in ('#invoiceArchive .financial-row-amount>strong','#subscriberDirectory .financial-row-amount>strong','#accommodationHistory .financial-row-amount>strong')]
        assert max(amount_sizes)-min(amount_sizes) <= .5, ('amount typography drift',width,amount_sizes)

        for wid in ('invoiceArchive','subscriberDirectory','accommodationHistory'):
            shell=page.locator(f'#{wid} .financial-page-shell').bounding_box(); assert shell and shell['width'] <= width+1, (wid,width,shell)
        for row in page.locator('.financial-row').all():
            b=row.bounding_box(); assert b and b['x'] >= -0.5 and b['x']+b['width'] <= width+0.5, (width,b)

        # Useful density: ordinary financial rows should remain compact, not mini-detail cards.
        if width <= 412:
            for selector,limit in (('#invoiceArchive .financial-row',88),('#subscriberDirectory .financial-row',94),('#accommodationHistory .financial-row',96)):
                heights=[x.bounding_box()['height'] for x in page.locator(selector).all()[:4]]
                assert heights and max(heights) <= limit, ('financial row density',width,selector,heights)
            ledger_heights=[x.bounding_box()['height'] for x in page.locator('#subscriberProfile .subscriber-ledger-disclosure>summary').all()[:4]]
            assert ledger_heights and max(ledger_heights) <= 90, ('ledger collapsed density',width,ledger_heights)

        # Advanced filter is an overlay, not a giant inline form.
        assert not page.locator('#invoiceFilterLayer').is_visible(), width
        page.locator('#invoiceFilterLayer').evaluate("el=>el.classList.remove('hidden')")
        sheet=page.locator('#invoiceFilterLayer .financial-filter-sheet').bounding_box(); assert sheet
        if width <= 720:
            assert sheet['x'] <= 1 and abs(sheet['width']-width) <= 2, ('mobile filter sheet width',width,sheet)
            assert sheet['y']+sheet['height'] >= 2198, ('mobile filter sheet bottom alignment',width,sheet)
        else:
            limit = width*.9 if width < 900 else width*.7
            assert sheet['width'] < limit and sheet['x'] > 10, ('desktop filter dialog width',width,sheet)
        page.locator('#invoiceFilterLayer').evaluate("el=>el.classList.add('hidden')")

        # Profile and related-document constraints.
        profile=page.locator('#subscriberProfile .subscriber-profile-head').bounding_box(); assert profile and profile['height'] < (220 if width < 390 else 195), ('profile density',width,profile)
        related=page.locator('#invoiceDetail .invoice-related-document').bounding_box(); detail=page.locator('#invoiceDetail').bounding_box(); assert related and detail and related['x'] >= detail['x']-1 and related['x']+related['width'] <= detail['x']+detail['width']+1, (width,related,detail)
        assert page.locator('#financialPeriods .financial-info-disclosure').evaluate('el=>!el.open'), ('financial info disclosure',width)

        if width < 380:
            meta=page.locator('#invoiceDetail .invoice-receipt-meta dl>div').nth(2).bounding_box(); first=page.locator('#invoiceDetail .invoice-receipt-meta dl>div').nth(0).bounding_box(); assert meta and first and abs(meta['width']-first['width']) < 3, (width,meta,first)
        elif width >= 390:
            meta=page.locator('#invoiceDetail .invoice-receipt-meta dl>div').nth(2).bounding_box(); first=page.locator('#invoiceDetail .invoice-receipt-meta dl>div').nth(0).bounding_box(); assert meta and first and meta['width'] > first['width']*1.5, (width,meta,first)
        page.close()
    browser.close()
print('financial composition v2 browser PASS at 320/360/390/412/768/1366')
