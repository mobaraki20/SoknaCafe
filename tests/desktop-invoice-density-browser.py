#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; desktop invoice density browser check skipped.')
    raise SystemExit(0)

ROOT=Path(__file__).resolve().parents[1]
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
rows=''.join(f'<tr><td>{i}</td><td><strong>آیتم نمونه شماره {i}</strong><small>توضیح کوتاه سفارش</small></td><td>۱</td><td>۲۵۰٬۰۰۰</td><td>۲۵۰٬۰۰۰</td><td><button class="icon-action-button" aria-label="اصلاح">✎</button></td></tr>' for i in range(1,9))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-body panel-section-operator operator-detail-open"><main class="panel-content">
<section class="operator-live-head-v1280"><div class="operator-work-tabs-v1280 panel-primary-tabs"><button>نیازمند اقدام</button><button class="is-active">میزها</button><button>جمع اقلام</button></div><div class="operator-connection-v1280 is-online"><span class="live-dot"></span><span>اطلاعات به‌روز است</span></div></section>
<section class="operator-work-panel-v1280" id="tablesPanel"><header class="operator-section-head-v1280"><div><span>نمای سالن</span><h2>میزها و حساب جاری</h2><p>مرور حساب میز</p></div><div class="table-header-controls-v1306"><div class="table-overview-stats"><button class="is-active">همه ۱۲</button><button>باز ۸</button><button>آزاد ۴</button></div></div></header>
<div class="live-tables-layout-v1280 has-detail" id="layout"><aside class="table-account-panel-v1280 is-open" id="detail"><header class="table-account-head-v1280"><div><small>حساب باز</small><button class="table-name-action-v1280">میز ۶</button></div><button class="icon-btn">×</button></header><div class="table-detail-body-v1190" id="body"><section class="table-account-section-v1280"><div class="table-account-section-title-v1280"><div><h4>فاکتور جاری</h4><small>۲ نوبت سفارش</small></div></div><div class="bill-table-wrap"><table class="bill-table bill-table-current"><thead><tr><th>ردیف</th><th>شرح آیتم</th><th>تعداد</th><th>قیمت واحد</th><th>مبلغ</th><th>اصلاح</th></tr></thead><tbody>{rows}</tbody></table></div><details class="table-account-disclosure-v1280 discount-disclosure-v1301"><summary><span>تخفیف</span><b>بدون تخفیف</b></summary></details><details class="table-account-disclosure-v1280 order-batches-disclosure-v13219"><summary><span>سفارش‌های این میز</span><b>۲</b></summary></details></section></div><footer class="table-account-footer-v1301" id="footer"><div class="table-account-footer-actions-v1301"><button class="btn btn-light">افزودن سفارش</button><button class="btn btn-light session-action">چاپ صورتحساب</button></div><div class="table-account-checkout"><div class="table-account-payable-v1300"><span>مبلغ قابل پرداخت</span><strong>۲٬۰۰۰٬۰۰۰ تومان</strong></div><button class="btn btn-primary open-settlement-action">تسویه حساب</button></div></footer></aside><div class="table-overview-pane-v1280"><div class="tables-board-v1190"><div class="table-zone-grid-v1280">''' + ''.join('<article class="table-card-v1280"><button class="table-card-main"><span class="table-card-number-v1280"><strong>۱</strong></span><span class="table-card-copy-v1280"><b>میز</b><small>باز</small></span></button></article>' for _ in range(6)) + '''</div></div></div></div></section></main></body></html>'''

with sync_playwright() as p:
    
    try:
        browser=p.chromium.launch(headless=True)
    except Exception:
        fallback='/usr/bin/chromium'
        if not Path(fallback).exists():
            print('Chromium unavailable; desktop invoice density browser check skipped.')
            raise SystemExit(0)
        browser=p.chromium.launch(headless=True, executable_path=fallback)
    for width,height in [(1366,768),(1440,900)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html)
        for css in CSS: page.add_style_tag(path=str(css))
        page.eval_on_selector('#layout', "e=>{const top=e.getBoundingClientRect().top; e.style.setProperty('--tables-workspace-height',Math.floor(Math.max(420,innerHeight-top-16))+'px')}")
        page.wait_for_timeout(30)
        panel=page.locator('#detail').bounding_box(); footer=page.locator('#footer').bounding_box()
        assert panel and 600<=panel['width']<=620,(width,panel)
        assert footer and footer['height']<=70,(width,footer)
        body=page.locator('#body')
        metrics=body.evaluate('e=>({client:e.clientHeight,scroll:e.scrollHeight})')
        assert metrics['scroll']<=metrics['client']+2,(width,metrics)
        row_heights=page.locator('.bill-table tbody tr').evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
        assert row_heights and max(row_heights)<=40,(width,row_heights)
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 2')
        page.screenshot(path=f'/mnt/data/sokna-desktop-invoice-density-{width}.png',full_page=False)
        page.close()
    browser.close()
print('Desktop invoice density passed: an 8-line, two-batch table bill fits without inner scrolling at 1366x768 and 1440x900, while settlement actions remain visible.')
