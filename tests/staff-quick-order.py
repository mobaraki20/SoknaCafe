#!/usr/bin/env python3
from pathlib import Path
import json, re, hashlib
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
page=read('staff/quick-order.php'); js=read('assets/js/staff-quick-order.js'); css=read('assets/css/quick-order.css'); layout=read('includes/panel_layout.php'); api=read('staff/api_quick_order.php'); service=read('includes/staff_order_service.php'); owner=api+'\n'+service; status=read('operator/api_status.php'); functions=read('includes/functions.php')
assert 'id="quickOrderPage"' in page and 'quick-order-page-shell' in page
assert 'includes/staff_quick_order.php' not in layout and not (ROOT/'includes/staff_quick_order.php').exists()
assert 'data-open-quick-order' not in layout and "staff/quick-order.php" in layout
assert 'quickOrderSearchToggle' in page and 'quick-order-search is-collapsed' in page
assert '@media(max-width:1023px)' in css and '.quick-order-search.is-collapsed{display:none}' in css
assert 'grid-template-columns:repeat(3,minmax(0,1fr))' in css and '.quick-order-category{height:86px;min-height:86px' in css
assert '@media(max-width:359px)' in css and 'grid-template-columns:repeat(2,minmax(0,1fr))' in css
assert 'quick-order-cart-body' in page and '.quick-order-cart-body{min-height:0;overflow-y:auto' in css
assert '.quick-order-current-lines{max-height:none;overflow:visible' in css
assert '.quick-order-pending-orders{max-height:none;overflow:visible' in css
assert 'sessionStorage' in js and 'sokna.quick-order.' in js
assert 'history.pushState' in js and 'quickOrderView' in js
assert 'validIcons' in js and 'duplicateIcons' not in js

menu=json.loads(read('database/default_menu.json'))
sprite=read('assets/icons/ui-sprite.svg')
sprite_keys=set(re.findall(r'id="icon-([^\"]+)"',sprite))
menu_icon_keys=[str(c.get('icon_key','')).strip() for c in menu.get('categories',[])]
assert len(menu_icon_keys)==len(menu.get('categories',[])) and len(menu_icon_keys)>=10 and all(menu_icon_keys)
assert all(key in sprite_keys for key in menu_icon_keys), menu_icon_keys
valid_line=js.split('const validIcons = new Set([',1)[1].split(']);',1)[0]
assert all(("'"+key+"'") in valid_line for key in menu_icon_keys)
registry_part=functions.split('function category_icon_registry(): array',1)[1].split('function category_icon_library(): array',1)[0]
registry_icon_keys=set(re.findall(r"'key'=>'([^']+)'",registry_part))
assert all(("'"+key+"'") in valid_line for key in registry_icon_keys), sorted(registry_icon_keys)
assert "icon('message')" in js and 'pencil' not in js.lower()

# 1.31.7: pending guest orders are independent immediate decisions, not deferred
# state carried into the next staff-order POST.
for retired in ['pending_action','pending_order_ids','pendingAction','pendingOrderIds','فعلاً منتظر بماند','تأیید هر']:
    assert retired not in js+owner+page, retired
assert 'window.OPERATOR_STATUS_API' in page and "operator/api_status.php" in page
assert 'data-qo-pending-status="accounted"' in js and 'data-qo-pending-status="cancelled"' in js
assert 'تأیید سفارش' in js and 'رد سفارش' in js
assert 'reviewPendingOrder' in js and 'confirmAction' in js
assert "status === 'cancelled'" in js and "okLabel: 'رد سفارش'" in js
assert "سفارش مهمان منتظر بررسی است؛ ابتدا آن را تأیید یا رد کنید." in owner
assert "pending_order_count" in api and "pending_orders" in api
assert 'confirm_order_locked' in status and "'cancelled'" in status
# Idempotent duplicate success must commit exactly once in its branch.
branch=status.split('if ($oldStatus === $status',1)[1].split('throw new RuntimeException',1)[0]
assert branch.count('$pdo->commit();')==1, 'operator status retry branch must commit once'
dup_branch=service.split('if ($duplicate = $duplicateStmt->fetch())',1)[1].split('$sessionStmt',1)[0]
assert '$pdo->commit();' not in dup_branch, 'canonical service must not own transaction commit'
assert "'duplicate'=>true" in dup_branch and "'_after_commit_order_id'=>0" in dup_branch, 'duplicate retry must return persisted success without side effects'
assert api.count('$pdo->commit();')==1 and api.find('$pdo->commit();') < api.find('staff_order_after_commit($result)'), 'quick-order route must commit once before after-commit acceleration'

assert 'request_token' in js and 'request_token' in owner
assert 'localStorage' in js and '12 * 3600 * 1000' in js and 'state.uncertain' in js
assert 'quickOrderUncertainNotice' in page and 'نتیجه ثبت قبلی هنوز مشخص نیست' in page
assert 'این میز آزاد است' not in page+js
assert 'دسته انتخاب‌شده' not in page+js
assert 'window.location.assign' in js
# Approved fulfillment UI extends the prior visual owner without introducing a second stylesheet.
assert '.quick-order-takeaway-tool' in css and '.quick-order-takeaway-stepper' in css and '.quick-order-takeaway-badge' in css
assert 'fulfillment_mode' in js and 'takeaway_quantity' in js
print('Quick-order contract passed: one compact takeaway owner, mixed dine-in/takeaway lines, independent immediate guest decisions, and idempotent retry guards.')
