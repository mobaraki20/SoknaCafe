#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
menu=read('assets/js/menu.js')
api=read('api/guest_orders.php')
markup=read('menu/index.php')
css=read('assets/css/guest-menu.css')

checks={
 'obsolete_append_endpoint_removed': "if ($action === 'append')" not in api and 'client_refresh_required' not in api,
 'explicit_edit_signature_is_persisted': 'editingOrderSignature' in menu and 'editingOrderNeedsReconcile' in menu and 'editingOrderSignature,' in menu,
 'edit_append_create_are_separate': "submittedMode = explicitOrder ? 'edit' : appendTarget ? 'append' : 'create'" in menu,
 'edit_never_falls_through_to_append': 'const appendTarget = explicitOrder ? null : mutableAppendTarget();' in menu,
 'append_merges_only_new_draft': 'mergeDraftIntoMutableOrder' in menu and 'fullEditableOrderPayload' not in menu,
 'explicit_edit_conflict_is_inline_and_blocking': 'setEditConflict' in menu and 'reconcileEditingOrder' in menu and 'Boolean(editConflict || editingOrderNeedsReconcile)' in menu,
 'terminal_edit_cannot_become_new_submit': "if (editingOrderCode && !editingOrder)" in menu and "setEditConflict('locked'" in menu,
 'conflict_has_explicit_two_actions': all(x in markup for x in ['guestOrderConflictTitle','guestOrderConflictPrimary','guestOrderConflictSecondary']),
 'edit_mode_is_visible_and_exitable': 'cartModeRow' in markup and 'guestEditExit' in markup and 'requestExitGuestEdit' in menu,
 'cart_total_distinguishes_append': "cartTotalLabel.textContent = !editingOrder && appendTarget ? 'جمع موارد جدید' : 'جمع سفارش'" in menu,
 'dynamic_submit_copy_stays_editable': "msg('submit_order_update'" in menu and "msg('submit_order_add'" in menu,
 'guest_order_cards_are_progressively_disclosed': 'guest-order-more' in menu and 'slice(0, 4)' in menu and 'slice(4)' in menu,
 'status_copy_is_short': "pending_approval: 'منتظر تأیید کافه'" in menu and "accounted: 'تأییدشده'" in menu,
 'zero_takeaway_stays_silent_until_cart_disclosure': 'guestTakeawayDisclosure' in markup and 'takeawayDraft' in menu and "const fulfillmentBadge=take>0?" in menu,
 'cart_layout_has_stable_grid_owner': '.cart-line-lower{' in css and 'grid-template-columns:minmax(0,1fr) auto' in css,
 'old_conflict_owner_removed': 'editConflictOrder' not in menu and 'guestOrderConflictReload' not in menu,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    print('FAIL v1334_guest_order_workflow: '+','.join(failed))
    raise SystemExit(1)
print('PASS v1334_guest_order_workflow: '+','.join(checks))
