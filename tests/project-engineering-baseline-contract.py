#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
policy = (ROOT / 'DEVELOPER_READ_FIRST_FA.md').read_text(encoding='utf-8')
readme = (ROOT / 'README_FA.md').read_text(encoding='utf-8')
decisions = (ROOT / 'docs' / 'DECISIONS_FA.md').read_text(encoding='utf-8')

checks = {
    'root policy exists and is prominent': 'قبل از هر تغییر در Sokna این فایل را بخوانید' in policy,
    'pre-operational lifecycle locked': 'PRE-OPERATIONAL' in policy and 'سامانه عملیاتی شده است' in policy,
    'legacy cleanup explicitly allowed': 'می‌تواند حذف، ادغام یا بازطراحی شود' in policy,
    'scale tables locked': '50 میز' in policy,
    'scale concurrent guests locked': '100 تا 110 مهمان هم‌زمان' in policy,
    'seasonality locked': '3 ماه در سال' in policy,
    'small team locked': '8 تا 9 نفر' in policy,
    'owner-only change control': 'فقط با **اعلام صریح Owner**' in policy,
    'README points to policy': 'DEVELOPER_READ_FIRST_FA.md' in readme,
    'active decisions mirror lifecycle': 'Pre-Operational' in decisions,
    'active decisions mirror scale': '100–110 مهمان هم‌زمان' in decisions and '8–9 نفر' in decisions,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if failed:
    raise SystemExit('Engineering baseline contract failed: ' + ', '.join(failed))
print('PASS: project engineering baseline contract')
