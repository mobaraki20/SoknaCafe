#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
    'assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/responsive.css'
])
menus=(ROOT/'assets/js/panel-menus.js').read_text(encoding='utf-8')
choice=(ROOT/'assets/js/panel-choice.js').read_text(encoding='utf-8')
guest=(ROOT/'assets/css/guest-menu.css').read_text(encoding='utf-8')

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])

    # Context menu is a task sheet on mobile, but remains spatially anchored on desktop.
    html=f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main id="panelContent" class="panel-content" style="height:844px;overflow:auto"><div style="height:620px"></div><div class="row-action-menu" data-action-menu data-action-menu-label="عود یاسمن"><button class="btn btn-light" id="trigger" data-action-menu-trigger>مدیریت</button><div class="row-action-popover" data-action-menu-popover><a href="#">ویرایش</a><button>کپی</button><button>غیرفعال‌کردن</button></div></div><div style="height:300px"></div></main><script>{menus}</script></body></html>'''
    pg=b.new_page(viewport={'width':390,'height':844});pg.set_content(html)
    pg.click('#trigger');pg.wait_for_timeout(80)
    m=pg.locator('.row-action-popover').bounding_box(); assert m
    assert 368 <= m['width'] <= 372 and 8 <= m['x'] <= 12,(m,)
    assert m['y']+m['height'] <= 836,(m,)
    assert pg.locator('.row-action-popover').get_attribute('data-menu-placement') == 'sheet'
    assert pg.locator('.panel-action-menu-backdrop').is_visible()
    assert pg.locator('body').evaluate("e=>e.classList.contains('panel-action-menu-open')")
    pg.close()

    pg=b.new_page(viewport={'width':1366,'height':768});pg.set_content(html)
    pg.click('#trigger');pg.wait_for_timeout(80)
    r=pg.locator('#trigger').bounding_box(); m=pg.locator('.row-action-popover').bounding_box(); assert r and m
    assert m['width'] <= 282 and m['x'] >= 7 and m['x']+m['width'] <= 1359,(r,m)
    assert pg.locator('.row-action-popover').get_attribute('data-menu-placement') in ('above','below','above-clamped','below-clamped')
    assert pg.locator('.panel-action-menu-backdrop').is_hidden()
    pg.close()

    # Compact vs browse semantics and visual viewport resize behavior.
    options=''.join(f'<option value="{i}">کالای آزمایشی {i}</option>' for i in range(30))
    html=f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><label>واحد ورود<select id="compact" class="form-control" data-choice-mode="adaptive"><option>عدد</option><option>بسته ۱۲ عددی</option></select></label><label>کالا<select id="browse" class="form-control" data-choice-mode="browse" data-choice-search="true" data-choice-search-focus="true">{options}</select></label></main><script>{choice}</script></body></html>'''
    pg=b.new_page(viewport={'width':390,'height':844});pg.set_content(html)
    triggers=pg.locator('.panel-choice-trigger')
    triggers.nth(0).click();pg.wait_for_timeout(50)
    assert pg.locator('#panelChoiceLayer').evaluate("e=>e.classList.contains('mode-compact')")
    assert not pg.locator('.panel-choice-grip').is_visible()
    pg.locator('[data-panel-choice-close]').last.click();pg.wait_for_timeout(30)
    triggers.nth(1).click();pg.wait_for_timeout(80)
    layer=pg.locator('#panelChoiceLayer')
    assert layer.evaluate("e=>e.classList.contains('mode-browse') && e.classList.contains('has-search')")
    assert pg.locator('[data-panel-choice-search] input').is_visible()
    before=layer.evaluate("e=>parseFloat(getComputedStyle(e).getPropertyValue('--choice-vv-height'))")
    pg.set_viewport_size({'width':390,'height':520});pg.wait_for_timeout(100)
    after=layer.evaluate("e=>parseFloat(getComputedStyle(e).getPropertyValue('--choice-vv-height'))")
    sheet=pg.locator('.panel-choice-sheet').bounding_box()
    assert after < before and after <= 522,(before,after)
    assert sheet and sheet['y']+sheet['height'] <= 522,(after,sheet)
    pg.close()

    # Shared surface stack owns card separation.
    html=f'''<!doctype html><html lang="fa" dir="rtl"><style>{css}</style><body class="panel-body"><main class="panel-content"><div class="panel-surface-stack"><section class="card" id="c1" style="height:80px"></section><section class="card" id="c2" style="height:80px"></section></div></main></body></html>'''
    pg=b.new_page(viewport={'width':390,'height':844});pg.set_content(html)
    a=pg.locator('#c1').bounding_box();c=pg.locator('#c2').bounding_box();assert a and c
    assert c['y']-(a['y']+a['height']) >= 15,(a,c)
    pg.close()

    # Guest table-change actions must remain one line at smallest supported phone widths.
    for width in (320,360,390,412):
        html=f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{guest}</style><body class="guest-menu-page"><div id="tableChangeModal"><div class="success-box" style="width:min(560px,calc(100vw - 24px))"><div class="modal-actions"><button class="btn btn-primary">بله، همین میز</button><button class="btn btn-light">QR میز فعلی</button></div></div></div></body></html>'''
        pg=b.new_page(viewport={'width':width,'height':700});pg.set_content(html)
        vals=pg.locator('#tableChangeModal .modal-actions .btn').evaluate_all("els=>els.map(e=>({h:e.getBoundingClientRect().height,lh:parseFloat(getComputedStyle(e).lineHeight)||0,sw:e.scrollWidth,cw:e.clientWidth,ws:getComputedStyle(e).whiteSpace}))")
        assert all(v['sw']<=v['cw']+1 and v['ws']=='nowrap' for v in vals),(width,vals)
        pg.close()

    b.close()
print('1.32.4 design-system browser checks passed: mobile task sheets + desktop anchored menus, semantic choice overlays, visual-viewport resizing, card spacing, and one-line guest actions.')
