#!/usr/bin/env python3
from pathlib import Path
import re
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; login picker browser check skipped.')
    raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'login.php').read_text(encoding='utf-8')
scripts=re.findall(r'<script>\s*(\(\(\) => \{.*?\}\)\(\);)\s*</script>',source,re.S)
assert scripts,'login picker script not found'
script=scripts[-1]
options=''.join(f'''<label class="login-account-option" data-user-name="کاربر {i}"><input type="radio" name="user_id" value="{i}"><span>کاربر {i}</span><i aria-hidden="true">✓</i></label>''' for i in range(1,21))
HTML=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="login-page sokna-login-v19"><main class="login-shell-v19"><section class="login-card"><div class="login-logo"><img alt="نشان سکنا"></div><form class="login-form" id="loginForm" novalidate><label for="loginAccountTrigger">نام و نام خانوادگی</label><button class="login-account-trigger" type="button" id="loginAccountTrigger" aria-controls="loginAccountModal" aria-expanded="false"><span>حساب ورود</span><strong id="loginAccountSelected">انتخاب حساب</strong><span>⌄</span></button><p class="inline-form-error hidden" id="loginAccountError">یک حساب را انتخاب کنید.</p><label for="loginPassword">رمز عبور</label><input class="form-control" id="loginPassword" type="password" name="password"><p class="inline-form-error hidden" id="loginPasswordError">رمز عبور را وارد کنید.</p><button class="btn btn-primary btn-block" type="submit">ورود</button><div class="login-account-modal hidden" id="loginAccountModal"><button class="login-account-backdrop" type="button" data-login-account-close></button><section class="login-account-sheet"><header class="login-account-head"><div><h2>انتخاب حساب</h2><small>نام خود را از فهرست انتخاب کنید.</small></div><button class="login-account-close" type="button" data-login-account-close>×</button></header><div class="login-account-list" role="radiogroup">{options}</div></section></div></form></section></main><script>{script}</script></body></html>'''
CSS=[ROOT/'assets/css/tokens.css',ROOT/'assets/css/app.css',ROOT/'assets/css/responsive.css',ROOT/'assets/css/panel.css']
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,900):
        page=browser.new_page(viewport={'width':width,'height':800});page.set_content(HTML)
        for css in CSS: page.add_style_tag(path=str(css))
        page.click('#loginAccountTrigger')
        assert page.locator('#loginAccountModal').is_visible()
        sheet=page.locator('.login-account-sheet').bounding_box(); assert sheet
        # Approved 1.30.7 UX: centered modal on mobile and desktop.
        assert sheet['y'] > 20 and sheet['y'] + sheet['height'] < 790, (width,sheet)
        assert page.locator('.login-account-list').evaluate('(el)=>el.scrollHeight>el.clientHeight'), '20-user list should scroll internally'
        row=page.locator('.login-account-option').nth(0); name=row.locator('span'); tick=row.locator('i'); rb=row.bounding_box(); nb=name.bounding_box(); tb=tick.bounding_box(); assert rb and nb and tb
        assert abs((nb['y']+nb['height']/2)-(tb['y']+tb['height']/2)) < 3, (width,nb,tb)
        assert rb['height'] <= 56, (width,rb)
        page.locator('input[name="user_id"][value="20"]').check(force=True)
        assert page.locator('#loginAccountSelected').inner_text()=='کاربر 20'
        assert not page.locator('#loginAccountModal').is_visible()
        page.fill('#loginPassword','secret')
        prevented=page.evaluate('''() => { const f=document.getElementById('loginForm'); let prevented=false; f.addEventListener('submit',e=>{prevented=e.defaultPrevented; e.preventDefault();},{once:true}); f.requestSubmit(); return prevented; }''')
        # Account remains selected; our handler should not expose inline validation.
        assert page.locator('#loginAccountError').get_attribute('class').find('hidden')>=0
        assert page.locator('#loginPasswordError').get_attribute('class').find('hidden')>=0
        assert not page.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth')
        page.close()
    browser.close()
print('Login picker browser passed at mobile/desktop widths: 20-user internal scroll, names-only selection, centered-modal mobile/desktop behavior, and no root overflow.')
