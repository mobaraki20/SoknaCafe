#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; read=lambda p:(ROOT/p).read_text(encoding='utf-8')
defs=read('includes/functions.php');users=read('admin/users.php');auth=read('includes/auth.php');schema=read('database/schema.sql')
for cap in ['orders_floor','cashier_accounts','preparation','shift_supervision','inventory_purchase']:
    assert f"'{cap}'" in defs
assert 'team_responsibility_definitions()' in users and 'name="responsibilities[]"' in users
assert 'name="capabilities[]"' not in users
for retired in ['permissionTemplate','waiterAssignments','waiter_scope_mode','الگوی مسئولیت','legacy_capabilities_to_responsibilities','waiter_table_assignments','staff_order_acknowledgements']:
    assert retired not in schema+defs+users
assert 'حداقل یک مسئولیت' in users and "audit_log_write_strict($pdo, 'user.access_updated'" in users
assert 'SELECT id,username,display_name,role,active FROM users' in auth and "(int)$fresh['active'] !== 1" in auth
for path,guard in {'operator/api_status.php':"require_capability('orders_floor')",'staff/api_quick_order.php':"require_any_capability(['orders_floor','cashier_accounts'])",'operator/api_bill.php':"require_capability('cashier_accounts')",'operator/api_controls.php':"require_capability('shift_supervision')",'waiter/api_action.php':"require_any_capability(['orders_floor','preparation'])"}.items(): assert guard in read(path)
assert 'function confirm_order_locked' in defs and 'confirm_order_locked(' in read('operator/api_status.php')
assert 'operator/api_status.php' in read('staff/quick-order.php') and 'pending_action' not in read('staff/api_quick_order.php')
assert 'user_preparation_areas' in schema and 'order_preparation_claims' in schema
queue=read('waiter/api_action.php');feed=read('waiter/api_feed.php')
assert "'claim_order_area'" in queue and 'order_preparation_claims' in queue+feed
for retired in ["'receive_order'","'ready_order'",'order_preparation_state']:
    assert retired not in queue+feed+schema
compact=defs.replace(' ','').replace('\n',''); assert "'kitchen'=>'آشپزخانه'" in compact and "'bar'=>'بار'" in compact
print('Compact responsibilities passed: five human-facing responsibility bundles, persistent kitchen/bar scope, granular backend authorization, one canonical immediate confirmation path and one claim per preparation area.')
