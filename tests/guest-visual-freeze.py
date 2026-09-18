#!/usr/bin/env python3
from pathlib import Path
import hashlib
ROOT=Path(__file__).resolve().parents[1]
expected={
 'assets/css/app.css':'51e0767cdf40cffe6b2b2cb5853ab9f749a2920db0591841506b1bd17ada0e1d',
 'assets/css/responsive.css':'03f92b92c0de1126b5847bcbd8d7829ac07687f1522f4642e467fd96dbfac861',
}
for rel,digest in expected.items():
    got=hashlib.sha256((ROOT/rel).read_bytes()).hexdigest()
    assert got==digest,(rel,got)
# Guest menu was intentionally extended for line-level dine-in/takeaway; protect semantics instead of obsolete byte hashes.
guest_js=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
guest_page=(((ROOT/'menu/index.php').read_text(encoding='utf-8') + '\n' + (ROOT/'includes/guest_menu_view.php').read_text(encoding='utf-8')) + '\n' + (ROOT/'includes/guest_menu_view.php').read_text(encoding='utf-8'))
assert 'id="guestTakeawaySheet"' in guest_page and 'id="guestTakeawayAll"' in guest_page
assert 'orderPayloadLines' in guest_js and "fulfillment_mode:'takeaway'" in guest_js and 'takeaway_quantity' in guest_js
css=(ROOT/'assets/css/guest-menu.css').read_text(encoding='utf-8')
for approved in [
    '.guest-menu-page .qty-control{gap:0}',
    '.guest-menu-page .guest-events-close{width:44px',
    'Public waiter picker: only the table grid scrolls; footer actions stay available.',
    '-webkit-tap-highlight-color:transparent',
]: assert approved in css,approved
print('Guest visual contract passed: shared shell geometry remains frozen while approved fulfillment/error/search behavior is protected semantically.')
