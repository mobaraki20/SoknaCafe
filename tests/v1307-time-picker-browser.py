#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-components.css'])
validation=(ROOT/'assets/js/panel-validation.js').read_text(encoding='utf-8')
timepicker=(ROOT/'assets/js/panel-time-picker.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-content"><form id="f"><div class="form-group"><label for="start">شروع</label><input class="form-control panel-time-field" id="start" type="time" name="start" value="08:00" data-minute-step="15" required></div><label class="form-group"><span>پایان</span><input class="form-control" id="end" type="time" name="end" value="01:00" data-minute-step="15" required></label></form></main><script>{validation}</script><script>{timepicker}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,900):
        pg=b.new_page(viewport={'width':width,'height':844},is_mobile=width<721,has_touch=width<721)
        pg.set_content(html)
        assert pg.locator('.panel-time-trigger').count()==2
        assert pg.locator('#start').evaluate("e=>e.classList.contains('panel-time-source') && e.tabIndex===-1")
        assert pg.locator('.panel-time-trigger').nth(0).inner_text().strip().startswith('۸:۰۰ قبل از ظهر')
        pg.locator('.panel-time-trigger').nth(0).click()
        assert pg.locator('#panelTimeLayer').is_visible()
        assert pg.locator('#panelTimeTitle').inner_text().strip()=='شروع'
        # Quarter-hour contract for operational controls.
        assert pg.locator('.panel-time-minutes .panel-time-option').count()==4
        pg.locator('[data-hour="9"]').click()
        pg.locator('[data-minute="15"]').click()
        pg.locator('[data-panel-time-apply]').click()
        assert pg.locator('#start').input_value()=='09:15'
        assert pg.locator('.panel-time-trigger').nth(0).inner_text().strip().startswith('۹:۱۵ قبل از ظهر')
        # Outside/backdrop closes without changing source.
        pg.locator('.panel-time-trigger').nth(1).click(); assert pg.locator('#panelTimeLayer').is_visible()
        before=pg.locator('#end').input_value()
        pg.locator('.panel-time-backdrop').click(position={'x':4,'y':4})
        assert not pg.locator('#panelTimeLayer').is_visible() and pg.locator('#end').input_value()==before
        # Native source cannot summon keyboard/picker through normal tab flow.
        assert pg.locator('#start').get_attribute('aria-hidden')=='true'
        assert not pg.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth')
        pg.close()
    b.close()
print('1.30.7 shared time picker passed at 320/390/900: one trigger per time, Persian 12-hour display with 24-hour canonical storage, quarter-hour selection, outside-close and hidden native input.')
