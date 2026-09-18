#!/usr/bin/env python3
from pathlib import Path
import ast
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source);HTML=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        HTML=ast.literal_eval(node.value);break
assert isinstance(HTML,str)
HTML=HTML.replace('<button id="closeTableDetail">×</button>','<button class="table-account-return" id="closeTableDetail" type="button" aria-label="بازگشت به نمای همه میزها"><span>نمای همه میزها</span></button>')
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
JS=ROOT/'assets/js/operator.js';MENU_JS=ROOT/'assets/js/panel-menus.js'
SETUP=r'''
Object.assign(feed.tables[0],{
 active:true,session_id:101,bill_order_count:2,bill_subtotal:4715000,bill_discount:0,bill_final_total:4715000,
 bill_paid_subtotal:0,bill_paid_discount:0,bill_paid_total:0,bill_remaining_subtotal:4715000,bill_remaining_discount:0,bill_remaining_total:4715000,
 bill_paid_receipt_count:0,bill_itemized_active:false,bill_signature:'sig-mobile',pending_preparation_adjustments:0,
 bill_items:[
  {item_name:'شیک پینات',quantity:1,paid_quantity:0,remaining_quantity:1,unit_price:315000,line_total:315000,item_note:'',source_lines:[{id:111,ordered_quantity:1,quantity:1,paid_quantity:0,remaining_quantity:1,unit_price:315000}]},
  {item_name:'سالاد سزار',quantity:3,paid_quantity:0,remaining_quantity:3,unit_price:620000,line_total:1860000,item_note:'',source_lines:[{id:114,ordered_quantity:3,quantity:3,paid_quantity:0,remaining_quantity:3,unit_price:620000}]}
 ],bill_orders:[{id:11,order_number:11,status:'accounted',created_time:'۱۹:۴۵',items:[]},{id:12,order_number:12,status:'accounted',created_time:'۲۰:۰۲',items:[]}]
});
'''

def open_table(browser,width,partial=False):
    page=browser.new_page(viewport={'width':width,'height':844});page.set_default_timeout(7000);page.set_content(HTML)
    for css in CSS:page.add_style_tag(path=str(css))
    page.evaluate(SETUP)
    if partial:
        page.evaluate("Object.assign(feed.tables[0],{bill_paid_subtotal:2110000,bill_paid_total:2110000,bill_remaining_subtotal:2605000,bill_remaining_total:2605000,bill_paid_receipt_count:1,bill_itemized_active:true});")
    page.add_script_tag(path=str(MENU_JS));page.add_script_tag(path=str(JS));page.wait_for_selector('[data-select-table="1"]',state='attached')
    page.click('#tabTables');page.click('[data-select-table="1"]');page.wait_for_timeout(80)
    return page

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (360,390,412):
        page=open_table(browser,width,False)
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        close=page.locator('#closeTableDetail').bounding_box();assert close and close['width']>=44 and close['height']>=44
        assert page.locator('#closeTableDetail').evaluate("e=>getComputedStyle(e,'::after').content").strip('\\"')=='×'
        assert 'تغییر میز' in page.locator('#tableDetailTitle').evaluate("e=>getComputedStyle(e,'::after').content")
        assert page.locator('.table-account-payable-v1300').evaluate('e=>getComputedStyle(e).display')=='none'
        cta=page.locator('.open-settlement-action');assert '۴٬۷۱۵٬۰۰۰' in cta.inner_text().replace('،','٬') or '4715000' in cta.inner_text().replace('٬','').replace('،','')
        bb=cta.bounding_box();assert bb and bb['height']>=44
        page.close()

        page=open_table(browser,width,True)
        progress=page.locator('.table-account-settlement-progress.is-mobile-compact');assert progress.count()==1
        text=progress.inner_text();assert 'مانده' not in text and '۲٬۱۱۰٬۰۰۰' in text.replace('،','٬')
        cta=page.locator('.open-settlement-action');cta_text=cta.inner_text().replace('،','٬')
        assert 'ادامه تسویه جداگانه' in cta_text and ('۲٬۶۰۵٬۰۰۰' in cta_text or '2605000' in cta_text.replace('٬',''))
        assert page.locator('.table-account-payable-v1300').evaluate('e=>getComputedStyle(e).display')=='none'
        page.close()
    browser.close()
print('Mobile cashier regression PASS: approved header/footer restored, amount owned by CTA, partial balance is not duplicated at 360/390/412.')
