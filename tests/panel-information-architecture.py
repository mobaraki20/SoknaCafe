#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
layout=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
for label in ['عملیات','منو و مهمان','مالی','گزارش‌ها','سامانه و زیرساخت']:
    assert label in layout,label
assert "'transfer' =>" not in layout.split('$navGroups',1)[1].split('$navBadges',1)[0]
for rel in ['admin/items.php','admin/menu_transfer.php','admin/item_form.php','admin/tags.php','admin/tag_form.php']:
    assert 'panel_subnav(' in (ROOT/rel).read_text(encoding='utf-8'),rel

for retired in ["'tags' => [$base . '/admin/tags.php'", "'messages' => [$base . '/admin/messages.php'", "'push' => [$base . '/admin/push_devices.php'", "'updater' => [$base . '/admin/update/'", "'accommodation_settings' => [$base . '/admin/accommodation_settings.php'"]:
    assert retired not in layout, retired
assert "'tags'=>'items'" in layout and "'messages'=>'settings'" in layout and "'push'=>'settings'" in layout and "'updater'=>'maintenance'" in layout

assert layout.count("'/help.php'")==1 and layout.count("'/logout.php'")==1
assert 'quick-order-mobile-fab' not in layout
assert 'staff/quick-order.php' in layout and 'data-open-quick-order' not in layout
print('Panel information architecture passed: simplified owner-based navigation, hidden subpages mapped to their owners, item-management subnav, and one non-floating quick-order entry.')
