#!/usr/bin/env python3
from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

seed=json.loads(read('database/inventory_seed.json'))
assert seed.get('version')==1
items=seed.get('items',[])
assert len(items)==89, len(items)
assert sum(len(x.get('purchase_units',[])) for x in items)==64
by_name={x['name']:x for x in items}
assert by_name['سیب‌زمینی کاله']['base_unit']=='g'
assert {(u['name'],u['base_quantity']) for u in by_name['سیب‌زمینی کاله']['purchase_units']}=={('بسته ۲.۵ کیلویی',2500),('کارتن ۴ بسته‌ای',10000)}
assert all(u['conversion_mode']=='actual_quantity' and u['base_quantity'] is None for u in by_name['بستنی وانیلی کاله']['purchase_units'])
assert by_name['پپسی']['purchase_units'][0]['base_quantity']==24
assert by_name['شانی']['purchase_units'][0]['base_quantity']==24
assert by_name['آب معدنی کوچک']['review_status']=='needs_review' and by_name['آب معدنی کوچک']['purchase_units'][0]['base_quantity']==12
assert by_name['نان برگر تک‌نان']['purchase_units'][0]['base_quantity'] is None

schema=read('database/schema.sql')
for table in [
    'inventory_items','inventory_purchase_units','inventory_balances','inventory_movements',
    'inventory_recipe_versions','inventory_recipe_components','inventory_count_sessions',
    'inventory_count_lines','inventory_order_events'
]:
    needle=f'CREATE TABLE IF NOT EXISTS {table}'
    assert needle in schema, table
assert 'actual_total_cost BIGINT UNSIGNED NULL' in schema
assert 'uq_inventory_movement_idempotency' in schema and 'uq_inventory_order_event_idempotency' in schema

inv=read('includes/inventory.php')
assert 'function inventory_record_movement_locked' in inv
assert "if ($type !== 'cost_adjustment' && $quantity === 0)" in inv
assert 'inventory_order_recipe_snapshot' in inv and "'recipe_snapshot'" in inv
assert 'function inventory_module_runtime_ready_locked' in inv
assert "sokna_module_setting_state_locked($pdo, 'inventory')" in inv
assert 'if (!inventory_module_runtime_ready_locked($pdo)) return;' in inv
assert "['known','estimated','unknown']" in inv
assert "'partial'=>'قیمت ناقص'" in inv
assert 'Existing positive stock has unknown historical cost' in inv
assert 'function inventory_process_order_best_effort' not in inv
assert not re.search(r'\b(?:UPDATE|DELETE\s+FROM)\s+inventory_movements\b',inv,re.I), 'movements must stay immutable'

# Protected order paths only create the durable local event inside the order transaction.
# Protected order requests only register bounded after-response work after commit; no materializer belongs before the HTTP response.
quick_order_owner='\n'.join([read('staff/api_quick_order.php'),read('includes/staff_order_service.php')])
for path in ['includes/functions.php','staff/api_quick_order.php','includes/staff_order_service.php','operator/api_bill.php','operator/api_status.php']:
    text=read(path)
    assert 'inventory_process_pending_order_events' not in text, path
    assert 'inventory_process_order_event(' not in text, path
assert "inventory_enqueue_order_event_tx($pdo,'accounted'" in read('includes/functions.php')
assert re.search(r"inventory_enqueue_order_event_tx\s*\(\s*\$pdo\s*,\s*'accounted'", quick_order_owner)
assert "inventory_enqueue_order_event_tx($pdo,'quantity_adjusted'" in read('operator/api_bill.php')
# Rejecting a still-pending guest order has consumed nothing, so it must not create a compensating inventory event.
status_api=read('operator/api_status.php')
assert "inventory_enqueue_order_event_tx($pdo,'cancelled'" not in status_api
assert "inventory_enqueue_order_event_tx($pdo,'reaccounted'" not in status_api
assert 'inventory_register_after_response_order' in quick_order_owner
assert 'inventory_process_order_events_for_order' not in quick_order_owner
for path in ['operator/api_bill.php','operator/api_status.php']:
    body=read(path)
    assert 'inventory_register_after_response_order' in body, path
    assert 'inventory_process_order_events_for_order' not in body, path
assert 'inventory_' not in read('includes/settlement.php'), 'settlement must remain isolated from inventory'
assert 'inventory_' not in read('staff/quick-order.php'), 'protected Quick Order UI markup must remain inventory-free'
assert 'inventory' not in read('assets/css/quick-order.css').lower(), 'protected Quick Order CSS must remain inventory-free'

caps=read('includes/functions.php')
for cap in ['inventory_view','inventory_cost_view','inventory_operations','inventory_finalize','inventory_manage']:
    assert f"'{cap}'" in caps
users=read('admin/users.php')
assert '$capabilityLabels = capability_definitions();' in users
assert 'team_responsibility_definitions()' in users and 'name="responsibilities[]"' in users
assert 'name="capabilities[]"' not in users
layout=read('includes/panel_layout.php')
assert "'inventory' => ['انبار'" in layout
assert "'/admin/inventory.php'" in layout
assert 'user_has_inventory_access($user)' in layout
assert 'assets/css/inventory.css' in layout

auth=read('includes/auth.php')
assert 'user_has_inventory_access' in auth

home=read('admin/inventory.php')
assert '$inventoryReady && $canOps' in home
assert "!$inventoryInitialized && $canManage" in home
assert 'راه‌اندازی موجودی اولیه' in home
assert 'inventory_cost_status_labels' in home

for page in [
    'admin/inventory.php','admin/inventory_items.php','admin/inventory_item_form.php','admin/inventory_item.php',
    'admin/inventory_review.php','admin/inventory_receive.php','admin/inventory_waste.php',
    'admin/inventory_opening.php','admin/inventory_count_start.php','admin/inventory_count.php',
    'admin/inventory_adjustment.php','tools/inventory-worker.php'
]:
    assert (ROOT/page).is_file(), page
    text=read(page)
    assert 'window.alert' not in text and 'window.confirm' not in text and 'window.prompt' not in text, page

item_form=read('admin/inventory_item_form.php')
assert 'کپی به‌عنوان کالای جدید' in item_form
assert 'موجودی، میانگین هزینه، گردش و تاریخچه منتقل نمی‌شوند' in item_form
assert 'warning_threshold_major' in item_form and 'unit_major_quantity[]' in item_form
assert 'purchase_units_state' in item_form and 'data-unit-review' in item_form
assert 'نیازمند تکمیل' in item_form and "'label'=>'آماده'" in item_form

menu_form=read('admin/item_form.php')
assert 'مصرف از انبار' in menu_form
assert 'inventory_save_recipe_locked' in menu_form
assert 'data-choice-mode="browse"' in menu_form

opening=read('admin/inventory_opening.php')
assert 'inventory_initialized()' in opening and 'فقط یک‌بار' in opening
count=read('admin/inventory_count.php')
assert 'شمارش کور فعال است' in count
assert 'inventory_count_finalize_locked' in count
receive=read('admin/inventory_receive.php')
assert 'مبلغ کل خرید' in receive
waste=read('admin/inventory_waste.php')
for reason in ['خرابی','تاریخ‌گذشته','اشتباه آماده‌سازی','ریخت‌وپاش','سایر']:
    assert reason in waste
adjust=read('admin/inventory_adjustment.php')
for label in ['اصلاح مقدار','اصلاح قیمت','برگشت به تأمین‌کننده']:
    assert label in adjust

worker=read('tools/inventory-worker.php')
assert "PHP_SAPI !== 'cli'" in worker and 'inventory_process_pending_order_events' in worker
assert 'GET_LOCK' in worker and '--retry-failed' in worker

print('Inventory v1.32.0 contracts passed: 89-item seed, immutable ledger, cost/recipe snapshots, init gate, durable order outbox, protected-flow isolation, management/receiving/waste/count/correction UI and permissions.')
