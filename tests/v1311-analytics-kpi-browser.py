#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/panel.css','assets/css/panel-components.css'] if (ROOT/p).exists())
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{{margin:0;padding:12px;background:#f6f1e7}}{css}</style></head><body><main><section id="analyticsOverview"><div class="metric-grid compact-kpi-grid"><article class="metric-card" id="net"><span>فروش خالص</span><strong>۱۲۱٬۷۶۹٬۰۰۰</strong><small>مقایسه قبلی کافی نیست</small></article><article class="metric-card"><span>فاکتور معتبر</span><strong>۳۶</strong></article><article class="metric-card"><span>میانگین فاکتور</span><strong>۳٬۳۸۲٬۴۷۲</strong></article><article class="metric-card"><span>میانگین مدت نشست</span><strong>۲۲۲۳ دقیقه</strong></article></div></section></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,360,390,412):
        page=browser.new_page(viewport={'width':width,'height':800})
        page.set_content(html)
        data=page.evaluate("""()=>{
          const card=document.querySelector('#net'); const val=card.querySelector('strong');
          const c=card.getBoundingClientRect(), v=val.getBoundingClientRect(), cs=getComputedStyle(val);
          return {iw:innerWidth,sw:document.documentElement.scrollWidth,cl:c.left,cr:c.right,vl:v.left,vr:v.right,vw:v.width,cw:c.width,fs:cs.fontSize,ws:cs.whiteSpace};
        }""")
        assert data['sw'] <= data['iw']+1, (width, 'root overflow', data)
        assert data['vl'] >= data['cl']-1 and data['vr'] <= data['cr']+1, (width, 'net-sales value escapes KPI card', data)
        assert data['vw'] <= data['cw']+1, (width, 'net-sales too wide', data)
        assert data['ws']=='nowrap', (width, 'net-sales must stay one line', data)
        page.close()
    browser.close()
print('1.31.7 analytics KPI browser passed: long Persian net-sales value stays one line and inside its card at 320/360/390/412px.')
