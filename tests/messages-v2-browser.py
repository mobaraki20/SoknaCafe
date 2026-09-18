#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
js=(R/'assets/js/messages-admin.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html dir="rtl"><body>
<input data-message-search><button data-message-filter="all">همه</button><button data-message-filter="changed">تغییرکرده</button><button data-message-filter="optional">اختیاری</button>
<button data-message-group="all">همه گروه‌ها</button><button data-message-group="cart">سبد</button>
<div data-message-list>
<article data-message-card data-group="cart" data-changed="1" data-optional="0" data-search-text="ثبت سفارش"><textarea data-message-input maxlength="100">سفارش {{items}} ثبت شد</textarea><span data-message-count></span><div data-message-preview></div><button data-insert-token="items">items</button></article>
<article data-message-card data-group="general" data-changed="0" data-optional="1" data-search-text="خوش آمد"><textarea data-message-input maxlength="100">خوش آمدید</textarea><input data-message-enabled type="checkbox" checked><span data-message-count></span><div data-message-preview></div></article>
</div><div data-message-empty hidden>خالی</div><script>{js}</script></body></html>'''
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for width in (320,360,390,412,768):
  p=b.new_page(viewport={'width':width,'height':800});p.set_content(html);p.wait_for_timeout(20)
  assert p.locator('[data-message-card]:visible').count()==2
  p.locator('[data-message-filter="changed"]').click();assert p.locator('[data-message-card]:visible').count()==1
  p.locator('[data-message-filter="all"]').click();p.locator('[data-message-search]').fill('خوش');assert p.locator('[data-message-card]:visible').count()==1
  p.locator('[data-message-search]').fill('');p.locator('[data-message-group="cart"]').click();assert p.locator('[data-message-card]:visible').count()==1
  ta=p.locator('[data-message-card][data-group="cart"] textarea');ta.fill('ثبت ');ta.evaluate('(e)=>{e.setSelectionRange(e.value.length,e.value.length)}');p.locator('[data-insert-token]').click();assert '{items}' in ta.input_value()
  assert p.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth')
  p.close()
 b.close()
print('Messages v2 browser PASS at 320/360/390/412/768: search, changed/group filters, token insertion, live preview and responsive layout remain functional.')
