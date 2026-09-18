#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
fun=read('includes/functions.php') + read('includes/function_domains/messages.php'); push=read('api/create_order.php'); dash=read('admin/index.php'); prep=read('includes/printing.php'); menu=read('assets/js/menu.js')
assert "return 'سفارش ' . fa_digits((string)order_display_number($order));" in fun
assert "customer_message('staff_pending_order_title'" in push and "customer_message('staff_pending_order_body'" in push
assert "'default'=>'سفارش جدید · {table}'" in fun and "'default'=>'{order} منتظر تأیید است.'" in fun
assert 'dashboard-recent-row' in dash and '$isException' in dash
assert "'سفارش ' . fa_digits((string)order_display_number($order))" in prep
assert "msg('fulfillment_takeaway','بیرون‌بر')" in menu and 'بیرون‌بر هم دارید؟' in read('menu/index.php')
print('1.32.20 human order reference and exception-first UI contract PASS')
