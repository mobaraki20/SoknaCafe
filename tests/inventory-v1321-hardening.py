#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

count=read('admin/inventory_count.php')
inv=read('includes/inventory.php')
schema=read('database/schema.sql')
assert "if (!$isOpening && $line['counted_at'] === null)" in count
assert 'inventory_balance_locked($pdo,(int)$line[\'inventory_item_id\'])' in count
assert 'system_quantity_snapshot=0,unit_cost_snapshot=NULL,actual_quantity=NULL' in count
assert 'هنوز شمارش نشده' in count
assert "$systemSnapshot = $sessionType === 'opening'" in inv
assert "$unitCostSnapshot = $sessionType === 'opening'" in inv

# Normal small-cafe operation self-processes only a bounded backlog from the inventory home;
# data-entry pages do not become workers and the CLI worker remains an optional accelerator.
assert 'inventory_process_pending_order_events(10)' in read('admin/inventory.php')
for path in ['admin/inventory_count_start.php','admin/inventory_opening.php','admin/inventory_receive.php','admin/inventory_waste.php']:
    assert 'inventory_process_pending_order_events' not in read(path), path
assert 'inventory_process_pending_order_events' in read('tools/inventory-worker.php')
assert 'function inventory_process_order_events_for_order' in inv

adjust=read('admin/inventory_adjustment.php')
for needle in ['effective_quantity','remaining_returnable','previous_effective_quantity_base','remaining_returnable_before']:
    assert needle in adjust, needle
assert '$delta=$target-$currentEffectiveQuantity;' in adjust
assert 'if ($amount > $remainingReturnable)' in adjust
assert 'if ((string)$movement[\'movement_type\']===\'purchase_receive\' && $target < $returnedQuantity)' in adjust

assert 'inventory_rebuild_projection_locked($pdo,$itemId)' in inv
assert "in_array($type, ['quantity_correction','cost_adjustment'], true)" in inv

home=read('admin/inventory.php')
assert '$lowStock + $negativeStock' in home
assert '$lowStock + $reviewCount' not in home
assert 'نیاز به تأیید' in home

menu=read('admin/item_form.php')
receive=read('admin/inventory_receive.php')
waste=read('admin/inventory_waste.php')
for text in [menu,receive,waste]:
    assert 'data-choice-mode="browse"' in text and 'data-choice-search="true"' in text
assert 'data-choice-placeholder="true"' in menu
choice=read('assets/js/panel-choice.js')
assert 'isPlaceholderOption' in choice and 'data-panel-choice-search' in choice and 'applySearchFilter' in choice

items=read('admin/inventory_items.php')
assert 'panel-list-card inventory-management-list' in items
assert 'panel-list-row inventory-management-row' in items
assert 'بازگشت به انبار' in items and 'inventory-management-filters' in items

assert 'system_quantity_snapshot BIGINT NOT NULL DEFAULT 0' in schema

help_text=read('includes/help_topics.php')
assert 'دستکش، لیوان شیک و نی شیک' in help_text
assert 'بازکردن صفحه به‌تنهایی هیچ داده‌ای را تغییر نمی‌دهد' in help_text

print('Inventory 1.32.1 hardening contracts passed: count race fix, correction/return guards, bounded local outbox processing, cautious costing, searchable choices, shared list UI, consumables guidance, against canonical schema.')
