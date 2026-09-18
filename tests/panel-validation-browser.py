#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
JS=(ROOT/'assets/js/panel-validation.js').read_text(encoding='utf-8')
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>{CSS}</style></head><body class="panel-body"><main class="panel-content">
<form id="f"><div class="form-group"><label for="name">نام آیتم</label><input class="form-control" id="name" required aria-describedby="name-help"><small id="name-help">راهنما</small></div>
<div class="form-group"><label for="kind">نوع</label><select class="form-control" id="kind" required><option value="">انتخاب</option><option value="a">الف</option></select></div>
<div class="form-group"><label><input id="agree" type="checkbox" required> تأیید تغییر دامنه</label></div>
<div class="form-group"><label for="url">آدرس پایه API</label><input class="form-control" id="url" type="url"></div>
<div class="form-group"><label for="num">دقیقه هشدار</label><input class="form-control" id="num" type="number" min="5" max="180"></div>
<div class="form-group"><label for="pass">رمز تازه</label><input class="form-control" id="pass" minlength="8"></div>
<button id="save" type="submit">ذخیره</button><button id="bypass" type="submit" formnovalidate>بازنشانی</button></form>
</main><script>window.submitCount=0;document.getElementById('f').addEventListener('submit',e=>{{e.preventDefault();window.submitCount++;}});</script></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(html); page.add_script_tag(content=JS)
    page.click('#save'); page.wait_for_timeout(60)
    err=page.locator('#name-validation')
    assert err.is_visible() and 'نام آیتم' in err.inner_text()
    assert page.locator('#name').get_attribute('aria-invalid')=='true'
    desc=page.locator('#name').get_attribute('aria-describedby') or ''
    assert 'name-help' in desc and 'name-validation' in desc
    assert page.locator('#name').evaluate('(e)=>document.activeElement===e')
    assert page.evaluate('window.submitCount')==0
    page.fill('#name','لاته'); page.wait_for_timeout(30)
    assert page.locator('#name-validation').count()==0 and page.locator('#name').get_attribute('aria-invalid') is None
    assert page.locator('#name').get_attribute('aria-describedby')=='name-help'
    page.select_option('#kind','a'); page.check('#agree')
    page.fill('#url','example'); page.click('#save'); page.wait_for_timeout(30)
    assert 'http یا https' in page.locator('#url-validation').inner_text()
    page.fill('#url','https://example.com'); page.fill('#num','2'); page.click('#save'); page.wait_for_timeout(30)
    assert 'کمتر از 5' in page.locator('#num-validation').inner_text()
    page.fill('#num','20'); page.fill('#pass','123'); page.click('#save'); page.wait_for_timeout(30)
    assert 'حداقل 8 نویسه' in page.locator('#pass-validation').inner_text()
    page.fill('#pass','12345678'); page.click('#save'); page.wait_for_timeout(30)
    assert page.evaluate('window.submitCount')==1
    page.fill('#name',''); page.click('#bypass'); page.wait_for_timeout(30)
    assert page.evaluate('window.submitCount')==2, 'formnovalidate was blocked by the shared validator.'
    browser.close()
print('Panel validation browser passed: Persian inline errors, focus/ARIA, correction cleanup, constraints and formnovalidate.')
