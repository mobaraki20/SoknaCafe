#!/usr/bin/env python3
from pathlib import Path
import re
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'admin/settings.php').read_text(encoding='utf-8')
m=re.search(r'<template id="businessShiftTemplate">(.*?)</template>\s*<script>\s*(\(\(\)=>\{.*?\}\)\(\);)\s*</script>',source,re.S)
assert m,'shift template/script not found'
template,script=m.groups()
# Start with one active shift, mirroring server output.
row='''<article class="business-shift-row" data-business-shift><input type="hidden" name="shift_key[]" value="shift_1" data-shift-key><div class="business-shift-number"></div><label class="business-shift-name"><span>نام شیفت</span><input class="form-control" name="shift_label[]" value="روزانه"></label><label><span>شروع</span><input class="form-control panel-time-field" type="time" name="shift_start[]" value="08:00"></label><label><span>پایان</span><input class="form-control panel-time-field" type="time" name="shift_end[]" value="01:00"></label><button type="button" data-remove-business-shift>×</button></article>'''
html=f'''<!doctype html><html><body><button id="addBusinessShift" type="button">افزودن</button><div id="businessShiftSettings">{row}</div><template id="businessShiftTemplate">{template}</template><script>{script}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page();pg.set_content(html)
    def rows(): return pg.locator('[data-business-shift]').count()
    assert rows()==1 and not pg.locator('#addBusinessShift').evaluate('e=>e.classList.contains("hidden")')
    assert pg.locator('[data-remove-business-shift]').nth(0).is_disabled()
    pg.click('#addBusinessShift'); assert rows()==2
    key2=pg.locator('input[name="shift_key[]"]').nth(1).input_value(); assert key2.startswith('shift_') and key2!='shift_1'
    pg.click('#addBusinessShift'); assert rows()==3 and pg.locator('#addBusinessShift').evaluate('e=>e.classList.contains("hidden")')
    key3=pg.locator('input[name="shift_key[]"]').nth(2).input_value(); assert key3.startswith('shift_') and key3 not in ('shift_1',key2)
    pg.locator('[data-remove-business-shift]').nth(2).click(); assert rows()==2 and not pg.locator('#addBusinessShift').evaluate('e=>e.classList.contains("hidden")')
    pg.locator('[data-remove-business-shift]').nth(1).click(); assert rows()==1 and pg.locator('[data-remove-business-shift]').nth(0).is_disabled()
    b.close()
print('1.30.7 dynamic shift settings passed: 1→2→3 cards, add cap, stable unique new keys, and last shift cannot be removed.')
