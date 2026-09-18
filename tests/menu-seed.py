#!/usr/bin/env python3
import json
from pathlib import Path
from PIL import Image

root = Path(__file__).resolve().parents[1]
data = json.loads((root / 'database/default_menu.json').read_text(encoding='utf-8'))

assert [m['menu_key'] for m in data['menus']] == ['main','breakfast','lunch']
assert data['menus'][0]['status'] == 'active'
assert data['menus'][1]['status'] == data['menus'][2]['status'] == 'draft'
assert not any(m['menu_key'] == 'dinner' for m in data['menus'])

assert len(data['categories']) == 15, 'Approved taxonomy has 15 canonical categories'
assert len(data['items']) == 128, 'Uploaded catalog has 128 records'
assert sum(int(row.get('active',1)) for row in data['items']) == 109, 'Uploaded catalog has 109 active records'
assert sum(1-int(row.get('active',1)) for row in data['items']) == 19, 'Uploaded catalog has 19 inactive records'

keys=[row['category_key'] for row in data['categories']]
names=[row['name'] for row in data['categories']]
assert len(keys)==len(set(keys)) and len(names)==len(set(names)), 'Category identity/name must be unique in seed'
assert {'قهوه','چای و دمنوش','شربت‌های سنتی','نوشیدنی‌های سرد','شیک و اسموتی','کیک و دسر','نوشیدنی‌های آماده','پیش‌غذا و سالاد','برگر و ساندویچ','پاستا','خوراک‌ها','غذای اصلی','ترشی و مخلفات','افزودنی‌ها','خدمات داخلی'} == set(names)
assert not {'بار گرم','بار سرد','فینگرفود','شربت‌خونه','سیب‌زمینی‌ها','خدمات'}.intersection(names)

codes = [row['item_code'] for row in data['items']]
assert len(codes) == len(set(codes)), 'Duplicate item_code in seed'
assert not set(codes).intersection(data.get('retired_item_codes', [])), 'Active seed code also marked retired'
category_keys = {row['category_key'] for row in data['categories']}
menu_keys = {row['menu_key'] for row in data['menus']}
assert all(row['category_key'] in category_keys for row in data['items'])
assert all(set(row.get('menus',[])) <= menu_keys for row in data['items'])

by_code={row['item_code']:row for row in data['items']}
assert by_code['FOOD-BURGER-BEEF']['price']==655000
assert by_code['FOOD-ZINGER']['price']==630000
assert by_code['HOT-ESPRESSO-SINGLE']['price']==110000
assert by_code['FOOD-ABDOUGH']['active']==0
assert by_code['POTATO-CHEDDAR']['active']==0
assert by_code['MENU-1017']['category_key']=='add-ons' and by_code['MENU-1017']['staff_only']==1
for code in ('SERVICE-TAKEAWAY','SERVICE-CAKE'):
    row=by_code[code]
    assert row['category_key']=='internal-services' and row['staff_only']==1 and row['preparation_station']=='none'
for row in data['items']:
    if row['category_key']=='main-dishes':
        assert row['active']==0 and row['menus']==['lunch']

by_cat={row['category_key']:row for row in data['categories']}
assert by_cat['add-ons']['audience']=='staff_only'
assert by_cat['internal-services']['audience']=='staff_only'
assert by_cat['coffee']['audience']=='guest_staff'

for row in data['categories'] + data['items']:
    image_path = row.get('image_path')
    if not image_path:
        continue
    path = root / image_path
    assert path.is_file(), f'Missing bundled image: {image_path}'
    assert path.suffix.lower() == '.webp', f'Bundled image is not WebP: {image_path}'
    with Image.open(path) as image:
        assert image.size == (900, 900), f'Unexpected seed image size {image.size}: {image_path}'
print('Menu seed PASS: 3 menus, 15 canonical categories, 128 items, 109 active/19 inactive, stable keys and staff-only internal categories.')
