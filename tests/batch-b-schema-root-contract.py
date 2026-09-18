#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding="utf-8")
schema=read("database/schema.sql")
schema_health=read("includes/schema_health.php")
tables=read("admin/tables.php")
functions=read("includes/functions.php")
business=read("includes/business_time.php")
settlement=read("includes/settlement.php")
layout=read("includes/panel_layout.php")
operator=read("operator/api_orders.php")
invoices=read("includes/invoices_page.php")
inv=read("includes/inventory.php")
inv_home=read("admin/inventory.php")
inv_start=read("admin/inventory_count_start.php")
inv_open=read("admin/inventory_opening.php")
inv_count=read("admin/inventory_count.php")

assert "table_number SMALLINT UNSIGNED NOT NULL" in schema
assert "backfill_unambiguous_table_numbers" not in tables
assert "repair_legacy_numbers" not in tables
assert "table_number IS NULL" not in tables
assert "?missing=1" not in tables and "table-number-direct-action" not in tables
assert "function extract_table_number" not in functions
assert 'name="table_number" required' in tables

assert schema.count("business_date DATE NOT NULL") == 4
assert schema.count("business_shift_key VARCHAR(40) NOT NULL") == 4
assert schema.count("business_shift_label VARCHAR(80) NOT NULL") == 4
assert schema.count("business_cutoff_snapshot CHAR(5) NOT NULL") == 4
for src in (business,settlement,layout,operator,invoices):
    assert "business_date IS NULL" not in src
assert "Legacy sessions without a snapshot" not in business
assert "$legacyStart" not in settlement and "$legacyEnd" not in settlement
assert "WHERE sr.business_date=?" in settlement

assert "function inventory_open_count_session" in inv
assert "function inventory_open_count_sessions" not in inv
assert "inventory_count_start_guard" in inv
open_owner=inv[inv.index("function inventory_open_count_session"):inv.index("function inventory_open_count_message")]
assert "WHERE s.status='draft'" in open_owner and "LIMIT 1" in open_owner
assert "شمارش قدیمی هنوز باز هستند" not in inv
assert "چند شمارش" not in inv_home and "چند شمارش" not in inv_start
assert "inventory_open_count_session" in inv_home and "inventory_open_count_session" in inv_start and "inventory_open_count_session" in inv_open
assert "شمارش باز دیگر باقی مانده" not in inv_count
print("Batch B root contract PASS: mandatory table identity, strict operational snapshots, and singular inventory-count ownership are canonical.")

assert "cafe_table_number_not_null" in schema_health
assert "business_snapshot_" in schema_health and "_not_null" in schema_health
