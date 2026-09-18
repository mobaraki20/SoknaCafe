#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
JS=(ROOT/'assets/js/settings-sections.js').read_text(encoding='utf-8')
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
sections={
'settingsBrand':['settingsIdentity','settingsFavicon'],
'settingsGuest':['settingsTheme','settingsPublic','settingsGuestFeatures','settingsGuestMessages'],
'settingsOperations':['settingsOperationalPreferences'],
'settingsIntegrations':['settingsDomain','settingsIntegrationLinks'],
'settingsSystem':['settingsHealth','settingsSystemOptions','settingsDeviceTools','settingsSecurity'],
}
nav=''.join(f'<a href="#{g}">{g}</a>' for g in sections)
parts=[]
for group,ids in sections.items():
  for id_ in ids:
    body='<p>تنظیم نمونه</p>'
    if id_=='settingsHealth': body='<div class="settings-health-body-v1306"><div class="settings-health-summary-v1306 is-ok"><span>✓</span><div><strong>همه بررسی‌های اصلی سالم هستند</strong><small>آماده استفاده</small></div></div><dl class="settings-health-list-v1306"><div><dt>پردازش تصاویر</dt><dd>فعال</dd></div><div><dt>تولید WebP</dt><dd>فعال</dd></div><div><dt>JPEG / PNG</dt><dd>پشتیبانی می‌شود</dd></div><div><dt>پوشه تصاویر</dt><dd>قابل نوشتن</dd></div><div><dt>حداکثر حجم هر فایل</dt><dd>۲۰M</dd></div><div><dt>حداکثر حجم کل بارگذاری</dt><dd>۲۵M</dd></div></dl></div>'
    parts.append(f'<section class="card settings-section" id="{id_}"><div class="card-body">{body}</div></section>')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="panel-body"><aside class="sidebar" id="sidebar"></aside><button class="sidebar-backdrop" id="sidebarBackdrop"></button><main class="panel-main"><div class="panel-content"><section class="settings-section-index"><nav class="panel-primary-tabs settings-section-nav" data-settings-nav>{nav}</nav></section><div class="settings-page-grid">{''.join(parts)}</div></div></main></body></html>'''
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  for width in (320,360,390,412,768):
    page=browser.new_page(viewport={'width':width,'height':900});page.set_content(html);page.add_script_tag(content=JS)
    for group in sections:
      page.evaluate(f"location.hash='#{group}'");page.dispatch_event('body','click');page.wait_for_timeout(30)
      page.evaluate(f"window.dispatchEvent(new HashChangeEvent('hashchange'))")
      page.wait_for_timeout(20)
      data=page.evaluate('''() => ({inner:innerWidth,doc:document.documentElement.scrollWidth,x:scrollX,visual:visualViewport?.width||innerWidth,sidebarOpen:document.getElementById('sidebar').classList.contains('open'),sidebar:document.getElementById('sidebar').getBoundingClientRect(),health:document.getElementById('settingsHealth').hidden?null:document.getElementById('settingsHealth').getBoundingClientRect(),summary:document.querySelector('#settingsHealth .settings-health-summary-v1306')?.getBoundingClientRect()})''')
      assert data['doc'] <= width+1,(width,group,data)
      assert abs(data['x'])<1 and abs(data['visual']-width)<2,(width,group,data)
      assert not data['sidebarOpen']
      if group=='settingsSystem':
        assert data['health']['left']>=-1 and data['health']['right']<=width+1,(width,data)
        assert data['summary']['left']>=-1 and data['summary']['right']<=width+1,(width,data)
    page.close()
  browser.close()
print('Settings mobile section browser passed at 320/360/390/412/768: no root widening/pan, closed sidebar stays closed, compact health summary stays local.')
