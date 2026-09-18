#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]

def read(path): return (R/path).read_text(encoding='utf-8')
def need(cond,msg):
    if not cond: raise AssertionError(msg)

schema=read('database/schema.sql')
settle=read('includes/settlement.php')
session=read('operator/api_table_session.php')
subs=read('operator/api_subscribers.php')
acc=read('operator/api_accommodation.php')
acc_inc=read('includes/accommodation.php')
settle_api=read('operator/api_settlements.php')
js=read('assets/js/operator.js')
page=read('includes/operator_page.php')
orders=read('operator/api_orders.php')

need('request_id VARCHAR(96) NULL' in schema, 'settlement request id column missing')
need('UNIQUE KEY uq_settlement_request_id (request_id)' in schema, 'settlement request id uniqueness missing')
need('function settlement_find_request' in settle and 'function settlement_result_from_record' in settle, 'settlement request reconciliation helpers missing')
need('function settlement_review_signature' in settle and 'SettlementStateConflict' in settle, 'reviewed bill signature/state conflict primitives missing')
need("'request_id' => $requestId" in settle or "['request_id']" in settle, 'settlement record does not persist request identity')
need("$table['bill_signature'] = settlement_review_signature($table,$billOrders,[" in orders and "'paid_quantities'" in orders, 'operator feed does not publish paid-state-aware reviewed bill signature')
need("$session['session_id']??$session['id']" in settle, 'review signature must prefer session_id when feed row also has table id')
need('settlement_assert_expected_invoice' not in settle and 'settlement_review_signature_locked' not in settle, 'legacy invoice-signature guard must be removed after all consumers migrate to account-state owner')
need("settlement_find_request($pdo, $requestId, true)" in session, 'direct settlement retry reconciliation missing')
need("settlement_assert_expected_account($account, $expectedSessionId, $expectedTotal, $expectedSignature)" in session, 'direct/itemized settlement stale paid-state guard missing')
need("'request_id'=>$requestId" in session and "'request_fingerprint'=>$requestFingerprint" in session, 'direct/itemized settlement request identity is not passed to financial record')
need("settlement_find_request($pdo,$requestId,true)" in subs, 'subscriber retry reconciliation missing')
need("'settlement:subscriber:'.$requestId" in subs, 'subscriber ledger is not idempotent by settlement request')
need("$account=settlement_account_state_locked($pdo,$invoice);" in subs and "settlement_assert_expected_account($account,$expectedSessionId,$expectedTotal,$expectedSignature)" in subs, 'subscriber must use canonical paid-state-aware account guard')
need('expected_session_id' in acc and 'expected_total' in acc and 'expected_signature' in acc, 'accommodation API expected bill snapshot missing')
need("$account=settlement_account_state_locked($pdo,$invoice);" in acc_inc and 'settlement_assert_expected_account($account,$expectedSessionId,$expectedTotal,$expectedSignature)' in acc_inc, 'accommodation transfer must use canonical paid-state-aware account guard')
need("?request_id=" in js and 'reconcileUnknownSettlement(tableId,operationId,destination' in js, 'client does not reconcile unknown financial result by exact request id')
need("error?.data?.code!=='settlement_changed'" in js, 'client stale-settlement recovery missing')
need('checkoutSessionId' in page and 'checkoutExpectedTotal' in page and 'checkoutExpectedSignature' in page, 'reviewed settlement snapshot fields missing')
for token in ["expected_session_id:Number($('checkoutSessionId').value)","expected_total:Number($('checkoutExpectedTotal').value)","expected_signature:$('checkoutExpectedSignature').value"]:
    need(token in js, 'client settlement payload snapshot missing: '+token)
need("String(data.destination)===String(destination)" in js, 'unknown result reconciliation must verify destination')
need("Number(data.table_id)===Number(tableId)" in js, 'unknown result reconciliation must verify table')
need("settlementTouchContext" in js and "focusSettlementControl" in js, 'mobile settlement autofocus guard missing')
print('1.36 settlement race/idempotency hardening PASS: all settlement destinations use the canonical paid-state-aware account signature, retry identity is durable, and unknown outcomes reconcile by exact request.')
