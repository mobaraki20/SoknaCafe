#!/usr/bin/env python3
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
center=read('includes/sokna_center.php')
api=read('api/sokna_center_payroll_reminder_count.php')
layout=read('includes/panel_layout.php')
js=read('assets/js/panel-core.js')
css=read('assets/css/panel-layout.css')
registry=json.loads(read('tests/defect_class_registry.json'))
checks=[]
def ok(cond,msg):
    checks.append((bool(cond),msg))
    if not cond: print('FAIL:',msg)

ok("'/api/s2s/payroll_reminders.php'" in center, 'official Core count-only endpoint is used')
ok("'payroll_reminder_summary'" in center and "'SOKNA-S2S'" in center, 'official S2S purpose/type is used')
ok("'Authorization: Sokna-HMAC '" in center, 'existing HMAC connection authenticates the read')
ok('sokna_center_secret()' in center and 'sokna_center_build_s2s_read_token' in center, 'existing paired secret is reused; no parallel secret owner')
ok('session_write_close()' in api, 'Cafe session lock is released before Core network I/O')
ok("sokna_center_personnel_access_state" in api and "($user['role'] ?? '') !== 'admin'" in api, 'staff remains fail-closed on existing launcher entitlement')
ok("json_response(['success'=>true,'count'=>null])" in api, 'unavailable/unauthorized summary is fail-silent')
ok("data-payroll-reminder-count" in layout and "data-payroll-reminder-url" in layout, 'badge is inside the existing Personnel launcher')
ok("/admin/personnel.php" in layout, 'existing Handoff destination is unchanged')
ok('sessionStorage' in js and '90 * 1000' in js, 'browser cache is short-lived 90 seconds')
ok("state: 'unknown'" in js or "'unknown'" in js, 'failure is cached as unknown rather than fake zero')
ok('Number.isInteger(data.count)' in js and 'data.count >= 0' in js, 'only a non-negative integer count is accepted')
ok('hidePayrollReminderBadge' in js and 'CafeUI.toast' not in js[js.index('// Payroll reminder'):js.index('// Personnel visibility')], 'payroll badge failure never raises a toast')
ok('pointer-events:none' in css and 'white-space:nowrap' in css, 'badge/label do not create a separate touch target or wrap')
for forbidden in ['employee_name','salary_amount','remaining_amount','advance_amount','payroll_items']:
    ok(forbidden not in api and forbidden not in layout and forbidden not in js, f'Cafe payroll UI boundary excludes {forbidden}')
for forbidden in ['CREATE TABLE','ALTER TABLE','INSERT INTO payroll','UPDATE payroll']:
    ok(forbidden not in api, 'count feature has no Cafe payroll persistence')
ok(any(x.get('id')=='payroll_reminder_count_boundary' for x in registry.get('classes',[])), 'payroll count boundary defect class is registered')
if any(not c for c,_ in checks): raise SystemExit(1)
print(f'Payroll reminder badge contract PASS: {len(checks)} checks')
