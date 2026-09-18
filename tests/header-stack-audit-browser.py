#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/responsive.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content">
<div id="messages" class="toolbar"><div class="panel-copy-stack"><strong>متن‌ها و تجربه مهمان</strong><small class="muted">فقط متن‌هایی که واقعاً در جریان فعلی مهمان مصرف می‌شوند اینجا قابل ویرایش‌اند.</small></div><a class="btn btn-light">بازگشت</a></div>
<div id="pageactions" class="panel-page-actions"><div><strong>بازه گزارش</strong><span class="muted">دوشنبه ۱۹ مرداد</span></div></div>
<div id="cardhead" class="card-head"><h2>حساب‌های شخصی تیم</h2><small>هر اقدام با نام صاحب همین حساب ثبت می‌شود.</small></div>
</div></main></body></html>'''
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  for w in (320,360,390,768,1366):
    page=browser.new_page(viewport={'width':w,'height':900}); page.set_content(html)
    for sel,a,b in [('#messages .panel-copy-stack','strong','small'),('#pageactions>div','strong','span')]:
      d=page.eval_on_selector(sel,f"el=>{{const a=el.querySelector('{a}').getBoundingClientRect(),b=el.querySelector('{b}').getBoundingClientRect();return [a.bottom,b.top]}}")
      assert d[1] >= d[0]-0.5, (w,sel,d)
    page.close()
  browser.close()
print('Header copy-stack browser passed at 320/360/390/768/1366: title and helper text remain vertically separated under shared owners.')
