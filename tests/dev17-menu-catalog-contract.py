#!/usr/bin/env python3
from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
schema=read('database/schema.sql')
seed_php=read('includes/default_menu_seed.php')
cat_form=read('admin/category_form.php')
transfer=read('admin/menu_transfer.php')
bootstrap=read('bootstrap.php')
data=json.loads(read('database/default_menu.json'))

for token in ['CREATE TABLE IF NOT EXISTS menus','CREATE TABLE IF NOT EXISTS menu_categories','CREATE TABLE IF NOT EXISTS menu_items','category_key VARCHAR(80) NOT NULL','audience VARCHAR(20) NOT NULL']:
    assert token in schema, f'missing schema token: {token}'
assert 'UNIQUE KEY uq_categories_key (category_key)' in schema
assert 'UNIQUE KEY uq_menus_key (menu_key)' in schema
assert "WHERE category_key=?" in seed_php, 'seed must identify category by stable key, not display name'
assert "WHERE name=? LIMIT 1" not in seed_php, 'default seed must not own category identity by display name'
assert 'menu_catalog_new_category_key' in cat_form, 'category form must generate stable keys for newly-created categories'
assert 'category_key' in transfer and 'WHERE category_key=?' in transfer, 'menu transfer must import categories by stable category_key'
assert 'WHERE name=?' not in transfer, 'menu transfer must never use category display name as identity'
assert "includes/menu_catalog.php" in bootstrap
assert [m['menu_key'] for m in data['menus']]==['main','breakfast','lunch']
assert all('category_key' in c and 'audience' in c for c in data['categories'])
assert all('category_key' in i and 'menus' in i for i in data['items'])
assert sum(1 for i in data['items'] if i['staff_only'])==3
assert sum(1 for i in data['items'] if i['active'])==109
print('dev17 phase2 catalog contract PASS')
