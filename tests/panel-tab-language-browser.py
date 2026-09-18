#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/operator-live.css'])
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="panel-body"><main class="panel-main"><div class="panel-content">
<section class="operator-live-head-v1280"><div class="operator-work-tabs-v1280 panel-primary-tabs" role="tablist"><button role="tab" class="is-active" aria-selected="true">نیازمند اقدام</button><button role="tab" aria-selected="false">میزها</button><button role="tab" aria-selected="false">جمع اقلام</button></div></section>
<nav class="panel-primary-tabs" aria-label="بخش اصلی"><a class="is-active" aria-current="page">نمای کلی</a><a>تنظیمات</a><a>عیب‌یابی</a></nav>
<nav class="panel-subnav" aria-label="زیرمنو"><a class="is-active" aria-current="page">موجودی</a><a>سابقه</a><a>شمارش‌ها</a></nav>
<div class="segmented-control compact"><button class="is-active">همه</button><button>باز</button><button>آزاد</button></div>
</div></main></body></html>'''
def rgb(s): return s.replace(' ','')
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  for width in (320,390,768,1366):
    page=browser.new_page(viewport={'width':width,'height':800},has_touch=width<600,is_mobile=width<600)
    page.set_content(HTML); page.add_style_tag(content=CSS)
    data=page.evaluate('''() => { const gp=e=>getComputedStyle(e); const primary=document.querySelector('.panel-primary-tabs .is-active'); const secondary=document.querySelector('.panel-subnav .is-active'); const filter=document.querySelector('.segmented-control .is-active'); return {doc:document.documentElement.scrollWidth,w:innerWidth,pH:primary.getBoundingClientRect().height,sH:secondary.getBoundingClientRect().height,primaryBg:gp(primary).backgroundColor,secondaryBg:gp(secondary).backgroundColor,filterBg:gp(filter).backgroundColor}; }''')
    assert data['doc']<=width+1,(width,data)
    assert data['pH']>=43.5,(width,data)
    if width<600: assert data['sH']>=43.5,(width,data)
    assert rgb(data['primaryBg'])!=rgb(data['secondaryBg']),(width,data)
    assert rgb(data['primaryBg'])!=rgb(data['filterBg']),(width,data)
    page.close()
  browser.close()
print('Panel tab language browser PASS at 320/390/768/1366: primary, secondary and filter hierarchy stays distinct without root overflow.')
