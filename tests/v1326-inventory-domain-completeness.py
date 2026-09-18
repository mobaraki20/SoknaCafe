#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

schema=read('database/schema.sql')
core=read('includes/inventory.php')
home=read('admin/inventory.php')
items=read('admin/inventory_items.php')
item=read('admin/inventory_item.php')
adjust=read('admin/inventory_adjustment.php')
report=read('admin/inventory_report.php')

# Category is now business scope for periodic counts: prevent orphan master-data keys.
assert 'CONSTRAINT fk_inventory_items_category FOREIGN KEY (category) REFERENCES inventory_categories(category_key)' in schema

# Movement history is paginated and item detail links to the complete filtered ledger.
assert 'LIMIT 250' not in home
assert '$movementPerPage=50' in home
assert 'panel-pagination' in home
assert "name=\"item\"" in home or "name=\"item\"" in home
assert '۳۰ ثبت اخیر انبار' in item
assert 'inventory.php?tab=movements&item=' in item

# Backlog warning counts affected orders rather than presenting event counts as sales.
assert 'problem_order_count' in core
assert "'problem_orders'" in core
assert 'مصرف انبار برخی سفارش‌ها هنوز کامل ثبت نشده است' in home
assert 'خود سفارش‌ها محفوظ هستند' in home

# Persian search uses the shared normalizer; no page-local variant list remains.
assert 'normalize_persian_search($q)' in home
assert 'normalize_persian_search($q)' in items
assert '$variants=' not in items

# Exact supplier refund is carried as the negative cost of the return; omitted refund remains estimated.
assert "'total_cost_delta'=>$refund === null ? null : -$refund" in adjust
assert "'cost_status'=>$refund === null ? 'estimated' : 'known'" in adjust

# Report UI may stay compact, but Excel re-runs the unbounded applied-range queries.
assert '$consumptionSql=' in report and "$consumptionSql.' LIMIT 30'" in report
assert '$itemSql=' in report and "$itemSql.' LIMIT 20'" in report
assert '$countVarianceSql=' in report and "$countVarianceSql.' LIMIT 20'" in report
assert '$exportConsumptionRows' in report
assert '$exportItemRows' in report
assert '$exportVarianceRows' in report
assert "'name'=>'مغایرت شمارش'" in report
assert 'مغایرت شمارش دوره‌ای' in report
assert 'خرید خالص ثبت‌شده' in report
assert "movement_type='cost_adjustment'" in report
assert "movement_type='purchase_return'" in report

print('v1.32.14 inventory domain completeness contracts passed: category integrity, ledger pagination, distinct-order sync warning, corrected net purchases, count variance and complete Excel exports are wired.')
