#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
fn=read('includes/functions.php') + read('includes/function_domains/messages.php'); index=((read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')) + '\n' + read('includes/guest_menu_view.php')); menu=read('assets/js/menu.js')
checks=[]
def ok(c,m): checks.append((bool(c),m)); print('FAIL:',m) if not c else None
for key in ['fulfillment_takeaway']:
    ok(f"'key'=>'{key}'" in fn, f'guest message definition: {key}')
ok("default'=>'بیرون‌بر'" in fn, 'guest fulfillment exception language is human-facing')
ok('legacy_defaults' not in fn and 'منتظر تأیید اپراتور است.' not in fn, 'pre-launch legacy operator wording/default migration is removed')
ok("default'=>'سفارش ثبت شد و منتظر تأیید کافه است.'" in fn, 'pending status no longer exposes internal operator terminology')
ok('بیرون‌بر هم دارید؟' in index and 'در صورت نیاز، موارد بیرون‌بر را مشخص کنید.' in index and 'بقیه اقلام داخل کافه سرو می‌شوند.' in index, 'cart keeps dine-in silent and exposes takeaway progressively')
ok("msg('fulfillment_takeaway','بیرون‌بر')" in menu, 'takeaway exception label uses shared guest-message owner')
ok("pending_approval: 'منتظر تأیید کافه'" in menu and 'orderStatusShortLabel(order.status)' in menu, 'pending card uses compact guest-facing status copy')
ok('سرویس کیک' not in index and 'سرویس بیرون‌بر' not in index, 'staff-only service items are not hardcoded into guest UI')
if any(not c for c,_ in checks): raise SystemExit(1)
print(f'guest copy/domain parity PASS: {len(checks)} checks')
