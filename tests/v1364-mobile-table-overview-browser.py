#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
HTML="""<!doctype html><html lang='fa' dir='rtl'><head><meta name='viewport' content='width=device-width,initial-scale=1'></head><body class='panel-body panel-section-operator'><section class='operator-work-panel-v1280' id='tablesPanel'><header class='operator-section-head-v1280'><div><span>نمای سالن</span><h2>میزها و حساب جاری</h2><p>چیدمان سالن ثابت می‌ماند؛ مرتب‌سازی زمانی فقط همین نما را تغییر می‌دهد.</p></div></header><div class='live-tables-layout-v1280'><div class='table-overview-pane-v1280'><div class='table-overview-toolbar'><div class='table-overview-stats'><button>همه ۳۵</button><button>باز ۳۰</button><button>آزاد ۵</button></div><div class='table-sort-control-v1306 table-sort-mobile'><button>سالن و شماره</button><button>بیشترین حضور</button><button>کمترین حضور</button></div><label class='table-sort-select'><span>ترتیب نمایش</span><select><option>سالن و شماره میز</option></select></label></div><div class='tables-board-v1190'><div class='table-zone-v1280'><header><h3>بدون دسته‌بندی</h3></header><div class='table-zone-grid-v1280'><article class='table-card-v1280'></article><article class='table-card-v1280'></article></div></div></div></div></div></section></body></html>"""
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (360,390,412):
        page=browser.new_page(viewport={'width':width,'height':844});page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        assert page.locator('.table-sort-select').evaluate("e=>getComputedStyle(e).display")=='none'
        mobile=page.locator('.table-sort-mobile');assert mobile.evaluate("e=>getComputedStyle(e).display")=='grid'
        assert mobile.locator('button').count()==3
        for btn in mobile.locator('button').all():
            box=btn.bounding_box();assert box and box['height']>=44
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        page.close()
    page=browser.new_page(viewport={'width':1366,'height':768});page.set_content(HTML)
    for css in CSS: page.add_style_tag(path=str(css))
    assert page.locator('.table-sort-mobile').evaluate("e=>getComputedStyle(e).display")=='none'
    assert page.locator('.table-sort-select').evaluate("e=>getComputedStyle(e).display")!='none'
    browser.close()
print('Mobile table overview PASS: direct one-tap sorting at 360/390/412, desktop select remains desktop-only.')
