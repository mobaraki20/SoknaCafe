#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
bill=read('operator/api_bill.php'); inv=read('includes/inventory.php'); op=read('includes/operator_page.php'); js=read('assets/js/operator.js'); css=read('assets/css/panel-components.css')
assert "prepared_removed_quantity" in bill and "array_key_exists('prepared',$data)" not in bill
assert "$unpreparedRemovedQuantity=$requiresPreparation?($removedQuantity-$preparedRemovedQuantity):0" in bill
assert "'restore_quantity'=>$unpreparedRemovedQuantity" in bill
assert "?int $restoreQuantity = null" in inv and "min($delta, $restoreQuantity)" in inv
assert 'billItemRequiresPreparation' in op and 'billItemPreparedQuantity' in op
assert 'data-stepper-for="billItemPreparedQuantity"' in op and "data-bill-step" in op and 'bill-edit-reason-options' in op
assert "requiresPreparation" in js and "prepared_removed_quantity" in js and 'bindSwipeDismiss' in js
assert '.bill-edit-reason-options{grid-template-columns:repeat(2' in css or '.bill-edit-reason-options{border:0' in css
print('1.32.20 mixed preparation quantity adjustment contract PASS')
