#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
schema=read('database/schema.sql'); funcs=read('includes/functions.php'); guest=read('api/create_order.php'); quick=read('staff/api_quick_order.php') + '\n' + read('includes/staff_order_service.php'); bill=read('operator/api_bill.php'); mig=read('release/1.36.4-dev.22-order-business-number.sql'); modules=read('includes/modules.php')
assert 'business_order_number INT UNSIGNED NOT NULL' in schema
assert 'UNIQUE KEY uq_orders_business_number (business_date,business_order_number)' in schema
assert 'CREATE TABLE IF NOT EXISTS order_business_sequences' in schema
assert 'function order_allocate_business_number(PDO $pdo,string $businessDate): int' in funcs
assert "SELECT last_number FROM order_business_sequences WHERE business_date=? FOR UPDATE" in funcs
assert "if(!$pdo->inTransaction())" in funcs
assert "business_order_number FROM orders WHERE id=?" in funcs
assert "function order_display_code" in funcs and "$id = is_array($order) ? (int)($order['id'] ?? 0) : (int)$order;" in funcs
for name,src in [('guest',guest),('quick',quick),('bill',bill)]:
    assert 'order_allocate_business_number' in src,name
    assert 'business_order_number' in src,name
assert "'order_business_sequences'" in modules
for token in ['ADD COLUMN business_order_number','COUNT(b.id) business_order_number','uq_orders_business_number','CREATE TABLE order_business_sequences','MAX(business_order_number)']:
    assert token in mig,token
print('dev22 phase3 daily order number contract PASS')
