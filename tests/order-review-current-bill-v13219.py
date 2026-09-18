#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
quick_page=(ROOT/'staff/quick-order.php').read_text(encoding='utf-8')
quick_js=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')
quick_css=(ROOT/'assets/css/quick-order.css').read_text(encoding='utf-8')
op_js=(ROOT/'assets/js/operator.js').read_text(encoding='utf-8')
op_api=(ROOT/'operator/api_orders.php').read_text(encoding='utf-8')
op_css=(ROOT/'assets/css/operator-live.css').read_text(encoding='utf-8')
import json
registry=json.loads((ROOT/'tests/defect_class_registry.json').read_text(encoding='utf-8'))
ids={row['id'] for row in registry['classes']}

# Order-review sheet: one total owner, destructive clear is secondary, takeaway is an exception-level quick mode (not repeated on every line).
assert 'quickOrderCartHeadTotal' not in quick_page
assert 'quick-order-cart-menu' not in quick_page and 'id="quickOrderClear"' in quick_page
assert 'id="quickOrderClear"' in quick_page and 'data-qo-clear-action="clear"' in quick_page
assert 'id="quickOrderTakeawayTool"' in quick_page and '>بیرون‌بر</button>' in quick_page
assert 'id="quickOrderTakeawayMode"' in quick_page and 'موارد بیرون‌بر' in quick_page and 'id="quickOrderTakeawayDone"' in quick_page
assert "state.fulfillmentEditing" in quick_js and 'quick-order-takeaway-stepper' in quick_js
assert "!state.fulfillmentEditing&&take>0?fulfillmentUi:''" in quick_js
assert '.quick-order-takeaway-stepper' in quick_css and '.quick-order-takeaway-tool' in quick_css

# Current bill: takeaway survives aggregation as operational metadata.
assert "'takeaway_quantity' => 0" in op_api
assert "['takeaway_quantity'] += (int)$line['quantity']" in op_api
assert "'fulfillment_mode'=>normalize_fulfillment_mode" in op_api
assert 'bill-takeaway-meta' in op_js and 'takeaway_quantity' in op_js

# Healthy/simple current bill stays dense: no one-batch disclosure and no duplicate subtotal without discount.
assert 'if(confirmed.length<=1)return' in op_js
assert 'سفارش‌های این میز' in op_js
assert 'تفکیک نوبت‌های سفارش' not in op_js
assert 'const invoiceTotals=discount?' in op_js
assert 'invoiceHeading=confirmed.length>1' in op_js

# Printer unavailable is status, not a dead button; account header does not repeat duration noise.
assert 'table-account-print-status-v13219' in op_js and 'چاپ غیرفعال' in op_js
assert "window.CUSTOMER_PRINT_CONFIGURED?`<button" in op_js
assert "table.active?status.label:'آماده پذیرش'" in op_js
assert '.table-account-print-status-v13219' in op_css

assert {'order_review_density','current_bill_operational_exception_parity'} <= ids

print('Order review/current bill contract passed: exception-first cart-level takeaway mode, direct Trash/Undo, single total owner, exception metadata, conditional batches/subtotal, and non-action printer status.')
