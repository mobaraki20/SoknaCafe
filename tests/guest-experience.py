#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')
index = (read('menu/index.php') + '\n' + read('includes/guest_menu_view.php'))
menu = read('assets/js/menu.js')
css = read('assets/css/guest-menu.css')
foundation = read('assets/css/app.css')
sw = read('service-worker.js')

assert (ROOT / 'assets/css/guest-menu.css').is_file()
assert not (ROOT / 'assets/js/menu-preview.js').exists()
for retired in ['assets/css/v170.css','assets/css/v180.css','assets/css/guest-order-refinement.css']:
    assert not (ROOT / retired).exists(), f'retired guest layer remains: {retired}'

assert 'assets/css/guest-menu.css' in index
assert not re.search(r'assets/css/v\d+\.css', index)
assert 'guest-menu-page' in index
assert 'guest-mode-public' in index and 'guest-mode-table' in index
assert '$showTableUi=$table!==null' in index
assert '$showOrderUi=$canOrder' in index
assert 'assets/js/menu-preview.js' not in index and index.count('assets/js/menu.js') == 1
assert '<?php if($showTableUi): ?><div class="guest-table-label"' in index
assert '<?php if($showOrderUi): ?><div class="inline-qty"' in index
assert 'id="itemDetailOptionSlot"' in index and 'id="itemDetailNoteBlock"' in index and 'class="item-detail-suggestion hidden"' in index
assert '<footer class="item-detail-footer">' in index

# Public and table contexts share one runtime; PHP capability gates keep public mode read-only.
assert 'CAFE_PUBLIC_WAITER_TABLES' in index
assert 'public-table-grid' in index
assert 'publicWaiterTable' in index and 'data-public-table' in menu
assert 'previewTableSearch' not in index and 'جست‌وجوی شماره میز' not in index
assert 'pointerdown' in menu and 'pointerup' in menu
assert 'event.preventDefault()' in menu and 'event.stopPropagation()' in menu
assert "window.CAFE_TABLE=<?= json_script($table?" in index
assert "window.CAFE_CAN_ORDER=<?= $showOrderUi?'true':'false' ?>" in index

# Table runtime owns ordering and uses idempotent server tokens.
assert 'async function submitOrder(options = {})' in menu
assert 'data.client_token || context.submittedTarget?.client_token || pendingToken' in menu
assert 'function bindDismissibleBackdrop' in menu
assert 'event.preventDefault()' in menu and 'event.stopPropagation()' in menu
assert 'window.SoknaHorizontalRail' in read('assets/js/horizontal-rail.js') and 'function enhanceHorizontalRail' not in menu

for selector in [
    '.guest-menu-page', '.featured-menu-card', '.item-detail-panel',
    '.item-detail-footer', '.cart-drawer', '.waiter-fab',
    '.public-table-grid', '@media(max-width:720px)', '@media(max-width:940px)'
]:
    assert selector in css, f'canonical guest selector missing: {selector}'
assert 'direction:ltr' in css  # physical minus/count/plus and desktop media layout
assert '.theme-courtyard' in foundation
assert '.search-result-card' in foundation

version=read('VERSION.txt').strip()
assert f"const CACHE='cafe-staff-v{version}'" in sw
assert './assets/css/guest-menu.css' in sw
assert './assets/js/menu.js' in sw and './assets/js/menu-preview.js' not in sw
for retired in ['v170.css','v180.css','guest-order-refinement.css']:
    assert retired not in sw

ids = re.findall(r'(?:\s|<)id="([^"]+)"', index)
allowed = {'tableNotice'}
duplicates = sorted(i for i in set(ids) if ids.count(i) > 1 and i not in allowed and '<?' not in i)
assert not duplicates, 'duplicate static IDs: ' + ', '.join(duplicates)
print('Canonical guest experience checks passed: shared public/table runtime, capability gates, modal safety and current assets.')
