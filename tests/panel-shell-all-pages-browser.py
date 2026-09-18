#!/usr/bin/env python3
from pathlib import Path
import os
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
    'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css',
    'assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
shell=(ROOT/'assets/js/panel-shell.js').read_text(encoding='utf-8')
menus=(ROOT/'assets/js/panel-menus.js').read_text(encoding='utf-8')
sections=['help','waiter','invoices','subscribers','operator','marketing','push','items','maintenance','tables','analytics','accommodation_settings','tags','settings','events','messages','categories','financial_periods','users','dashboard','printing','accommodation','operations_report','inventory','inventory_report','activity_report']
all_sections=list(sections)
shard=os.getenv('SOKNA_PANEL_SHELL_SHARD','').strip()
if shard:
    try:
        index,total=(int(x) for x in shard.split('/',1))
        assert total>0 and 0<=index<total
    except Exception as exc:
        raise SystemExit('Invalid SOKNA_PANEL_SHELL_SHARD; expected zero-based index/total, e.g. 0/4') from exc
    sections=[section for i,section in enumerate(all_sections) if i % total == index]
links=''.join(f'<a href="#x{i}" {"aria-current=page" if i==2 else ""}>گزینه {i}</a>' for i in range(1,8))
content='''
<section class="panel-page-actions"><strong>صفحه آزمایشی</strong><button class="btn btn-light">عملیات</button></section>
<div class="settings-section-index"><div><strong>راهنما</strong><span>محتوای sticky</span></div></div>
<section class="card"><div class="card-body" style="min-height:360px"><div class="row-action-menu" data-action-menu><button id="rowAction" type="button" class="btn btn-light" data-action-menu-trigger aria-expanded="false">عملیات</button><div class="row-action-popover" data-action-menu-popover role="menu"><button role="menuitem">گزینه آزمایشی</button></div></div><p>محتوا</p></div></section>
'''

def make_html(section, include_footer=True):
    footer=f'<script>{menus}</script>' if include_footer else ''
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--accent:#b85c38;--line:#e5ddd3}}{css}</style></head><body class="panel-body panel-section-{section}"><div class="panel-shell"><aside class="sidebar" id="sidebar"><div class="brand-block">سکنا</div><div class="side-nav-groups"><section class="side-nav-group is-current" data-nav-group="nav-1"><button class="side-nav-group-toggle" type="button" aria-expanded="true"><span class="side-nav-group-title">عملیات</span></button><nav class="side-nav">{links}</nav></section></div><footer class="sidebar-user"><nav class="sidebar-user-actions"><a href="#help">راهنما</a><a href="#logout">خروج</a></nav></footer></aside><button class="sidebar-backdrop" id="sidebarBackdrop" type="button" tabindex="-1"></button><main class="panel-main"><header class="panel-topbar"><button class="icon-btn mobile-menu" id="panelNavToggle" type="button" data-panel-nav-toggle aria-controls="sidebar" aria-expanded="false">☰</button><div class="panel-page-heading"><div class="panel-page-copy"><span class="panel-eyebrow">بخش</span><h1>{section}</h1></div></div><div class="topbar-actions"><a class="btn btn-primary quick-order-launch" href="#quick">+</a><div class="topbar-tools" id="panelToolsMenu"><button class="icon-btn topbar-tools-toggle" id="panelToolsToggle" type="button" aria-haspopup="menu" aria-controls="panelToolsPopover" aria-expanded="false">⋯</button><div class="topbar-tools-menu hidden" id="panelToolsPopover" role="menu" aria-hidden="true"><a role="menuitem" class="topbar-tool" href="#guest">نمایش منوی مهمان</a></div></div></div></header><script>{shell}</script><div class="panel-content" id="panelContent">{content}</div></main></div>{footer}</body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    failures=[]
    # Reuse one page across the matrix. Creating 96 Chromium pages is slow and can
    # exhaust the Playwright pipe in constrained CI, while viewport/content reset
    # preserves the same shell assertions for every section × width combination.
    page=browser.new_page(viewport={'width':1366,'height':900})
    active_errors=[]
    page.on('pageerror',lambda exc: active_errors.append(str(exc)))
    for section in sections:
        for width,height in ((320,720),(390,844),(768,1024),(1366,900)):
            page.set_viewport_size({'width':width,'height':height})
            active_errors.clear()
            page.goto('about:blank')
            page.set_content(make_html(section))
            try:
                tools=page.locator('#panelToolsToggle')
                assert page.evaluate("document.documentElement.dataset.panelShellReady==='1'")
                # No page content may intercept the shared topbar controls.
                assert tools.evaluate("b=>{const r=b.getBoundingClientRect(),e=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return e===b||b.contains(e)}")
                tools.click(); page.wait_for_timeout(10)
                assert page.locator('#panelToolsPopover').is_visible(), (section,width,'tools')
                page.keyboard.press('Escape')
                assert page.locator('#panelToolsPopover').is_hidden()
                if width<=960:
                    nav=page.locator('#panelNavToggle')
                    assert nav.is_visible()
                    assert nav.evaluate("b=>{const r=b.getBoundingClientRect(),e=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return e===b||b.contains(e)}")
                    nav.click(); page.wait_for_timeout(10)
                    assert page.locator('#sidebar').get_attribute('aria-hidden')=='false', (section,width,'nav')
                    assert 'open' in (page.locator('#sidebar').get_attribute('class') or '')
                    page.keyboard.press('Escape')
                    assert 'open' not in (page.locator('#sidebar').get_attribute('class') or '')
                else:
                    assert not page.locator('#panelNavToggle').is_visible()
                page.locator('#rowAction').click(); page.wait_for_timeout(10)
                assert page.locator('[data-action-menu-popover]').is_visible(), (section,width,'row action')
                page.keyboard.press('Escape')
                assert not active_errors, (section,width,list(active_errors))
            except Exception as exc:
                failures.append((section,width,str(exc),list(active_errors)))
    # Critical shell must remain operational even if a page never reaches panel_footer.
    page.set_viewport_size({'width':390,'height':844})
    active_errors.clear()
    page.goto('about:blank')
    page.set_content(make_html('analytics', include_footer=False))
    page.locator('#panelNavToggle').click(); assert 'open' in (page.locator('#sidebar').get_attribute('class') or '')
    page.keyboard.press('Escape')
    page.locator('#panelToolsToggle').click(); assert page.locator('#panelToolsPopover').is_visible()
    page.close()
    browser.close()
    if failures:
        raise SystemExit('Panel shell failures: '+repr(failures[:10]))
print(f'Panel shell browser passed: {len(sections)} panel sections × 4 widths' + (f' [shard {shard}]' if shard else '') + ', overlay hit-testing, hamburger/tools/row menus, and shell remains interactive without footer scripts.')
