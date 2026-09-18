#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
css='\n'.join((R/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel-components.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{css}</style></head><body><div class="audit-feed-v2"><section class="audit-day-group"><details class="audit-v2-row has-details" data-family="finance"><summary class="audit-v2-summary"><strong>حساب میز ۲ را تسویه کرد</strong><div class="audit-v2-context"><span>فاکتور I-1405-000028</span></div><div class="audit-v2-meta"><span>مدیر کافه</span><time>۱۷:۲۲</time><span class="audit-v2-more">جزئیات</span></div></summary><dl class="audit-v2-details"><div><dt>مبلغ نهایی</dt><dd>۱٬۵۶۵٬۰۰۰ تومان</dd></div><div><dt>چاپ فاکتور</dt><dd>درخواست شد و وارد صف شد</dd></div></dl></details></section></div></body></html>'''
with sync_playwright() as p:
 b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox']);page=b.new_page(viewport={'width':390,'height':844});page.set_content(html)
 row=page.locator('details.audit-v2-row'); assert not row.evaluate('e=>e.open'); page.locator('.audit-v2-summary').click();assert row.evaluate('e=>e.open');assert page.locator('.audit-v2-details').is_visible();assert '۱٬۵۶۵٬۰۰۰' in page.locator('.audit-v2-details').inner_text();assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth+1');b.close()
print('PASS v1333 activity browser: compact timeline expands to readable audit details without overflow.')
