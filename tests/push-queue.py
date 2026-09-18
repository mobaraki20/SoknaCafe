#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
push=read('includes/push.php'); worker=read('tools/push-worker.php'); schema=read('database/schema.sql')
for token in ['function push_enqueue_event_tx','INSERT INTO push_event_queue','function push_process_queue','push_event_deliveries','status=\'processing\'',"$deliveryState = $ok ? 'sent'"]:
    assert token in push, token
assert 'register_shutdown_function' not in push, 'Push transport must never be scheduled from user-request shutdown.'
assert 'fastcgi_finish_request' not in push, 'Push architecture must not depend on FPM response flushing.'
assert (ROOT/'tools/push-worker.php').is_file()
assert not (ROOT/'tools/process-push-queue.php').exists()
assert not (ROOT/'staff/api_push_worker.php').exists()
assert 'PHP_SAPI !== \'cli\'' in worker and 'GET_LOCK' in worker and 'push_process_queue' in worker
assert 'push_event_deliveries' in schema and 'event_key' in schema
for rel in ['api/create_order.php','api/waiter_call.php','staff/api_quick_order.php','operator/api_status.php','operator/api_bill.php']:
    text=read(rel)
    if rel=='api/create_order.php': text += '\n' + read('includes/guest_order_service.php')
    if rel=='api/waiter_call.php': text += '\n' + read('includes/waiter_call_service.php')
    assert ('push_enqueue_event_tx' in text or 'push_enqueue_confirmed_order_tx' in text), rel
    assert 'push_process_queue' not in text and 'push_send_one' not in text, rel
# Canonical confirmed-order helper must itself enqueue into the same transactional outbox.
assert 'function push_enqueue_confirmed_order_tx' in push and "push_enqueue_event_tx($pdo,'order'" in push
# Device test must use the same transactional outbox + worker path as operational events; raw transport diagnostics stay server-side/admin-side.
device=read('assets/js/device-notifications.js'); test_api=read('waiter/api_push.php')
assert 'DNS ${' not in device and 'TLS ${' not in device and 'cURL ${' not in device
assert "action==='test'" in test_api and "push_enqueue_event('diagnostic'" in test_api and 'push_send_subscription' not in test_api and 'push_diagnostic_summary' in push
print('RC6 Push architecture passed: transactional outbox, one CLI worker, delivery ledger/retry, and no network transport in order requests.')
