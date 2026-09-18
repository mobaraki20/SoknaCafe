#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
core=(ROOT/'assets/js/panel-core.js').read_text(encoding='utf-8')
css=(ROOT/'assets/css/panel-layout.css').read_text(encoding='utf-8')

def page_html(status=200,payload=None,raw=None):
    payload_js=json.dumps(payload,ensure_ascii=False) if payload is not None else 'null'
    raw_js=json.dumps(raw,ensure_ascii=False) if raw is not None else 'null'
    pre=f'''<script>
    (()=>{{
      const store=new Map();
      Object.defineProperty(window,'sessionStorage',{{configurable:true,value:{{getItem:k=>store.has(k)?store.get(k):null,setItem:(k,v)=>store.set(k,String(v)),removeItem:k=>store.delete(k),clear:()=>store.clear()}}}});
      window.__fetchCalls=0; window.__status={status}; window.__payload={payload_js}; window.__raw={raw_js};
      window.fetch=async()=>{{window.__fetchCalls++; const ok=window.__status>=200&&window.__status<300; return {{ok,status:window.__status,json:async()=>{{if(window.__raw!==null) throw new SyntaxError('invalid json'); return window.__payload;}}}};}};
    }})();
    </script>'''
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="csrf-token" content="csrf-test"><style>{css}</style>{pre}</head>
<body class="panel-body"><aside class="sidebar open" style="position:static;height:auto;min-height:0"><div></div><div class="side-nav-groups"><section class="side-nav-group is-open"><nav class="side-nav">
<a id="personnel" href="/admin/personnel.php" data-center-personnel-link data-center-personnel-refresh="0" data-center-personnel-url="" data-payroll-reminder-url="/api/sokna_center_payroll_reminder_count.php" data-payroll-reminder-user="7"><span class="side-nav-icon">●</span><span class="side-nav-label">پرسنل و حقوق</span><span class="side-nav-badge" data-payroll-reminder-count hidden aria-hidden="true"></span></a>
</nav></section></div><div></div></aside><main id="panelContent"></main><div id="panelToast"></div><script>{core}</script></body></html>'''

def run_case(browser,width,status=200,payload=None,raw=None,expected=None):
    ctx=browser.new_context(viewport={'width':width,'height':800},is_mobile=width<=412,has_touch=width<=412)
    page=ctx.new_page(); page.set_content(page_html(status,payload,raw)); page.wait_for_timeout(80)
    badge=page.locator('[data-payroll-reminder-count]')
    hidden=badge.evaluate('el=>el.hidden'); text=badge.text_content() or ''
    if expected is None:
        assert hidden is True and text=='', (width,status,payload,raw,hidden,text)
    else:
        assert hidden is False and text==expected, (width,status,payload,text)
        assert badge.get_attribute('aria-hidden')=='false'
    assert page.locator('#personnel').get_attribute('href')=='/admin/personnel.php'
    assert badge.evaluate("el=>getComputedStyle(el).pointerEvents")=='none'
    geo=page.locator('#personnel').evaluate("el=>({scroll:el.scrollWidth,client:el.clientWidth,labelWhite:getComputedStyle(el.querySelector('.side-nav-label')).whiteSpace})")
    assert geo['scroll']<=geo['client']+1,(width,geo)
    assert geo['labelWhite']=='nowrap',(width,geo)
    before=page.evaluate('window.__fetchCalls')
    page.evaluate('CafeUI.refreshPayrollReminderBadge()'); page.wait_for_timeout(40)
    assert page.evaluate('window.__fetchCalls')==before,('cache_miss',width,before,page.evaluate('window.__fetchCalls'))
    ctx.close()

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    run_case(browser,390,payload={'success':True,'count':0})
    run_case(browser,390,payload={'success':True,'count':1},expected='۱')
    for width in (320,360,390,412): run_case(browser,width,payload={'success':True,'count':12},expected='۱۲')
    run_case(browser,390,status=500,payload={'success':False,'count':None})
    run_case(browser,390,status=403,payload={'success':False,'count':None})
    run_case(browser,390,status=401,payload={'success':False,'count':None})
    run_case(browser,390,status=200,payload=None,raw='{broken json')
    run_case(browser,390,status=200,payload={'success':True,'count':None})
    browser.close()
print('Payroll reminder badge browser PASS: 0/1/12, 320/360/390/412 layout, failure silence, same Handoff target, and 90s cache reuse behavior.')
