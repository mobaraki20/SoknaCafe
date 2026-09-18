#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
items=read('admin/items.php'); transfer=read('admin/menu_transfer.php'); modules=read('includes/modules.php')
assert not (R/'admin/category_order.php').exists()
assert not (R/'admin/item_order.php').exists()
for p in ['admin/items.php','admin/categories.php','admin/item_form.php','admin/tags.php','admin/tag_form.php','admin/menu_transfer.php','includes/modules.php']:
    src=read(p)
    assert 'category_order.php' not in src and 'item_order.php' not in src, p
assert 'reorder_menu_categories' in items and 'reorder_category_items' in items
assert 'SELECT category_id FROM menu_categories WHERE menu_id=? ORDER BY sort_order,category_id FOR UPDATE' in items
assert 'SELECT id FROM items WHERE category_id=? ORDER BY sort_order,id FOR UPDATE' in items
assert "'category_key'" in transfer and "'menu_keys'" in transfer and "'category_menu_keys'" in transfer
assert 'WHERE category_key=?' in transfer and 'WHERE name=?' not in transfer
assert "DELETE FROM menu_items WHERE item_id=?" in transfer and "INSERT INTO menu_items(menu_id,item_id)" in transfer
assert 'stable-category-menu-keys-v1' in transfer
assert 'admin/category_order.php' not in modules and 'admin/item_order.php' not in modules
print('PASS dev17 phase7 cleanup: ordering has one owner, legacy pages are gone, and CSV transfer uses stable category/menu identities.')
