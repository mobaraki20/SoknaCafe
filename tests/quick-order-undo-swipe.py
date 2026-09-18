#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')
css=(ROOT/'assets/css/quick-order.css').read_text(encoding='utf-8')
page=(ROOT/'staff/quick-order.php').read_text(encoding='utf-8')
assert "CLEAR_UNDO_MS = 8000" in js
assert "data.qoClearAction" not in js  # guard against accidental wrong dataset casing
assert "dataset.qoClearAction = isUndo ? 'undo' : 'clear'" in js
assert "icon(isUndo ? 'undo' : 'trash')" in js
assert "بازگردانی حذف سبد" in js
assert 'id="icon-undo"' in (ROOT/'assets/icons/ui-sprite.svg').read_text(encoding='utf-8')
assert "restoreClearedCart" in js and "armClearUndo" in js and "invalidateClearUndo" in js
assert "pointerdown', beginCartDrag" in js and "pointermove', moveCartDrag" in js and "pointerup', endCartDrag" in js
assert "quick-order-cart.is-dragging" in css and "touch-action:none" in css
assert 'id="quickOrderClear"' in page and 'id="quickOrderCartClose"' in page
# No visible handle/new controls: requested behavior must not change the established sheet geometry or chrome.
assert 'drag-handle' not in page.lower() and 'quickOrderDrag' not in page
print('Quick Order Undo + swipe structural contract passed: single existing clear control is reused, no new visible control/handle, gesture is attached to the established cart header.')
