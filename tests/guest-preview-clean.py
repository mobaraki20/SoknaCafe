#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
menu=(root/'menu/index.php').read_text(encoding='utf-8')
shared=(root/'assets/js/menu.js').read_text(encoding='utf-8')
css=(root/'assets/css/guest-menu.css').read_text(encoding='utf-8')
settings=(root/'admin/settings.php').read_text(encoding='utf-8')

assert 'assets/js/menu-preview.js' not in menu and menu.count('assets/js/menu.js') == 1
assert '$isPublic=$table===null;' in menu
assert "<?= $isPublic?'is-menu-public guest-mode-public':'guest-mode-table' ?>" in menu
assert '<?php if($showTableUi): ?>' in menu and '<?php if($showOrderUi): ?>' in menu
assert "window.CAFE_TABLE=<?= json_script($table?" in menu and "window.CAFE_CAN_ORDER=<?= $showOrderUi?'true':'false' ?>" in menu
assert 'CAFE_PREVIEW_TABLES' not in shared and 'CAFE_PUBLIC_WAITER_TABLES' in shared
assert 'previewWaiterTableSearch' not in menu and 'preview_table_id' not in menu+shared
assert 'guest-mode-preview' not in css and 'is-menu-preview' not in css
assert 'public-table-picker' in menu and 'public-table-grid' in menu and 'public_waiter_call_enabled' in settings
assert 'object-fit:contain' in css and "$showOrderUi?' can-order':' view-only'" in menu
# The admin visual mock remains a true preview; production Public Menu is never named preview in runtime state.
assert 'پیش‌نمایش منوی عمومی' in settings
for retired in ['preview-v163','preview-cart','preview-add','preview-table-label','میز ۱۲']:
    assert retired not in settings, f'Legacy ordering preview remains in settings: {retired}'
print('Guest public/table owner consolidation checks passed.')
