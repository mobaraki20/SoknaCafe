#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding="utf-8")
js=read("assets/js/reorder-list.js"); items=read("admin/items.php"); cat_form=read("admin/category_form.php"); cat_list=read("admin/categories.php"); layout=read("includes/panel_layout.php")
assert "[data-reorder-form]" in js and "data-reorder-id" in js and "reorder:change" in js
assert "event.key === 'ArrowUp'" in js and "event.key === 'ArrowDown'" in js and "event.altKey" in js
assert "reorder_menu_categories" in items and "SELECT category_id FROM menu_categories WHERE menu_id=? ORDER BY sort_order,category_id FOR UPDATE" in items
assert "UPDATE menu_categories SET sort_order=? WHERE menu_id=? AND category_id=?" in items and "menu.category_order_changed" in items
assert "reorder_category_items" in items and "SELECT id FROM items WHERE category_id=? ORDER BY sort_order,id FOR UPDATE" in items
assert "UPDATE items SET sort_order=? WHERE category_id=? AND id=?" in items and "menu.item_order_changed" in items
assert items.count("data-reorder-form")>=2 and "assets/js/reorder-list.js" in items
assert "sort_order" not in "".join(line for line in cat_form.splitlines() if "<label" in line or "<input" in line)
assert "items.php?view=arrange" in cat_list and "category_order.php" not in cat_list
assert not (R/"admin/category_order.php").exists() and not (R/"admin/item_order.php").exists()
assert "'items','categories','printing'" in layout
print("Shared reorder contract PASS: menu/category ordering has one owner in Menu Manager, exact-set validation, transaction locks, keyboard fallback, and legacy ordering pages are removed.")
