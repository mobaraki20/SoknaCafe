#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
owner=read('includes/menu_catalog.php'); guest=read('menu/index.php'); quick=read('staff/api_quick_order.php'); funcs=read('includes/functions.php'); create=(read('api/create_order.php') + '\n' + read('includes/guest_order_service.php')); edit=(read('api/guest_orders.php') + '\n' + read('includes/guest_order_manage_service.php'))
assert 'function menu_catalog_snapshot' in owner
assert 'menu_items mi' in owner and 'menu_categories mc' in owner
assert "c.audience='guest_staff'" in owner and 'COALESCE(i.staff_only,0)=0' in owner
assert "m.status='active'" in owner and 'menu_catalog_schedule_sql' in owner
assert 'menu_catalog_snapshot(db(),' in guest
assert "FROM items i JOIN categories c" not in guest, 'guest page still owns a duplicate item/category query'
assert "menu_catalog_snapshot($pdo,'staff_order'" in quick
assert 'WHERE i.active=1 AND i.available=1 AND c.active=1' not in quick, 'quick-order still owns duplicate catalog visibility SQL'
assert 'menu_catalog_orderable_membership_sql' in funcs and 'menu_active' in funcs
assert "order_catalog_item_is_orderable($item, 'guest')" in create
assert "order_catalog_item_is_orderable($item, 'guest')" in edit
print('dev17 phase3 shared catalog contract PASS')
