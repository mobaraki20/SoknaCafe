#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
center=read('includes/sokna_center.php')
layout=read('includes/panel_layout.php')
settings=read('admin/settings.php')
center_settings=read('admin/center_settings.php')
personnel=read('admin/personnel.php')
api=read('api/sokna_center_users.php')
return_page=read('center_return.php')
bootstrap=read('bootstrap.php')
schema=read('database/schema.sql')

assert "require_once __DIR__ . '/includes/sokna_center.php';" in bootstrap
assert 'تنظیم اتصال مرکز سکنا' in settings and 'center_settings.php' in settings
assert "'/admin/personnel.php', 'پرسنل و حقوق'" in layout and "'personnel'=>'مدیریت مجموعه'" in layout
assert 'require_login([\'admin\'])' in center_settings
assert 'require_login();' in personnel and "require_login(['admin'])" not in personnel
assert 'require_login();' in return_page and 'user_home_path()' in return_page


# Personnel launcher visibility comes from Center entitlement hint, not Cafe HR capabilities.
assert 'sokna_center_personnel_access_state' in layout
assert 'data-center-personnel-link' in layout and 'sokna_center_personnel_access.php' in layout
assert 'can_open_personnel' in center and 'sokna_center_personnel_entitlement_from_data' in center
assert 'sokna_center_refresh_personnel_access' in center
assert "sokna_center_test_connection($actorUserId, null, null, false)" in center
assert "user_has_capability('personnel'" not in layout and "user_has_capability('payroll'" not in layout
entitlement_api=read('api/sokna_center_personnel_access.php')
assert "REQUEST_METHOD'] ?? 'GET') !== 'POST'" in entitlement_api and 'csrf_valid' in entitlement_api
assert 'sokna_center_refresh_personnel_access' in entitlement_api

# Manager UI: only status, pairing key, pair/re-pair and test. No technical transport fields.
for token in ['وضعیت اتصال','کلید اتصال مرکز سکنا','اتصال و آزمایش','اتصال مجدد / تغییر کلید','آزمایش اتصال']:
    assert token in center_settings, token
for forbidden in ['آدرس مرکز سکنا','Core URL','Context','Origin','Return URL','Audience','Secret','TTL','Nonce','شناسه کلید','آخرین اتصال موفق','آخرین خطا']:
    assert forbidden not in center_settings, forbidden

# Explicit pair endpoint; probe/handoff remain strict and cannot be used as repair paths.
assert "'/auth/pair.php'" in center and "'pair', $actorUserId" in center
assert "'/auth/probe.php'" in center and "'probe', $actorUserId, 'CAFE', $baseUrl, $secret, 'strict'" in center
assert "'handoff', $id, $context, null, null, 'strict'" in center
assert "$purpose === 'pair' && $trustMode !== 'pair'" in center
assert 'Auto-enrollment' not in center

# Canonical Handoff claims and 60s TTL. Scope alias restrictions to the Handoff builder;
# the official read-only SOKNA-S2S contract intentionally uses issuer/audience/expires_at.
handoff_start=center.index('function sokna_center_build_token(')
handoff_end=center.index('function sokna_center_endpoint(', handoff_start)
handoff_builder=center[handoff_start:handoff_end]
for needle in ["'iss' => 'cafe'","'sub' => (string)$localUserId","'context' => 'CAFE'","'aud' => $baseUrl","'purpose' => $purpose","'iat' => $now","'exp' => $now + 60","'nonce' => bin2hex(random_bytes(24))","'origin' => sokna_center_request_origin()","'return_url' => sokna_center_return_url()"]:
    assert needle in handoff_builder, needle
for forbidden_claim in ["'issuer' => 'cafe'","'local_user_id' => (string)$localUserId","'audience' => $baseUrl","'issued_at' => $now","'expires_at' => $now + 60","'trust_mode' => $trustMode"]:
    assert forbidden_claim not in handoff_builder, forbidden_claim

# Official count-only Payroll Reminder read uses the existing pair secret and its own exact S2S contract.
payroll_api=read('api/sokna_center_payroll_reminder_count.php')
for needle in ["'issuer'=>'cafe'","'audience'=>'center'","'purpose'=>$purpose","'context'=>'CAFE'","'sub'=>(string)$localUserId","'timestamp'=>$now","'expires_at'=>$now + 60", "'SOKNA-S2S'", "'/api/s2s/payroll_reminders.php'", "'Authorization: Sokna-HMAC '"]:
    assert needle in center, needle
assert "'payroll_reminder_summary'" in center
assert 'sokna_center_payroll_reminder_count' in payroll_api
assert 'session_write_close()' in payroll_api
assert "['success'=>true,'count'=>null]" in payroll_api
for forbidden in ['employee_name','remaining_amount','salary_amount','advance_amount','payroll_items']:
    assert forbidden not in payroll_api, forbidden
for forbidden in ['can_view_salary','is_hr_admin','payroll_permission']:
    assert forbidden not in center and forbidden not in personnel

# POST handoff only; no token in URL/log and Center outage has the exact human message.
assert 'method="post"' in personnel.lower() and 'name="token"' in personnel
assert '?token=' not in personnel and '?token=' not in center
assert 'Referrer-Policy: no-referrer' in personnel and 'Cache-Control: no-store' in personnel
assert 'مرکز سکنا موقتاً در دسترس نیست.' in center

# Exact allowed integration audit events only in active Center owner/launcher.
for event in ['center_pair_success','center_pair_failed','center_probe_success','center_probe_failed','center_handoff_started','center_handoff_failed']:
    assert event in center+personnel, event
for old in ['center.connection_settings_updated','center.connection_paired','center.connection_test_failed','center.connection_test_succeeded','center.handoff_started','center.handoff_failed']:
    assert old not in center+personnel, old

# User directory: HMAC, timestamp, nonce/replay, fixed audience/purpose/context, pagination, allow-list SQL.
assert 'api/sokna_center_users.php'
for needle in ['Sokna-HMAC','SOKNA-S2S',"$issuer !== 'center'","$audience !== 'cafe'","$purpose !== 'user_directory'","$context !== 'CAFE'",'nonce_replay','pagination_mismatch']:
    assert needle in center, needle
assert 'SELECT id,display_name,role,active,updated_at FROM users' in api
assert 'password_hash' not in api and 'password' not in api.lower()
assert "'local_user_id'" in center and "'display_name'" in center and "'role'" in center and "'active'" in center and "'updated_at'" in center
for forbidden in ["'username' =>","'password_hash' =>","'session' =>","'csrf_token' =>"]:
    assert forbidden not in center, forbidden
assert "'pagination'" in api and 'per_page' in api and 'has_next' in api

# No HR schema/capability in Cafe DB.
low=schema.lower()
for forbidden in ['employees','salaries','payroll','employee_id','core_permissions']:
    assert forbidden not in low, forbidden
assert 'sokna_center' not in schema

# No native dialogs in active Center flow and no Center network dependency in bootstrap/login/order flows.
for source in [center_settings,personnel]:
    for native in ['window.alert(','window.confirm(','window.prompt(']: assert native not in source
assert 'sokna_center_test_connection(' not in bootstrap
for p in ['login.php','api/create_order.php','staff/api_quick_order.php','operator/api_settlements.php','print-agent/v4/api.php']:
    assert 'sokna_center_test_connection(' not in read(p), p

print('1.31.7 Sokna Center signature/secret-flow integration contracts passed.')
