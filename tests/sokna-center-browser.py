#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
source=(ROOT/'admin/center_settings.php').read_text(encoding='utf-8')
for token in ['اتصال به مرکز سکنا','وضعیت اتصال','کلید اتصال مرکز سکنا','اتصال و آزمایش','اتصال مجدد / تغییر کلید','آزمایش اتصال']:
    assert token in source, token
for token in ['آدرس مرکز سکنا','کلید Handoff','Origin','Return URL','Context','Audience','Secret','TTL','Nonce','شناسه کلید','آخرین خطا']:
    assert token not in source, token
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content"><section class="card settings-section" id="centerSettings"><div class="card-head"><div><h2>اتصال به مرکز سکنا</h2><small>اتصال امن کافه به مرکز سکنا فقط با کلید اتصال انجام می‌شود.</small></div></div><div class="card-body"><div class="integration-health-grid"><div><span>وضعیت اتصال</span><strong>متصل</strong></div></div><form class="form-grid" style="margin-top:16px"><div class="form-group full"><label for="key">کلید اتصال مرکز سکنا</label><input id="key" class="form-control ltr-input" dir="ltr" type="password"></div><div class="form-group full"><div class="panel-action-bar" id="actions"><button class="btn btn-primary" id="primary">اتصال مجدد / تغییر کلید</button><button class="btn btn-light" id="secondary">آزمایش اتصال</button></div></div></form></div></section></div></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':900})
        page.set_content(html)
        data=page.evaluate('''() => {const card=document.getElementById('centerSettings').getBoundingClientRect(),p=document.getElementById('primary').getBoundingClientRect(),s=document.getElementById('secondary').getBoundingClientRect();return {doc:document.documentElement.scrollWidth,card:{l:card.left,r:card.right},p:{l:p.left,r:p.right},s:{l:s.left,r:s.right},targets:[...document.querySelectorAll('button,input')].map(x=>x.getBoundingClientRect().height)}}''')
        assert data['doc']<=width+1,(width,data)
        assert data['card']['l']>=-1 and data['card']['r']<=width+1,(width,data)
        assert all(h>=44 for h in data['targets']),(width,data['targets'])
        if width>=560: assert data['p']['r']>data['s']['r'],(width,data)
        page.close()
    browser.close()
print('1.31.7 Center settings browser passed: 320px+, RTL primary-right, single-key UI, no technical fields.')
