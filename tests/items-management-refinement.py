#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')

auth = read('includes/auth.php')
items = read('admin/items.php')
item_form = read('admin/item_form.php')
order = items
js = read('assets/js/items-management.js')
order_js = read('assets/js/reorder-list.js')
css = read('assets/css/items-management.css')
layout = read('includes/panel_layout.php')
sw = read('service-worker.js')

assert 'deny_access_and_return' in auth
assert "$name . ' به این بخش دسترسی ندارد.'" in auth
assert "flash('warning'" in auth and 'user_home_path($user)' in auth
assert "json_response(['success' => false, 'message' => $message], 403)" in auth
assert 'exit(\'شما اجازه دسترسی' not in auth

for needle in ['item-filter-bar','data-item-action="availability"','data-item-action="active"','item-editor-drawer','bulk_update','quick_update','panel_subnav(','محل آماده‌سازی','بدون تصویر']:
    assert needle in items, needle
assert 'کنترل موجودی امروز' not in items
assert 'name="sort_order"' not in item_form
assert "SELECT COALESCE(MAX(sort_order),0)+10" in item_form
assert 'ordered_ids' in order and 'UPDATE items SET sort_order=? WHERE category_id=? AND id=?' in order
assert 'draggable="true"' in order and 'data-reorder-up' in order and 'data-reorder-down' in order and 'view=arrange' in order
assert 'data-quick-edit-item' in items and 'SOKNA_ITEMS_ENDPOINT' in items
assert 'class="items-tools"' not in items and "'order'=>['items.php?view=arrange','چیدمان']" in items and 'ورود و خروجی' in items
assert 'CafeUI?.confirm' in js and "action:'bulk_update'" in js
assert 'dragstart' in order_js and 'data-reorder-id' in order_js
assert '.items-management-card' in css and '.item-editor-drawer' in css and '.item-order-list' not in css
assert "assets/css/items-management.css" in layout
assert './assets/css/items-management.css' in sw
assert './assets/js/items-management.js' in sw and './assets/js/reorder-list.js' in sw
print('Access-return and item-management structural checks passed.')
