#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
js=(R/'assets/js/reorder-list.js').read_text(encoding='utf-8')
css=(R/'assets/css/reorder.css').read_text(encoding='utf-8')
html=f'''<!doctype html><html dir="rtl"><style>{css}</style><body><form data-reorder-form><input data-reorder-output><div class="reorder-list" data-reorder-list>
<article class="reorder-row" draggable="true" data-reorder-id="10"><button data-reorder-handle type="button">☰</button><div class="reorder-copy"><strong>قهوه</strong></div><div class="reorder-actions"><button data-reorder-up type="button">↑</button><button data-reorder-down type="button">↓</button></div></article>
<article class="reorder-row" draggable="true" data-reorder-id="20"><button data-reorder-handle type="button">☰</button><div class="reorder-copy"><strong>غذا</strong></div><div class="reorder-actions"><button data-reorder-up type="button">↑</button><button data-reorder-down type="button">↓</button></div></article>
<article class="reorder-row" draggable="true" data-reorder-id="30"><button data-reorder-handle type="button">☰</button><div class="reorder-copy"><strong>خدمات · غیرفعال</strong></div><div class="reorder-actions"><button data-reorder-up type="button">↑</button><button data-reorder-down type="button">↓</button></div></article>
</div></form><script>{js}</script></body></html>'''
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for width in (320,360,390,412,768,1366):
  p=b.new_page(viewport={'width':width,'height':800});p.set_content(html);p.wait_for_timeout(30)
  assert p.locator('[data-reorder-output]').input_value()=='[10,20,30]'
  p.locator('[data-reorder-id="20"] [data-reorder-up]').click(); assert p.locator('[data-reorder-output]').input_value()=='[20,10,30]'
  p.locator('[data-reorder-id="30"] [data-reorder-handle]').focus();p.keyboard.down('Alt');p.keyboard.press('ArrowUp');p.keyboard.up('Alt');assert p.locator('[data-reorder-output]').input_value()=='[20,30,10]'
  assert p.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth')
  p.close()
 b.close()
print('Shared reorder browser PASS at 320/360/390/412/768/1366: one control preserves ordering, keyboard fallback, inactive rows, and no horizontal overflow.')
