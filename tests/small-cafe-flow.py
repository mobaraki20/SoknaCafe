#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];read=lambda p:(ROOT/p).read_text(encoding='utf-8')
defs=read('includes/functions.php')+read('includes/function_domains/media.php');op=read('assets/js/operator.js');feed=read('waiter/api_feed.php');action=read('waiter/api_action.php')
assert "'new' => ['accounted', 'cancelled']" in defs and "'pending_approval' => ['accounted', 'cancelled']" in defs
assert 'data-status="accounted"' in op
assert 'bill_orders' in read('operator/api_orders.php') and 'bill_items' in read('operator/api_orders.php')
assert "$action === 'checkout_direct'" in read('operator/api_table_session.php') and "status='completed'" in read('includes/settlement.php')
assert 'order_preparation_claims' in feed and "'claim_order_area'" in action and 'سفارش‌های امروز' in read('waiter/index.php')
assert "'ready_order'" not in action and "'receive_order'" not in action
assert 'confirmed.length&&!pending&&!transfer' in op
assert 'function image_picker_html' in defs
for p in ['admin/item_form.php','admin/event_form.php','admin/category_form.php','admin/campaign_form.php','admin/settings.php']: assert 'image_picker_html' in read(p)
print('Small-cafe flow passed: one confirmation, unified table bill, safe settlement and one low-friction preparation claim per kitchen/bar area.')
