#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
app=read('assets/css/app.css')
pc=read('assets/css/panel-components.css')
menus=read('assets/js/panel-menus.js')
choice=read('assets/js/panel-choice.js')
recv=read('admin/inventory_receive.php')
waste=read('admin/inventory_waste.php')
item=read('admin/item_form.php')
iitem=read('admin/inventory_item_form.php')
invcss=read('assets/css/inventory.css')
itemcss=read('assets/css/items-management.css')
layout=read('includes/panel_layout.php')
qr=read('admin/qr.php')
count=read('admin/inventory_count.php')
schema=read('database/schema.sql')
ops=read('admin/operations_report.php')
analytics=read('admin/analytics.php')
funcs=read('includes/functions.php')
index=read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')

# Context menus: shared panel owner; desktop stays anchored while mobile uses the standard task sheet.
assert '.row-action-popover' not in app, 'legacy app.css still owns panel contextual menu'
assert '.row-action-popover.is-viewport-popover' in pc
assert '.row-action-popover.is-mobile-action-sheet' in pc
assert 'visualViewport' in menus and 'data-menu-placement' in menus and 'is-mobile-action-sheet' in menus
assert 'panel-action-menu-backdrop' in menus
assert '.item-admin-actions .row-action-popover{inset-inline-end:0}' not in itemcss, 'page CSS must not position the shared contextual popover'

# Choice semantics and Android visual viewport handling.
assert 'data-choice-mode="adaptive" data-inventory-purchase-unit' in recv
assert 'data-choice-mode="adaptive" data-inventory-purchase-unit' in waste
assert '--choice-vv-height' in choice and 'window.visualViewport' in choice
assert 'SoknaActionMenu?.close' in choice
assert 'normalizeSearch' in choice and ".replace(/[يى]/g, 'ی')" in choice

# Recipe styles live with item management, not inventory page CSS.
assert 'recipe-component-row' in itemcss
assert 'recipe-component-row' not in invcss
assert 'form-section-header' in pc and 'form-section-header' in item

# Shared surfaces and reports.
for src in (ops, analytics, read('admin/inventory_report.php'), read('admin/activity_report.php')):
    assert 'panel-surface-stack' in src
assert "'inventory_report' =>" in layout and "'activity_report' =>" in layout
assert 'انبار و سود' in layout and 'فعالیت کاربران' in layout
assert 'human_duration_seconds' in funcs and 'human_duration_seconds' in ops and 'human_duration_seconds' in analytics

# QR list must be a compact list, not table-to-card conversion.
assert 'qr-table-list' in qr and 'mobile-card-table' not in qr.split('<?php elseif($single)')[0]

# Inventory master-data and count cancellation are explicit and non-destructive.
assert 'CREATE TABLE IF NOT EXISTS inventory_categories' in schema
assert 'category VARCHAR(64)' in schema
assert "inventory_count_cancel_locked" in count
assert 'هیچ تغییری روی موجودی اعمال نشد' in count
assert 'data-choice-mode="adaptive"' in iitem and 'واحد ثبت موجودی' in iitem

# Guest table-change actions stay concise.
assert 'بله، همین میز' in index and 'QR میز فعلی' in index
assert 'نه، QR میز فعلی رو می‌زنم' not in index

print('1.32.4 design-system contracts passed: shared owners, overlay semantics, reports, inventory master data, QR list, and concise guest actions are locked.')
