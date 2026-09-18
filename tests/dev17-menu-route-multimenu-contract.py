#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
menu=read('menu/index.php'); quick=read('staff/quick-order.php'); js=read('assets/js/staff-quick-order.js'); funcs=read('includes/functions.php'); sitemap=read('sitemap.php'); index=read('index.php')
assert not (ROOT/'menu.php').exists()
assert "canonical_asset('menu/')" in menu and "asset('menu/')" in menu
assert "menu_catalog_snapshot(db(),$isPublic?'guest_public':'guest_table'" in menu
assert 'guest-menu-switcher' in menu and "$_GET['menu']" in menu
assert "return canonical_asset('menu/') . '?table='" in funcs
assert "canonical_asset('menu/')" in sitemap
assert '/menu/' in index
assert 'quickOrderMenus' in quick and 'data-qo-menu' in js and 'catalogUrl' in js
assert 'state.menuKey' in js and 'renderMenus()' in js
print('dev17 phase5 canonical Menu + multi-menu consumer contract PASS')
