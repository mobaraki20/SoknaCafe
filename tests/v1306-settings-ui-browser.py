#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
source=(ROOT/'admin/settings.php').read_text(encoding='utf-8')
for token in ('settings-health-summary-v1306','settings-health-list-v1306','جزئیات فنی','business-shift-settings','panel-time-field','مرز تغییر روز عملیاتی','حداکثر سه شیفت'):
    assert token in source, token
# Technical PHP names must not be primary health row labels.
health=source[source.index('id="settingsHealth"'):source.index('id="settingsSystemOptions"')]
assert '<dt>upload_max_filesize</dt>' not in health
assert '<dt>post_max_size</dt>' not in health
assert '<dt>GD</dt>' in health  # technical details only
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content"><div class="settings-page-grid">
<section class="card settings-section" id="settingsOperationalPreferences"><div class="card-body"><div class="form-group full"><div class="settings-inline-note"><strong>روز عملیاتی و شیفت‌ها</strong></div></div><div class="form-group full"><label>مرز تغییر روز عملیاتی</label><input class="form-control panel-time-field" type="time" value="04:00"></div><div class="form-group full"><div class="business-shifts-heading"><div><label>شیفت‌های عملیاتی</label></div><button class="btn btn-light">افزودن شیفت</button></div><div class="business-shift-settings"><article class="business-shift-row"><div class="business-shift-number">۱</div><label class="business-shift-name"><span>نام شیفت</span><input class="form-control" value="روزانه"></label><label><span>شروع</span><input class="form-control panel-time-field" type="time" value="08:00"></label><label><span>پایان</span><input class="form-control panel-time-field" type="time" value="01:00"></label><button class="icon-btn business-shift-remove">×</button></article></div></div></div></section>
<section class="card settings-section" id="settingsHealth"><div class="card-body settings-health-body-v1306"><div class="settings-health-summary-v1306 is-ok"><span>✓</span><div><strong>همه بررسی‌های اصلی سالم هستند</strong><small>بارگذاری و بهینه‌سازی تصویر آماده استفاده است.</small></div></div><dl class="settings-health-list-v1306">{''.join(f'<div><dt>{a}</dt><dd>{b}</dd></div>' for a,b in [('پردازش تصاویر','فعال'),('تولید WebP','فعال'),('JPEG / PNG','پشتیبانی می‌شود'),('پوشه تصاویر','قابل نوشتن'),('حداکثر حجم هر فایل','۲۰M'),('حداکثر حجم کل بارگذاری','۲۵M')])}</dl><details class="settings-health-details-v1306"><summary>جزئیات فنی</summary><dl><div><dt>GD</dt><dd>enabled</dd></div></dl></details></div></section>
</div></div></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,360,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':1000})
        page.set_content(html)
        data=page.evaluate('''() => ({inner:innerWidth,doc:document.documentElement.scrollWidth,health:document.getElementById('settingsHealth').getBoundingClientRect(),ops:document.getElementById('settingsOperationalPreferences').getBoundingClientRect(),rows:[...document.querySelectorAll('.business-shift-row')].map(x=>x.getBoundingClientRect())})''')
        assert data['doc'] <= width+1,(width,data)
        assert data['health']['left']>=-1 and data['health']['right']<=width+1,(width,data)
        assert data['ops']['left']>=-1 and data['ops']['right']<=width+1,(width,data)
        assert all(r['left']>=-1 and r['right']<=width+1 for r in data['rows']),(width,data)
        page.close()
    browser.close()
print('1.30.6 settings UI browser passed at 320/360/390/412/768/1366: compact health and business-time/shift controls stay within the panel viewport.')
