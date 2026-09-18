#!/usr/bin/env python3
from pathlib import Path
import re
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
qo=read('assets/js/staff-quick-order.js'); qoc=read('assets/css/quick-order.css'); qop=read('staff/quick-order.php')
choice=read('assets/js/panel-choice.js'); jalali=read('assets/js/panel-jalali.js'); pc=read('assets/css/panel-components.css'); inv=read('includes/invoices_page.php')
audit=read('includes/audit_presentation.php'); activity=read('admin/activity_report.php'); settlement=read('includes/settlement.php'); layout=read('assets/css/panel-layout.css')
checks={
 'quick_order_confirm_shared_owner': '.panel-confirm-layer{' in pc and '.panel-confirm-layer{' not in layout,
 'quick_order_table_scroll_owner': 'overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch' in qoc and 'els.tableStage.scrollTop = 0' in qo,
 'quick_order_draft_exit_copy': 'پیش‌نویس این میز حفظ می‌شود' in qo,
 'quick_order_category_search': 'quickOrderCategorySearch' in qop and "els.categorySearch?.addEventListener('click'" in qo,
 'quick_order_category_density': 'grid-template-columns:repeat(3,minmax(0,1fr))' in qoc and 'height:86px;min-height:86px' in qoc,
 'quick_order_takeaway_is_cart_level_action': 'quickOrderTakeawayTool' in qop and 'state.fulfillmentEditing' in qo and 'quick-order-takeaway-tool' in qoc,
 'quick_order_cart_no_horizontal_scroll': 'overflow-y:auto;overflow-x:hidden' in qoc,
 'quick_order_fulfillment_mode_stays_inline': 'quickOrderTakeawayMode' in qop and '.quick-order-takeaway-mode{' in qoc and '.quick-order-takeaway-stepper{' in qoc,
 'filter_choice_embedded_context': "select.closest('[data-financial-filter-sheet]')" in choice and "return 'embedded'" in choice,
 'filter_jalali_embedded_context': "isEmbedded = (input)" in jalali and 'jalali-picker-inline' in jalali and 'showModal' in jalali,
 'filter_sheet_marks_embedded_context': 'data-overlay-context="embedded"' in inv,
 'financial_cta_full_width_mobile': '.financial-filter-sheet-actions .btn-primary{width:100%' in pc,
 'audit_expandable_details': 'audit_human_detail_rows' in audit and 'audit-v2-details' in activity and 'audit-v2-summary' in pc,
 'settlement_audit_snapshot_richer': bool(re.search(r"'invoice_number'\s*=>\s*\$invoiceNumber", settlement)) and bool(re.search(r"'table_name'\s*=>", settlement)) and "'print_job_id'" in settlement,
}
failed=[k for k,v in checks.items() if not v]
if failed:
 print('FAIL v1333_ui_contracts: '+','.join(failed)); raise SystemExit(1)
print('PASS v1333_ui_contracts: '+','.join(checks))
