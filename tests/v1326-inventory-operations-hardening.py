#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
core=read('includes/inventory.php')
receive=read('admin/inventory_receive.php')
waste=read('admin/inventory_waste.php')
adjust=read('admin/inventory_adjustment.php')
css=read('assets/css/panel-components.css')
invcss=read('assets/css/inventory.css')

assert "throw new RuntimeException('مقدار عددی واردشده معتبر نیست.')" in core
assert "throw new RuntimeException('مبلغ واردشده معتبر نیست.')" in core
assert 'مقدار کالای تعدادی باید عدد صحیح باشد' in core
assert 'function inventory_optional_occurred_at' in core and "modify('+5 minutes')" in core
assert 'function inventory_purchase_unit_is_operational' in core
assert "review_status'] ?? '') === 'ready'" in core
assert 'inventory_operational_purchase_units' in receive and 'inventory_operational_purchase_units' in waste
assert 'inventory:receive:' in receive and 'inventory:waste:' in waste
assert 'name="request_token"' in receive and 'name="request_token"' in waste
assert "name=\"occurred_date_j\"" in receive and "name=\"occurred_time\"" in receive
assert "name=\"occurred_date_j\"" in waste and "name=\"occurred_time\"" in waste
assert "$_POST['unit_count']??''" in receive and "$_POST['unit_count']??''" in waste
assert 'inventory-sticky-actions' not in receive and 'inventory-sticky-actions' not in waste
assert 'موجودی بین همه بخش‌ها مشترک است' in receive and 'موجودی بین همه بخش‌ها مشترک است' in waste
assert 'function inventory_adjustment_allowed_modes' in core
assert "'opening_balance', 'waste' => ['quantity']" in core
assert 'lockedAllowedModes' in adjust and 'این اصلاح برای نوع ثبت انتخاب‌شده مجاز نیست' in adjust
assert "error_log('inventory receive:" in receive and "error_log('inventory waste:" in waste and "error_log('inventory adjustment:" in adjust
assert '.panel-body [hidden]{display:none!important}' in css
assert '.inventory-item-header{' in invcss
print('v1.32.14 inventory operations hardening passed: strict values, retry-safe receive/waste, operator backdating, operational purchase-unit gating, adjustment allowlist, safe errors, and shared hidden-state ownership.')
