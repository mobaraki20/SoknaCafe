#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
login=(root/'login.php').read_text(encoding='utf-8')
auth=(root/'includes/auth.php').read_text(encoding='utf-8')
assert "header('Cache-Control: no-store, max-age=0')" in login
assert 'auth_login_rate_status($userId)' in login
assert 'auth_login_rate_fail($userId)' in login
assert 'auth_login_rate_clear($userId)' in login
assert "نام انتخاب‌شده یا رمز عبور درست نیست" in login
assert "تلاش‌های ناموفق زیادی ثبت شده است" in login
assert "password" not in auth[auth.index('function auth_login_rate_path'):auth.index('function login(string')].lower(), 'rate state must not persist passwords'
assert "hash('sha256', 'account|'" in auth and "hash('sha256', 'ip|'" in auth
assert '$accountLimit = 8;' in auth and '$ipLimit = 30;' in auth and '$window = 10 * 60;' in auth
print('RC2 login security contract PASS: no-store login, generic errors, and bounded credential-free throttling are wired.')
