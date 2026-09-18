#!/usr/bin/env python3
from pathlib import Path
import ast, os
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; itemized settlement browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'tests/operator-live-browser.py').read_text(encoding='utf-8')
tree=ast.parse(source); html=None
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets):
        html=ast.literal_eval(node.value); break
assert isinstance(html,str)
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css',ROOT/'assets/css/panel-layout.css',ROOT/'assets/css/panel-components.css',ROOT/'assets/css/operator-live.css']
JS=ROOT/'assets/js/operator.js'; MENU_JS=ROOT/'assets/js/panel-menus.js'

setup=r'''
window.CHECKOUT_PRINT_DEFAULT=false;
Object.assign(feed.tables[0],{
  bill_discount:0,bill_final_total:610000,bill_paid_subtotal:0,bill_paid_discount:0,bill_paid_total:0,
  bill_remaining_subtotal:610000,bill_remaining_discount:0,bill_remaining_total:610000,bill_paid_receipt_count:0,
  bill_itemized_active:false,bill_signature:'sig-0',pending_preparation_adjustments:0
});
feed.tables[0].bill_items[0].source_lines[0].unit_price=210000;
feed.tables[0].bill_items[0].source_lines[0].paid_quantity=0;
feed.tables[0].bill_items[0].source_lines[0].remaining_quantity=1;
feed.tables[0].bill_items[1].source_lines[0].unit_price=200000;
feed.tables[0].bill_items[1].source_lines[0].paid_quantity=0;
feed.tables[0].bill_items[1].source_lines[0].remaining_quantity=2;
const __baseFetch=window.fetch;
const __resp=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
window.fetch=async(url,options={})=>{
  const u=String(url);let body={};try{body=JSON.parse(options.body||'{}')}catch(_){ }
  if(u==='/session'&&body.action==='checkout_itemized_review'){
    window.__requests.push({url:u,...body});
    const price={111:210000,112:200000};let gross=0;const lines=[];
    for(const row of body.items||[]){const amount=Number(price[Number(row.order_item_id)]||0)*Number(row.quantity||0);gross+=amount;lines.push({order_item_id:Number(row.order_item_id),name:Number(row.order_item_id)===111?'قهوه دمی':'کیک شکلاتی',quantity:Number(row.quantity),unit_price:Number(price[Number(row.order_item_id)]||0),gross_amount:amount,discount_amount:0,net_amount:amount});}
    const remaining=Math.max(0,Number(feed.tables[0].bill_remaining_total)-gross);
    return __resp({success:true,persisted:false,table_id:1,session_id:101,review:{subtotal:gross,discount:0,total:gross,remaining_subtotal:remaining,remaining_discount:0,remaining_total:remaining,closes_session:remaining===0,lines}});
  }
  if(u==='/session'&&body.action==='checkout_itemized'){
    window.__requests.push({url:u,...body});
    const price={111:210000,112:200000};let gross=0;
    for(const row of body.items||[]){
      const id=Number(row.order_item_id),qty=Number(row.quantity||0);gross+=Number(price[id]||0)*qty;
      for(const group of feed.tables[0].bill_items){for(const src of group.source_lines||[]){if(Number(src.id)===id){src.paid_quantity=Number(src.paid_quantity||0)+qty;src.remaining_quantity=Math.max(0,Number(src.quantity||0)-Number(src.paid_quantity||0));group.paid_quantity=(group.source_lines||[]).reduce((a,s)=>a+Number(s.paid_quantity||0),0);group.remaining_quantity=(group.source_lines||[]).reduce((a,s)=>a+Number(s.remaining_quantity||0),0);}}}
    }
    feed.tables[0].bill_paid_subtotal+=gross;feed.tables[0].bill_paid_total+=gross;feed.tables[0].bill_remaining_subtotal=Math.max(0,610000-feed.tables[0].bill_paid_subtotal);feed.tables[0].bill_remaining_total=Math.max(0,610000-feed.tables[0].bill_paid_total);feed.tables[0].bill_paid_receipt_count+=1;feed.tables[0].bill_itemized_active=feed.tables[0].bill_remaining_total>0;feed.tables[0].bill_signature='sig-'+feed.tables[0].bill_paid_total;
    const closes=feed.tables[0].bill_remaining_total===0;if(closes){feed.tables[0].active=false;feed.tables[0].session_id=null;}
    return __resp({success:true,persisted:true,request_id:body.request_id,table_id:1,session_id:101,total_amount:gross,remaining_total:feed.tables[0].bill_remaining_total,closes_session:closes,message:closes?'حساب کامل شد.':'پرداخت ثبت شد و مانده باقی است.'});
  }
  return __baseFetch(url,options);
};
'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (390,1280):
        page=browser.new_page(viewport={'width':width,'height':900});page.set_default_timeout(7000)
        errors=[];page.on('pageerror',lambda err: errors.append(str(err)))
        page.set_content(html)
        for css in CSS: page.add_style_tag(path=str(css))
        page.evaluate(setup)
        page.add_script_tag(path=str(MENU_JS));page.add_script_tag(path=str(JS))
        page.wait_for_selector('[data-select-table="1"]',state='attached')
        page.click('#tabTables');page.click('[data-select-table="1"]');page.wait_for_timeout(80)
        page.click('.open-settlement-action');page.wait_for_selector('#checkoutModal:not(.hidden)')
        assert page.locator('[data-settlement]').count()==4
        page.click('[data-settlement="itemized"]');page.wait_for_selector('#itemizedSettlementModal:not(.hidden)')
        assert '۶۱۰' in page.locator('#itemizedAccountSummary').inner_text()
        assert page.locator('#itemizedReviewStep').count()==0
        rows=page.locator('.itemized-selection-row');assert rows.count()==2
        # Select one coffee. Server review should happen automatically, but the user sees one final CTA only.
        page.locator('[data-itemized-key="0"] [data-itemized-step="1"]').click()
        page.wait_for_function("()=>document.querySelector('#submitItemizedSettlement')?.textContent.includes('۲۱۰')")
        review_req=[r for r in page.evaluate('window.__requests') if r.get('action')=='checkout_itemized_review'][-1]
        assert review_req['items']==[{'order_item_id':111,'quantity':1}],review_req
        assert '۱' in page.locator('#itemizedSelectionCount').inner_text()
        assert page.locator('.itemized-selection-row').first.get_attribute('class').find('is-selected') >= 0
        if width==390:
            summary=page.locator('#itemizedAccountSummary')
            assert summary.locator('.itemized-mobile-balance').count()==1
            summary_text=summary.inner_text()
            assert summary_text.count('مانده حساب')==1 and 'جمع حساب' not in summary_text and 'پرداخت‌شده' not in summary_text
            first_row=page.locator('.itemized-selection-row').first.bounding_box(); stepper=page.locator('.itemized-selection-row').first.locator('.itemized-qty-stepper').bounding_box()
            assert stepper['x'] > first_row['x'] and stepper['width'] < first_row['width']*0.55, (first_row,stepper)
            assert '۱ عدد' in page.locator('#submitItemizedSettlement').inner_text() and '۲۱۰' in page.locator('#submitItemizedSettlement').inner_text()
        page.click('#submitItemizedSettlement');page.wait_for_timeout(260)
        pay_req=[r for r in page.evaluate('window.__requests') if r.get('action')=='checkout_itemized'][-1]
        assert pay_req['items']==[{'order_item_id':111,'quantity':1}],pay_req
        assert pay_req.get('expected_total')==610000,pay_req
        # Partial payment stays inside the same itemized modal and refreshes remaining account.
        assert not page.locator('#itemizedSettlementModal').evaluate('e=>e.classList.contains("hidden")')
        notice_text=page.locator('#itemizedFlowNotice').inner_text()
        assert 'پرداخت' in notice_text and '۲۱۰' in notice_text and '۴۰۰' not in notice_text
        assert '۴۰۰' in page.locator('#itemizedAccountSummary').inner_text()
        assert page.locator('#addMissedItemizedItem').is_visible()
        assert 'افزودن قلم جاافتاده' in page.locator('#addMissedItemizedItem').inner_text()
        if width==390:
            assert page.locator('#itemizedSettlementModal .settlement-content').evaluate("e=>getComputedStyle(e).scrollbarWidth") == 'none'
            plus_box=page.locator('.itemized-selection-row').first.locator('[data-itemized-step="1"]').bounding_box()
            plus_icon_box=page.locator('.itemized-selection-row').first.locator('[data-itemized-step="1"] .ui-icon').bounding_box()
            assert plus_box['width'] >= 44 and plus_box['height'] >= 44
            assert plus_icon_box['width'] < plus_box['width'] and plus_icon_box['height'] < plus_box['height'], (plus_box,plus_icon_box)
            page.wait_for_timeout(2700)
            assert page.locator('#itemizedFlowNotice').evaluate("e=>e.classList.contains('hidden')")
        detail=page.locator('#tableDetailShell').inner_text()
        assert 'تسویه جداگانه فعال' in detail
        active_cta=page.locator('#tableDetailShell .open-settlement-action').inner_text()
        assert '۴۰۰' in active_cta and 'ادامه تسویه جداگانه' in active_cta
        assert page.locator('#tableDetailTitle').is_disabled()
        assert page.locator('#tableDetailShell .session-action[data-action="print_prebill"]').count()==0
        missed=page.locator('#tableDetailShell a[href*="quick-order"][href*="mode=late_accounting"]')
        assert missed.count()==1
        assert 'افزودن قلم جاافتاده' in missed.inner_text()
        assert 'resume_settlement%3Ditemized' in (missed.get_attribute('href') or ''), missed.get_attribute('href')
        # After itemized settlement starts, the secondary method-change action is hidden; close and re-enter directly.
        assert not page.locator('#backItemizedSettlement').is_visible()
        page.click('#closeItemizedSettlement');page.wait_for_function("()=>document.querySelector('#itemizedSettlementModal')?.classList.contains('hidden')")
        pay_cta=page.locator('#tableDetailShell .open-settlement-action')
        assert 'ادامه تسویه جداگانه' in pay_cta.inner_text() and '۴۰۰' in pay_cta.inner_text()
        page.click('.open-settlement-action');page.wait_for_selector('#itemizedSettlementModal:not(.hidden)')
        assert page.locator('#checkoutModal').evaluate('e=>e.classList.contains("hidden")')
        assert int(page.locator('#checkoutExpectedTotal').input_value())==400000
        # Select all remaining and finish with one click.
        page.click('#selectAllItemizedRemaining')
        page.wait_for_function("()=>document.querySelector('#submitItemizedSettlement')?.textContent.includes('۴۰۰')")
        page.click('#submitItemizedSettlement');page.wait_for_timeout(220)
        pays=[r for r in page.evaluate('window.__requests') if r.get('action')=='checkout_itemized']
        assert pays[-1].get('expected_total')==400000,pays[-1]
        assert page.locator('#itemizedSettlementModal').evaluate('e=>e.classList.contains("hidden")')
        assert not errors,errors
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
        if width==390:
            page.screenshot(path=str(Path(os.getenv('SOKNA_SCREENSHOT_DIR','/tmp'))/'sokna-itemized-settlement-v17-390.png'),full_page=True)
        page.close()
    browser.close()
print('Itemized settlement browser PASS: one-click reviewed payment, partial modal continuity, direct re-entry, missed-item action, final remaining payment, mobile/desktop overflow.')
