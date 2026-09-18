#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
runtime=(ROOT/'includes/updater_engine/1.5.3/runtime.php').read_text(encoding='utf-8')
console=(ROOT/'includes/updater_engine/1.5.3/console.php').read_text(encoding='utf-8')
assert "SOKNA_UPDATER_RUNTIME_VERSION = '1.5.3'" in runtime
assert "SOKNA_UPDATER_ENGINE = 'sokna-updater-engine-1.5.3'" in runtime
# RC2 -> RC3 begins while 1.5.2 is executing; accepted fallback engines must remain byte-identical.
import hashlib
legacy_hashes = {
    'includes/updater_engine/1.5.1/runtime.php':'7155a53c181b4d2c8697f879a6e830f5dad5ce4e45fdbe07cdbd0b552319a577',
    'includes/updater_engine/1.5.1/console.php':'c14a13b15723e1380e00565e702f21bd4bed277bb4133b54a35c9f04ca1ea775',
    'includes/updater_engine/1.5.2/runtime.php':'b674de49dd84b30447e208b5cffc9879657aa678278d4f9f7c60bef9937aaf15',
    'includes/updater_engine/1.5.2/console.php':'80226214d3c84d7ae5542f9187f88dfe744e822920cb1b7f5e2a1339f7f5147a',
}
for rel, expected in legacy_hashes.items():
    actual = hashlib.sha256((ROOT/rel).read_bytes()).hexdigest()
    assert actual == expected, f'immutable updater fallback drifted: {rel}'
assert 'function updater_actor_snapshot' in runtime
assert 'function updater_history_write_ready' in runtime
assert 'function sru_history(array $entry): bool' in runtime
assert '[SOKNA_UPDATER_AUDIT]' in runtime and "result=write_failed" in runtime
assert "function updater_record_history(array $entry): void" in runtime and 'sru_history($entry);' in runtime
assert "function updater_discard_pending(string $id): void" in runtime and 'sru_discard_pending($id);' in runtime
for code in ['update_completed','rollback_completed','update_failed_before_apply','update_recovery_required','update_automatic_rollback','update_cancelled_before_apply']:
    assert code in runtime, code
for forbidden in ['امضای صحت','عملیات و بررسی سلامت کامل شده‌اند','account-choice-list','sur_admin_users()']:
    assert forbidden not in console, forbidden
for required in ['صحت فایل‌ها و SHA-256','بررسی سلامت پایه','ثبت تاریخچه در دسترس نیست','نام کاربری مدیر','بازگشت امن','data-rollback-open','برای تأیید بازگشت دیتابیس بنویسید: بازگشت','آخرین فعالیت‌ها','جزئیات فنی موتور','ساختار حیاتی دیتابیس','تغییر دیتابیس:']:
    assert required in console, required
assert '<link rel="stylesheet"' not in console, 'updater must remain asset-independent'
assert 'panel-layout.css' not in console
assert not re.search(r'\b(?:require|include)(?:_once)?\s*\(?[^;]*bootstrap\.php', console)
assert 'viewport-fit=cover' in console
assert "new Intl.DateTimeFormat('fa-IR-u-ca-persian'" in console
# Completed state is compact: progress belongs only to active-operation branch.
active=console.find("<?php if($operationActive): ?>")
completed=console.find("<?php elseif($publicStatus==='completed'):",active)
assert active!=-1 and completed!=-1
assert 'class="progress"' in console[active:completed]
completed_end=console.find("<?php elseif($publicStatus==='failed'):",completed)
assert 'class="progress"' not in console[completed:completed_end]
print('Current Updater Engine 1.5.3 contracts passed: immutable side-by-side engine, strict audit owner, truthful health/integrity copy, non-enumerating login and compact rollback/history UX.')
