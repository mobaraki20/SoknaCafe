#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
menu=read('menu/index.php'); js=read('assets/js/menu.js'); waiter=read('api/waiter_call.php'); funcs=read('includes/functions.php'); panel=read('includes/panel_layout.php'); about=read('about.php'); index=read('index.php')
assert "function waiter_call_allowed(bool $publicContext = false): bool" in funcs
assert "waiter_call_allowed(true)" in menu and "waiter_call_allowed(false)" in menu
assert "!$isPublicRequest && !waiter_call_allowed(false)" in waiter
assert "$isPublicRequest && !waiter_call_allowed(true)" in waiter
assert "confirmWaiter && waiterAction === 'create'" in js and "confirmWaiter.disabled = false" in js
assert "$base . '/menu/'" in panel and "نمایش منوی مهمان" in panel
assert "$base . '/index.php') ?>\"><?= ui_icon('eye') ?><span>نمایش منوی مهمان" not in panel
assert "$aboutParams['menu']" in menu and "$menuParams['menu']=$menuKey" in about
assert "foreach (['table', 'menu'] as $key)" in index and "redirect(asset('menu/')" in index
print('dev22 phase1 guest-menu contract PASS')
