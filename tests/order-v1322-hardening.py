#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

func=read('includes/functions.php')
guest=(read('api/guest_orders.php') + '\n' + read('includes/guest_order_manage_service.php'))
create=(read('api/create_order.php') + '\n' + read('includes/guest_order_service.php'))
menu=read('assets/js/menu.js')
bill=read('operator/api_bill.php')
op=read('assets/js/operator.js')
opmarkup=read('includes/operator_page.php')
status=read('operator/api_status.php')
qapi=read('staff/api_quick_order.php')
qservice=read('includes/staff_order_service.php')
qowner=qapi + '\n' + qservice
qjs=read('assets/js/staff-quick-order.js')
inv=read('includes/inventory.php')
invhome=read('admin/inventory.php')
waitfeed=read('waiter/api_feed.php')
waitaction=read('waiter/api_action.php')
publicstatus=read('api/order_status.php') + '\n' + read('includes/guest_order_status_service.php')
schema=read('database/schema.sql')
helptext=read('includes/help_topics.php')

# One catalog-validation owner across order entry points.
assert 'function order_catalog_items_locked' in func and 'function order_catalog_item_is_orderable' in func
for body in [create,qowner,bill,guest]:
    assert 'order_catalog_items_locked' in body

# Guest mutable order: one full replacement, safe retry/stale guard, reduction allowed under pause.
assert "status IN('pending_approval','new')" in create
assert "if ($action === 'append')" not in guest and "client_refresh_required" not in guest
assert "expected_signature" in guest and "order_changed" in guest
assert 'function guest_order_payload_matches_current' in guest and 'این تغییرات قبلاً ذخیره شده‌اند.' in guest
assert "close_table_session((int)$session['id'],null,'guest_cancelled')" in guest.replace(' ','')
assert "if(!$increase&&$old)" in guest.replace(' ','')
assert "order_acceptance_blocked_scope_for_station" in guest
assert "action: 'update'" in menu and "action: 'append'" not in menu
assert 'mergeDraftIntoMutableOrder' in menu and 'fullEditableOrderPayload' not in menu
assert "submittedMode = explicitOrder ? 'edit' : appendTarget ? 'append' : 'create'" in menu
assert "if (submittedMode === 'edit'" in menu and "submittedMode === 'append'" in menu

# Confirmed-item decrease separates physical preparation from financial correction.
assert 'prepared_before_adjustment' not in schema
assert "array_key_exists('prepared_removed_quantity',$data)" in bill and "array_key_exists('prepared',$data)" not in bill
assert "$unpreparedRemovedQuantity" in bill and "'restore_quantity'=>$unpreparedRemovedQuantity" in bill and "inventory_enqueue_order_event_tx($pdo,'quantity_adjusted'" in bill
assert 'مهمان‌نوازی' not in bill
assert 'از مقدار حذف‌شده، چند عدد آماده شده بود؟' in opmarkup
assert 'prepared_removed_quantity' in op and 'billItemRequiresPreparation' in op
# Increasing an old bill line is a new order at the menu price the cashier actually observed.
assert 'current_menu_price' in read('operator/api_orders.php')
assert 'billItemExpectedPrice' in opmarkup and 'expected_price:expectedPrice' in op
assert "(int)$current['price']!==$expectedPrice" in bill

# Settlement warns but never asks for an override or blocks on preparation corrections.
for path in ['operator/api_table_session.php','operator/api_subscribers.php','operator/api_accommodation.php']:
    body=read(path)
    assert 'accept_preparation_adjustment' not in body
    assert 'preparation_adjustment_pending' not in body
assert 'pending_preparation_adjustments' in read('operator/api_orders.php')
assert 'اصلاحیه آماده‌سازی هنوز باز است' in op
assert 'accept_preparation_adjustment' not in op

# Preparation survives early financial settlement.
assert "o.status IN('accounted','completed')" in waitfeed
assert "in_array((string)$order['status'],['accounted','completed'],true)" in waitaction

# Quick Order binds submit/retry to the exact table-session observed on screen.
assert '$expectedSessionId' in qowner and 'expected_session_id' in qowner
assert 'حساب این میز تغییر کرده است؛ صفحه را تازه کنید.' in qowner
assert 'order_catalog_items_locked' in qowner
assert 'expectedSessionId' in qjs and 'expected_session_id' in qjs
assert 'sokna.quick-order.uncertain.v2.' in qjs

# Generic status recovery is deliberately narrow; confirmed cancellation uses Bill Correction.
assert 'force_recovery' not in status
assert "close_table_session($sessionId, $userId, 'pending_rejected')" in status
assert "!in_array($status, ['accounted','cancelled'], true)" in status
assert "'accounted' => ['completed']" in func
assert "'cancelled' => []" in func

# Inventory outbox stays durable and FIFO per order; request paths only schedule bounded after-response materialization.
assert "p.order_id=e.order_id AND p.id<e.id AND p.status<>'done'" in inv
assert 'function inventory_process_order_events_for_order' in inv
for body in [qowner,bill,status]:
    assert 'inventory_register_after_response_order' in body
    assert 'inventory_process_order_events_for_order' not in body
assert 'inventory_process_pending_order_events(10)' in invhome
assert 'Worker انبار' not in invhome and 'صف همگام‌سازی' not in invhome and 'رویداد محلی' not in invhome
assert 'stale_pending' in inv and 'INTERVAL 2 MINUTE' in inv

# Public status must not call an unconfirmed `new` order accepted.
assert "in_array((string)$order['status'],['accounted','completed'],true)" in publicstatus.replace(' ','')

# Required preparation print intent is part of the order transaction; only secondary delivery/push remains best-effort.
assert 'function order_side_effect_best_effort_tx' in func
assert 'print_enqueue_prep_order($pdo' in func and "order_side_effect_best_effort_tx($pdo, 'print preparation order'" not in func
assert 'print_enqueue_prep_order($pdo' in qowner
assert 'print_enqueue_prep_' in bill
assert 'order_side_effect_best_effort_tx' in create and 'order_side_effect_best_effort_tx' in qowner and 'order_side_effect_best_effort_tx' in bill and 'order_side_effect_best_effort_tx' in status

# Help mirrors the approved small-cafe contract.
for needle in ['مصرف مواد تعریف‌شده در زمان تأیید سفارش', 'آماده شده بود یا نه', 'اصلاحیه باز آماده‌سازی تسویه را متوقف نمی‌کند']:
    assert needle in helptext, needle

print('PASS Sokna 1.32.2 ordering hardening: retry-safe guest/staff flows, prepared-aware correction, nonblocking settlement, preparation continuity, FIFO inventory outbox and narrow status transitions.')
