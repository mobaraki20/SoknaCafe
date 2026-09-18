#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
app=(ROOT/'assets/css/app.css').read_text()
guest=(ROOT/'assets/css/guest-menu.css').read_text()
qo=(ROOT/'assets/css/quick-order.css').read_text()
gjs=(ROOT/'assets/js/menu.js').read_text()
sjs=(ROOT/'assets/js/staff-quick-order.js').read_text()
index=((ROOT/'menu/index.php').read_text() + '\n' + (ROOT/'includes/guest_menu_view.php').read_text())
staff=(ROOT/'staff/quick-order.php').read_text()
for selector in ('.cart-drawer{','.drawer-backdrop{','.drawer-head{','.drawer-content{','.cart-line{','.qty-control{','.drawer-foot{','.total-row{','.empty-cart{'):
    assert selector not in app, f'Guest cart selector leaked back into app.css: {selector}'
assert guest.count('.guest-menu-page .drawer-head{')==1
assert guest.count('.guest-menu-page .drawer-content{')==1
assert guest.count('.guest-menu-page .drawer-foot{')==1
assert '.guest-menu-page .guest-takeaway-sheet>footer{justify-content:flex-end}' not in guest, 'Takeaway sheet footer must not be patched by a later unconditional override.'
assert '1.33.1 — Guest fulfillment' not in guest
assert '1.33.1 — Quick Order fulfillment' not in qo
assert 'quick-order-cart-total-head' not in qo
assert 'grid-template-areas:"copy qty total note"' not in qo
assert 'cartDefaultFulfillment' not in gjs+sjs
assert 'legacyDraftKey' not in sjs
assert 'button.is-active' not in guest
assert 'id="guestTakeawayDisclosure"' in index and 'id="guestTakeawayAll"' in index and 'همه بیرون‌بر' in index
assert 'id="quickOrderTakeawayTool"' in staff and 'id="quickOrderTakeawayMode"' in staff and 'id="quickOrderTakeawayAll"' in staff and 'همه بیرون‌بر' in staff
assert 'cartFulfillmentSummary' in index and 'quickOrderFulfillmentSummary' in staff
assert 'role="status" aria-live="polite" aria-atomic="true"' in index
assert 'عدد بیرون‌بر' in gjs
print('Cart owner contract passed: guest/staff cart owners are consolidated, takeaway is exception-first at cart level, and obsolete fulfillment state/duplicate selectors are gone.')
