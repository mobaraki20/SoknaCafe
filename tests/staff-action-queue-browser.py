#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/staff-action-queue.css'])
js=(ROOT/'assets/js/waiter.js').read_text(encoding='utf-8')
payload={'success':True,'unchanged':False,'snapshot':'x','call_ids':['2'],'attention_order_ids':['8'],'permissions':{'orders_floor':True,'preparation':True,'can_claim_preparation':False,'monitor_preparation':True},'tasks':[
 {'key':'call-2','kind':'call','id':2,'table_id':2,'table_name':'میز ۲','zone_label':'حیاط','title':'فراخوان مهمان','subtitle':'هنوز کسی نپذیرفته','status':'new','accepted_by':'','mine':False,'created_at':'2026-08-03 01:00:00','time_ago':'۲۱ دقیقه پیش','waiting_minutes':21,'priority':300021},
 {'key':'order-8','kind':'order','id':8,'table_id':7,'table_name':'میز ۷','zone_label':'فضای داخلی','title':'سفارش جدید مهمان','subtitle':'۴ عدد · برگر، نوشیدنی','status':'pending_approval','pending':True,'total':920000,'note':'بدون پیاز','created_at':'2026-08-03 01:10:00','time_ago':'۱۱ دقیقه پیش','waiting_minutes':11,'priority':200011}
]}
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="x"><style>.hidden{{display:none!important}}body{{font-family:Tahoma;margin:0;padding:16px}}{css}</style></head><body><section class="staff-queue-toolbar"><div><h2>صف رسیدگی کافه</h2></div><div class="staff-queue-utilities"><div id="waiterConnection" class="waiter-connection"><span></span><strong>...</strong><small id="waiterLastSync"></small></div><details class="device-settings"><summary>این دستگاه</summary><div><button id="waiterSound">صدا</button><button id="waiterNotify">اعلان</button><button id="waiterNotifyTest" class="hidden">تست</button></div></details></div></section><section class="staff-queue-summary"><button class="staff-queue-stat is-active" data-task-filter="all"><strong id="staffAllCount">0</strong><span>همه</span></button><button class="staff-queue-stat" data-task-filter="call"><strong id="staffCallCount">0</strong><span>فراخوان‌ها</span></button><button class="staff-queue-stat" data-task-filter="order"><strong id="staffOrderCount">0</strong><span>سفارش‌ها</span></button></section><section class="staff-action-section"><div class="staff-action-head"><h2>نیازمند اقدام</h2><button id="staffRefreshQueue">تازه</button></div><div id="staffActionQueue"></div></section><div class="staff-quick-order-dock"><button>ثبت سریع سفارش</button></div><script>window.WAITER_FEED_API='/feed';window.WAITER_ACTION_API='/action';window.CAFE_CURRENCY='تومان';window.fetch=async(url,opt={{}})=>new Response({json.dumps(payload,ensure_ascii=False)!r},{{status:200,headers:{{'Content-Type':'application/json'}}}});</script></body></html>'''
with sync_playwright() as p:
 b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for width,height in [(390,844),(768,900),(1280,900)]:
  page=b.new_page(viewport={'width':width,'height':height});page.set_content(html);page.add_script_tag(content=js);page.wait_for_selector('.staff-task-card')
  assert page.locator('.staff-task-card').count()==2
  assert 'حیاط' in page.locator('.staff-task-card').first.inner_text() and 'فضای داخلی' in page.locator('.staff-task-card').nth(1).inner_text()
  page.locator('[data-task-filter="call"]').click();assert page.locator('.staff-task-card').count()==1 and 'فراخوان' in page.locator('.staff-task-card').inner_text()
  dock=page.locator('.staff-quick-order-dock').bounding_box();assert dock and dock['y']+dock['height']<=height+2,f'{width}: quick-order dock outside safe viewport'
  body=page.locator('body').evaluate('(e)=>({sw:e.scrollWidth,cw:e.clientWidth})');assert body['sw']<=body['cw']+2,f'{width}: horizontal overflow'
  page.close()
 b.close()
print('Staff action-queue browser passed at mobile, tablet and desktop widths.')
