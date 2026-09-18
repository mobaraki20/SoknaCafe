#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css'])
js='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/js/panel-shell.js','assets/js/panel-core.js','assets/js/panel-menus.js'])
links=''.join(f'<a href="#p{i}" {"aria-current=page" if i==4 else ""}>گزینه {i}</a>' for i in range(1,19))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--accent:#b85c38;--line:#e5ddd3;--ui-border:#e5ddd3;--ui-shadow:0 14px 34px #0002}}{css}</style></head><body class="panel-body"><div class="panel-shell"><aside class="sidebar" id="sidebar"><div class="brand-block"><strong>سکنا</strong></div><div class="side-nav side-nav-groups"><section class="side-nav-group"><h2 class="side-nav-group-title">عملیات</h2><nav class="side-nav">{links}</nav></section></div><footer class="sidebar-user"><div class="sidebar-user-identity"><strong>مدیر کافه</strong></div><nav class="sidebar-user-actions"><a href="#help">راهنما</a><a href="#logout" id="logout">خروج</a></nav></footer></aside><button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="بستن منو" tabindex="-1"></button><main class="panel-main"><header class="panel-topbar"><button class="icon-btn mobile-menu" id="panelNavToggle" type="button" data-panel-nav-toggle aria-label="بازکردن منو" aria-controls="sidebar" aria-expanded="false">☰</button><div class="panel-page-heading"><h1>آیتم‌های منو</h1></div><div class="topbar-actions"><time class="panel-clock" id="panelClock" data-server-epoch="1785688200" data-timezone="Asia/Tehran"><strong class="panel-clock-time">۱۶:۵۰</strong><span class="panel-clock-date">۱۴۰۵/۰۵/۱۱</span></time><div class="topbar-tools" id="panelToolsMenu"><button class="icon-btn topbar-tools-toggle" id="panelToolsToggle" type="button" aria-label="ابزارهای بیشتر" aria-haspopup="menu" aria-controls="panelToolsPopover" aria-expanded="false">⋯</button><div class="topbar-tools-menu hidden" id="panelToolsPopover" role="menu" aria-hidden="true"><button class="topbar-tool" id="showGuestMenu" role="menuitem" type="button">نمایش منوی مهمان</button><button class="topbar-tool" id="installApp" role="menuitem" type="button">نصب وب‌اپ</button></div></div></div></header><div id="panelContent" style="height:2400px;padding:20px"><button id="outside">محتوا</button></div></main></div><div id="panelToast" class="hidden"></div><script>{js}</script></body></html>'''
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  desktop=browser.new_page(viewport={'width':1440,'height':900});desktop.set_content(html)
  assert not desktop.locator('#panelNavToggle').is_visible()
  assert desktop.locator('#sidebar').get_attribute('aria-hidden')=='false'
  desktop.locator('#panelToolsToggle').click(); desktop.wait_for_timeout(20)
  assert desktop.locator('#panelToolsPopover').is_visible() and desktop.locator('#panelToolsToggle').get_attribute('aria-expanded')=='true'
  assert desktop.evaluate("document.activeElement?.getAttribute('role')")=='menuitem'
  desktop.locator('#showGuestMenu').click(); desktop.wait_for_timeout(20)
  assert desktop.locator('#panelToolsPopover').is_hidden()
  desktop.locator('#panelToolsToggle').click(); desktop.keyboard.press('Escape')
  assert desktop.locator('#panelToolsPopover').is_hidden() and desktop.evaluate("document.activeElement===document.querySelector('#panelToolsToggle')")
  desktop.close()
  for width,height in ((768,1024),(390,844),(320,720)):
    page=browser.new_page(viewport={'width':width,'height':height}); page.set_content(html)
    assert page.locator('#panelNavToggle').is_visible(), width
    assert page.locator('#sidebar').get_attribute('aria-hidden')=='true'
    page.locator('#panelToolsToggle').click(); page.locator('#panelNavToggle').click(); page.wait_for_timeout(40)
    assert page.locator('#panelToolsPopover').is_hidden(), 'Main navigation must close secondary tools.'
    assert 'open' in (page.locator('#sidebar').get_attribute('class') or '')
    assert page.evaluate("document.body.classList.contains('panel-sidebar-open')")
    assert page.evaluate("getComputedStyle(document.body).overflow")=='hidden'
    sidebar=page.locator('#sidebar').bounding_box(); footer=page.locator('.sidebar-user').bounding_box(); nav=page.locator('.side-nav-groups').bounding_box()
    assert sidebar and footer and nav
    assert footer['y']+footer['height'] <= height+1, (width,footer,height)
    assert nav['y']+nav['height'] <= footer['y']+1, (nav,footer)
    before=page.evaluate('scrollY'); page.mouse.move(width-20,height//2); page.mouse.wheel(0,1000); page.wait_for_timeout(30)
    assert page.evaluate('scrollY')==before
    page.keyboard.press('Escape'); page.wait_for_timeout(20)
    assert 'open' not in (page.locator('#sidebar').get_attribute('class') or '')
    assert page.evaluate("document.activeElement===document.querySelector('#panelNavToggle')")
    page.locator('#panelNavToggle').click(); page.locator('#sidebarBackdrop').click(position={'x':3,'y':100}); page.wait_for_timeout(20)
    assert 'open' not in (page.locator('#sidebar').get_attribute('class') or '')
    if width <= 640:
      page.locator('#panelToolsToggle').click(); page.wait_for_timeout(20)
      tools=page.locator('#panelToolsPopover'); box=tools.bounding_box()
      assert tools.is_visible() and box and 'is-mobile-tools-sheet' in (tools.get_attribute('class') or '')
      x=box['x']+box['width']/2; y=box['y']+12
      for typ,cy in [('pointerdown',y),('pointermove',y+130),('pointerup',y+130)]:
        tools.dispatch_event(typ,{'pointerType':'touch','pointerId':91,'button':0,'clientX':x,'clientY':cy})
      page.wait_for_timeout(240)
      assert tools.is_hidden(), f'{width}: tools sheet swipe-down did not close'
    page.close()
  browser.close()
print('Panel navigation browser passed: fixed footer/logout, scroll-only link region, safe mobile drawer, single early shell controller, focus and close behavior.')
