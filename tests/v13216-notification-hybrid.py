#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

push=read('includes/push.php')
functions=read('includes/functions.php')
kick=read('api/push_kick.php')
drain=read('api/push_drain.php')
runtime=read('assets/js/push-runtime.js')
panel=read('includes/panel_layout.php')
push_admin=read('admin/push_devices.php')

# Transactional outbox remains the source of truth.
assert 'push_enqueue_event_tx' in push and 'INSERT INTO push_event_queue' in push
assert 'push_register_kick_queue($queueId)' in push

# Immediate delivery happens only after the main response when FPM supports it.
assert "function_exists('fastcgi_finish_request')" in functions
assert 'fastcgi_finish_request();' in functions
assert 'push_after_response_drain();' in functions
assert 'session_write_close()' in functions

# Non-FPM fallback is a signed, queue-id scoped kick. It never accepts arbitrary event data.
assert "'purpose'=>'queue_kick'" in push and 'push_kick_token_decode' in push
assert 'push_process_queue(1, $queueId)' in kick
assert "(int)($claims['qid'] ?? 0) !== $queueId" in kick
assert 'push_send_subscription' not in kick

# Authenticated pages opportunistically retry a small amount of backlog without requiring Cron/Worker.
assert 'require_login();' in drain and 'csrf_valid' in drain and 'push_process_queue(2)' in drain
assert 'window.SOKNA_PUSH_DRAIN_URL' in panel and 'assets/js/push-runtime.js' in panel
assert 'setInterval(() => void drain(), 20000)' in runtime
assert 'keepalive: true' in runtime and "data?._push?.kick" in runtime

# Stale/actionability guard runs immediately before recipient resolution/delivery.
for token in [
    'function push_event_actionability',
    "if ($age > 300)",
    "if ($age > 900)",
    "if ($age > 1800)",
    "['pending_approval','new']",
    "['accounted','completed']",
    'preparation_items_signature($items)',
    'preparation_already_claimed',
    'push_expire_queue_event($pdo, $queueId',
    "'no_eligible_recipients'",
    "'no_active_subscriptions'",
]:
    assert token in push, token
assert push.index('push_event_actionability') < push.index('push_recipient_ids((string)$row')

# Queue worker is retained as an accelerator, not shown as a mandatory dependency.
assert 'ارسال خودکار' in push_admin and 'پردازش مستقیم برای مقیاس فعلی سکنا کافی است.' in push_admin
assert 'دریافت اعلان‌های سالن و آماده‌سازی برای مدیر' in push_admin
assert 'مدیر به‌طور پیش‌فرض اعلان‌های زنده سالن' not in push_admin

# Operational mutations still only enqueue inside their transactions; no provider call is coupled to business success.
for rel in ['api/create_order.php','api/waiter_call.php','staff/api_quick_order.php','operator/api_status.php','operator/api_bill.php']:
    text=read(rel)
    if rel=='api/create_order.php': text += '\n' + read('includes/guest_order_service.php')
    if rel=='api/waiter_call.php': text += '\n' + read('includes/waiter_call_service.php')
    if rel=='staff/api_quick_order.php': text += '\n' + read('includes/staff_order_service.php')
    assert ('push_enqueue_event_tx' in text or 'push_enqueue_confirmed_order_tx' in text), rel
    assert 'push_send_subscription' not in text, rel
    assert 'push_process_queue' not in text, rel

# In-app operational polling remains present as the non-Push source of truth.
assert 'operator/api_orders.php' in read('includes/operator_page.php')
assert 'waiter/api_feed.php' in read('waiter/index.php')

print('notification hybrid contract PASS: transactional outbox + after-response kick + opportunistic retry + optional worker + stale guard')
