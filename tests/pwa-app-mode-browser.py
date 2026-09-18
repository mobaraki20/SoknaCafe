#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css'])
shell=(ROOT/'assets/js/panel-shell.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><style>:root{{--primary:#365b4c;--line:#e5ddd3;--muted:#66716b}}{css}</style></head><body class="panel-body"><div class="panel-shell"><aside class="sidebar" id="sidebar"><div class="brand-block">سکنا</div><div class="side-nav-groups"><nav class="side-nav"><a href="#a">آیتم</a></nav></div><footer class="sidebar-user">مدیر</footer></aside><button class="sidebar-backdrop" id="sidebarBackdrop"></button><main class="panel-main"><header class="panel-topbar"><button class="mobile-menu" data-panel-nav-toggle aria-controls="sidebar">منو</button><div class="panel-page-heading"><div class="panel-page-copy"><span class="panel-eyebrow">عملیات</span><h1>خانه</h1></div></div><a class="quick-order-launch">سفارش</a></header><div class="panel-network-banner hidden" id="panelNetworkBanner"><strong>قطع</strong></div><nav class="app-bottom-nav" id="appBottomNav"><a class="is-active" href="#home"><span class="app-nav-icon">⌂</span><span>خانه</span></a><a href="#ops"><span class="app-nav-icon">◉</span><span>عملیات</span></a><a href="#quick"><span class="app-nav-icon">＋</span><span>سفارش</span></a><button type="button" data-panel-nav-toggle data-app-nav-more aria-controls="sidebar" aria-expanded="false"><span>•••</span><span>بیشتر</span></button></nav><div class="panel-content" id="panelContent"><div style="height:1400px">محتوا</div></div></main></div><div class="pwa-update-banner hidden" id="pwaUpdateBanner">آپدیت</div><script>{shell}</script></body></html>'''
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
  for width,height in ((320,720),(360,800),(390,844),(412,915)):
    normal=browser.new_page(viewport={'width':width,'height':height});normal.set_content(html)
    assert normal.locator('#appBottomNav').is_hidden(), ('normal browser nav must stay hidden',width)
    normal.close()
    page=browser.new_page(viewport={'width':width,'height':height});page.set_content(html)
    page.evaluate("document.documentElement.classList.add('pwa-standalone')")
    page.wait_for_timeout(20)
    nav=page.locator('#appBottomNav'); assert nav.is_visible(), width
    bb=nav.bounding_box(); assert bb and bb['x']>=-1 and bb['x']+bb['width']<=width+1,(width,bb)
    items=nav.locator('a,button'); assert items.count()==4
    for i in range(items.count()):
      ib=items.nth(i).bounding_box(); assert ib and ib['height']>=49,(width,i,ib)
    content=page.locator('#panelContent').bounding_box(); assert content and content['width']<=width+1
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'), width
    assert page.locator('.mobile-menu').is_hidden() and page.locator('.quick-order-launch').is_hidden()
    page.locator('[data-app-nav-more]').click()
    page.wait_for_function("Math.abs(document.getElementById('sidebar').getBoundingClientRect().right - innerWidth) < 1")
    assert 'open' in (page.locator('#sidebar').get_attribute('class') or '')
    assert page.locator('[data-app-nav-more]').get_attribute('aria-expanded')=='true'
    backdrop=page.locator('#sidebarBackdrop'); bdb=backdrop.bounding_box(); sdb=page.locator('#sidebar').bounding_box(); assert bdb and sdb
    # Click the actually visible backdrop strip, never the part physically covered by the RTL drawer.
    visible_strip=sdb['x']-bdb['x']
    assert visible_strip >= 20, (width,'drawer leaves no tappable backdrop',sdb,bdb)
    backdrop.click(position={'x':max(2,visible_strip/2),'y':min(80,bdb['height']-2)})
    page.wait_for_function("!document.getElementById('sidebar').classList.contains('open')")
    assert 'open' not in (page.locator('#sidebar').get_attribute('class') or '')
    page.close()
  desktop=browser.new_page(viewport={'width':1366,'height':900});desktop.set_content(html);desktop.evaluate("document.documentElement.classList.add('pwa-standalone')")
  assert desktop.locator('#appBottomNav').is_hidden(), 'installed desktop keeps desktop shell'
  browser.close()
print('PWA app-mode browser passed at 320/360/390/412 and desktop: no overflow, app nav only in installed mobile mode, touch targets and shared drawer interaction preserved.')
