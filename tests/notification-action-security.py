#!/usr/bin/env python3
from pathlib import Path
import json
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
push=read('includes/push.php'); endpoint=read('api/push_action.php'); sw=read('service-worker.js'); fn=read('includes/functions.php'); schema=read('database/schema.sql')
checks=[]
def ok(c,m):
    checks.append((bool(c),m))
    if not c: print('FAIL:',m)
ok("'v'=>2" in push and "'did'=>" in push and "'subid'=>" in push and "'nonce'=>" in push, 'action token is v2 and delivery/subscription/nonce bound')
ok('push_action_claim_once' in push and 'INSERT INTO push_action_claims' in push, 'single-use claim owner exists')
ok('UNIQUE KEY uq_push_action_nonce' in schema, 'single-use nonce is schema enforced in canonical schema')
ok("ps.user_id=?" in push and "d.subscription_id=?" in push, 'claim verifies delivery belongs to the same user/subscription')
ok("'approve_order','title'=>'تأیید سفارش'" in push and "'open','title'=>'بررسی سفارش'" in push, 'clean pending orders expose explicit approve/review actions')
ok('push_pending_order_quick_approvable' in push and 'has_item_note' in push and 'customer_note' in push, 'direct approval is withheld when guest notes require review')
ok("cancel" not in push[push.index("elseif($eventType==='pending_order'"):push.index('return $notification', push.index("elseif($eventType==='pending_order'"))], 'notification does not expose one-tap reject')
ok("$pdo->beginTransaction();\n        if(!push_action_claim_once($claims))" in endpoint and "$pdo->beginTransaction();\n    if(!push_action_claim_once($claims))" in endpoint, 'single-use claim participates in domain transaction so transient failure can retry safely')
ok("in_array('orders_floor',user_capabilities($userId),true)" in endpoint, 'permission is rechecked at action time')
ok('lock_order_context($pdo,$orderId)' in endpoint and 'confirm_order_locked' in endpoint, 'notification order approval uses canonical lock/mutation owners')
ok("$oldStatus==='accounted'" in endpoint and "'idempotent'=>true" in endpoint, 'already-confirmed order resolves idempotently')
ok("['accept_call','approve_order'].includes(event.action)" in sw and 'SOKNA_PUSH_ACTION_RESULT' in sw and 'openNotificationTarget(resultTarget)' in sw, 'service worker completes supported actions in background, avoids duplicate success notification, and deep-links failures')
ok("['action'=>'accept_call','title'=>'رسیدگی شد']" in push and "UPDATE waiter_calls SET status='done',active_table_guard=NULL" in endpoint, 'waiter notification action closes the active call instead of leaving an accepted task open')
ok("$call['status']==='accepted'&&(int)$call['accepted_by_user_id']!==$userId" in endpoint, 'notification completion cannot close another staff member active call')
ok('function lock_order_context' in fn and 'table -> session -> order' in fn, 'canonical order lock owner is shared')
if any(not c for c,_ in checks): raise SystemExit(1)
print(f'notification action security PASS: {len(checks)} checks')
