#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
row=lambda label,value:f'''<div class="event-meta-row"><svg class="ui-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><div class="event-meta-copy"><small>{label}</small><strong>{value}</strong></div></div>'''
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{css}</style></head><body><article class="event-card" style="max-width:720px"><div class="event-card-body"><div class="event-meta-v118">{row('محل برگزاری','حیاط خانه سکنا')}{row('ظرفیت کل','۲۰ نفر')}{'<div class="event-meta-row event-fee-row"><svg class="ui-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><div class="event-meta-copy"><small>هزینه شرکت در رویداد</small><strong class="event-fee-amount">۱٬۲۰۰٬۰۰۰ تومان</strong></div></div>'}{'<div class="event-meta-row event-admission-row"><svg class="ui-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><div class="event-meta-copy"><small>توضیحات حضور</small><strong>هزینه برای هر نفر است</strong></div></div>'}</div></div></article></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    for width in [320,390,720,1024]:
        page=browser.new_page(viewport={'width':width,'height':700}); page.set_content(html)
        data=page.evaluate('''() => {const g=document.querySelector('.event-meta-v118'), rows=[...g.children].map(e=>e.getBoundingClientRect());return {overflow:document.documentElement.scrollWidth>innerWidth+1,rows:rows.map(r=>({l:r.left,r:r.right}))}}''')
        assert not data['overflow'],(width,data)
        assert all(r['l']>=-1 and r['r']<=width+1 for r in data['rows']),(width,data)
        body=page.locator('body').inner_text()
        assert 'هزینه شرکت در رویداد' in body and '۱٬۲۰۰٬۰۰۰ تومان' in body and 'شرایط ورود' not in body
        page.close()
    browser.close()
print('Event metadata browser checks passed.')
