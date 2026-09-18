#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
import json
ROOT=Path(__file__).resolve().parents[1]
JS='\n'.join((ROOT/f).read_text(encoding='utf-8') for f in ['assets/js/panel-core.js','assets/js/panel-form-state.js'])
CSS='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])

def build_html(error=False):
    alert='<div class="alert alert-error">اطلاعات فرم را اصلاح کن.</div>' if error else ''
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>.hidden{{display:none!important}}{CSS}</style></head><body class="panel-body"><aside id="sidebar"></aside><header class="panel-topbar"></header><main id="panelContent">{alert}<button id="launch">ثبت در حساب مشترک</button><form method="post"><input name="title" value=""><textarea name="description"></textarea><select name="status"><option value="draft">پیش‌نویس</option><option value="active">فعال</option></select><button type="submit">ذخیره</button></form><div id="modal" class="success-modal hidden" role="dialog" aria-modal="true" aria-hidden="true"><div class="success-box"><input id="modalInput"><button id="action">ثبت</button><button id="close">بستن</button></div></div></main><div id="panelToast" class="panel-toast hidden"></div></body></html>'''

def install_storage(page, initial=None):
    payload=json.dumps(initial or {},ensure_ascii=False)
    page.evaluate(f"""()=>{{window.__sessionStore={payload};Object.defineProperty(window,'sessionStorage',{{configurable:true,value:{{setItem:(k,v)=>window.__sessionStore[k]=String(v),getItem:(k)=>Object.prototype.hasOwnProperty.call(window.__sessionStore,k)?window.__sessionStore[k]:null,removeItem:(k)=>delete window.__sessionStore[k],clear:()=>window.__sessionStore={{}}}}}});}}""")

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(build_html(False));install_storage(page);page.add_script_tag(content=JS)
    page.fill('[name="title"]','کمپین عصرانه')
    page.fill('[name="description"]','این متن نباید پس از خطای اعتبارسنجی پاک شود.')
    page.select_option('[name="status"]','active')
    page.wait_for_timeout(220)
    page.focus('#launch')
    page.evaluate("CafeUI.dialog.open(document.getElementById('modal'),document.getElementById('launch'))")
    page.wait_for_timeout(40)
    assert page.locator('#modal').is_visible()
    assert page.locator('#modalInput').evaluate('(e)=>document.activeElement===e'),'Dialog did not move focus inside.'
    styles=page.locator('#modal').evaluate("e=>({bg:getComputedStyle(e).backgroundColor,box:getComputedStyle(e.querySelector('.success-box')).backgroundColor})")
    assert styles['bg']!='rgba(0, 0, 0, 0)' and styles['box'] in ('rgb(255, 255, 255)','rgba(255, 255, 255, 1)'),styles
    assert page.locator('form').evaluate("e=>e.classList.contains('panel-dialog-inert')"),'Page content did not become inert.'
    page.evaluate("CafeUI.runAction({button:document.getElementById('action'),request:async()=>({ok:true}),onSuccess:async()=>CafeUI.dialog.close(document.getElementById('modal'))})")
    page.wait_for_timeout(80)
    assert page.locator('#modal').is_hidden()
    assert page.locator('#launch').evaluate('(e)=>document.activeElement===e'),'Focus did not return to the launcher.'
    assert not page.locator('#action').is_disabled(),'Action button remained disabled after success.'
    store=page.evaluate('window.__sessionStore')

    page.set_content(build_html(True));install_storage(page,store);page.add_script_tag(content=JS);page.wait_for_timeout(120)
    assert page.input_value('[name="title"]')=='کمپین عصرانه'
    assert 'نباید' in page.input_value('[name="description"]')
    assert page.input_value('[name="status"]')=='active'
    assert page.locator('[name="title"]').evaluate('(e)=>document.activeElement===e'),'First form field was not focused after validation error.'
    browser.close()
print('Panel dialog and form-state browser checks passed: solid modal, inert background, focus recovery, clean action state and preserved inputs.')
