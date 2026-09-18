#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];read=lambda p:(ROOT/p).read_text(encoding='utf-8')
idx=read('waiter/index.php');feed=read('waiter/api_feed.php');action=read('waiter/api_action.php');js=read('assets/js/waiter.js')
for token in ['آماده‌سازی','صف آماده‌سازی','staffActionQueue','سفارش‌های امروز','mode=preparation']: assert token in idx,token
assert 'staff-quick-order-dock' not in idx
for forbidden in ['کارهای جاری کافه','مسئولیت شیفت','waiterTables','staffTableSearch','data-task-filter="call"','data-task-filter="order"']: assert forbidden not in idx,forbidden
for token in ["$preparationMode=(string)($_GET['mode']??'')==='preparation'",'$ordersAllowed=!$preparationMode','order_preparation_claims','area_key']: assert token in feed,token
assert 'WAITER_STATUS_API' in js and 'claim_order_area' in js
assert "'claim_order_area'" in action and "'confirm_order'" not in action
for retired in ["'receive_order'","'ready_order'",'responsibility_active(']: assert retired not in action
assert '.staff-action-queue' in read('assets/css/staff-action-queue.css')
print('Preparation queue passed: kitchen/bar claims only, today history, no duplicate floor calls/orders and no ready/delivery chain.')
