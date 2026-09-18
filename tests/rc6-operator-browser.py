#!/usr/bin/env python3
from pathlib import Path
import ast
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source); HTML=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        HTML=ast.literal_eval(node.value);break
assert HTML
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']

def setup(browser,width=1440,height=980):
    page=browser.new_page(viewport={'width':width,'height':height});page.set_content(HTML)
    for css in CSS: page.add_style_tag(path=str(css))
    page.add_script_tag(path=str(ROOT/'assets/js/panel-menus.js'));page.add_script_tag(path=str(ROOT/'assets/js/operator.js'))
    page.wait_for_selector('[data-select-table="1"]',state='attached');page.click('#tabTables');return page

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=setup(browser)
    # Grid keeps contextual review, but opened table panel exposes direct actions without a second review step.
    page.wait_for_selector('.table-card-review[data-review-order="20"]', state='attached')
    assert page.locator('.table-card-review[data-review-order="20"]').count()>=1 and 'بررسی سفارش' in page.locator('.table-card-review[data-review-order="20"]').first.inner_text()
    page.click('[data-select-table="2"]');page.wait_for_timeout(40)
    detail=page.locator('#tableDetailBody')
    assert 'بررسی سفارش' not in detail.inner_text()
    primary=detail.locator('.pending-order-inline-actions .btn-primary'); reject=detail.locator('.pending-order-inline-actions .btn-light')
    assert primary.count()==1 and reject.count()==1
    assert primary.inner_text().strip()=='تأیید سفارش' and reject.inner_text().strip()=='رد سفارش'
    pb,rb=primary.bounding_box(),reject.bounding_box(); assert pb['x']>rb['x'],(pb,rb)
    assert detail.evaluate("e=>getComputedStyle(e).overflowY")=='auto'
    assert detail.locator('.bill-table-wrap').count()==0 or all(x=='visible' for x in detail.locator('.bill-table-wrap').evaluate_all("els=>els.map(e=>getComputedStyle(e).overflowY)"))

    # Discount input: Persian digits, grouping only for fixed amount, independent suffix cell.
    page.click('[data-select-table="1"]');page.wait_for_timeout(30)
    disc=page.locator('.discount-disclosure-v1301');disc.evaluate('e=>e.open=true')
    page.locator('[data-discount-type-choice="fixed"]').click();inp=page.locator('.bill-discount-value');inp.fill('۱۲۳۴۵۶۷');page.wait_for_timeout(20)
    assert inp.input_value()=='۱٬۲۳۴٬۵۶۷',inp.input_value()
    suffix=page.locator('[data-discount-suffix]'); assert suffix.inner_text().strip()=='تومان'
    ib,sb=inp.bounding_box(),suffix.bounding_box(); assert ib['x']+ib['width'] <= sb['x']+1 or sb['x']+sb['width'] <= ib['x']+1,(ib,sb)
    page.locator('[data-discount-type-choice="percent"]').click();inp.fill('٢٥');page.wait_for_timeout(20)
    assert inp.input_value()=='۲۵' and suffix.inner_text().strip()=='٪'
    page.close()

    mobile=setup(browser,390,844);mobile.click('[data-select-table="1"]');mobile.wait_for_timeout(30)
    rows=mobile.locator('#tableDetailBody .bill-table tbody tr')
    heights=rows.evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
    assert heights and max(heights)<=62,heights
    assert mobile.locator('#tableDetailBody').evaluate("e=>getComputedStyle(e).overflowY")=='auto'
    assert all(v=='visible' for v in mobile.locator('#tableDetailBody .bill-table-wrap').evaluate_all("els=>els.map(e=>getComputedStyle(e).overflowY)"))
    assert mobile.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 2')
    mobile.close();browser.close()
print('RC6 operator browser passed: direct pending actions, RTL action order, single account scroll owner, localized discount input and dense mobile bill rows.')
