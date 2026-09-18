#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def read(p): return (ROOT/p).read_text(encoding='utf-8')

push=read('includes/push.php')
prefs=read('notification_preferences.php')
schema=read('database/schema.sql')
panel=read('includes/panel_layout.php')
admin=read('admin/push_devices.php')
guest=read('assets/js/menu.js')
guest_markup=((read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')) + '\n' + read('includes/guest_menu_view.php'))
quick=read('assets/js/staff-quick-order.js')
quick_markup=read('staff/quick-order.php')

# Notification preferences: responsibility -> preference; missing rows preserve current delivery.
for key in ('waiter_call','pending_guest_order','preparation'):
    assert f"'{key}'" in push, key
assert "'pending_order' => 'pending_guest_order'" in push
assert "'order' => 'preparation'" in push
assert 'push_user_preference_enabled' in push and 'if (!$responsible) return false;' in push
assert 'return $value === false ? true' in push
assert 'user_notification_preferences' in schema
assert 'PRIMARY KEY (user_id, preference_key)' in schema
assert "push.user_preferences_changed" in prefs and 'verify_csrf' in prefs
assert 'خاموش‌کردن Push، وظیفه یا سفارش را از پنل حذف نمی‌کند' in prefs
assert 'notification_preferences.php' in panel
assert 'pushCoverageWarnings' in admin and 'دستگاه فعالی برای دریافت اعلان ندارد' in admin

# Guest takeaway is exception-first and staged in a focused sheet; no per-line controls in normal cart.
policy=read('assets/js/fulfillment-policy.js')
assert 'id="guestTakeawayDisclosure"' in guest_markup
assert 'بیرون‌بر هم دارید؟' in guest_markup
assert 'id="guestTakeawayLayer"' in guest_markup and 'id="guestTakeawayConfirm"' in guest_markup
assert 'guestFulfillmentControl' not in guest_markup
assert 'takeawayDraft' in guest and 'closeGuestTakeawaySheet(true)' in guest
assert 'closeGuestTakeawaySheet(false)' in guest
assert 'fulfillment.ratioHtml' in guest and 'takeaway_allowed' in ((read('menu/index.php') + '\n' + read('includes/guest_menu_view.php')) + '\n' + read('includes/guest_menu_view.php'))
assert 'window.SoknaFulfillment' in policy
# Conflict must block submit and require explicit latest-version reload.
assert 'id="guestOrderConflict"' in guest_markup and 'id="guestOrderConflictPrimary"' in guest_markup and 'id="guestOrderConflictSecondary"' in guest_markup
assert 'if (submitting || editConflict || editingOrderNeedsReconcile || !cart.size' in guest
assert "const refreshCodes = ['order_changed','order_not_editable','order_not_found']" in guest
assert 'editingOrderSignature' in guest and 'reconcileEditingOrder' in guest and 'setEditConflict' in guest
assert 'editConflictOrder' not in guest and 'client_refresh_required' not in guest
assert "guestOrderConflictPrimary?.addEventListener('click'" in guest and "guestOrderConflictSecondary?.addEventListener('click'" in guest

# Quick Order: direct Trash -> Undo; takeaway is one compact cart-level edit mode.
assert 'id="quickOrderClear"' in quick_markup and 'quick-order-cart-menu' not in quick_markup
assert 'id="quickOrderTakeawayTool"' in quick_markup and 'id="quickOrderTakeawayMode"' in quick_markup
assert 'data-qo-clear-action' in quick_markup and "els.clear.dataset.qoClearAction = isUndo ? 'undo' : 'clear'" in quick
assert 'fulfillmentEditing: false' in quick and 'cartDefaultFulfillment' not in quick
assert 'fulfillmentOpen' not in quick
assert 'data-qo-takeaway-delta' in quick and 'quick-order-takeaway-badge' in quick
assert 'takeaway_allowed' in read('staff/api_quick_order.php')

# CSS ownership: new takeaway owners exist and obsolete fulfillment-owner blocks are gone.
gcss=read('assets/css/guest-menu.css')
qcss=read('assets/css/quick-order.css')
assert gcss.count('Guest takeaway selection.') == 1
assert qcss.count('Exception-first takeaway tool.') == 1
assert 'Guest fulfillment: dine-in is silent; takeaway is an explicit exception.' not in gcss
assert 'Quick Order fulfillment: dine-in is silent; takeaway is an explicit exception.' not in qcss

print('UI/notification contracts PASS: staged Guest takeaway, compact Staff takeaway mode, explicit conflict recovery, direct Trash/Undo, and permission-safe Push preferences.')
