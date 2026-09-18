#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
INTERACTION=(ROOT/'assets/js/interaction-modality.js').read_text(encoding='utf-8')
CHOICE=(ROOT/'assets/js/panel-choice.js').read_text(encoding='utf-8')
CONDITIONS=(ROOT/'assets/js/panel-conditions.js').read_text(encoding='utf-8')
JALALI=(ROOT/'assets/js/panel-jalali.js').read_text(encoding='utf-8')
CSS=(ROOT/'assets/css/panel-components.css').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
.hidden{{display:none!important}} .panel-content{{padding:12px}} .form-control{{min-height:44px}} {CSS}
</style></head><body><div class="panel-content"><form id="reportForm" method="get">
<div><select class="form-control" name="period" id="reportPeriod" data-choice-mode="compact" data-choice-label="بازه گزارش"><option value="today" selected>امروز</option><option value="week">این هفته</option><option value="month">این ماه</option><option value="custom">بازه دلخواه</option></select></div>
<div class="form-group hidden" data-panel-condition-source="reportPeriod" data-panel-condition-value="custom" id="fromGroup"><input id="operationsFromDateJ" name="from_j" data-jalali-date inputmode="none" value="۱۴۰۵/۰۵/۱۸" required><button type="button" data-open-jalali="operationsFromDateJ">تقویم</button></div>
<div class="form-group hidden" data-panel-condition-source="reportPeriod" data-panel-condition-value="custom" id="toGroup"><input id="operationsToDateJ" name="to_j" data-jalali-date inputmode="none" value="۱۴۰۵/۰۵/۱۸" required><button type="button" data-open-jalali="operationsToDateJ">تقویم</button></div>
<button id="csv" type="submit" name="action" value="export">خروجی CSV</button></form></div></body></html>'''
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  for width in (320,360,390,412):
    context=browser.new_context(viewport={'width':width,'height':820},is_mobile=True,has_touch=True)
    page=context.new_page(); page.set_content(html)
    page.add_script_tag(content=INTERACTION); page.add_script_tag(content=CHOICE); page.add_script_tag(content=CONDITIONS); page.add_script_tag(content=JALALI); page.wait_for_timeout(80)
    trigger=page.locator('#reportPeriod + .panel-choice-trigger')
    assert trigger.count()==1
    # Initial preset owns dependent state: hidden and disabled.
    assert not page.locator('#fromGroup').is_visible()
    assert page.locator('#operationsFromDateJ').is_disabled()
    # Real touch path through shared choice owner.
    trigger.tap(); page.locator('.panel-choice-option',has_text='بازه دلخواه').tap(); page.wait_for_timeout(40)
    assert page.input_value('#reportPeriod')=='custom'
    assert page.locator('#fromGroup').is_visible() and page.locator('#toGroup').is_visible()
    assert not page.locator('#operationsFromDateJ').is_disabled() and not page.locator('#operationsToDateJ').is_disabled()
    # Pointer/touch must not restore focus to trigger, preventing Android focus ring residue.
    assert page.evaluate("document.activeElement !== document.querySelector('#reportPeriod + .panel-choice-trigger')")
    # Jalali picker still owns date input; CSV uses current custom range.
    page.locator('#operationsFromDateJ').tap(); page.wait_for_timeout(20)
    page.locator('.jalali-picker-dialog [data-jalali-day]').nth(1).tap(); page.wait_for_timeout(20)
    page.locator('#reportForm').evaluate("""(form)=>form.addEventListener('submit',(e)=>{e.preventDefault();window.__submitted=Object.fromEntries(new FormData(form,e.submitter).entries());})""")
    page.locator('#csv').tap(); page.wait_for_timeout(10)
    submitted=page.evaluate('window.__submitted')
    assert submitted['action']=='export' and submitted['period']=='custom'
    assert submitted['from_j']==page.input_value('#operationsFromDateJ') and submitted['to_j']==page.input_value('#operationsToDateJ')
    # Returning to preset immediately hides/disables dependent inputs.
    trigger.tap(); page.locator('.panel-choice-option',has_text='امروز').tap(); page.wait_for_timeout(20)
    assert not page.locator('#fromGroup').is_visible() and page.locator('#operationsFromDateJ').is_disabled()
    context.close()
  # Keyboard contract: focus is intentionally restored for accessibility.
  context=browser.new_context(viewport={'width':390,'height':820})
  page=context.new_page(); page.set_content(html); page.add_script_tag(content=INTERACTION); page.add_script_tag(content=CHOICE); page.add_script_tag(content=CONDITIONS); page.wait_for_timeout(60)
  trigger=page.locator('#reportPeriod + .panel-choice-trigger'); trigger.focus(); trigger.press('Enter'); page.wait_for_timeout(20)
  option=page.locator('.panel-choice-option',has_text='بازه دلخواه'); option.focus(); option.press('Enter'); page.wait_for_timeout(20)
  assert page.evaluate("document.activeElement === document.querySelector('#reportPeriod + .panel-choice-trigger')")
  context.close(); browser.close()
print('Operations report browser passed: shared Choice + Conditions contract toggles custom dates on real touch, removes touch focus residue, preserves Jalali/CSV behavior, and restores focus for keyboard users.')
