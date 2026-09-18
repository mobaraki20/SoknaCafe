#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
event=(ROOT/'admin/event_form.php').read_text(encoding='utf-8')
assert "preg_replace('/(?:\\s*[—-]\\s*نوبت جدید)+\\s*$/u'" in event
# Equivalent behavior contract for historical titles.
rx=re.compile(r'(?:\s*[—-]\s*نوبت جدید)+\s*$')
for source,expected in [
    ('دورهمی انگلیسی','دورهمی انگلیسی — نوبت جدید'),
    ('دورهمی انگلیسی — نوبت جدید','دورهمی انگلیسی — نوبت جدید'),
    ('دورهمی انگلیسی — نوبت جدید — نوبت جدید','دورهمی انگلیسی — نوبت جدید'),
    ('دورهمی انگلیسی - نوبت جدید','دورهمی انگلیسی — نوبت جدید'),
]:
    base=rx.sub('',source).strip() or source
    assert base+' — نوبت جدید'==expected,(source,base)
ops=(ROOT/'admin/operations_report.php').read_text(encoding='utf-8')
assert 'همه سفارش‌های اقامت با موفقیت منتقل شده‌اند.' not in ops
assert 'انتقال اقامت نیازمند رسیدگی در این بازه وجود دارد.' in ops
maintenance=(ROOT/'admin/maintenance.php').read_text(encoding='utf-8')
assert 'مرکز به‌روزرسانی و بازیابی' in maintenance
assert 'مرکز بازیابی مستقل' not in maintenance and 'بازکردن مرکز بازیابی' not in maintenance
index=(ROOT/'menu/index.php').read_text(encoding='utf-8')
assert 'مرکز به‌روزرسانی و بازیابی' in index
print('1.30.5 static contracts passed: event copy suffix, exception-only accommodation status, and update/recovery naming.')
