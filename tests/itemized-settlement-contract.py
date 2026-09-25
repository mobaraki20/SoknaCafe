#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

schema=read('database/schema.sql')
alloc=read('includes/settlement_allocations.php')
settlement=read('includes/settlement.php')
session=read('operator/api_table_session.php')
orders=read('operator/api_orders.php')
printing=read('includes/printing.php')
reporting=read('includes/reporting.php')
analytics=read('admin/analytics.php')
inventory_report=read('admin/inventory_report.php')
page=read('includes/operator_page.php')
js=read('assets/js/operator.js')
css=read('assets/css/operator-live.css')
quick_page=read('staff/quick-order.php')
quick_api=read('staff/api_quick_order.php')
staff_order_service=read('includes/staff_order_service.php')
quick_order_owner='\n'.join([quick_api,staff_order_service])
quick_js=read('assets/js/staff-quick-order.js')
locks='\n'.join(read(p) for p in [
    'api/create_order.php','staff/api_quick_order.php','includes/staff_order_service.php','operator/api_bill.php',
    'operator/api_table_session.php','operator/api_subscribers.php','includes/accommodation.php'
])

checks={
 'schema line table': 'CREATE TABLE IF NOT EXISTS settlement_record_lines' in schema,
 'schema allocation fields': all(x in schema for x in ['request_fingerprint','settlement_kind','closes_session','remaining_total','allocation_version']),
 'finance allocation owner': 'function settlement_account_state_locked' in alloc and 'FOR UPDATE' in alloc,
 'immutable line insert': 'settlement_record_lines_create_locked' in alloc and 'INSERT INTO settlement_record_lines' in alloc,
 'server selection review': 'function settlement_review_selection' in alloc and "checkout_itemized_review" in session,
 'single settlement engine': 'settlement_finalize_review_locked' in settlement and 'settlement_finalize_itemized_locked' in settlement,
 'allocation version remains legacy=1 unless tax snapshot requires v2': "!empty($invoice['tax_document_active']) ? 2 : 1" in settlement and "?? 1" in settlement,
 'idempotency fingerprint': 'settlement_request_fingerprint' in alloc and 'settlement_assert_request_match' in settlement and 'uq_settlement_request_id' in schema,
 'account signature paid state': 'paid_quantities' in alloc and 'paid_total' in alloc and 'bill_signature' in orders,
 'conflict response': "code'=>'settlement_changed'" in session and '409' in session,
 'exact print by settlement': 'function print_enqueue_final_invoice(PDO $pdo, int $settlementId' in printing and 'WHERE sr.id=?' in printing,
 'exact itemized reversal': 'itemized_exact_receipt' in settlement and 'copyLines' in settlement,
 'report canonical lines': 'FROM settlement_record_lines sl' in reporting and 'report_settlement_line_source_sql' in analytics and 'report_settlement_line_source_sql' in inventory_report,
 'receipt vs bill kpi': 'receiptCount' in analytics and 'billCount' in analytics,
 'runtime edit locks': locks.count('settlement_assert_session_editable_locked') >= 5 and 'settlement_session_has_active_itemized_locked' in locks,
 'operator itemized destination': 'data-settlement="itemized"' in page and 'itemizedSettlementModal' in page,
 'operator itemized single-step review/finalize': "action:'checkout_itemized_review'" in js and "action:'checkout_itemized'" in js and 'submitItemizedSettlement' in js and 'itemizedReviewStep' not in page,
 'remaining-aware direct': 'bill_remaining_total' in js and 'پرداخت تمام مانده' in js and 'ادامه تسویه جداگانه' in js,
 'partial UI lock': 'bill_itemized_active' in js and 'accountEditLocked' in js,
 'touch stepper': '.itemized-qty-stepper' in css and ('min-height:44px' in css.replace(' ','') or 'height:44px' in css.replace(' ','')),

 'mobile scroll chrome hidden': 'scrollbar-width:none' in css and '.itemized-settlement-box .settlement-content::-webkit-scrollbar' in css,
 'compact visual stepper with touch hit area': 'min-width:44px' in css.replace(' ','') and 'button.ui-icon{width:18px;height:18px;padding:7px' in css.replace(' ',''),
 'late-accounting explicit action label': '<span>افزودن قلم جاافتاده</span>' in page,
 'success notice auto collapse owner': 'itemizedNoticeTimer:null' in js and '2600' in js and "tone!=='warning'" in js,
 'late-accounting cashier route': 'افزودن قلم جاافتاده' in js and "mode','late_accounting'" in js and 'data-mode="<?= e($mode) ?>"' in quick_page and "resume_settlement','itemized'" in js,
 'late-accounting hard permission': "user_has_capability('cashier_accounts'" in quick_order_owner and "ثبت قلم جاافتاده فقط برای صندوق‌دار" in quick_order_owner,
 'late-accounting requires active itemized session': "$mode === 'late_accounting'" in quick_order_owner and '!$itemizedActive' in quick_order_owner and '$expectedSessionId !== $sessionId' in quick_order_owner,
 'late-accounting suppresses preparation only': "$mode !== 'late_accounting' && $hasPreparation" in quick_order_owner and "inventory_enqueue_order_event_tx" in quick_order_owner and "late_accounting" in quick_order_owner,
 'late-accounting audited': "order.late_accounting_created" in quick_order_owner and "preparation_suppressed'=>true" in quick_order_owner,
 'late-accounting fixed table/dine-in UI': 'lateAccounting' in quick_js and "mode, request_token" in quick_js and "fulfillment_mode:'dine_in'" in quick_js,
 'late-accounting returns to itemized context': "destination.searchParams.set('open_table'" in quick_js and "destination.searchParams.set('resume_settlement', 'itemized')" in quick_js and "startupResumeSettlement==='itemized'" in js and "returnUrl.searchParams.set('resume_settlement','itemized')" in js,
 'no payment inventory mutation': 'inventory_movement' not in settlement.lower() and 'inventory_movement' not in alloc.lower(),
 'no order quantity mutation in allocation owner': 'UPDATE order_items SET quantity' not in alloc and 'UPDATE order_items SET quantity' not in settlement,
}
failed=[name for name,ok in checks.items() if not ok]
if failed:
    for name in failed: print('FAIL:',name)
    raise SystemExit(1)
for name in checks: print('PASS:',name)
print(f'Itemized settlement contract PASS: {len(checks)} checks.')
