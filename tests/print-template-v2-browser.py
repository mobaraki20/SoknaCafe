#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
import json,re
R=Path(__file__).resolve().parents[1]
js=(R/'assets/js/print-template-designer.js').read_text(encoding='utf-8')
css='\n'.join((R/x).read_text(encoding='utf-8') for x in ['assets/css/tokens.css','assets/css/app.css','assets/css/reorder.css','assets/css/panel-components.css'])
samples={'default':{'document_kind':'customer_final','title':'کافه سکنا','invoice_number':'فاکتور ۲۸','table_name':'میز ۸','display_date':'۱۶ مرداد · ۱۴:۳۶','actor_name':'مدیر','settlement_label':'تسویه مستقیم','sections':[{'items':[{'name':'پاستا چیکن آلفردو','quantity':2,'unit_price':580000,'line_total':1160000},{'name':'سرویس بیرون‌بر','quantity':1,'unit_price':15000,'line_total':15000}]}],'subtotal':1175000,'discount':0,'total':1175000},'discount':{'document_kind':'customer_final','title':'کافه سکنا','invoice_number':'فاکتور ۲۹','table_name':'میز ۳','display_date':'۱۶ مرداد · ۱۵:۰۰','settlement_label':'حساب مشترک','sections':[{'items':[{'name':'رینگر','quantity':2,'unit_price':620000,'line_total':1240000}]}],'subtotal':1240000,'discount':200000,'total':1040000}}
html=f'''<!doctype html><html dir="rtl"><style>{css}</style><body><form data-print-template-form><input name="base_font_size" value="23"><input name="title_font_size" value="30"><input name="table_font_size" value="28"><input name="line_spacing" value="5"><input name="margin" value="9"><input name="footer" value="از همراهی شما سپاسگزاریم."><input name="show_time" type="checkbox" checked><input name="show_actor" type="checkbox"><input name="show_section_titles" type="checkbox" checked><select name="design[density]"><option value="compact" selected>compact</option></select><select name="design[item_layout]"><option value="columnar" selected>columnar</option><option value="columnar-compact">compact</option><option value="two-line">two-line</option></select><select name="design[separator_style]"><option value="solid" selected>solid</option></select><input data-reorder-output value='["brand","meta","items","summary","settlement","footer"]'><input name="labels[subtotal]" value="جمع اقلام"><input name="labels[discount]" value="تخفیف"><input name="labels[total]" value="جمع نهایی"><input name="labels[settlement]" value="نحوه ثبت"></form><select data-preview-width><option value="80">80</option><option value="58">58</option></select><select data-preview-scenario><option value="default">default</option><option value="discount">discount</option></select><div data-thermal-preview></div><div class="print-template-section-order"><div class="reorder-row"><button class="reorder-handle" aria-label="گرفتن و جابه‌جایی اقلام"></button><div></div><div class="reorder-actions"><button class="btn btn-sm" data-up aria-label="انتقال اقلام به بالا">↑</button><button class="btn btn-sm" data-down aria-label="انتقال اقلام به پایین">↓</button></div></div></div><script id="print-template-samples" type="application/json">{json.dumps(samples,ensure_ascii=False)}</script><script>{js}</script></body></html>'''
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for viewport in (320,390,768,1024):
  p=b.new_page(viewport={'width':viewport,'height':1100});p.set_content(html);p.wait_for_timeout(80)
  prev=p.locator('[data-thermal-preview]');assert 'فاکتور ۲۸' in prev.inner_text() and 'جمع نهایی' in prev.inner_text() and 'شرح' in prev.inner_text()
  p.locator('[data-preview-width]').select_option('58');p.wait_for_timeout(20);assert 'is-58' in (prev.get_attribute('class') or '') and 'تعداد × فی' in prev.inner_text()
  p.locator('[data-preview-scenario]').select_option('discount');p.wait_for_timeout(20);assert 'تخفیف' in prev.inner_text()
  assert p.evaluate('(e)=>e.scrollWidth<=e.clientWidth',prev.element_handle())

  if viewport <= 390:
   for sel in ('.print-template-section-order .reorder-handle','[data-up]','[data-down]'):
    box=p.locator(sel).bounding_box(); assert box and box['width']>=44 and box['height']>=44,(viewport,sel,box)

  p.close()
 b.close()
print('Print Template v2 browser PASS: 58/80 preview, totals/discount, and responsive receipt rendering remain readable without internal horizontal overflow.')
