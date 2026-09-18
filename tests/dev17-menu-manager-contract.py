from pathlib import Path
root=Path(__file__).resolve().parents[1]
items=(root/'admin/items.php').read_text()
js=(root/'assets/js/items-management.js').read_text()
cat=(root/'admin/category_form.php').read_text()
menu=(root/'admin/menu_form.php').read_text()
mods=(root/'includes/modules.php').read_text()
assert "panel_header('مدیریت منو'" in items
for token in ['name="menu"','name="audience"','name="sort"','active_first','inactive_first','unavailable_first','bulkMenuTarget','menu_add','menu_remove']:
    assert token in items, token
assert 'نام، کد، دسته یا برچسب' in items
assert "document.querySelectorAll('#itemFilterForm select')" in js
assert 'Search is intentionally submitted by Enter/the explicit button' in js
assert 'compositionstart' not in js
assert "menuTarget" in js and "menu_add" in js and "menu_remove" in js
assert "audience" in cat and "menu_categories" in cat
assert "schedule_days" in menu and "daily_start" in menu and "status" in menu
assert "admin/menu_form.php" in mods and "'menus','menu_categories','menu_items'" in mods
print('PASS dev17 menu manager contract')
