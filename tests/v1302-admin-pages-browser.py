#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
tables='''<div class="page-grid tables-page-grid is-editor-mode"><section class="card table-card tables-list-card"><table class="data-table tables-management-table"><tbody><tr><td data-label="میز"><strong>میز ۱۲</strong></td><td data-label="بخش">تراس</td><td data-label="وضعیت">آزاد</td><td data-label="عملیات"><button class="btn btn-sm btn-light">عملیات</button></td></tr></tbody></table></section><section class="card tables-editor-card"><header><a class="btn btn-sm btn-light tables-editor-back" href="#">بازگشت به فهرست</a></header><form class="form-grid"><label class="form-group"><span>نام میز</span><input class="form-control"></label><label class="form-group"><span>شماره میز</span><input class="form-control"></label><div class="form-group full actions"><button class="btn btn-primary">ذخیره میز</button><button class="btn btn-light">انصراف</button></div></form></section></div>'''
qr='''<section class="card qr-security-summary"><div class="card-body qr-security-grid"><div class="qr-security-status"><strong>QR فعال فعلی</strong><span>وضعیت</span></div><div class="qr-security-actions"><button class="btn btn-outline">بازگردانی QR قبلی</button><details class="qr-rotation-control"><summary class="btn btn-danger">ابطال فوری و ساخت QR جدید</summary><div><button class="btn btn-danger">ساخت QR جایگزین</button></div></details></div></div></section><div class="qr-grid is-single"><section class="card qr-card"><div class="qr-vector"><svg></svg></div><h3>میز ۱۲</h3><p>تراس</p><div class="qr-card-actions"><button class="btn btn-sm">دانلود SVG</button><button class="btn btn-sm">دانلود PNG</button></div></section></div>'''
html=lambda body:f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content">{body}</div></main></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for w in (320,390,412,768,1366,1920):
        page=browser.new_page(viewport={'width':w,'height':900}); page.set_content(html(tables))
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), ('tables',w)
        if w<=760:
            assert page.locator('.tables-list-card').is_hidden(), w
            assert page.locator('.tables-editor-card').is_visible(), w
            assert page.locator('.tables-editor-back').is_visible(), w
            assert page.locator('.tables-editor-card .btn').first.bounding_box()['height']>=44
        else:
            assert page.locator('.tables-list-card').is_visible(), w
        page.close()
    for w in (320,390,412,768,1366,1920):
        page=browser.new_page(viewport={'width':w,'height':900}); page.set_content(html(qr))
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), ('qr',w)
        if w<=760:
            grid=page.locator('.qr-security-grid').evaluate('e=>getComputedStyle(e).gridTemplateColumns')
            assert ' ' not in grid.strip(), (w,grid)
            for b in page.locator('.qr-card-actions .btn').all(): assert b.bounding_box()['height']>=44
        page.close()
    browser.close()
print('1.30.2 Tables/QR responsive browser passed at 320/390/412/768/1366/1920: editor-first mobile, no horizontal overflow, QR stacking and touch targets.')
