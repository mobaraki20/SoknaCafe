#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

schema=read('database/schema.sql')
for index_name in ('idx_table_sessions_business_day','idx_orders_business_day','idx_waiter_calls_business_day','idx_settlement_business_day'):
    assert index_name in schema
assert schema.count('business_date DATE NOT NULL') == 4
assert schema.count('business_shift_key VARCHAR(40) NOT NULL') == 4
assert schema.count('business_shift_label VARCHAR(80) NOT NULL') == 4
assert schema.count('business_cutoff_snapshot CHAR(5) NOT NULL') == 4
assert 'business_date IS NULL' not in read('includes/settlement.php')
assert 'business_date IS NULL' not in read('includes/panel_layout.php')

for path in ('api/create_order.php','staff/api_quick_order.php','operator/api_bill.php','api/waiter_call.php','includes/functions.php','includes/settlement.php'):
    text=read(path)
    assert 'business_date' in text and 'business_shift_key' in text and "['cutoff']" in text, path

for path in ('admin/index.php','waiter/api_feed.php','includes/invoices_page.php','admin/analytics.php','admin/operations_report.php'):
    text=read(path)
    assert 'business_date' in text, path

all_php='\n'.join(p.read_text(encoding='utf-8',errors='ignore') for p in ROOT.rglob('*.php'))
assert 'CURDATE()' not in all_php
assert 'DATE(settled_at)' not in all_php

ops=read('admin/operations_report.php')
reporting=read('includes/reporting.php')
assert 'format_jalali_date($from' in reporting
assert 'format_jalali_datetime($from' not in reporting
assert 'report_range_resolve' in ops and 'report_render_range_fields' in ops
assert "business_snapshot_filter_sql" in read('includes/business_time.php')
assert "sr.destination='accommodation'" in ops and 'settlement_business_date' in ops
assert "accommodation_attention_where_sql('at')" in ops

settings=read('admin/settings.php')
assert 'business_day_cutoff' in settings and 'business_shifts_json' in settings
assert 'روز عملیاتی' in settings and 'حداکثر سه شیفت' in settings

operator=read('assets/js/operator.js')
assert "['layout','oldest','newest']" in operator
assert "sokna.operator.tableSort" in operator
assert "state.tableSort==='oldest'" in operator
assert 'UPDATE cafe_tables SET sort_order' not in operator

print('1.30.6 business-time/static contract passed: immutable snapshots, operational-day reporting, shift settings, accommodation financial snapshot use and view-only table sort.')
