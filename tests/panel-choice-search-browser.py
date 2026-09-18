#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
js=(ROOT/'assets/js/panel-choice.js').read_text(encoding='utf-8')
options=''.join(f'<option value="x{i}">کالای آزمایشی {i}</option>' for i in range(1,31))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--line:#ddd5ca;--muted:#6f675f;--font-ui:Tahoma;--panel-line:#ddd5ca;--panel-surface:#fff}}{css}</style></head><body class="panel-body"><main class="panel-content"><div class="form-group"><label for="ingredient">ماده انبار</label><select id="ingredient" class="form-control" data-choice-mode="browse" data-choice-search="true"><option value="" disabled hidden data-choice-placeholder="true" selected>انتخاب ماده</option><option value="water">آب معدنی کوچک</option><option value="sparkling">آب گازدار لیمویی کاله</option><option value="fries">سیب‌زمینی کاله</option><option value="milk">شیر پرچرب کاله</option>{options}</select></div></main><script>{js}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':390,'height':844});pg.set_content(html,wait_until='load')
    trigger=pg.locator('.panel-choice-trigger'); assert trigger.count()==1
    trigger.click(); pg.wait_for_timeout(60)
    layer=pg.locator('#panelChoiceLayer'); assert layer.is_visible()
    search=pg.locator('[data-panel-choice-search] input'); assert search.is_visible()
    labels=pg.locator('.panel-choice-option span').all_text_contents()
    assert 'انتخاب ماده' not in labels, labels[:5]
    assert len(labels) >= 34
    search.fill('شیر کاله'); pg.wait_for_timeout(30)
    visible=pg.locator('.panel-choice-option:visible span').all_text_contents()
    assert visible==['شیر پرچرب کاله'], visible
    sheet=pg.locator('.panel-choice-sheet').bounding_box(); assert sheet and sheet['height'] < 500, sheet
    clear=pg.locator('[data-panel-choice-search-clear]'); cb=clear.bounding_box(); assert cb and cb['width'] >= 44 and cb['height'] >= 44, cb
    search.fill('آب'); pg.wait_for_timeout(30)
    visible=pg.locator('.panel-choice-option:visible span').all_text_contents()
    assert visible==['آب معدنی کوچک','آب گازدار لیمویی کاله'], visible
    pg.locator('.panel-choice-option:visible',has_text='آب معدنی کوچک').click(); pg.wait_for_timeout(30)
    assert layer.is_hidden()
    assert pg.locator('#ingredient').input_value()=='water'
    assert 'آب معدنی کوچک' in trigger.inner_text()
    assert pg.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    b.close()
print('1.32.1 searchable browse choice passed: placeholder excluded, mobile search filters large option sets, selection syncs and closes cleanly.')
