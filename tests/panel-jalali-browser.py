#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
JS=(ROOT/'assets/js/panel-jalali.js').read_text(encoding='utf-8')
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
ids=['invoiceFromDateJ','invoiceToDateJ','operationsFromDateJ','operationsToDateJ','itemScheduleStartDateJ','itemScheduleEndDateJ','tagStartDateJ','tagEndDateJ','eventStartDateJ','eventEndDateJ','campaignStartDateJ','campaignEndDateJ']
controls=''.join(f'''<div class="form-group"><label>{id_}</label><div class="jalali-date-control"><input class="form-control" id="{id_}" data-jalali-date inputmode="numeric" {'required' if id_.startswith('event') else ''}><button class="jalali-date-button" type="button" data-open-jalali="{id_}">تقویم</button></div></div>''' for id_ in ids)
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="panel-body"><main class="panel-content"><div class="form-grid">{controls}</div></main></body></html>'''

with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])

  # Desktop/fine pointer: all fields use one picker owner; the field and icon both open it.
  for width in (768,1366):
    page=browser.new_page(viewport={'width':width,'height':900});page.set_content(html);page.add_script_tag(content=JS);page.wait_for_timeout(20)
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),width
    for id_ in ids:
      button=page.locator(f'[data-open-jalali="{id_}"]')
      box=button.bounding_box(); assert box and box['width']>=43.5 and box['height']>=43.5,(width,id_,box)
      assert page.locator(f'#{id_}').is_editable(),(width,id_,'desktop date field should remain editable')
    page.locator('#invoiceFromDateJ').click();page.wait_for_timeout(20)
    assert page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open')
    # Clicking the modal backdrop closes it.
    page.mouse.click(4,4);page.wait_for_timeout(20)
    assert not page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open')
    # Calendar icon follows the exact same owner.
    page.locator('[data-open-jalali="invoiceFromDateJ"]').click();page.wait_for_timeout(20)
    assert page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open')
    first=page.locator('.jalali-picker-dialog [data-jalali-day]').first
    first.click();page.wait_for_timeout(20)
    value=page.input_value('#invoiceFromDateJ')
    assert '/' in value and any(ch in value for ch in '۰۱۲۳۴۵۶۷۸۹'),(width,value)
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),('after',width)
    page.close()

  # Mobile/touch-primary: date fields are picker-only so Android/iOS should not summon a keyboard.
  for width in (320,360,390,412):
    context=browser.new_context(viewport={'width':width,'height':900},is_mobile=True,has_touch=True)
    page=context.new_page();page.set_content(html);page.add_script_tag(content=JS);page.wait_for_timeout(30)
    assert page.evaluate("matchMedia('(pointer: coarse)').matches"),(width,'expected coarse pointer')
    for id_ in ids:
      state=page.locator(f'#{id_}').evaluate('(e)=>({readOnly:e.readOnly,inputMode:e.getAttribute("inputmode"),hasPopup:e.getAttribute("aria-haspopup"),expanded:e.getAttribute("aria-expanded"),willValidate:e.willValidate,valueMissing:e.validity.valueMissing})')
      trigger_state=page.locator(f'[data-open-jalali="{id_}"]').evaluate('(e)=>({hasPopup:e.getAttribute("aria-haspopup"),expanded:e.getAttribute("aria-expanded")})')
      assert state['readOnly'] is False,(width,id_,state)
      assert state['inputMode']=='none',(width,id_,state)
      assert state['hasPopup']=='dialog' and state['expanded']=='false',(width,id_,state)
      assert trigger_state['hasPopup']=='dialog' and trigger_state['expanded']=='false',(width,id_,trigger_state)
      if id_.startswith('event'):
        assert state['willValidate'] is True and state['valueMissing'] is True,(width,id_,'required validation must remain active',state)
    page.locator('#operationsFromDateJ').tap();page.wait_for_timeout(30)
    assert page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open'),(width,'field tap must open picker')
    assert page.locator('#operationsFromDateJ').get_attribute('aria-expanded')=='true',(width,'field aria-expanded must follow picker')
    assert page.locator('[data-open-jalali="operationsFromDateJ"]').get_attribute('aria-expanded')=='true',(width,'trigger aria-expanded must follow picker')
    assert page.evaluate("document.activeElement !== document.getElementById('operationsFromDateJ')"),(width,'touch date field must not keep focus/raise keyboard')
    page.mouse.click(3,3);page.wait_for_timeout(20)
    assert not page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open'),(width,'backdrop must close picker')
    page.locator('[data-open-jalali="operationsToDateJ"]').tap();page.wait_for_timeout(20)
    assert page.locator('.jalali-picker-dialog').evaluate('(e)=>e.open'),(width,'icon tap must open picker')
    page.locator('.jalali-picker-dialog [data-jalali-day]').first.tap();page.wait_for_timeout(20)
    assert page.input_value('#operationsToDateJ')
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),width
    context.close()

  browser.close()
print('Shared Jalali picker browser passed: field+icon open the same owner, backdrop closes, mobile uses inputmode=none without weakening required validation, Persian selection works, no root overflow.')
