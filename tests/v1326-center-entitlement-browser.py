#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CORE=str(ROOT/'assets/js/panel-core.js')
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta name="csrf-token" content="csrf-test"></head><body class="panel-body"><div class="panel-shell"><aside><section hidden data-center-personnel-group><nav><a hidden data-center-personnel-link data-center-personnel-refresh="1" data-center-personnel-url="/entitlement" href="/admin/personnel.php">پرسنل و حقوق</a></nav></section></aside><main><div id="panelContent"></div></main></div><div id="panelToast" class="hidden"></div></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    # Explicit allow reveals the launcher non-blockingly.
    page=browser.new_page(viewport={'width':390,'height':800})
    page.set_content(HTML)
    page.evaluate("""window.__urls=[];window.fetch=async(url)=>{window.__urls.push(String(url));return new Response(JSON.stringify({success:true,supported:true,allowed:true}),{status:200,headers:{'Content-Type':'application/json'}})}""")
    page.add_script_tag(path=CORE)
    page.wait_for_function("window.__urls.includes('/entitlement')")
    page.wait_for_function("!document.querySelector('[data-center-personnel-link]').hidden")
    assert page.eval_on_selector('[data-center-personnel-group]','el=>el.hidden') is False
    assert page.eval_on_selector('[data-center-personnel-link]','el=>el.dataset.centerPersonnelRefresh') == '0'
    page.close()

    # Explicit deny remains hidden.
    page=browser.new_page(viewport={'width':390,'height':800})
    page.set_content(HTML)
    page.evaluate("""window.__urls=[];window.fetch=async(url)=>{window.__urls.push(String(url));return new Response(JSON.stringify({success:true,supported:true,allowed:false}),{status:200,headers:{'Content-Type':'application/json'}})}""")
    page.add_script_tag(path=CORE)
    page.wait_for_function("window.__urls.includes('/entitlement')")
    assert page.eval_on_selector('[data-center-personnel-link]','el=>el.hidden') is True
    assert page.eval_on_selector('[data-center-personnel-group]','el=>el.hidden') is True
    page.close()

    # Unsupported/unknown entitlement remains hidden; unknown is never permission for staff.
    page=browser.new_page(viewport={'width':390,'height':800})
    page.set_content(HTML)
    page.evaluate("""window.__urls=[];window.fetch=async(url)=>{window.__urls.push(String(url));return new Response(JSON.stringify({success:true,supported:false,allowed:false}),{status:200,headers:{'Content-Type':'application/json'}})}""")
    page.add_script_tag(path=CORE)
    page.wait_for_function("window.__urls.includes('/entitlement')")
    assert page.eval_on_selector('[data-center-personnel-link]','el=>el.hidden') is True
    assert page.eval_on_selector('[data-center-personnel-group]','el=>el.hidden') is True
    page.close()

    # Refresh failure also stays hidden.
    page=browser.new_page(viewport={'width':390,'height':800})
    page.set_content(HTML)
    page.evaluate("""() => { window.__urls=[]; window.fetch=(url)=>{window.__urls.push(String(url));return Promise.reject(new Error('offline'))} }""")
    page.add_script_tag(path=CORE)
    page.wait_for_function("window.__urls.includes('/entitlement')")
    assert page.eval_on_selector('[data-center-personnel-link]','el=>el.hidden') is True
    assert page.eval_on_selector('[data-center-personnel-group]','el=>el.hidden') is True
    page.close()
    browser.close()
print('Center personnel entitlement browser passed: allow reveals; deny/unknown/failure stay hidden for staff.')
