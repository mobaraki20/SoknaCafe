#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
# Reuse the stable Quick Order DOM fixture; execute only fixture definitions, not its test runner.
source=(ROOT/'tests/staff-quick-order-browser.py').read_text(encoding='utf-8')
prefix=source.split("html=f'''",1)[0]
ns={'__file__':str(ROOT/'tests/staff-quick-order-browser.py')}
exec(prefix,ns)
markup=ns['markup']; css=ns['css']; js=ns['js']; categories=ns['categories']; items=ns['items']
pending_order={'id':125,'number':'۱۲۵','total':660000,'items':[{'name':'شربت خیار سکنجبین','quantity':3,'unit_price':220000,'line_total':660000}]}
initial={'success':True,'tables':[{'id':1,'name':'میز ۱','table_number':1,'code':'T-1','zone_label':'سالن','sort_order':1,'is_open':True,'current_total':5430000,'current_final_total':5430000,'current_order_count':2,'discount_type':'','discount_value':0,'current_quantity':13,'current_items':[],'pending_order_count':1,'pending_orders':[pending_order]}], 'categories':categories,'items':items}
updated={'success':True,'tables':[{'id':1,'name':'میز ۱','table_number':1,'code':'T-1','zone_label':'سالن','sort_order':1,'is_open':True,'current_total':6090000,'current_final_total':6090000,'current_order_count':3,'discount_type':'','discount_value':0,'current_quantity':16,'current_items':[],'pending_order_count':0,'pending_orders':[]}], 'categories':categories,'items':items}
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"><style>.hidden{{display:none!important}}body{{font-family:Tahoma,sans-serif;margin:0}}{css}</style></head><body class="quick-order-page">{markup}<script>window.STAFF_QUICK_ORDER_API='/quick';window.OPERATOR_STATUS_API='/status';window.SOKNA_ICON_SPRITE='/sprite.svg';window.QUICK_ORDER_USER_KEY='7';window.__reviewCalls=[];window.__catalogState='initial';window.CafeUI={{toast:()=>{{}},confirm:async()=>true}};window.fetch=async(url,opts={{}})=>{{if(String(url).includes('/status')){{const body=JSON.parse(opts.body||'{{}}');window.__reviewCalls.push(body);window.__catalogState='updated';return new Response(JSON.stringify({{success:true,message:body.status==='cancelled'?'سفارش رد شد.':'سفارش تأیید شد.'}}),{{status:200,headers:{{'Content-Type':'application/json'}}}});}}const data=window.__catalogState==='initial'?{json.dumps(initial,ensure_ascii=False)}:{json.dumps(updated,ensure_ascii=False)};return new Response(JSON.stringify(data),{{status:200,headers:{{'Content-Type':'application/json'}}}});}};</script></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
    page.set_content(html); page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})"); page.add_script_tag(content=js); page.wait_for_selector('[data-qo-pending-status="accounted"]', timeout=5000)
    # No staff items are selected. Approval must still POST immediately.
    assert page.locator('#quickOrderSubmit').is_disabled()
    assert 'ابتدا سفارش مهمان' in page.locator('#quickOrderSubmit').inner_text()
    page.evaluate("document.querySelector('#quickOrderCart').classList.add('is-open')"); page.wait_for_timeout(30)
    page.locator('[data-qo-pending-status="accounted"]').click(); page.wait_for_timeout(120)
    calls=page.evaluate('window.__reviewCalls')
    assert len(calls)==1 and calls[0]['order_id']==125 and calls[0]['status']=='accounted', calls
    assert page.locator('#quickOrderPending').evaluate("e=>e.classList.contains('hidden')")
    assert page.locator('#quickOrderCurrentTotal').inner_text().strip() in {'۶٬۰۹۰٬۰۰۰','۶,۰۹۰,۰۰۰','۶۰۹۰۰۰۰'}
    page.close()
    # Rejection is also an immediate independent status request, after confirm.
    page=browser.new_page(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
    page.set_content(html); page.evaluate("Object.defineProperty(window,'sessionStorage',{value:(()=>{const m=new Map();return {getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k),clear:()=>m.clear()}})()})"); page.add_script_tag(content=js); page.wait_for_selector('[data-qo-pending-status="cancelled"]', timeout=5000)
    page.evaluate("document.querySelector('#quickOrderCart').classList.add('is-open')"); page.wait_for_timeout(30)
    page.locator('[data-qo-pending-status="cancelled"]').click(); page.wait_for_timeout(120)
    calls=page.evaluate('window.__reviewCalls')
    assert len(calls)==1 and calls[0]['status']=='cancelled', calls
    page.close(); browser.close()
print('1.31.7 Quick Order pending-order browser passed: accept/reject are immediate per-order actions and do not require a staff cart item.')
