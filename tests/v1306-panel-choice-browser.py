#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-components.css'] if (ROOT/p).exists())
validation=(ROOT/'assets/js/panel-validation.js').read_text(encoding='utf-8')
choice=(ROOT/'assets/js/panel-choice.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-content"><form id="f"><label class="form-group"><span>بازه</span><select class="form-control" name="days" data-choice-mode="compact" required><option value="">انتخاب کنید</option><option value="7">۷ روز</option><option value="30" selected>۳۰ روز</option></select></label><label class="form-group"><span>پرینتر</span><select class="form-control" id="dynamic" data-choice-mode="browse"><option value="all">همه</option></select></label><label class="form-group"><span>مقصد</span><select class="form-control" id="embedded" data-choice-mode="embedded"><option value="a">الف</option><option value="b">ب</option></select></label><label class="form-group"><span>شیفت</span><select class="form-control" id="adaptive" data-choice-mode="adaptive"><option>کل روز</option><option>صبح</option><option>عصر</option><option>مقایسه</option></select></label><button id="submit">ثبت</button></form></main></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
    pg.set_content(html); pg.add_script_tag(content=validation); pg.add_script_tag(content=choice)
    assert pg.locator('.panel-choice-trigger').count()==4
    # Compact choice is a centered modal on mobile.
    pg.locator('.panel-choice-trigger').nth(0).click()
    assert pg.locator('#panelChoiceLayer').is_visible() and pg.locator('#panelChoiceLayer').evaluate("e=>e.classList.contains('mode-compact')")
    box=pg.locator('.panel-choice-sheet').bounding_box(); assert box and box['y']>20 and box['y']+box['height']<830, box
    pg.get_by_role('option',name='۷ روز').click(); assert pg.locator('select[name=days]').input_value()=='7'
    # Browse is a mobile bottom sheet and re-reads dynamic options on open.
    pg.evaluate("document.querySelector('#dynamic').insertAdjacentHTML('beforeend','<option value=active>پرینتر جدید</option>');document.querySelector('#dynamic').value='active'")
    pg.locator('.panel-choice-trigger').nth(1).click(); assert pg.locator('#panelChoiceLayer').evaluate("e=>e.classList.contains('mode-browse')")
    sheet=pg.locator('.panel-choice-sheet').bounding_box(); assert sheet and sheet['y']+sheet['height']>=842, sheet
    assert pg.get_by_role('option',name='پرینتر جدید').get_attribute('aria-selected')=='true'
    pg.locator('.panel-choice-backdrop').click(position={'x':4,'y':4}); assert not pg.locator('#panelChoiceLayer').is_visible()
    # Embedded choice never opens a second overlay.
    pg.locator('.panel-choice-trigger').nth(2).click(); assert pg.locator('.panel-choice-inline').is_visible(); assert not pg.locator('#panelChoiceLayer').is_visible()
    pg.locator('.panel-choice-inline').get_by_role('option',name='ب').click(); assert pg.locator('#embedded').input_value()=='b'
    # Adaptive <=5 resolves to compact.
    pg.locator('.panel-choice-trigger').nth(3).click(); assert pg.locator('#panelChoiceLayer').evaluate("e=>e.classList.contains('mode-compact')")
    pg.locator('.panel-choice-backdrop').click(position={'x':4,'y':4})
    # Required validation opens internal compact choice, not native UI.
    pg.evaluate("const s=document.querySelector('select[name=days]');s.value='';document.querySelector('#f').requestSubmit()")
    pg.wait_for_timeout(50); assert pg.locator('#panelChoiceLayer').is_visible(); assert pg.locator('select[name=days]').get_attribute('aria-invalid')=='true'
    pg.close()
    # Early-owner contract: load owner immediately after panel-content opens, before select markup exists.
    early=b.new_page(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
    early.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><head><style>{css}</style></head><body><main class="panel-content" id="p"></main></body></html>''')
    early.add_script_tag(content=choice)
    early.evaluate("document.querySelector('#p').insertAdjacentHTML('beforeend','<select class=\"form-control\" data-choice-mode=\"compact\" id=\"late\"><option>امروز</option><option>فردا</option></select>')")
    early.wait_for_timeout(20); assert early.locator('#late').evaluate("e=>e.classList.contains('panel-choice-source')")
    early.locator('.panel-choice-trigger').click(); assert early.locator('#panelChoiceLayer').is_visible()
    early.close(); b.close()
print('1.31.7 semantic panel-choice passed: compact modal, browse sheet, embedded inline, adaptive compact, dynamic options, validation and early enhancement.')
