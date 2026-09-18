#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

# P0-1: exactly one daily-work entry point.
need((ROOT/'operator/index.php').is_file(),'canonical operator route missing')
need(not (ROOT/'admin/operator.php').exists(),'duplicate admin operator route returned')
for p in ['staff/quick-order.php','includes/panel_layout.php','includes/modules.php']:
    need('admin/operator.php' not in read(p),f'legacy operator route reference remains in {p}')
need("'/operator/index.php'" in read('staff/quick-order.php'),'Quick Order no longer returns to canonical operator route')

# P0-2: redirect-only accommodation compatibility route is gone.
need(not (ROOT/'staff/accommodation_charge.php').exists(),'accommodation compatibility route returned')
need('staff/accommodation_charge.php' not in read('includes/modules.php'),'accommodation compatibility entry remains registered')

# P0-3: dead Sokna Center alias is gone; compact canonical signer remains.
center=read('includes/sokna_center.php')
need('function sokna_center_sign_payload' not in center,'dead Sokna Center signing alias returned')
need('function sokna_center_sign_compact' in center,'canonical Sokna Center signer missing')

# P0-4: supply test targets structure/domain semantics, not historical UI copy.
supply_test=read('tests/v1360-operations-purchase-permissions.py')
need('supplyNeedForm' in supply_test and 'supply_request_upsert_locked' in supply_test,'semantic Supply contract missing')
need("need('اعلام نیاز'" not in supply_test and "'ثبت نیازها' in need_page" not in supply_test,'historical Supply copy assertion returned')

# P0-5: one precise prepared-removal quantity contract; boolean shim/column are gone.
bill=read('operator/api_bill.php'); js=read('assets/js/operator.js'); schema=read('database/schema.sql')
need("array_key_exists('prepared_removed_quantity',$data)" in bill,'canonical prepared removal input missing')
need("array_key_exists('prepared',$data)" not in bill,'legacy boolean prepared payload returned')
need('payload.prepared_removed_quantity=prepared' in js,'operator client is not using canonical prepared removal quantity')
need('prepared_before_adjustment' not in schema and 'prepared_before_adjustment' not in bill,'redundant prepared-before column returned')
need('prepared_removed_quantity' in schema,'prepared removal audit quantity missing')

print('Sokna 1.36.3 Batch A root-cause cleanup contract PASS.')
