#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
js=(ROOT/'assets/js/waiter.js').read_text(encoding='utf-8')
payload={
 'success':True,'unchanged':False,'snapshot':'p6a','revision':'r1',
 'call_ids':[],'attention_keys':['order-11-kitchen','order-12-bar'],
 'permissions':{
   'orders_floor':False,'preparation':True,'preparation_visible':True,'preparation_actionable':True,
   'can_claim_preparation':True,'monitor_preparation':True,
   'visible_preparation_areas':['kitchen','bar'],'actionable_preparation_areas':['kitchen']
 },
 'tasks':[
   {'key':'order-11-kitchen','kind':'order','queue_stage':'preparation','area':'kitchen','area_label':'آشپزخانه','id':11,'table_id':1,'table_name':'میز ۱','zone_label':'داخل','title':'آشپزخانه · سفارش تأییدشده','subtitle':'۱ عدد','status':'accounted','pending':False,'claimed':False,'total':100,'note':'','items':[{'quantity':1,'item_name':'غذا','fulfillment_mode':'dine_in','item_note':''}],'created_at':'2026-09-19 10:00:00','updated_at':'2026-09-19 10:00:00','time_ago':'همین حالا','waiting_minutes':0,'priority':1},
   {'key':'order-12-bar','kind':'order','queue_stage':'preparation','area':'bar','area_label':'بار','id':12,'table_id':2,'table_name':'میز ۲','zone_label':'داخل','title':'بار · سفارش تأییدشده','subtitle':'۱ عدد','status':'accounted','pending':False,'claimed':False,'total':80,'note':'','items':[{'quantity':1,'item_name':'نوشیدنی','fulfillment_mode':'dine_in','item_note':''}],'created_at':'2026-09-19 10:00:00','updated_at':'2026-09-19 10:00:00','time_ago':'همین حالا','waiting_minutes':0,'priority':1},
 ],
 'today_orders':[]
}
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="x"></head><body>
<div id="waiterConnection"><span></span><strong></strong><small id="waiterLastSync"></small></div>
<div id="staffActionQueue"></div><div id="staffTodayOrders"></div><button id="staffRefreshQueue"></button><button id="waiterSound"></button>
<script>
window.WAITER_FEED_API='/feed';window.WAITER_ACTION_API='/action';window.WAITER_STATUS_API='/status';
window.WAITER_CAN_CLAIM=true;window.WAITER_ACTIONABLE_AREAS=['kitchen'];window.WAITER_VISIBLE_AREAS=['kitchen','bar'];
window.fetch=async()=>new Response({json.dumps(payload,ensure_ascii=False)!r},{{status:200,headers:{{'Content-Type':'application/json'}}}});
</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=b.new_page(viewport={'width':390,'height':844})
    page.set_content(html)
    page.add_script_tag(content=js)
    page.wait_for_selector('.staff-task-card')
    cards=page.locator('.staff-task-card')
    assert cards.count()==2
    kitchen=cards.filter(has_text='آشپزخانه')
    bar=cards.filter(has_text='بار')
    assert kitchen.locator('[data-action="claim_order_area"]').count()==1
    assert kitchen.locator('[data-action="claim_order_area"]').get_attribute('data-area')=='kitchen'
    assert bar.locator('[data-action="claim_order_area"]').count()==0
    assert 'هنوز دریافت نشده' in bar.inner_text()
    page.close();b.close()
print('Phase 6A preparation area browser PASS: global visibility with per-area actionability.')
