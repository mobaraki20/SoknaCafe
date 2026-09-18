#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
src=(ROOT/'tests/staff-quick-order-browser.py').read_text(encoding='utf-8')
prefix=src.split('with sync_playwright() as p:',1)[0]
ns={'__file__':str(ROOT/'tests/staff-quick-order-browser.py')}
exec(compile(prefix,'staff-quick-order-browser-prefix','exec'),ns)
html=ns['html'].replace('id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php"','id="quickOrderPage" data-initial-table="1" data-return-url="/operator/index.php" data-mode="late_accounting"')
policy=ns['policy']; js=ns['js']; payload=ns['payload']
late=json.loads(json.dumps(payload,ensure_ascii=False))
late['tables'][0].update({'session_id':101,'session_status':'active','is_open':True,'current_total':610000,'current_final_total':610000,'remaining_total':400000,'paid_total':210000,'itemized_active':True,'current_order_count':1,'current_quantity':3,'current_items':[{'name':'قهوه دمی','unit_price':210000,'quantity':1,'note':'','line_total':210000}]})
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844});page.set_default_timeout(7000);errors=[];page.on('pageerror',lambda e: errors.append(str(e)))
    page.set_content(html)
    page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})")
    page.evaluate("payload=>{window.__lateBody=null;window.CafeUI.requestErrorMessage=(e,f)=>String(e&&e.message||f);window.fetch=async(url,opt={})=>{if((opt.method||'GET')==='POST'){try{window.__lateBody=JSON.parse(opt.body||'{}')}catch(_){ }return new Response(JSON.stringify({success:false,message:'test stop'}),{status:422,headers:{'Content-Type':'application/json'}});}return new Response(JSON.stringify(payload),{status:200,headers:{'Content-Type':'application/json'}});};}",late)
    page.add_script_tag(content=policy);page.add_script_tag(content=js)
    page.wait_for_selector('[data-qo-category="10"]',state='attached')
    assert page.locator('#quickOrderTableStage').evaluate('e=>e.classList.contains("hidden")')
    assert page.locator('#quickOrderChangeTable').evaluate('e=>e.classList.contains("hidden")')
    page.locator('[data-qo-category="10"]').click();page.wait_for_timeout(30)
    page.locator('[data-qo-add="101"]').click();page.wait_for_timeout(30)
    assert page.locator('#quickOrderTakeawayTool').evaluate('e=>e.classList.contains("hidden")')
    assert '۴۰۰' in page.locator('#quickOrderPreviousTotal').inner_text()
    assert 'ثبت قلم جاافتاده' in page.locator('#quickOrderSubmit').inner_text()
    page.locator('#quickOrderSubmit').evaluate('e=>e.click()');page.wait_for_timeout(120)
    body=page.evaluate('window.__lateBody')
    assert body and body.get('mode')=='late_accounting',body
    assert body.get('expected_session_id')==101,body
    assert body.get('items') and all(r.get('fulfillment_mode')=='dine_in' for r in body['items']),body
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    browser.close()
print('Late-accounting browser PASS: fixed table/session, remaining-aware summary, no takeaway, explicit late mode and dine-in-only payload.')
