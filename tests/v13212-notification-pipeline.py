#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text(encoding='utf-8')
def need(v,m):
    if not v:
        print('1.32.14 notification pipeline FAILED:',m)
        sys.exit(1)
push=t('includes/push.php'); api=t('waiter/api_push.php'); js=t('assets/js/device-notifications.js'); shell=t('includes/panel_layout.php')
need("['waiter_call','pending_order','order','diagnostic']" in push,'diagnostic event is not an outbox event')
need("if ($eventType === 'diagnostic')" in push and "target_user_id" in push,'diagnostic event is not explicitly targeted')
need("WHEN 'diagnostic' THEN 5" in push,'diagnostic queue priority missing')
need("push_enqueue_event('diagnostic'" in api,'shared test still bypasses operational outbox')
need("push_send_subscription" not in api,'device test still directly sends and can produce false-positive')
need("$action==='test_status'" in api and "push_event_deliveries" in api,'full pipeline test cannot verify worker/delivery result')
need("'last_error'=>$row['last_error']" not in api and "last.last_error" not in js,'device test must not expose raw worker/provider errors to staff')
need("worker_fresh" in api and "admin_live_operations" in api,'test API does not expose optional-worker/routing diagnostics')
need("post('test_status'" in js and "SoknaPushRuntime?.handleResponse" in js and "Worker صف اعلان اجرا نمی‌شود" not in js,'browser test does not exercise the real self-draining outbox path')
need("admin_live_operations === false" in js,'admin routing silence is not explained after a healthy pipeline test')
need('آزمایش مسیر کامل اعلان' in shell,'UI still labels direct/partial notification test ambiguously')
print('1.32.14 notification pipeline contracts PASS')
