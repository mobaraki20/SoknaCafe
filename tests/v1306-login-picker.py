#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
login=(ROOT/'login.php').read_text(encoding='utf-8')
css=(ROOT/'assets/css/panel.css').read_text(encoding='utf-8')
assert '<select class="form-control" name="user_id"' not in login, 'Native account select must be removed.'
assert 'SELECT id,display_name FROM users' in login and 'role' not in login.split('SELECT id,display_name FROM users',1)[1].split(')->fetchAll()',1)[0], 'Login account query must not fetch/display roles.'
assert 'role_label' not in login, 'Account type must not be shown on login.'
assert 'id="loginAccountModal"' in login and 'role="radiogroup"' in login, 'Internal accessible account picker is required.'
assert 'name="user_id"' in login and 'type="radio"' in login, 'Picker must submit native form radios without a JS-only account contract.'
assert 'جست' not in login, 'Approved account picker has no search UI.'
assert "favicon_url(192)" in login and 'alt="نشان سکنا"' in login, 'Login card must use the shared Sokna favicon owner.'
assert 'novalidate' in login and 'یک حساب را انتخاب کنید.' in login and 'رمز عبور را وارد کنید.' in login, 'Login validation must be inline Persian, not browser-native.'
assert "sokna.login.lastUserId" in login, 'Last selected account may be remembered locally.'
assert 'loginAccountGrip' not in login and 'touchstart' not in login, 'Centered login modal must not retain bottom-sheet swipe behavior.'
assert '<div class="login-account-backdrop"' in login, 'Backdrop must be non-focusable.'
assert '.login-account-list' in css and 'overflow-y:auto' in css, 'Picker must scale to about 20 users with internal scrolling.'
assert '.login-account-modal' in css and 'place-items:center' in css, 'Login account picker is a centered modal on mobile and desktop.'
assert ':focus-within' not in css[css.find('.login-account-option'):css.find('.login-account-option')+2500], 'Selected rows must not get a duplicate focus-within state.'
assert 'Asia/Tehran' in login, 'Approved technical timezone label must remain exact.'
print('Login picker 1.31.7 contract passed: centered names-only modal, no search/native select/swipe, nonfocus backdrop, remembered account and internal scroll.')
