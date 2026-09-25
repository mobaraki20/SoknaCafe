#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-section-printing"><main class="panel-content">
<nav class="panel-primary-tabs print5-tabs"><a class="is-active">نمای کلی</a><a>تنظیمات چاپ</a><a>عیب‌یابی</a></nav>
<section class="print5-status-strip is-warn"><div class="print5-status-main"><span class="print5-health-icon is-warn"></span><div><strong>چاپ نیازمند رسیدگی است</strong><small>آخرین تحویل موفق به صف چاپ: امروز</small></div></div><div class="print5-status-facts"><span><b>آماده</b> سرویس چاپ داخلی</span><span><b>۲/۲</b> مقصد آماده</span><span class="is-problem"><b>۱</b> نیازمند رسیدگی</span></div><button class="btn btn-light">تازه‌سازی</button></section>
<div class="print5-overview-grid"><div class="print5-overview-main"><section class="card print5-ops-card"><div class="card-head"><strong>نیازمند رسیدگی</strong></div><div class="print5-job-list"><article class="print5-job-row is-problem"><header><div><strong>فیش آماده‌سازی</strong><span>امروز</span></div><span class="badge badge-failed">نتیجه نامشخص</span></header><div class="print5-job-human-meta"><span>آماده‌سازی مشترک</span><span>۲ تلاش</span></div><div class="print5-job-actions"><button class="btn btn-light">چاپ انجام شده است</button><button class="btn btn-primary">چاپ مجدد مستقل</button></div></article></div></section></div><aside class="print5-overview-side"><section class="card print5-infra-card"><div class="card-head"><strong>زیرساخت چاپ</strong></div><div class="print5-infra-list"><div class="print5-infra-row"><span class="print5-status-dot is-ok"></span><div><strong>سرویس چاپ داخلی</strong><small>DESKTOP-CAFE · نسخه داخلی</small></div><span>۳ پرینتر</span></div></div></section></aside></div>
<section class="card print5-section"><div class="card-head"><strong>مقصدهای چاپ</strong></div><div class="card-body"><div class="print5-destination-list"><details class="print5-destination-editor"><summary><div><span class="print5-status-dot is-ok"></span><div><strong>سند مشتری</strong><small>EPSON Front</small></div></div><div><span class="print5-state is-ok">آماده</span><span class="print5-edit-label">ویرایش</span></div></summary><form class="print5-destination" data-print-destination><div class="print5-destination-fields"><div class="print5-pair"><label><span>پرینتر اصلی</span><select class="form-control" data-print-printer-select="primary" data-current-printer="EPSON Front"></select></label></div><label class="print5-active"><input type="checkbox" checked><span>این مقصد فعال باشد</span></label></div><details class="print5-destination-advanced"><summary>تنظیمات بیشتر مقصد</summary><div class="print5-advanced-fields"><label><span>پرینتر جایگزین</span><select class="form-control" data-print-printer-select="fallback" data-current-printer="Old Queue"></select></label></div></details><div class="print5-destination-footer"><span class="print5-destination-meta">رول ۸۰ میلی‌متر</span><div class="actions"><button class="btn btn-light" type="button" data-print-editor-close>انصراف</button><button class="btn btn-primary">ذخیره تغییرات</button></div></div></form></details></div></div></section>
<details class="card print5-advanced"><summary><div><strong>تنظیمات پیشرفته چاپ</strong><span>پیش‌فرض چاپ هنگام تسویه، قالب‌ها و تنظیمات تخصصی</span></div></summary><div class="card-body">advanced</div></details>
<script type="application/json" id="printing-printers-data">[{"name":"EPSON Front","default":true,"offline":false},{"name":"Microsoft Print to PDF","default":false,"offline":false}]</script>
</main></body></html>'''
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in ((1440,980),(1024,900),(390,844)):
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        page.add_script_tag(path=str(ROOT/'assets/js/printing-settings.js'))
        page.wait_for_timeout(100)
        assert page.locator('.print5-tabs a').count()==3
        assert page.locator('.print5-hero').count()==0
        assert page.locator('.print5-advanced').get_attribute('open') is None
        primary=page.locator('[data-print-printer-select="primary"]')
        assert primary.input_value()=='EPSON Front'
        assert primary.locator('option').count()==3
        fallback=page.locator('[data-print-printer-select="fallback"]')
        assert fallback.input_value()=='Old Queue'
        assert any('دیده نشد' in text for text in fallback.locator('option').all_text_contents())
        editor=page.locator('.print5-destination-editor')
        assert editor.get_attribute('open') is None
        editor.locator(':scope > summary').click()
        assert editor.get_attribute('open') is not None
        page.locator('[data-print-editor-close]').click()
        assert editor.get_attribute('open') is None
        editor.locator(':scope > summary').click()
        assert page.locator('[data-print-agent-select]').count()==0
        assert primary.is_disabled() is False
        assert fallback.is_disabled() is False
        advanced=page.locator('.print5-destination-advanced')
        assert advanced.get_attribute('open') is None
        advanced.locator(':scope > summary').click()
        assert advanced.get_attribute('open') is not None
        page.select_option('[data-print-printer-select="fallback"]','')
        assert fallback.input_value()==''
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
        columns=page.locator('.print5-overview-grid').evaluate("e=>getComputedStyle(e).gridTemplateColumns.split(' ').length")
        if width>1000: assert columns>=2, (width,columns)
        else: assert columns==1, (width,columns)
        page.close()
    browser.close()
print('Printing operations browser passed: 3-section hierarchy, compact desktop overview, edit-on-demand printer mapping, advanced disclosure and no overflow.')
