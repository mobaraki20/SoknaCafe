#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
guest=(ROOT/'assets/css/guest-menu.css').read_text(encoding='utf-8')
app=(ROOT/'assets/css/app.css').read_text(encoding='utf-8')
assert '-webkit-tap-highlight-color:transparent' in guest
for token in ['.guest-menu-page button','.guest-menu-page [role="button"]','.guest-menu-page [data-menu-item]','.guest-menu-page [data-featured-item]']:
    assert token in guest, token
assert '-webkit-tap-highlight-color:transparent' not in app, 'Tap behavior belongs to guest-menu owner, not global app CSS.'
assert ':focus-visible' in app, 'Keyboard focus visibility must remain available.'
print('Guest tap owner v1.30.6 contract passed: native mobile paint is suppressed in guest owner only and keyboard focus remains intact.')
