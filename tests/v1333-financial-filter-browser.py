#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
css='\n'.join((R/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel-components.css'])
choice=(R/'assets/js/panel-choice.js').read_text(encoding='utf-8')
jalali=(R/'assets/js/panel-jalali.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>.hidden{{display:none!important}}{css}</style></head><body><main class="panel-content"><div class="financial-filter-layer" id="invoiceFilterLayer" data-financial-filter-layer><button class="financial-filter-backdrop"></button><form class="financial-filter-sheet" data-financial-filter-sheet data-overlay-context="embedded"><div class="financial-filter-sheet-head"><div><small>فاکتورها</small><h2>فیلترها</h2></div></div><div class="financial-filter-sheet-body"><label class="form-group"><span>سال مالی</span><select class="form-control" id="period" data-choice-mode="adaptive"><option>همه</option><option>سال مالی ۱۴۰۵</option><option>سال مالی ۱۴۰۴</option></select></label><label class="form-group"><span>مقصد</span><select class="form-control" data-choice-mode="compact"><option>همه</option><option>تسویه مستقیم</option></select></label><label class="form-group"><span>وضعیت</span><select class="form-control" data-choice-mode="compact"><option>همه</option><option>ثبت‌شده</option></select></label><label class="form-group"><span>از تاریخ</span><div class="jalali-date-control"><input class="form-control" id="from" data-jalali-date inputmode="none"><button class="jalali-date-button" type="button" data-open-jalali="from">تقویم</button></div></label><label class="form-group"><span>تا تاریخ</span><div class="jalali-date-control"><input class="form-control" id="to" data-jalali-date inputmode="none"><button class="jalali-date-button" type="button" data-open-jalali="to">تقویم</button></div></label></div><div class="financial-filter-sheet-actions"><button class="btn btn-primary">اعمال فیلترها</button></div></form></div></main></body></html>'''
with sync_playwright() as p:
 b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for width in (320,390,412):
  page=b.new_page(viewport={'width':width,'height':844});page.set_content(html);page.add_script_tag(content=choice);page.add_script_tag(content=jalali);page.wait_for_timeout(80)
  trigger=page.locator('#period + .panel-choice-trigger'); assert trigger.is_visible(); trigger.click();page.wait_for_timeout(30)
  assert page.locator('#panelChoiceLayer').is_hidden(), 'Financial sheet must not open global choice overlay.'
  assert page.locator('#period').locator('xpath=following-sibling::*[contains(@class,"panel-choice-inline")]').is_visible()
  page.locator('[data-open-jalali="from"]').click();page.wait_for_timeout(30)
  assert not page.locator('dialog.jalali-picker-dialog').evaluate('e=>e.open'), 'Financial date must not use modal dialog.'
  inline=page.locator('#from').locator('xpath=../following-sibling::*[contains(@class,"jalali-picker-inline")]'); assert inline.is_visible()
  title=inline.locator('[data-jalali-title]'); before=title.inner_text()
  grid=inline.locator('[data-jalali-days]')
  box=grid.bounding_box(); assert box
  page.evaluate("([el,x,y])=>el.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,pointerId:81,pointerType:'touch',clientX:x,clientY:y}))",[grid.element_handle(),box['x']+box['width']*.78,box['y']+50])
  page.evaluate("([el,x,y])=>el.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:81,pointerType:'touch',clientX:x,clientY:y}))",[grid.element_handle(),box['x']+box['width']*.22,box['y']+52])
  page.wait_for_timeout(20); after=title.inner_text(); assert after!=before,(before,after)
  # Vertical drag must remain sheet scrolling, not month navigation.
  page.evaluate("([el,x,y])=>el.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,pointerId:82,pointerType:'touch',clientX:x,clientY:y}))",[grid.element_handle(),box['x']+box['width']*.5,box['y']+45])
  page.evaluate("([el,x,y])=>el.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:82,pointerType:'touch',clientX:x+3,clientY:y+95}))",[grid.element_handle(),box['x']+box['width']*.5,box['y']+45])
  page.wait_for_timeout(20); assert title.inner_text()==after,(after,title.inner_text())
  assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
  if width>=390:
   d=page.locator('.financial-filter-sheet-body>.form-group').nth(1).bounding_box(); st=page.locator('.financial-filter-sheet-body>.form-group').nth(2).bounding_box(); assert abs(d['y']-st['y'])<=1,(d,st)
  btn=page.locator('.financial-filter-sheet-actions .btn-primary').bounding_box(); sheet=page.locator('.financial-filter-sheet-actions').bounding_box(); assert btn['width'] >= sheet['width']-26,(btn,sheet)
  page.close()
 b.close()
print('PASS v1333 financial filter browser: embedded select/date, shared horizontal swipe, vertical-scroll guard, no nested overlay, responsive density, full-width primary CTA.')
