from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="login-page"><button id="loginBtn" class="login-account-trigger">حساب ورود</button><button id="modalClose" class="icon-btn" type="button">×</button><div class="panel-shell"><main class="panel-main"><div class="panel-content" id="panelContent"><label for="period">بازه</label><select class="form-control" id="period" data-choice-mode="compact" data-choice-label="بازه"><option value="today">امروز</option><option value="custom">بازه دلخواه</option></select></div></main></div><div id="panelToast" class="hidden"></div></body></html>'''

def center(el):
    b=el.bounding_box(); return {'x':b['x']+b['width']/2,'y':b['y']+b['height']/2}

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,360,390,412,900):
        page=browser.new_page(viewport={'width':width,'height':820}, has_touch=True, is_mobile=width<700)
        page.set_content(HTML)
        for css in ('assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'):
            page.add_style_tag(path=str(ROOT/css))
        page.add_script_tag(path=str(ROOT/'assets/js/interaction-modality.js'))
        page.add_script_tag(path=str(ROOT/'assets/js/panel-choice.js'))
        # Native Android tap highlight is suppressed in both login and panel custom controls.
        for sel in ('#loginBtn','.panel-choice-trigger'):
            tap=page.locator(sel).evaluate("el=>getComputedStyle(el).webkitTapHighlightColor")
            assert tap in ('rgba(0, 0, 0, 0)','transparent'), (width,sel,tap)
        # Touch path: opening and selecting must not leave focus on the tapped trigger.
        trig=page.locator('.panel-choice-trigger')
        trig.tap(); page.wait_for_timeout(30)
        assert page.evaluate("document.documentElement.dataset.inputModality")=='pointer'
        assert page.evaluate("document.activeElement?.classList?.contains('panel-choice-trigger')") is False
        page.locator('.panel-choice-option').filter(has_text='بازه دلخواه').tap(); page.wait_for_timeout(30)
        assert page.input_value('#period')=='custom'
        assert page.evaluate("document.activeElement?.classList?.contains('panel-choice-trigger')") is False
        # Pointer modality must suppress visual focus ring if browser retains focus on a custom button.
        page.locator('#loginBtn').tap(); page.wait_for_timeout(10)
        style=page.locator('#loginBtn').evaluate("el=>({outline:getComputedStyle(el).outlineStyle,tap:getComputedStyle(el).webkitTapHighlightColor})")
        assert style['tap'] in ('rgba(0, 0, 0, 0)','transparent')
        page.locator('#modalClose').tap(); page.wait_for_timeout(10)
        close_style=page.locator('#modalClose').evaluate("el=>({outline:getComputedStyle(el).outlineStyle,width:getComputedStyle(el).outlineWidth})")
        assert close_style['outline']=='none' or close_style['width']=='0px', (width,close_style)
        # Keyboard path still has keyboard modality and retains/restores focus.
        page.locator('body').click(position={'x':1,'y':1})
        for _ in range(5):
            page.keyboard.press('Tab')
            if page.evaluate("document.activeElement?.classList?.contains('panel-choice-trigger')"):
                break
        assert page.evaluate("document.documentElement.dataset.inputModality")=='keyboard'
        assert page.evaluate("document.activeElement?.classList?.contains('panel-choice-trigger')")
        page.keyboard.press('Enter'); page.wait_for_timeout(20)
        # selected/first option receives focus for keyboard accessibility.
        assert page.evaluate("document.activeElement?.classList?.contains('panel-choice-option')")
        page.keyboard.press('Escape'); page.wait_for_timeout(20)
        assert page.evaluate("document.activeElement?.classList?.contains('panel-choice-trigger')")
        page.close()
    browser.close()
print('PASS v1.31.7 shared touch/focus browser contract')
