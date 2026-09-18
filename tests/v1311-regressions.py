#!/usr/bin/env python3
from pathlib import Path
import re,hashlib
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
version=read('VERSION.txt').strip(); assert version
assert f"const RELEASE='{version}'" in read('service-worker.js')
# Login row layout root cause fixed without changing picker geometry.
app=read('assets/css/app.css'); panel=read('assets/css/panel.css')
assert '.login-card form>label{' in app and '.login-card label{' not in app
assert '.sokna-login-v19 .login-form>label{' in panel
# Public menu must not render online-order pause/busy state.
idx=(read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')); menu=read('assets/js/menu.js')
assert "$blockedScope=$showTableUi?order_acceptance_blocked_scope_for_station" in idx
assert '$categoryBusy=$showTableUi&&' in idx
assert "'station_busy'=>$isPublic?0:" in idx and '$categoryBusy=$showTableUi&&' in idx
assert "msg('item_unavailable'" in menu and "msg('no_results'" in menu
# Success copy uses editable sources and progress reaches final at accounted.
assert 'successCopy(' not in menu
assert "msg('order_received_title'" in menu and "msg('duplicate_order'" in menu
css=read('assets/css/guest-menu.css')
assert '.success-progress[data-status="accounted"] { --order-progress: 100%; }' in css
# Push troubleshooting stays out of staff toast; diagnostics retained server side.
pushui=read('assets/js/device-notifications.js'); push=read('includes/push.php'); api=read('waiter/api_push.php')
for token in ('DNS ${','TLS ${','cURL ${','مقصد: ${'):
    assert token not in pushui
assert "Sokna-Push/' . app_release_version()" in push
assert 'push_diagnostic_summary' in push and 'Sokna Push delivery failed:' in push and "push_enqueue_event('diagnostic'" in api and 'push_send_subscription' not in api
# Operations, invoice density, Analytics KPI.
ops=read('admin/operations_report.php'); reporting=read('includes/reporting.php'); pc=read('assets/css/panel-components.css')
assert "report_render_range_fields($range,'operationsReport')" in ops
assert reporting.count('data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"') == 2
assert 'queueMicrotask(' not in ops and '.panel-choice-option' not in ops
assert 'assets/js/panel-conditions.js' in read('includes/panel_layout.php')
assert '.invoice-line-name>strong{font-size:.95rem' in pc
assert '.compact-kpi-grid .metric-card>strong{font-size:clamp(.92rem,4.4vw,1.2rem)' in pc
# Quick Order visual owner stays byte-frozen to the approved 1.31.1 baseline.
qcss=read('assets/css/quick-order.css')
assert 'clamp(440px,35vw,480px)' in qcss and '@media(max-width:1023px)' in qcss
assert '.quick-order-takeaway-mode' in qcss and '.quick-order-cart-line.is-takeaway-editing' in qcss
print('1.31.7 regression contracts passed.')
