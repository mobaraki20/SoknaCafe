#!/usr/bin/env python3
from pathlib import Path
import json

ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
schema=read('database/schema.sql')
functions=read('includes/functions.php')
create=read('api/create_order.php')
guest_edit=((read('api/guest_orders.php') + '\n' + read('includes/guest_order_manage_service.php')) + '\n' + read('includes/guest_order_manage_service.php'))
quick=read('staff/api_quick_order.php')
printing=read('includes/printing.php')
push=read('includes/push.php')
inventory=read('includes/inventory.php')
index=((read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')) + '\n' + read('includes/guest_menu_view.php'))
catalog=read('includes/menu_catalog.php')
menu_js=read('assets/js/menu.js')
quick_js=read('assets/js/staff-quick-order.js')
policy=read('assets/js/fulfillment-policy.js')

# Canonical schema is explicit and default-safe.
assert "staff_only TINYINT(1) NOT NULL DEFAULT 0" in schema
assert "fulfillment_mode VARCHAR(16) NOT NULL DEFAULT 'dine_in'" in schema

# Domain owns fulfillment semantics; same item may exist once per mode.
assert "function normalize_fulfillment_mode" in functions
assert "$lineKey = $itemId . '|' . $fulfillmentMode" in functions
assert "$key=$itemId.'|'.$mode" in functions
assert "'fulfillment_mode'=>normalize_fulfillment_mode" in functions
assert "strcmp($a['fulfillment_mode'],$b['fulfillment_mode'])" in functions, 'Signatures must distinguish dine-in/takeaway siblings.'

# Guest never gets staff-only service inventory but staff catalog remains eligible.
assert "COALESCE(i.staff_only,0)=0" in catalog
assert "order_catalog_item_is_orderable($item, 'guest')" in create
assert "order_catalog_item_is_orderable($item, 'guest')" in guest_edit
assert 'staff_only' in functions and 'category_audience' in functions and 'order_catalog_items_locked' in functions

# Service-only station is a first-class non-preparation state and secondary pipelines skip it.
assert "'none' => 'بدون آماده‌سازی'" in functions
assert 'preparation_station_requires_work' in printing
assert 'preparation_station_requires_work' in push
assert 'preparation_station_requires_work' in inventory
assert 'if (!preparation_station_requires_work($station)) return false;' in functions
assert 'if (!preparation_station_requires_work($station)) return true;' in functions

# Persistence carries mode through guest/staff create/edit.
for src in (create,guest_edit,quick):
    assert 'fulfillment_mode' in src
assert "INSERT INTO order_items(order_id,item_id,item_name,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total)" in create
assert "INSERT INTO order_items(order_id,item_id,item_name,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total)" in guest_edit
assert "INSERT INTO order_items(order_id,item_id,item_name,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total)" in quick

# 1.35 fulfillment policy remains line-level but presentation is context-specific.
assert 'takeaway_quantity' in menu_js and 'orderPayloadLines' in menu_js
assert 'guestTakeawayDisclosure' in menu_js and 'takeawayDraft' in menu_js and 'guestTakeawayConfirm' in menu_js
assert 'takeaway_quantity' in quick_js and 'requestLines' in quick_js
assert 'quickOrderTakeawayTool' in quick_js or 'takeawayTool' in quick_js
assert 'SoknaFulfillment' in policy and 'setAll' in policy and 'ratioHtml' in policy
assert 'normalizedTakeaway' in policy and 'const takeaway=line=>normalizedTakeaway(line)' in policy
assert 'Number(clamp(line).takeaway_quantity' not in policy, 'Read-only fulfillment selectors must not mutate cart state.'
assert 'takeaway_allowed TINYINT(1) NOT NULL DEFAULT 1' in schema
assert 'order_catalog_item_allows_fulfillment' in functions
assert 'takeaway_not_allowed' in create and 'takeaway_allowed' in quick

print('Takeaway/service contract PASS: line-level fulfillment, explicit per-item eligibility, shared policy, Guest staged sheet, Staff inline mode, and server-side enforcement.')
