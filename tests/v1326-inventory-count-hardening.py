#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
core=read('includes/inventory.php')
start=read('admin/inventory_count_start.php')
count=read('admin/inventory_count.php')
home=read('admin/inventory.php')
schema=read('database/schema.sql')

# One-open-count invariant is owned by the core, not only the start page.
assert "inventory_count_start_guard" in core
assert 'function inventory_open_count_session' in core and 'function inventory_open_count_sessions' not in core
assert "WHERE s.status='draft'" in core and 'ORDER BY s.id' in core and 'LIMIT 1' in core
assert 'یک شمارش دیگر هنوز باز است' in core and 'شمارش قدیمی هنوز باز هستند' not in core

# Full/category scope is stored on the session while count_lines freeze membership.
assert 'scope_type VARCHAR(20)' in schema and 'scope_category_key VARCHAR(64)' in schema
assert 'idx_inventory_count_scope' in schema
assert "string $scopeType = 'full'" in core
assert "$scopeType === 'category'" in core and "AND i.category=?" in core
assert "'scope_type'=>$scopeType" in core and "'scope_category_key'=>$scopeCategoryKey" in core
assert 'name="scope_type"' in start and 'name="scope_category_key"' in start
assert 'کل انبار' in start and 'یک دسته' in start
assert 'اقلام همان لحظه شروع ثابت می‌شوند' in start
assert 'inventory_open_count_session' in start and 'ادامه شمارش' in home
assert 'بازکردن' in start

# Counter and finalizer are separate roles. Finalize cannot accept/derive count values.
assert "$canCount =" in count and "$canFinalize =" in count
assert "if (!$canCount) deny_access_and_return($user);" in count
assert "if (!$canFinalize) deny_access_and_return($user);" in count
assert "UPDATE inventory_count_lines SET difference_base=? WHERE id=?" in core
assert 'SET actual_quantity=?,difference_base=?' not in core
assert 'قبل از نهایی‌سازی، موجودی اولیه را مرور کن' in core
assert "$isOpening && $action === 'review' && $actual === null" in count
assert 'این حساب فقط می‌تواند نتیجه ثبت‌شده را مرور و نهایی کند' in count

print('v1.32.14 inventory count hardening passed: one active count, frozen full/category scope, counter/finalizer separation, and non-mutating finalization.')
